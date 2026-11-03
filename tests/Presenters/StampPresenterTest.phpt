<?php

/**
 * Test: StampPresenter edit forms store the values and redirect back (#3, #29), show invalid date errors (#31); the detail shows the feed data (#14, #4)
 */

declare(strict_types=1);

use App\Dto\CollectedStampDto;
use App\Forms\EditStampForm;
use App\Forms\FormFactory;
use App\Models\CollectionModel;
use App\Models\StampsModel;
use App\Presenters\StampPresenter;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
use Nette\Application\Responses\TextResponse;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/PresenterTestHelpers.php';


/** CollectionModel double without a database; records update calls. */
final class StampFakeCollectionModel extends CollectionModel
{
	/** @var list<array{int, int, array<string, mixed>}> */
	public array $updates = [];


	/** @param  array<int, CollectedStampDto>  $collection */
	public function __construct(
		private array $collection = [],
	) {
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


/** Stops the request before rendering, so no database or templates are needed. */
final class TestStampPresenter extends StampPresenter
{
	protected function beforeRender(): void
	{
		$this->terminate();
	}
}


function createStampPresenter(StampFakeCollectionModel $collectionModel): TestStampPresenter
{
	$translator = PresenterTestHelpers::createTranslator();
	$formFactory = new FormFactory($translator);
	$formFactory->register(new EditStampForm($translator));

	$presenter = new TestStampPresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['Sec-Fetch-Site' => 'same-origin']); // signals are CSRF protected
	$presenter->injectCollectionModel($collectionModel);
	$presenter->injectFormFactory($formFactory);
	$presenter->injectStampsModel(new class extends StampsModel { // the stamp is loaded in the action
		public function __construct()
		{
		}


		public function fetch(int $id): ActiveRow
		{
			return createRow(['id' => $id]);
		}
	});
	return $presenter;
}


/**
 * @param  array{int, int, array<string, mixed>}  $update
 */
function assertUpdate(int $stampId, string $date, string $comment, array $update): void
{
	Assert::same([$stampId, 1, $comment], [$update[0], $update[1], $update[2]['comment']]);
	Assert::type(DateTimeInterface::class, $update[2]['date']);
	Assert::same($date, $update[2]['date']->format('Y-m-d'));
}


// Submitting the edit form of a nearby stamp card stores the values and redirects back to the detail with the card's anchor
$collectionModel = new StampFakeCollectionModel;
$presenter = createStampPresenter($collectionModel);
$response = $presenter->run(new Request(
	'Stamp',
	'POST',
	['action' => 'default', 'id' => '1', 'do' => 'editStampForm-7-submit'],
	['id' => '7', 'date' => '1. 5. 2024', 'comment' => 'Windy', 'ok' => 'general.submit'],
));
Assert::count(1, $collectionModel->updates);
assertUpdate(7, '2024-05-01', 'Windy', $collectionModel->updates[0]);
Assert::type(RedirectResponse::class, $response);
assert($response instanceof RedirectResponse);
$url = new Nette\Http\Url($response->getUrl());
Assert::same('/stamp/1', $url->getPath());
Assert::same('stamp-7', $url->getFragment());
Assert::null($url->getQueryParameter('do'));


// Submitting the detail's own edit form stores the values and redirects back to the detail
$collectionModel = new StampFakeCollectionModel;
$presenter = createStampPresenter($collectionModel);
$response = $presenter->run(new Request(
	'Stamp',
	'POST',
	['action' => 'default', 'id' => '1', 'do' => 'editSingleStampForm-submit'],
	['id' => '1', 'date' => '2. 5. 2024', 'comment' => 'Foggy', 'ok' => 'general.submit'],
));
Assert::count(1, $collectionModel->updates);
assertUpdate(1, '2024-05-02', 'Foggy', $collectionModel->updates[0]);
Assert::type(RedirectResponse::class, $response);
assert($response instanceof RedirectResponse);
$url = new Nette\Http\Url($response->getUrl());
Assert::same('/stamp/1', $url->getPath());
Assert::null($url->getQueryParameter('do'));


// The collection is loaded in the action, so the detail's edit form has its defaults before rendering (e.g. while processing a submit)
$collectionModel = new StampFakeCollectionModel([
	1 => new CollectedStampDto(1, 'Sněžka', 'type', 'region', new DateTime, '50.7', '15.7', 'img.jpg', new DateTime('2024-05-01'), 'Windy', null),
]);
$presenter = createStampPresenter($collectionModel);
$presenter->run(new Request('Stamp', 'GET', ['action' => 'default', 'id' => '1']));
$form = $presenter['editSingleStampForm'];
Assert::same('1. 5. 2024', $form['date']->getValue());
Assert::same('Windy', $form['comment']->getValue());


/** StampsModel double without a database. */
final class StampFakeStampsModel extends StampsModel
{
	/**
	 * @param  array<string, mixed>  $stamp
	 * @param  list<array<string, mixed>>  $salespoints
	 * @param  array<int, array<string, mixed>>  $closest
	 */
	public function __construct(
		private array $stamp,
		private array $salespoints = [],
		private array $closest = [],
	) {
	}


