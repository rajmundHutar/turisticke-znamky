<?php

/**
 * Test: StampPresenter collect form stores the values and redirects back (#3, #29, #48), shows invalid date errors (#31); the detail shows the feed data (#14, #4);
 * the layout of the detail: collection status, "Kde koupit", collapsible description (#51)
 */

declare(strict_types=1);

use App\Dto\CollectedStampDto;
use App\Forms\EditStampForm;
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
require __DIR__ . '/../../app/Helpers/Flash.php'; // global class, loaded by RobotLoader in the app


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

	$presenter = new TestStampPresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['Sec-Fetch-Site' => 'same-origin']); // signals are CSRF protected
	$presenter->injectCollectionModel($collectionModel);
	$presenter->injectEditStampForm(new EditStampForm($translator));
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


$collected = static fn(int $id) => new CollectedStampDto($id, 'Stamp', null, null, null, '50.0', '14.0', null, new DateTime('2024-05-01'), 'Windy', null);


// Submitting the collect form for a collected nearby stamp stores the values and redirects back to the detail with the card's anchor
$collectionModel = new StampFakeCollectionModel([1 => $collected(1), 7 => $collected(7)]);
$presenter = createStampPresenter($collectionModel);
$response = $presenter->run(new Request(
	'Stamp',
	'POST',
	['action' => 'default', 'id' => '1', 'do' => 'collectForm-submit'],
	['id' => '7', 'date' => '1. 5. 2024', 'comment' => 'Windy', 'ok' => 'stamp.saveToCollection'],
));
Assert::count(1, $collectionModel->updates);
assertUpdate(7, '2024-05-01', 'Windy', $collectionModel->updates[0]);
Assert::type(RedirectResponse::class, $response);
assert($response instanceof RedirectResponse);
$url = new Nette\Http\Url($response->getUrl());
Assert::same('/stamp/1', $url->getPath());
Assert::same('stamp-7', $url->getFragment());
Assert::null($url->getQueryParameter('do'));


// Submitting the collect form for the collected detail stamp stores the values and redirects back to the detail
$collectionModel = new StampFakeCollectionModel([1 => $collected(1), 7 => $collected(7)]);
$presenter = createStampPresenter($collectionModel);
$response = $presenter->run(new Request(
	'Stamp',
	'POST',
	['action' => 'default', 'id' => '1', 'do' => 'collectForm-submit'],
	['id' => '1', 'date' => '2. 5. 2024', 'comment' => 'Foggy', 'ok' => 'stamp.saveToCollection'],
));
Assert::count(1, $collectionModel->updates);
assertUpdate(1, '2024-05-02', 'Foggy', $collectionModel->updates[0]);
Assert::type(RedirectResponse::class, $response);
assert($response instanceof RedirectResponse);
$url = new Nette\Http\Url($response->getUrl());
Assert::same('/stamp/1', $url->getPath());
Assert::null($url->getQueryParameter('do'));


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


	public function fetchClosest(float $lat, float $lng, ?int $num = 10, ?array $exclude = null, ?array $ids = null): array
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
 * @param  list<string>|null  $roles
 * @param  array<int, CollectedStampDto>  $collection
 */
function renderStampDetail(array $stamp, array $salespoints = [], array $closest = [], string $mapyApiKey = '', ?array $roles = null, array $collection = []): string
{
	$translator = new App\Helpers\Translator;
	$presenter = new StampPresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser($roles));
	$presenter->injectMapyConfig(new App\Helpers\MapyConfig($mapyApiKey));
	$presenter->injectEditStampForm(new EditStampForm($translator));
	$presenter->injectCollectionModel(new StampFakeCollectionModel($collection));
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
// a system salespoint and the missing images in the muted text colour, Bulma's grey is below 4.5:1 on the dark theme (#53)
Assert::match('%A%<li class="text-muted is-italic">%A%Prodej dočasně pozastaven%A%', $html);
Assert::notContains('has-text-grey', $html);
// the detail belongs to Známky in the bottom navigation (#53)
Assert::match('%A%<a href="/" class="bottom-nav-item" aria-current="page">%A%', renderStampDetail($feedStamp, roles: ['user']));


