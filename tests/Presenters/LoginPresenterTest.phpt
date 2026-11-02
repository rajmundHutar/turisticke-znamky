<?php

/**
 * Test: LoginPresenter returns to the stored request after login (#22)
 */

declare(strict_types=1);

use App\Forms\FormFactory;
use App\Forms\LoginForm;
use App\Models\UserModel;
use App\Presenters\AdminPresenter;
use App\Presenters\LoginPresenter;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
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


	public function __construct()
	{
		$this->passwords = new Passwords;
	}


	public function authenticate(string $email, string $password): Nette\Security\IIdentity
	{
		$this->authenticateCalls++;
		return parent::authenticate($email, $password);
	}


	public function fetchByEmail(string $email)
	{
		return $email === 'jana@example.com'
			? ['id' => 1, 'email' => $email, 'password' => $this->passwords->hash('secret'), 'role' => 'admin']
			: null;
	}
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
function login(User $user, Session $session, array $params = [], string $password = 'secret'): array
{
	$translator = PresenterTestHelpers::createTranslator();
	$formFactory = new FormFactory($translator);
	$formFactory->register(new LoginForm($translator));
	$presenter = new LoginPresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, $user, ['Sec-Fetch-Site' => 'same-origin'], $session);
	$presenter->injectFormFactory($formFactory);
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


/** Opens the admin page as an anonymous user; returns the backlink of the redirect to login. */
function openAdminAnonymously(Session $session): string
{
	$presenter = new AdminPresenter(PresenterTestHelpers::createTranslator());
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(null), [], $session);
	$response = $presenter->run(new Request('Admin', 'GET', ['action' => 'default']));
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
$formFactory = new FormFactory($translator);
$formFactory->register(new LoginForm($translator));
$presenter = new LoginPresenter($translator);
PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(null), [], createSession());
$presenter->injectFormFactory($formFactory);
$response = $presenter->run(new Request('Login', 'GET', ['action' => 'default', 'backlink' => 'abc12']));
assert($response instanceof Nette\Application\Responses\TextResponse);
Assert::match('%A%<form action="/login/?backlink=abc12" method="post"%A%', (string) $response->getSource());
