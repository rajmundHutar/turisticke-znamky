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
assert($response instanceof RedirectResponse);
Assert::same('http://localhost/', $response->getUrl()); // homepage root


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


// Logging out from "My collection" lands on the unfiltered homepage, not on the (now empty) filtered collection
$translator = PresenterTestHelpers::createTranslator();
$presenter = new App\Presenters\HomepagePresenter($translator);
$user = PresenterTestHelpers::createUser(['user']);
PresenterTestHelpers::injectPrimary($presenter, $user, ['Sec-Fetch-Site' => 'same-origin']);
$presenter->injectCollectionModel(new class extends App\Models\CollectionModel {
	public function __construct()
	{
	}


	public function fetchByUser(?int $userId): array
	{
		return [];
	}
});
$presenter->injectFilterForm(new App\Forms\FilterForm($translator));
$response = $presenter->run(new Request('Homepage', 'GET', [
	'action' => 'default',
	'search' => 'Praha',
	'sort' => '-date',
	'onlyCollected' => '1',
	'paginator-page' => '2',
	'do' => 'menu-logout',
]));
Assert::false($user->isLoggedIn());
Assert::type(RedirectResponse::class, $response);
assert($response instanceof RedirectResponse);
$url = new Nette\Http\Url($response->getUrl());
Assert::same([], $url->getQueryParameters());


/** StampsModel double without a database and without stamps. */
final class MenuFakeStampsModel extends App\Models\StampsModel
{
	public function __construct()
	{
	}


	public function search(array $filter, $limit = null, $offset = null): ?array
	{
		return null;
	}


	public function count(array $filter = []): int
	{
		return 0;
	}
}


/** CollectionModel double with an empty collection. */
final class MenuFakeCollectionModel extends App\Models\CollectionModel
{
	public function __construct()
	{
	}


	public function fetchByUser(?int $userId): array
	{
		return [];
	}
}


/**
 * Renders a page (a full page load) with an empty database; returns the HTML.
 * @param  list<string>|null  $roles
 * @param  array<string, string>  $params
 */
function renderMenuPage(string $presenterName, ?array $roles, array $params = []): string
{
	$translator = PresenterTestHelpers::createTranslator();
	$presenter = match ($presenterName) {
		'Homepage' => new App\Presenters\HomepagePresenter($translator),
		'Nearby' => new App\Presenters\NearbyPresenter($translator),
		'CollectionStats' => new App\Presenters\CollectionStatsPresenter($translator),
		'Login' => new LoginPresenter($translator),
		default => throw new LogicException("No presenter $presenterName in the test."),
	};
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser($roles));
	if ($presenter instanceof LoginPresenter) {
		$presenter->injectLoginForm(new App\Forms\LoginForm($translator));
	} else {
		$presenter->injectStampsModel(new MenuFakeStampsModel);
		$presenter->injectCollectionModel(new MenuFakeCollectionModel);
	}
	if ($presenter instanceof App\Presenters\HomepagePresenter) {
		$presenter->injectFilterForm(new App\Forms\FilterForm($translator));
	}
	if ($presenter instanceof App\Presenters\HomepagePresenter || $presenter instanceof App\Presenters\NearbyPresenter) {
		$presenter->injectEditStampForm(new App\Forms\EditStampForm($translator));
	}
	$response = $presenter->run(new Request($presenterName, 'GET', ['action' => 'default'] + $params));
	Assert::type(Nette\Application\Responses\TextResponse::class, $response);
	assert($response instanceof Nette\Application\Responses\TextResponse);
	return (string) $response->getSource();
}


/**
 * Items of the bottom navigation: label => whether it is the current page.
 * @return array<string, bool>
 */
function navItems(string $html): array
{
	Assert::match('%A%<nav class="bottom-nav" aria-label="menu.mainNavigation"%A%', $html);
	preg_match('~<nav class="bottom-nav".*?</nav>~s', $html, $nav);
	preg_match_all('~<a [^>]*class="bottom-nav-item"[^>]*>.*?<span class="bottom-nav-label">([^<]+)</span>~s', $nav[0], $m, PREG_SET_ORDER);
	$items = [];
	foreach ($m as [$item, $label]) {
		$items[$label] = str_contains($item, 'aria-current="page"');
	}
	return $items;
}