// Type, region and creation date are no longer displayed; missing links and salespoints render nothing (#14)
$html = renderStampDetail([
	'id' => 1,
	'name' => 'Praděd',
	'type' => 'Rozhledna',
	'region' => 'Jeseníky',
	'created_at' => new DateTime('2001-02-03'),
	'lat' => '50.08',
	'lng' => '17.23',
	'image' => '1-praded.png',
	'description' => null,
	'detail_url' => null,
	'website' => null,
	'wikipedia' => null,
	'facebook' => null,
]);
Assert::contains('/images/stamps/1-praded.png"', $html);
Assert::notMatch('~>\s*Rozhledna\s*<~', $html); // stays only in the map data
Assert::notMatch('~>\s*Jeseníky\s*<~', $html);
Assert::notContains('3. 2. 2001', $html);
Assert::notContains('Více na', $html);
Assert::notContains('rel="noopener"', $html);
Assert::notContains('Kde koupit', $html);
Assert::notContains('O místě', $html);


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
 * Submits the collect form with an invalid date as a logged-in user collecting the detail stamp and the nearby stamp 7; returns the rendered page.
 * @param  array<string, mixed>  $stamp
 * @param  array<int, array<string, mixed>>  $closest
 */
function submitInvalidDate(StampFakeCollectionModel $collectionModel, array $stamp, array $closest, string $stampId): string
{
	$translator = new App\Helpers\Translator;

	$presenter = new StampPresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['Sec-Fetch-Site' => 'same-origin']);
	$presenter->injectCollectionModel($collectionModel);
	$presenter->injectEditStampForm(new EditStampForm($translator));
	$presenter->injectStampsModel(new StampFakeStampsModel($stamp, [], $closest));
	$response = $presenter->run(new Request(
		'Stamp',
		'POST',
		['action' => 'default', 'id' => (string) $stamp['id'], 'do' => 'collectForm-submit'],
		['id' => $stampId, 'date' => '31. 2. 2024', 'comment' => 'Rainy', 'ok' => 'stamp.saveToCollection'],
	));
	Assert::type(TextResponse::class, $response);
	assert($response instanceof TextResponse);
	return (string) $response->getSource();
}


$detailStamp = ['id' => 1] + $feedStamp;


// An invalid date in the collect form is not saved; the error is shown in the shared sheet with the entered value kept,
// the sheet opens on page load (#31, #48)
foreach (['1', '7'] as $stampId) {
	$collectionModel = new StampFakeCollectionModel([1 => $collected(1), 7 => $collected(7)]);
	$html = submitInvalidDate($collectionModel, $detailStamp, $closest, $stampId);
	Assert::same([], $collectionModel->updates);
	Assert::match('~<dialog id="collect-sheet" class="collect-sheet" [^>]*data-open>.*<form[^>]+id="frm-collectForm".*value="31\. 2\. 2024".*Neplatné datum, zadejte D\. M\. RRRR\..*value="'
		. $stampId
		. '".*</form>~s', $html);
	Assert::same(1, substr_count($html, 'Neplatné datum'));
}


