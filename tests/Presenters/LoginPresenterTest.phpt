<?php

/**
 * Test: LoginPresenter returns to the stored request after login (#22) and throttles failed logins (#38)
 */

declare(strict_types=1);

use App\Forms\LoginForm;
use App\Models\LoginThrottle;
use App\Models\UserModel;
use App\Presenters\AdminPresenter;
use App\Presenters\LoginPresenter;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Http\Session;
use Nette\Security\Passwords;
use Nette\Security\User;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/PresenterTestHelpers.php';
require __DIR__ . '/../../app/Helpers/Flash.php'; // global class, loaded by RobotLoader in the app


/** UserModel without a database, knowing a single user; counts authenticate() calls. */
final class CountingUserModel extends UserModel
{
	public int $authenticateCalls = 0;

	private string $hash;


	public function __construct()
	{
		$this->passwords = new Passwords(PASSWORD_BCRYPT, ['cost' => 4]); // fast hashing, tests log in many times
		$this->hash = $this->passwords->hash('secret');
	}


	public function authenticate(string $email, string $password): Nette\Security\IIdentity
	{
		$this->authenticateCalls++;
		return parent::authenticate($email, $password);
	}


	public function fetchByEmail(string $email)
	{
		return $email === 'jana@example.com'
			? ['id' => 1, 'email' => $email, 'password' => $this->hash, 'role' => 'admin']
			: null;
	}
}


function createLoginThrottle(): LoginThrottle
{
	return new LoginThrottle((string) tempnam(sys_get_temp_dir(), 'login-throttle'), new MemoryStorage, new CountingUserModel);
}


function createSession(): Session
{
	$httpRequest = new Nette\Http\Request(new Nette\Http\UrlScript('http://localhost/'));
	return new Session($httpRequest, new Nette\Http\Response);
}


/**
 * Submits the login form; returns the presenter (for flash messages) and the redirect URL.
 * @param  array<string, string>  $params
 * @return array{LoginPresenter, string}
 */
function login(User $user, Session $session, array $params = [], string $password = 'secret', ?LoginThrottle $throttle = null): array
{
	$translator = PresenterTestHelpers::createTranslator();
	$presenter = new LoginPresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, $user, ['Sec-Fetch-Site' => 'same-origin'], $session, '10.0.0.1');
	$presenter->injectLoginForm(new LoginForm($translator));
	$presenter->injectLoginThrottle($throttle ?? createLoginThrottle());
	$response = $presenter->run(new Request(
		'Login',
		'POST',
		['action' => 'default', 'do' => 'loginForm-submit'] + $params,
		['email' => 'jana@example.com', 'password' => $password, 'ok' => 'Submit'],
	));
	Assert::type(RedirectResponse::class, $response);
	assert($response instanceof RedirectResponse);
	return [$presenter, $response->getUrl()];
}


/**
 * Opens the admin page as an anonymous user; returns the backlink of the redirect to login.
 * @param  array<string, string>  $params
 */
function openAdminAnonymously(Session $session, array $params = []): string
{
	$presenter = new AdminPresenter(PresenterTestHelpers::createTranslator());
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(null), [], $session);
	$response = $presenter->run(new Request('Admin', 'GET', ['action' => 'default'] + $params));
	Assert::type(RedirectResponse::class, $response);
	assert($response instanceof RedirectResponse);
	Assert::contains('/login', $response->getUrl());
	$backlink = $presenter->getLastCreatedRequest()?->getParameter('backlink');
	Assert::type('string', $backlink);
	return $backlink;
}


// Login with a backlink to a stored request returns to the original page
$session = createSession();
$backlink = openAdminAnonymously($session);
$userModel = new CountingUserModel;
$user = PresenterTestHelpers::createUser(null, $userModel);
[$presenter, $url] = login($user, $session, ['backlink' => $backlink]);
Assert::true($user->isLoggedIn());
Assert::same(1, $userModel->authenticateCalls);
Assert::match('http://localhost/admin/?_fid=%[0-9a-z]+%', $url);
Assert::same('login.loginSuccessful', $presenter->getTemplate()->flashes[0]->message);


// A signal in the original request is dropped: login returns to the page, not to the signal (#22)
$session = createSession();
$backlink = openAdminAnonymously($session, ['do' => 'importStamps']);
[, $url] = login(PresenterTestHelpers::createUser(null, new CountingUserModel), $session, ['backlink' => $backlink]);
Assert::match('http://localhost/admin/?_fid=%[0-9a-z]+%', $url);


// The stored request is used only once
[, $url] = login(PresenterTestHelpers::createUser(null, new CountingUserModel), $session, ['backlink' => $backlink]);
Assert::match('http://localhost/?_fid=%[0-9a-z]+%', $url);


// Login without a backlink goes to the homepage
[$presenter, $url] = login(PresenterTestHelpers::createUser(null, new CountingUserModel), createSession());
Assert::match('http://localhost/?_fid=%[0-9a-z]+%', $url);
Assert::same('login.loginSuccessful', $presenter->getTemplate()->flashes[0]->message);


// A bogus or foreign backlink goes to the homepage, never outside the application
foreach (['xxxxx', 'https://evil.example.com/', '//evil.example.com'] as $bogus) {
	[, $url] = login(PresenterTestHelpers::createUser(null, new CountingUserModel), createSession(), ['backlink' => $bogus]);
	Assert::match('http://localhost/?_fid=%[0-9a-z]+%', $url);
}