// The bottom navigation of a logged-in user: Poblíž · Známky · Sbírka · Statistiky, the current page marked (#53)
foreach ([
	['Homepage', [], 'menu.allStamps'],
	['Homepage', ['notCollected' => '1'], 'menu.allStamps'],
	['Homepage', ['onlyCollected' => '1'], 'menu.myCollection'],
	['Nearby', [], 'menu.nearby'],
	['CollectionStats', [], 'menu.myCollectionStats'],
	['Login', [], null],
] as [$presenterName, $params, $current]) {
	$items = navItems(renderMenuPage($presenterName, ['user'], $params));
	Assert::same(['menu.nearby', 'menu.allStamps', 'menu.myCollection', 'menu.myCollectionStats'], array_keys($items), $presenterName);
	Assert::same($current, array_search(true, $items, true) ?: null, "$presenterName " . json_encode($params));
	Assert::same($current === null ? 0 : 1, count(array_filter($items)), $presenterName);
}

// the links: Známky without a collection filter, Sbírka with onlyCollected
$html = renderMenuPage('Nearby', ['user']);
Assert::match('%A%<a href="/nearby/" class="bottom-nav-item" aria-current="page">%A%', $html);
Assert::match('%A%<a href="/" class="bottom-nav-item">%A%', $html);
Assert::match('%A%<a href="/?onlyCollected=1" class="bottom-nav-item">%A%', $html);
Assert::match('%A%<a href="/collection-stats/" class="bottom-nav-item">%A%', $html);


// An anonymous user has no collection: only Poblíž and Známky (#53)
Assert::same(['menu.nearby' => false, 'menu.allStamps' => true], navItems(renderMenuPage('Homepage', null)));
Assert::same(['menu.nearby' => true, 'menu.allStamps' => false], navItems(renderMenuPage('Nearby', null)));
Assert::same(['menu.nearby' => false, 'menu.allStamps' => false], navItems(renderMenuPage('Homepage', null, ['onlyCollected' => '1'])));


// No hamburger any more (#53)
Assert::notContains('navbar-burger', renderMenuPage('Homepage', ['user']));


// The header: the login icon for an anonymous user; logout (the signal) for a logged-in one, plus admin for the admin role (#53)
function headerOf(string $html): string
{
	Assert::match('%A%<header class="app-header">%A%', $html);
	preg_match('~<header class="app-header">.*?</header>~s', $html, $header);
	return $header[0];
}


$header = headerOf(renderMenuPage('Nearby', null));
Assert::match('%A%<a href="/login/" class="app-header-icon" aria-label="menu.login" title="menu.login">%A%', $header);
Assert::notContains('menu-logout', $header);
Assert::notContains('menu.administration', $header);

$header = headerOf(renderMenuPage('Nearby', ['user']));
Assert::match('%A%<a href="/nearby/?do=menu-logout" class="app-header-icon" aria-label="menu.logout" title="menu.logout">%A%', $header);
Assert::notContains('menu.login', $header);
Assert::notContains('menu.administration', $header);

$header = headerOf(renderMenuPage('Nearby', ['admin']));
Assert::match('%A%<a href="/admin/" class="app-header-icon" aria-label="menu.administration" title="menu.administration">%A%', $header);
Assert::contains('do=menu-logout', $header);


// The search icon in the header for everybody (#73): leads to the search field on the overview, JS focuses it there
foreach ([null, ['user'], ['admin']] as $roles) {
	foreach (['Nearby', 'Homepage', 'CollectionStats'] as $page) {
		if ($roles === null && $page === 'CollectionStats') {
			continue;
		}
		$header = headerOf(renderMenuPage($page, $roles));
		Assert::match('%A%<a href="/#frm-filterForm-search" class="app-header-icon js-header-search" aria-label="menu.search" title="menu.search">%A%', $header);
	}
}