	public function fetch(int $id): ActiveRow
	{
		return createRow($this->stamp);
	}


	public function fetchSalespoints(int $stampId): array
	{
		return array_map(createRow(...), $this->salespoints);
	}


	public function fetchClosest(float $lat, float $lng, ?int $num = 10, ?array $exclude = null): array
	{
		return $this->closest;
	}
}


/**
 * ActiveRow holding the given data, without a database (a selection without cache never refetches columns).
 * @param  array<string, mixed>  $data
 */
function createRow(array $data): ActiveRow
{
	$selection = (new ReflectionClass(Selection::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(Selection::class, 'cache'))->setValue($selection, null);
	return new ActiveRow($data, $selection);
}


/**
 * @param  array<string, mixed>  $stamp
 * @param  list<array<string, mixed>>  $salespoints
 * @param  array<int, array<string, mixed>>  $closest
 */
function renderStampDetail(array $stamp, array $salespoints = [], array $closest = [], string $mapyApiKey = ''): string
{
	$presenter = new StampPresenter(PresenterTestHelpers::createTranslator());
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(null));
	$presenter->injectMapyConfig(new App\Helpers\MapyConfig($mapyApiKey));
	$presenter->injectCollectionModel(new StampFakeCollectionModel);
	$presenter->injectStampsModel(new StampFakeStampsModel($stamp, $salespoints, $closest));
	$response = $presenter->run(new Request('Stamp', 'GET', ['action' => 'default', 'id' => (string) $stamp['id']]));
	Assert::type(TextResponse::class, $response);
	assert($response instanceof TextResponse);
	return (string) $response->getSource();
}


$feedStamp = [
	'id' => 3091,
	'name' => 'Nová známka',
	'type' => null,
	'region' => null,
	'created_at' => null,
	'lat' => '50.08',
	'lng' => '14.42',
	'image' => null,
	'description' => "First paragraph <b>bold</b>\n\nSecond paragraph",
	'detail_url' => 'https://turisticke-znamky.cz/items/3535',
	'website' => 'https://example.com/castle',
	'wikipedia' => 'https://cs.wikipedia.org/wiki/Hrad',
	'facebook' => 'https://www.facebook.com/hrad',
];
$salespoints = [
	['id' => 1, 'stamp_id' => 3091, 'name' => 'Pokladna hradu', 'lat' => '50.081', 'lng' => '14.421', 'website' => 'https://example.com/shop', 'sort' => 1, 'system' => 0],
	['id' => 2, 'stamp_id' => 3091, 'name' => 'Prodej dočasně pozastaven', 'lat' => null, 'lng' => null, 'website' => null, 'sort' => 2, 'system' => 1],
];
$closest = [
	7 => ['id' => 7, 'name' => 'Bez obrázku', 'type' => 'Rozhledna', 'lat' => '50.1', 'lng' => '14.5', 'image' => null, 'distance' => 1500.0, 'prettyDistance' => '1.5 km'],
];


// The detail of a feed stamp (no type, region, creation date nor image) shows its description, links and salespoints (#14, #4)
$html = renderStampDetail($feedStamp, $salespoints, $closest);
Assert::contains('First paragraph &lt;b&gt;bold&lt;/b&gt;<br>', $html); // escaped, newlines kept
Assert::contains('Second paragraph', $html);
Assert::contains('href="https://turisticke-znamky.cz/items/3535" target="_blank" rel="noopener"', $html);
Assert::notContains('/znamky/-c', $html);
foreach (['https://example.com/castle', 'https://cs.wikipedia.org/wiki/Hrad', 'https://www.facebook.com/hrad', 'https://example.com/shop'] as $link) {
	Assert::contains('href="' . $link . '" target="_blank" rel="noopener"', $html);
}
Assert::contains('Pokladna hradu', $html);
Assert::contains('Prodej dočasně pozastaven', $html);
Assert::contains('"lat":"50.081"', $html); // salespoint with location goes to the map
Assert::notContains('/images/stamps/"', $html); // no image without a file name, neither on the detail nor on the nearby card
Assert::notMatch('~>\s*Rozhledna\s*<~', $html); // type is no longer displayed on cards (it stays only in the map data)


// Type, region and creation date are no longer displayed; missing links and salespoints render nothing (#14)
$html = renderStampDetail([
	'id' => 1,
	'name' => 'Praděd',
	'type' => 'Rozhledna',
	'region' => 'Jeseníky',
	'created_at' => new DateTime('2001-02-03'),
	'lat' => '50.08',
	'lng' => '17.23',
	'image' => '1-praded.jpg',
	'description' => null,
	'detail_url' => null,
	'website' => null,
	'wikipedia' => null,
	'facebook' => null,
]);
Assert::contains('/images/stamps/1-praded.jpg"', $html);
Assert::notMatch('~>\s*Rozhledna\s*<~', $html); // stays only in the map data
Assert::notMatch('~>\s*Jeseníky\s*<~', $html);
Assert::notContains('3. 2. 2001', $html);
Assert::notContains('detail.detailsOn', $html);
Assert::notContains('rel="noopener"', $html);
Assert::notContains('detail.salespoints', $html);


/** StampsModel double without a database that knows no stamp. */
final class StampEmptyStampsModel extends StampsModel
{
	public function __construct()
	{
	}


