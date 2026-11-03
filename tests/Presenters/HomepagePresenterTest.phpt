<?php

/**
 * Test: HomepagePresenter persistent params and collect form redirect (#29, #48); no type filter (#14); jump to a stamp by its number (#48)
 */

declare(strict_types=1);

use App\Forms\EditStampForm;
use App\Forms\FormFactory;
use App\Models\CollectionModel;
use App\Presenters\HomepagePresenter;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
use Nette\Application\UI\ComponentReflection;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/PresenterTestHelpers.php';
require __DIR__ . '/../../app/Helpers/Flash.php'; // global class, loaded by RobotLoader in the app


/** CollectionModel double without a database with the stamp 5 collected; records update calls. */
final class HomepageFakeCollectionModel extends CollectionModel
{
	/** @var list<array{int, int, array<string, mixed>}> */
	public array $updates = [];

	private array $collection;


	public function __construct()
	{
		$this->collection = [5 => collectedStamp(5, '2024-01-01')];
	}


	public function fetchByUser(?int $userId): array
	{
		return $this->collection;
	}


	public function update(int $stampId, int $userId, array $data): void
	{
		$this->updates[] = [$stampId, $userId, $data];
	}
}


// Persistent params are declared via #[Persistent]; the @persistent annotation is deprecated since nette/application 3.3
Assert::noError(function () {
	Assert::same(
		['search', 'sort', 'onlyCollected'],
		array_keys((new ComponentReflection(HomepagePresenter::class))->getPersistentParams()),
	);
});


// Submitting the collect form for a collected stamp stores the values and redirects back (no stale collection rendered),
// keeping filter, sort and page (#29, #48)
$translator = PresenterTestHelpers::createTranslator();
$formFactory = new FormFactory($translator);
$formFactory->register(new EditStampForm($translator));
$collectionModel = new HomepageFakeCollectionModel;

$presenter = new HomepagePresenter($translator);
PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['Sec-Fetch-Site' => 'same-origin']); // signals are CSRF protected
$presenter->injectCollectionModel($collectionModel);
$presenter->injectFormFactory($formFactory);
// no stamps model nor templates: the request must end by the redirect before rendering

$response = $presenter->run(new Request(
	'Homepage',
	'POST',
	[
		'action' => 'default',
		'search' => 'Praha',
		'sort' => '-date',
		'onlyCollected' => '1',
		'paginator-page' => '3',
		'do' => 'collectForm-submit',
	],
	['id' => '5', 'date' => '2. 6. 2024', 'comment' => 'Sunny', 'ok' => 'stamp.saveToCollection'],
));
Assert::count(1, $collectionModel->updates);
[$stampId, $userId, $data] = $collectionModel->updates[0];
Assert::same([5, 1, 'Sunny'], [$stampId, $userId, $data['comment']]);
Assert::type(DateTimeInterface::class, $data['date']);
Assert::same('2024-06-02', $data['date']->format('Y-m-d'));
Assert::type(RedirectResponse::class, $response);
assert($response instanceof RedirectResponse);
$url = new Nette\Http\Url($response->getUrl());
Assert::same('Praha', $url->getQueryParameter('search'));
Assert::same('-date', $url->getQueryParameter('sort'));
Assert::same('1', $url->getQueryParameter('onlyCollected'));
Assert::same('3', $url->getQueryParameter('paginator-page'));
Assert::same('stamp-5', $url->getFragment());
Assert::null($url->getQueryParameter('do'));


/** StampsModel double without a database; records the filters it is asked for. */
final class HomepageFakeStampsModel extends App\Models\StampsModel
{
	/** @var list<array<string, mixed>> */
	public array $filters = [];

	/** @var list<array{mixed, mixed}> limit and offset of each search() call */
	public array $pages = [];


	/**
	 * @param  list<mixed>  $stamps  returned by search() as they are
	 * @param  ?int  $total  returned by count(), defaults to the number of $stamps
	 */
	public function __construct(
		private array $stamps = [],
		private ?int $total = null,
	) {
	}


	public function search(array $filter, $limit = null, $offset = null): ?array
	{
		$this->filters[] = $filter;
		$this->pages[] = [$limit, $offset];
		return $this->stamps ?: null;
	}


