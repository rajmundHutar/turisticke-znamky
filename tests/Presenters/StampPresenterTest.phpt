<?php

/**
 * Test: StampPresenter edit forms store the values and redirect back (#3, #29); the detail shows the feed data (#14, #4)
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
function renderStampDetail(array $stamp, array $salespoints = [], array $closest = []): string
{
	$presenter = new StampPresenter(PresenterTestHelpers::createTranslator());
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(null));
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
