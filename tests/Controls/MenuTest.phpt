<?php

/**
 * Test: logout is a same-origin signal of the menu, not a GET action (#36)
 */

declare(strict_types=1);

use App\Presenters\LoginPresenter;
use Nette\Application\BadRequestException;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
use Nette\Security\User;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../Presenters/PresenterTestHelpers.php';


/** @param  array<string, string>  $headers */
function createLoginPresenter(User $user, array $headers): LoginPresenter
{
	$presenter = new LoginPresenter(PresenterTestHelpers::createTranslator());
	PresenterTestHelpers::injectPrimary($presenter, $user, $headers);
	return $presenter;
}


// The menu's logout signal logs the user out and redirects to the homepage
$user = PresenterTestHelpers::createUser(['user']);
$presenter = createLoginPresenter($user, ['Sec-Fetch-Site' => 'same-origin']);
$response = $presenter->run(new Request('Login', 'GET', ['action' => 'default', 'do' => 'menu-logout']));
Assert::false($user->isLoggedIn());
Assert::type(RedirectResponse::class, $response);
Assert::same('Homepage', $presenter->getLastCreatedRequest()?->getPresenterName());
Assert::same('default', $presenter->getLastCreatedRequest()?->getParameter('action'));


// A logout link on a foreign page (cross-site request) does not log the user out
$user = PresenterTestHelpers::createUser(['user']);
$presenter = createLoginPresenter($user, ['Sec-Fetch-Site' => 'cross-site']);
$presenter->run(new Request('Login', 'GET', ['action' => 'default', 'do' => 'menu-logout']));
Assert::true($user->isLoggedIn());


// The old GET URL Login:out no longer logs the user out
$user = PresenterTestHelpers::createUser(['user']);
$presenter = createLoginPresenter($user, ['Sec-Fetch-Site' => 'cross-site']);
Assert::exception(
	fn() => $presenter->run(new Request('Login', 'GET', ['action' => 'out'])),
	BadRequestException::class,
);
Assert::true($user->isLoggedIn());
