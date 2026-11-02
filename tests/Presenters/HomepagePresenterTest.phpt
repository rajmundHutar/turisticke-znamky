<?php

/**
 * Test: HomepagePresenter persistent params and edit form redirect (#29); no type filter (#14)
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


/** CollectionModel double without a database; records update calls. */
final class HomepageFakeCollectionModel extends CollectionModel
{
	/** @var list<array{int, int, array<string, mixed>}> */
	public array $updates = [];


	public function __construct()
	{
	}


	public function fetchByUser(?int $userId): array
	{
		return [];
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


// Submitting the edit form stores the values and redirects back (no stale collection rendered), keeping filter, sort and page (#29)
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
		'do' => 'editStampForm-5-submit',
	],
	['id' => '5', 'date' => '2. 6. 2024', 'comment' => 'Sunny', 'ok' => 'general.submit'],
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


	public function __construct()
	{
	}


	public function search(array $filter, $limit = null, $offset = null): ?array
	{
		$this->filters[] = $filter;
		return null;
	}


	public function count(array $filter = []): int
	{
		$this->filters[] = $filter;
		return 0;
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