// A failed login stays on the login page and keeps the backlink for the next attempt
$session = createSession();
$backlink = openAdminAnonymously($session);
$user = PresenterTestHelpers::createUser(null, new CountingUserModel);
[$presenter, $url] = login($user, $session, ['backlink' => $backlink], 'wrong');
Assert::false($user->isLoggedIn());
Assert::same('login.loginNotSuccessful', $presenter->getTemplate()->flashes[0]->message);
Assert::match('http://localhost/login/?backlink=' . $backlink . '&_fid=%[0-9a-z]+%', $url);
[, $url] = login($user, $session, ['backlink' => $backlink]);
Assert::match('http://localhost/admin/?_fid=%[0-9a-z]+%', $url);


// The login form posts back with the backlink
$translator = PresenterTestHelpers::createTranslator();
$presenter = new LoginPresenter($translator);
PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(null), [], createSession());
$presenter->injectLoginForm(new LoginForm($translator));
$presenter->injectLoginThrottle(createLoginThrottle());
$response = $presenter->run(new Request('Login', 'GET', ['action' => 'default', 'backlink' => 'abc12']));
assert($response instanceof Nette\Application\Responses\TextResponse);
Assert::match('%A%<form action="/login/?backlink=abc12" method="post"%A%', (string) $response->getSource());


// After 5 failed attempts the next one is rejected without verifying the password, even a correct one (#38)
$throttle = createLoginThrottle();
$userModel = new CountingUserModel;
$user = PresenterTestHelpers::createUser(null, $userModel);
for ($i = 0; $i < 5; $i++) {
	[$presenter] = login($user, createSession(), [], 'wrong', $throttle);
	Assert::same('login.loginNotSuccessful', $presenter->getTemplate()->flashes[0]->message);
}
Assert::same(5, $userModel->authenticateCalls);
[$presenter, $url] = login($user, createSession(), [], 'secret', $throttle);
Assert::same(5, $userModel->authenticateCalls);
Assert::false($user->isLoggedIn());
Assert::same('login.tooManyAttempts', $presenter->getTemplate()->flashes[0]->message);
Assert::same(Flash::ERROR, $presenter->getTemplate()->flashes[0]->type);
Assert::match('http://localhost/login/?_fid=%[0-9a-z]+%', $url);


// A successful login resets only the account counter; the shared IP counter keeps counting (#38)
$throttle = createLoginThrottle();
$user = PresenterTestHelpers::createUser(null, new CountingUserModel);
for ($i = 0; $i < 4; $i++) {
	login($user, createSession(), [], 'wrong', $throttle);
}
login($user, createSession(), [], 'secret', $throttle);
Assert::true($user->isLoggedIn());
$user->logout(true);
[$presenter] = login($user, createSession(), [], 'wrong', $throttle);
Assert::same('login.loginNotSuccessful', $presenter->getTemplate()->flashes[0]->message);
[$presenter] = login($user, createSession(), [], 'secret', $throttle);
Assert::same('login.tooManyAttempts', $presenter->getTemplate()->flashes[0]->message);
Assert::false($user->isLoggedIn());


/** Renders the login page (GET) in the given session; returns the HTML. */
function renderLoginPage(Session $session, array $params = []): string
{
	$translator = PresenterTestHelpers::createTranslator();
	$presenter = new LoginPresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(null), [], $session);
	$presenter->injectLoginForm(new LoginForm($translator));
	$presenter->injectLoginThrottle(createLoginThrottle());
	$response = $presenter->run(new Request('Login', 'GET', ['action' => 'default'] + $params));
	assert($response instanceof Nette\Application\Responses\TextResponse);
	return html_entity_decode((string) $response->getSource()); // Latte encodes "@" in attributes
}


// After a failed login the e-mail stays filled in (not in the URL), the password is empty and focused (#58)
foreach (['without backlink' => [], 'with backlink' => ['backlink' => 'abc12']] as $case => $params) {
	$session = createSession();
	[, $url] = login(PresenterTestHelpers::createUser(null, new CountingUserModel), $session, $params, 'wrong');
	Assert::notContains('jana', $url, $case);
	$html = renderLoginPage($session, $params);
	Assert::match('%A%<input type="text" name="email"%A%value="jana@example.com"%A%', $html, $case);
	Assert::notContains('wrong', $html, $case);
	Assert::match('%A%<input type="password" name="password"%A%autofocus%A%', $html, $case);
	// shown once: a later plain visit has an empty form again
	Assert::notContains('jana@example.com', renderLoginPage($session, $params), $case);
}

// The same after a login rejected by the attempt limit (#58)
$session = createSession();
$throttle = createLoginThrottle();
$user = PresenterTestHelpers::createUser(null, new CountingUserModel);
for ($i = 0; $i < 6; $i++) {
	login($user, $session, [], 'wrong', $throttle);
}
Assert::contains('value="jana@example.com"', renderLoginPage($session));

// A plain visit has an empty form without autofocus on the password
$html = renderLoginPage(createSession());
Assert::notContains('jana@example.com', $html);
Assert::notMatch('%A%<input type="password" name="password"%A%autofocus%A%', $html);