	public function fetch(int $id): ?ActiveRow
	{
		return null;
	}
}


// An unknown stamp is a 404 raised in the action, before rendering (#7)
$presenter = createStampPresenter(new StampFakeCollectionModel);
$presenter->injectStampsModel(new StampEmptyStampsModel);
$e = Assert::exception(
	fn() => $presenter->run(new Request('Stamp', 'GET', ['action' => 'default', 'id' => '999999'])),
	Nette\Application\BadRequestException::class,
);
Assert::same(404, $e->getCode());


/**
 * Submits an edit form with an invalid date as a logged-in user collecting the detail stamp and the nearby stamp 7; returns the rendered page.
 * @param  array<string, mixed>  $stamp
 * @param  array<int, array<string, mixed>>  $closest
 */
function submitInvalidDate(StampFakeCollectionModel $collectionModel, array $stamp, array $closest, string $formName, string $stampId): string
{
	$translator = new App\Helpers\Translator;
	$formFactory = new FormFactory($translator);
	$formFactory->register(new EditStampForm($translator));

	$presenter = new StampPresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['Sec-Fetch-Site' => 'same-origin']);
	$presenter->injectCollectionModel($collectionModel);
	$presenter->injectFormFactory($formFactory);
	$presenter->injectStampsModel(new StampFakeStampsModel($stamp, [], $closest));
	$response = $presenter->run(new Request(
		'Stamp',
		'POST',
		['action' => 'default', 'id' => (string) $stamp['id'], 'do' => "$formName-submit"],
		['id' => $stampId, 'date' => '31. 2. 2024', 'comment' => 'Rainy', 'ok' => 'general.submit'],
	));
	Assert::type(TextResponse::class, $response);
	assert($response instanceof TextResponse);
	return (string) $response->getSource();
}


$collected = static fn(int $id) => new CollectedStampDto($id, 'Stamp', null, null, null, '50.0', '14.0', null, new DateTime('2024-05-01'), 'Windy', null);
$detailStamp = ['id' => 1] + $feedStamp;


// An invalid date in the detail's own edit form is not saved; the error is shown at the form with the entered value kept (#31)
$collectionModel = new StampFakeCollectionModel([1 => $collected(1), 7 => $collected(7)]);
$html = submitInvalidDate($collectionModel, $detailStamp, $closest, 'editSingleStampForm', '1');
Assert::same([], $collectionModel->updates);
Assert::match('~<form[^>]+id="frm-editSingleStampForm".*value="31\. 2\. 2024".*Invalid date, use D\. M\. YYYY\..*</form>~s', $html);
Assert::notContains('data-open', $html); // the nearby card's dialog stays closed


// An invalid date in a nearby card's edit form is not saved; the error is shown in its dialog, which opens on page load (#31)
$collectionModel = new StampFakeCollectionModel([1 => $collected(1), 7 => $collected(7)]);
$html = submitInvalidDate($collectionModel, $detailStamp, $closest, 'editStampForm-7', '7');
Assert::same([], $collectionModel->updates);
Assert::match('~<dialog class="modal" data-stampId="7" data-open>\s*.*value="31\. 2\. 2024".*Invalid date, use D\. M\. YYYY\..*</dialog>~s', $html);
Assert::same(1, substr_count($html, 'Invalid date')); // not at the detail's own form


// The Mapy.cz API key comes from the configuration, not from the template (#39)
$html = renderStampDetail($feedStamp, mapyApiKey: 'test-key-123');
Assert::contains('const API_KEY = "test-key-123";', $html);
Assert::notContains('qsdduL32xP7zUe8rj174i2LSClOB0JlpArnIk3fG_3Y', $html);