	public function count(array $filter = []): int
	{
		$this->filters[] = $filter;
		return $this->total ?? count($this->stamps);
	}
}


/** CollectionModel double returning the given collection. */
final class HomepageCollectionModelWith extends CollectionModel
{
	/** @param  array<int, mixed>  $collection */
	public function __construct(
		private array $collection,
	) {
	}


	public function fetchByUser(?int $userId): array
	{
		return $this->collection;
	}
}


/**
 * Renders the homepage (expecting no stamps) and returns the filters passed to the stamps model.
 * @param  list<string>|null  $roles
 * @param  array<int, mixed>  $collection
 * @param  array<string, string>  $params
 * @return list<array<string, mixed>>
 */
function homepageFilters(?array $roles, array $collection, array $params): array
{
	$translator = PresenterTestHelpers::createTranslator();
	$formFactory = new FormFactory($translator);
	$formFactory->register(new App\Forms\FilterForm($translator));
	$formFactory->register(new EditStampForm($translator)); // the collect form of a logged-in user
	$stampsModel = new HomepageFakeStampsModel;

	$presenter = new HomepagePresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser($roles));
	$presenter->injectCollectionModel(new HomepageCollectionModelWith($collection));
	$presenter->injectStampsModel($stampsModel);
	$presenter->injectFormFactory($formFactory);
	$response = $presenter->run(new Request('Homepage', 'GET', ['action' => 'default'] + $params));
	Assert::type(Nette\Application\Responses\TextResponse::class, $response);
	assert($response instanceof Nette\Application\Responses\TextResponse);
	Assert::contains('general.noItemsFound', (string) $response->getSource()); // empty state, no paginator crash

	Assert::same([], $presenter->getTemplate()->stamps ?? []);
	Assert::same(0, $presenter->getTemplate()->totalStampsCount);
	Assert::true(count($stampsModel->filters) > 0);
	return $stampsModel->filters;
}


// "My collection" of a logged-in user with an empty collection asks for no stamps, not all of them (#5)
foreach (homepageFilters(['user'], [], ['onlyCollected' => '1']) as $filter) {
	Assert::same([], $filter['ids'] ?? null);
}


// The same for an anonymous user opening ?onlyCollected=1 (#5)
foreach (homepageFilters(null, [], ['onlyCollected' => '1']) as $filter) {
	Assert::same([], $filter['ids'] ?? null);
}


// A non-empty collection filters by the collected stamp ids (#5)
foreach (homepageFilters(['user'], [3 => 'a', 8 => 'b'], ['onlyCollected' => '1']) as $filter) {
	Assert::same([3, 8], $filter['ids'] ?? null);
}


// Without onlyCollected there is no ids filter (#5)
foreach (homepageFilters(['user'], [3 => 'a'], []) as $filter) {
	Assert::false(array_key_exists('ids', $filter));
}


// Sorting an empty collection by date doesn't crash (search() returns null for no rows)
foreach (['date', '-date'] as $sort) {
	homepageFilters(null, [], ['onlyCollected' => '1', 'sort' => $sort]);
	homepageFilters(['user'], [], ['onlyCollected' => '1', 'sort' => $sort]);
}


// Sorting by collection date is done by the model in SQL across pages; the presenter passes the user and keeps the order (#6)
function collectedStamp(int $id, ?string $date): App\Dto\CollectedStampDto
{
	return new App\Dto\CollectedStampDto($id, "Stamp $id", null, null, null, '0', '0', null, $date === null ? null : new Nette\Utils\DateTime($date), null, null);
}