// A logged-in user sees the date and note of the collected detail stamp and buttons with the data for the shared sheet;
// no GET links nor per-card forms change the collection (#48)
$html = renderStampDetail($detailStamp, [], $closest, roles: ['user'], collection: [1 => $collected(1)]);
Assert::match(
	'~<section class="stamp-detail-collection" id="snippet--detailCollection">.*Ve sbírce od 1. 5. 2024.*Windy.*'
	. 'data-stamp-id="1" data-stamp-number="1" data-stamp-name="Nová známka" data-collected="1" data-date="1. 5. 2024" data-note="Windy"[^>]*>\s*<span>Upravit</span>~s',
	$html,
);
Assert::match(
	'~<div id="snippet--stamp-7">\s*<div class="card" id="stamp-7">.*class="button is-white stamp-card-button js-collect" '
	. 'data-stamp-id="7" data-stamp-number="7" data-stamp-name="Bez obrázku" data-collected="0" data-date="" data-note=""~s',
	$html,
);
Assert::notContains('toggleCollect', $html);
Assert::same(1, substr_count($html, '<dialog'));
Assert::same(1, substr_count($html, '<form'));
Assert::contains('<form action="/stamp/1" method="post" id="frm-collectForm" class="ajax" data-naja-history="off" data-naja-force-redirect>', $html);


// An anonymous user gets no collect buttons nor the form
$html = renderStampDetail($detailStamp, [], $closest);
Assert::notContains('js-collect', $html);
Assert::notContains('<form', $html);
Assert::notContains('Ve sbírce', $html);


// The detail (#51): the image on top, number and name, the collection status, "Kde koupit" before the collapsible "O místě", then the map
$snezka = ['id' => 8, 'name' => 'Sněžka', 'lat' => '50.7', 'lng' => '15.7', 'image' => '8-snezka.png', 'distance' => 900.0, 'prettyDistance' => '0.9 km'];
$nearby = [8 => $snezka] + $closest;
$html = renderStampDetail(['image' => '1-praded.png'] +
	$detailStamp, $salespoints, $nearby, roles: ['user'], collection: [1 => new CollectedStampDto(1, 'Stamp', null, null, null, '50.0', '14.0', null, new DateTime('2022-05-29'), 'S rodinou', null)]);
Assert::match('~<figure class="stamp-detail-image">\s*'
	. '<a data-fslightbox href="http://localhost/images/stamps/1-praded.png" target="_blank">\s*'
	. '<img src="http://localhost/images/stamps/1-praded.png" alt="Nová známka">.*</figure>\s*'
	. '<p class="stamp-detail-number">Známka #1</p>\s*<h1 class="title[^"]*">Nová známka</h1>.*'
	. 'Ve sbírce od 29. 5. 2022.*S rodinou.*'
	. '<h2[^>]*>Kde koupit</h2>.*Pokladna hradu.*'
	. '<h2[^>]*>O místě</h2>\s*<div class="stamp-description js-description" id="stamp-description">.*First paragraph.*</div>\s*'
	. '<button type="button" class="[^"]*js-description-toggle[^"]*"[^>]*aria-controls="stamp-description"[^>]*data-less="[^"]+"[^>]*>Zobrazit celý popis</button>.*'
	. 'id="map"~s', $html);
// the nearby cards load the lazy thumbnails, only the detail image is the full one (#52)
Assert::contains('<img src="http://localhost/thumb/8-snezka" width="400" height="300" alt="Sněžka" loading="lazy" decoding="async">', $html);
Assert::notContains('/images/stamps/8-snezka.png', $html);

// a logged-in user without the stamp gets the add button with the data for the shared sheet
$html = renderStampDetail($detailStamp, [], $closest, roles: ['user']);
Assert::notContains('Ve sbírce', $html);
Assert::match(
	'~<section class="stamp-detail-collection" id="snippet--detailCollection">\s*'
	. '<button type="button" class="[^"]*js-collect[^"]*" data-stamp-id="1" [^>]*data-collected="0"[^>]*>.*<span>Přidat do sbírky</span>~s',
	$html,
);


// The Mapy.cz API key comes from the configuration, not from the template (#39)
$html = renderStampDetail($feedStamp, mapyApiKey: 'test-key-123');
Assert::contains('const API_KEY = "test-key-123";', $html);
Assert::notContains('qsdduL32xP7zUe8rj174i2LSClOB0JlpArnIk3fG_3Y', $html);