$collection = [5 => collectedStamp(5, '2024-01-01'), 3 => collectedStamp(3, '2023-01-01'), 9 => collectedStamp(9, null)];
foreach (['date', '-date'] as $sort) {
	$translator = PresenterTestHelpers::createTranslator();
	$formFactory = new FormFactory($translator);
	$formFactory->register(new App\Forms\FilterForm($translator));
	$formFactory->register(new EditStampForm($translator));
	$modelOrder = [9, 5, 3]; // neither ascending nor descending by date
	$stampsModel = new HomepageFakeStampsModel(array_map(
		fn(int $id) => Nette\Utils\ArrayHash::from(['id' => $id, 'name' => "Stamp $id", 'image' => null]),
		$modelOrder,
	));

	$presenter = new HomepagePresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']));
	$presenter->injectCollectionModel(new HomepageCollectionModelWith($collection));
	$presenter->injectStampsModel($stampsModel);
	$presenter->injectFormFactory($formFactory);
	$response = $presenter->run(new Request('Homepage', 'GET', ['action' => 'default', 'onlyCollected' => '1', 'sort' => $sort]));
	Assert::type(Nette\Application\Responses\TextResponse::class, $response);
	assert($response instanceof Nette\Application\Responses\TextResponse);
	(string) $response->getSource(); // renders the template

	Assert::same($modelOrder, array_map(fn($stamp) => $stamp->id, $presenter->getTemplate()->stamps));
	foreach ($stampsModel->filters as $filter) {
		Assert::same($sort, $filter['sort']);
		Assert::same(1, $filter['userId'] ?? null);
		Assert::same([5, 3, 9], $filter['ids']);
	}
}


// An anonymous user sorting by date passes no user id (the model falls back to id order) (#6)
foreach (homepageFilters(null, [], ['onlyCollected' => '1', 'sort' => 'date']) as $filter) {
	Assert::null($filter['userId'] ?? null);
}


// An unknown sort value, or a date sort outside "My collection" (the form doesn't offer it there), is ignored instead of crashing
foreach (['nonsense', 'date', '-date'] as $sort) {
	foreach (homepageFilters(['user'], [], ['sort' => $sort]) as $filter) {
		Assert::null($filter['sort'], $sort);
	}
}
foreach (homepageFilters(['user'], [], ['onlyCollected' => '1', 'sort' => 'nonsense']) as $filter) {
	Assert::null($filter['sort']);
}


// The list shows 60 stamps per page; the second page starts at the 61st stamp (#49)
$translator = PresenterTestHelpers::createTranslator();
$formFactory = new FormFactory($translator);
$formFactory->register(new App\Forms\FilterForm($translator));
$formFactory->register(new EditStampForm($translator));
$stampsModel = new HomepageFakeStampsModel(array_map(
	fn(int $id) => Nette\Utils\ArrayHash::from(['id' => $id, 'name' => "Stamp $id", 'image' => $id === 5 ? 'stamp-5.jpg' : null]),
	[5, 7, 9],
), 150);
$presenter = new HomepagePresenter($translator);
PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']));
$presenter->injectCollectionModel(new HomepageCollectionModelWith([5 => collectedStamp(5, '2024-06-02'), 9 => collectedStamp(9, null)]));
$presenter->injectStampsModel($stampsModel);
$presenter->injectFormFactory($formFactory);
$response = $presenter->run(new Request('Homepage', 'GET', ['action' => 'default', 'paginator-page' => '2']));
assert($response instanceof Nette\Application\Responses\TextResponse);
$html = (string) $response->getSource();

$paginator = $presenter['paginator'];
assert($paginator instanceof App\Helpers\Paginator);
Assert::same(60, $paginator->getItemsPerPage());
Assert::same([[60, 60]], $stampsModel->pages);

// Each stamp is a compact row linking to its detail, with the number and the name (#49)
preg_match_all('~<article class="stamp-row" id="stamp-(\d+)">.*?</article>~s', $html, $m);
$rows = array_combine(array_map('intval', $m[1]), $m[0]);
Assert::same([5, 7, 9], array_keys($rows));
foreach ($rows as $id => $row) {
	Assert::contains("href=\"/stamp/$id\"", $row);
	Assert::contains("#$id", $row);
	Assert::contains("Stamp $id", $row);
}
Assert::contains('/images/stamps/stamp-5.jpg" alt="Stamp 5"', $rows[5]);

// A collected stamp shows its date and a check button carrying the data for the shared collect sheet (#49, #48)
Assert::contains('stamp.collectedOn 2. 6. 2024', $rows[5]);
Assert::contains(
	'<button type="button" class="button stamp-row-button js-collect is-success" data-stamp-id="5" data-stamp-number="5" '
	. 'data-stamp-name="Stamp 5" data-collected="1" data-date="2. 6. 2024" data-note="" aria-label="stamp.editInCollection"',
	$rows[5],
);
// ... without a date just "collected" (#49)
Assert::match('%A%stamp.collectedOn</span>%A%', $rows[9]);
Assert::contains('data-stamp-id="9" data-stamp-number="9" data-stamp-name="Stamp 9" data-collected="1" data-date=""', $rows[9]);

// A not collected stamp has the "+" button for adding (#49, #48)
Assert::contains(
	'<button type="button" class="button stamp-row-button js-collect" data-stamp-id="7" data-stamp-number="7" '
	. 'data-stamp-name="Stamp 7" data-collected="0" data-date="" data-note="" aria-label="stamp.collect"',
	$rows[7],
);
Assert::notContains('stamp.collectedOn', $rows[7]);

// No per-row dialogs, forms or GET links changing the collection; one shared collect form per page (#48)
Assert::notContains('<dialog', $html);
Assert::notContains('toggleCollect', $html);
Assert::same(2, substr_count($html, '<form')); // filter and collect form
Assert::match('%A%<div id="snippet--collectSheet"><div id="collect-sheet" class="collect-sheet" hidden>%A%<form action="/?paginator-page=2" method="post" id="frm-collectForm">%A%', $html);
Assert::contains('<div class="collect-sheet-photo"></div>', $html);

// No old big cards on the homepage (#49)
Assert::notContains('class="card"', $html);
Assert::notContains('ribbon', $html);


/** StampsModel double knowing only the stamp 1984; lists nothing. */
final class HomepageJumpStampsModel extends App\Models\StampsModel
{
	/** @var list<int> */
	public array $fetched = [];


	public function __construct()
	{
	}


	public function fetch(int $id): ?Nette\Database\Table\ActiveRow
	{
		$this->fetched[] = $id;
		if ($id !== 1984) {
			return null;
		}
		$selection = (new ReflectionClass(Nette\Database\Table\Selection::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Nette\Database\Table\Selection::class, 'cache'))->setValue($selection, null);
		return new Nette\Database\Table\ActiveRow(['id' => 1984], $selection);
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


/**
 * Submits the filter form (GET) with the given search term.
 * @return array{?Nette\Application\Response, HomepageJumpStampsModel}
 */
function submitSearch(string $search): array
{
	$translator = PresenterTestHelpers::createTranslator();
	$formFactory = new FormFactory($translator);
	$formFactory->register(new App\Forms\FilterForm($translator));
	$formFactory->register(new EditStampForm($translator));
	$stampsModel = new HomepageJumpStampsModel;
	$presenter = new HomepagePresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['Sec-Fetch-Site' => 'same-origin']);
	$presenter->injectCollectionModel(new HomepageCollectionModelWith([]));
	$presenter->injectStampsModel($stampsModel);
	$presenter->injectFormFactory($formFactory);
	$response = $presenter->run(new Request('Homepage', 'GET', [
		'action' => 'default',
		'search' => $search,
		'sort' => 'id',
		'do' => 'filterForm-submit',
	]));
	return [$response, $stampsModel];
}


// Searching for the exact number of an existing stamp goes straight to its detail (#48)
foreach (['1984', ' 1984 '] as $search) {
	[$response] = submitSearch($search);
	Assert::type(RedirectResponse::class, $response, $search);
	assert($response instanceof RedirectResponse);
	Assert::same('/stamp/1984', (new Nette\Http\Url($response->getUrl()))->getPath());
	Assert::null((new Nette\Http\Url($response->getUrl()))->getQueryParameter('search'));
}


// Not a pure number, or a number of no stamp: the normal search results (#48)
foreach (['19x', '1984 Praha', '1985'] as $search) {
	[$response, $stampsModel] = submitSearch($search);
	Assert::type(Nette\Application\Responses\TextResponse::class, $response, $search);
}
[, $stampsModel] = submitSearch('19x');
Assert::same([], $stampsModel->fetched); // no lookup for a term that is not a number
