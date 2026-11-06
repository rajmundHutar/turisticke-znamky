<?php

/**
 * Test: NearbyPresenter collect form keeps the location (#2, #48); the list of stamp rows with distances,
 * the location status and the switch Vše / Mám / Nemám (#51) with the counts over the 20 nearest stamps (#71)
 */

declare(strict_types=1);

use App\Dto\CollectedStampDto;
use App\Forms\EditStampForm;
use App\Models\CollectionModel;
use App\Models\StampsModel;
use App\Presenters\NearbyPresenter;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/PresenterTestHelpers.php';
require __DIR__ . '/../../app/Helpers/Flash.php'; // global class, loaded by RobotLoader in the app


/** StampsModel double without a database. */
final class NearbyFakeStampsModel extends StampsModel
{
	/** @var list<array{float, float, ?int, ?array<int>, ?array<int>}> */
	public array $closestCalls = [];


	/** @param  array<int, array<string, mixed>>  $closest */
	public function __construct(
		private array $closest = [],
	) {
	}


	public function fetchClosest(float $lat, float $lng, ?int $num = 10, ?array $exclude = null, ?array $ids = null): array
	{
		$this->closestCalls[] = [$lat, $lng, $num, $exclude, $ids];
		$closest = array_filter(
			$this->closest,
			fn(array $stamp): bool => !in_array($stamp['id'], $exclude ?? [], true) && ($ids === null || in_array($stamp['id'], $ids, true)),
		);
		return array_slice($closest, 0, $num, true);
	}


	public function fetch(int $id): ActiveRow
	{
		$selection = (new ReflectionClass(Nette\Database\Table\Selection::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Nette\Database\Table\Selection::class, 'cache'))->setValue($selection, null);
		return new ActiveRow(['id' => $id], $selection);
	}
}


/** CollectionModel double without a database; records add and update calls. */
final class NearbyFakeCollectionModel extends CollectionModel
{
	/** @var list<array{int, int, ?DateTimeInterface, ?string}> */
	public array $added = [];

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


	public function isCollected(int $stampId, int $userId): bool
	{
		return isset($this->collection[$stampId]);
	}


	public function add(int $stampId, int $userId, ?DateTimeInterface $date, ?string $comment): void
	{
		$this->added[] = [$stampId, $userId, $date, $comment];
		$this->collection[$stampId] = new CollectedStampDto($stampId, 'Stamp', null, null, null, '50.0', '14.0', null, null, $comment, null);
	}


	public function update(int $stampId, int $userId, array $data): void
	{
		$this->updates[] = [$stampId, $userId, $data];
	}
}


/** Stops the request before rendering, so no templates are needed. */
final class TestNearbyPresenter extends NearbyPresenter
{
	protected function beforeRender(): void
	{
		$this->terminate();
	}
}


function createNearbyPresenter(NearbyFakeCollectionModel $collectionModel): TestNearbyPresenter
{
	$translator = PresenterTestHelpers::createTranslator();

	$presenter = new TestNearbyPresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['Sec-Fetch-Site' => 'same-origin']); // signals are CSRF protected
	$presenter->injectStampsModel(new NearbyFakeStampsModel);
	$presenter->injectCollectionModel($collectionModel);
	$presenter->injectEditStampForm(new EditStampForm($translator));
	return $presenter;
}


// Adding a stamp by the collect form redirects back keeping lat/lng (#48)
$collectionModel = new NearbyFakeCollectionModel;
$presenter = createNearbyPresenter($collectionModel);
$response = $presenter->run(new Request(
	'Nearby',
	'POST',
	['action' => 'default', 'lat' => '50.1', 'lng' => '14.4', 'do' => 'collectForm-submit'],
	['id' => '5', 'date' => '', 'comment' => '', 'ok' => 'stamp.saveToCollection'],
));
Assert::same([[5, 1, null, null]], $collectionModel->added);
Assert::type(RedirectResponse::class, $response);
assert($response instanceof RedirectResponse);
$url = new Nette\Http\Url($response->getUrl());
Assert::same('50.1', $url->getQueryParameter('lat'));
Assert::same('14.4', $url->getQueryParameter('lng'));
Assert::same('stamp-5', $url->getFragment());
Assert::null($url->getQueryParameter('do'));


// Submitting the collect form for a collected stamp stores the values and redirects back (no stale collection rendered), keeping lat/lng
$collectionModel = new NearbyFakeCollectionModel([
	5 => new CollectedStampDto(5, 'Sněžka', 'type', 'region', new DateTime, '50.7', '15.7', 'img.jpg', new DateTime('2024-05-01'), 'Windy', null),
]);
$presenter = createNearbyPresenter($collectionModel);
$response = $presenter->run(new Request(
	'Nearby',
	'POST',
	['action' => 'default', 'lat' => '50.1', 'lng' => '14.4', 'do' => 'collectForm-submit'],
	['id' => '5', 'date' => '2. 6. 2024', 'comment' => 'Sunny', 'ok' => 'stamp.saveToCollection'],
));
Assert::count(1, $collectionModel->updates);
[$stampId, $userId, $data] = $collectionModel->updates[0];
Assert::same([5, 1, 'Sunny'], [$stampId, $userId, $data['comment']]);
Assert::type(DateTimeInterface::class, $data['date']); // converted from Y-m-d (#11)
Assert::same('2024-06-02', $data['date']->format('Y-m-d'));
Assert::type(RedirectResponse::class, $response);
assert($response instanceof RedirectResponse);
$url = new Nette\Http\Url($response->getUrl());
Assert::same('50.1', $url->getQueryParameter('lat'));
Assert::same('14.4', $url->getQueryParameter('lng'));
Assert::same('stamp-5', $url->getFragment());


// The AJAX redraw after the location is known carries the collect form, whose action keeps lat/lng (#48)
$translator = PresenterTestHelpers::createTranslator();
$presenter = new NearbyPresenter($translator);
PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['X-Requested-With' => 'XMLHttpRequest']);
$presenter->injectStampsModel(new NearbyFakeStampsModel);
$presenter->injectCollectionModel(new NearbyFakeCollectionModel);
$presenter->injectEditStampForm(new EditStampForm($translator));
$presenter->run(new Request('Nearby', 'GET', ['action' => 'default', 'lat' => '50.1', 'lng' => '14.4']));
$snippets = (array) $presenter->getPayload()->snippets;
Assert::same(['snippet--stamps'], array_keys($snippets));
Assert::contains(
	'<form action="/nearby/?lat=50.1&amp;lng=14.4" method="post" enctype="multipart/form-data" id="frm-collectForm" class="ajax" data-naja-history="off" data-naja-force-redirect>',
	$snippets['snippet--stamps'],
);


$closestStamps = [
	19 => ['id' => 19, 'name' => 'Osada Rezek', 'lat' => '50.7', 'lng' => '15.4', 'image' => null, 'distance' => 2120.0, 'prettyDistance' => '2,1 km'],
];


/**
 * Renders the Nearby page (a full page load) with the stamp 5 collected unless given; returns the HTML.
 * @param  list<string>|null  $roles
 * @param  array<string, string>  $params
 * @param  array<int, CollectedStampDto>|null  $collection
 */
function renderNearby(NearbyFakeStampsModel $stampsModel, ?array $roles, array $params, ?array $collection = null): string
{
	$translator = new App\Helpers\Translator;
	$presenter = new NearbyPresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser($roles));
	$presenter->injectStampsModel($stampsModel);
	$presenter->injectCollectionModel(new NearbyFakeCollectionModel($collection ?? [
		5 => new CollectedStampDto(5, 'Sněžka', 'type', 'region', new DateTime, '50.7', '15.7', 'img.jpg', new DateTime('2024-05-01'), 'Windy', null),
	]));
	$presenter->injectEditStampForm(new EditStampForm($translator));
	$response = $presenter->run(new Request('Nearby', 'GET', ['action' => 'default'] + $params));
	Assert::type(Nette\Application\Responses\TextResponse::class, $response);
	assert($response instanceof Nette\Application\Responses\TextResponse);
	return (string) $response->getSource();
}


// A logged-in user sees "Nemám" by default: the nearest stamps not in the collection, as stamp rows with the distance
// under the name; the switch links keep the location; no coordinates in the heading (#51)
$stampsModel = new NearbyFakeStampsModel($closestStamps);
$html = renderNearby($stampsModel, ['user'], ['lat' => '50.1', 'lng' => '14.4']);
Assert::same([[50.1, 14.4, 20, [5], null], [50.1, 14.4, 20, null, null]], $stampsModel->closestCalls);
Assert::match('~<article class="stamp-row" id="stamp-19">.*class="stamp-row-name">Osada Rezek</span>\s*<span class="stamp-row-distance">.*2,1 km</span>~s', $html);
Assert::match('~<li class="is-active">\s*<a href="/nearby/\?lat=50.1&amp;lng=14.4"[^>]*>Nemám&nbsp;·&nbsp;1</a>~', $html);
Assert::match('~<a href="/nearby/\?filter=all&amp;lat=50.1&amp;lng=14.4"[^>]*>Vše&nbsp;·&nbsp;1</a>~', $html);
Assert::match('~<a href="/nearby/\?filter=collected&amp;lat=50.1&amp;lng=14.4"[^>]*>Mám&nbsp;·&nbsp;0</a>~', $html);
Assert::match('~<h1 class="title[^"]*">Poblíž</h1>~', $html);
Assert::notContains('50.1, 14.4', $html);
Assert::notContains('nearby.forLocation', $html);
// the location status, filled in by bundle.js, with the Czech texts of every state; the button to locate again
Assert::match('~<p class="nearby-status" id="nearby-status" role="status"[^>]* data-locating="Zjišťuji polohu…" data-located="Poloha zjištěna · přesnost ±&#123;accuracy} m"'
	. ' data-denied="[^"]+" data-unavailable="[^"]+" data-timeout="[^"]+" data-unsupported="[^"]+"~', $html);
Assert::contains('js-locate', $html);
Assert::match('~<noscript>.*JavaScript.*</noscript>~s', $html);
Assert::contains('<dialog id="collect-sheet"', $html);


// "Mám" lists the nearest collected stamps, "Vše" all of them (#51)
$stampsModel = new NearbyFakeStampsModel($closestStamps);
$html = renderNearby($stampsModel, ['user'], ['lat' => '50.1', 'lng' => '14.4', 'filter' => 'collected']);
Assert::same([[50.1, 14.4, 20, null, [5]], [50.1, 14.4, 20, null, null]], $stampsModel->closestCalls);
Assert::match('~<li class="is-active">\s*<a [^>]*>Mám&nbsp;·&nbsp;0</a>~', $html);

$stampsModel = new NearbyFakeStampsModel($closestStamps);
$html = renderNearby($stampsModel, ['user'], ['lat' => '50.1', 'lng' => '14.4', 'filter' => 'all']);
Assert::same([[50.1, 14.4, 20, null, null]], $stampsModel->closestCalls); // the list itself gives the counts
Assert::match('~<li class="is-active">\s*<a [^>]*>Vše&nbsp;·&nbsp;1</a>~', $html);


// An anonymous user gets all the nearest stamps, no switch nor collect buttons (#51)
$stampsModel = new NearbyFakeStampsModel($closestStamps);
$html = renderNearby($stampsModel, null, ['lat' => '50.1', 'lng' => '14.4', 'filter' => 'collected']);
Assert::same([[50.1, 14.4, 20, null, null]], $stampsModel->closestCalls);
Assert::notContains('collection-switch', $html);
Assert::notContains('js-collect', $html);
Assert::contains('2,1 km', $html);


// Before the location is known, no stamps are loaded; the status is shown (#51)
$stampsModel = new NearbyFakeStampsModel($closestStamps);
$html = renderNearby($stampsModel, ['user'], []);
Assert::same([], $stampsModel->closestCalls);
Assert::contains('id="nearby-status"', $html);
Assert::notContains('stamp-row', $html);


// An invalid filter is "Nemám", not carried into the links (#51)
$stampsModel = new NearbyFakeStampsModel($closestStamps);
$html = renderNearby($stampsModel, ['user'], ['lat' => '50.1', 'lng' => '14.4', 'filter' => 'foo']);
Assert::same([[50.1, 14.4, 20, [5], null], [50.1, 14.4, 20, null, null]], $stampsModel->closestCalls);
Assert::notContains('filter=foo', $html);
Assert::match('~<li class="is-active">\s*<a [^>]*>Nemám&nbsp;·&nbsp;1</a>~', $html);


// A location that is not a finite number is ignored, not an error (#51)
foreach ([['1e309', '14.4'], ['abc', '14.4'], ['50.1', '-INF']] as [$lat, $lng]) {
	$stampsModel = new NearbyFakeStampsModel($closestStamps);
	$html = renderNearby($stampsModel, ['user'], ['lat' => $lat, 'lng' => $lng]);
	Assert::same([], $stampsModel->closestCalls);
	Assert::notContains('stamp-row', $html);
}


// Adding a stamp by AJAX in "Nemám" lists the nearest stamps without it: fetched after the change of the collection (#51)
$translator = PresenterTestHelpers::createTranslator();
$presenter = new NearbyPresenter($translator);
PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['Sec-Fetch-Site' => 'same-origin', 'X-Requested-With' => 'XMLHttpRequest']);
$stampsModel = new NearbyFakeStampsModel($closestStamps);
$presenter->injectStampsModel($stampsModel);
$presenter->injectCollectionModel(new NearbyFakeCollectionModel([
	5 => new CollectedStampDto(5, 'Sněžka', 'type', 'region', new DateTime, '50.7', '15.7', 'img.jpg', new DateTime('2024-05-01'), 'Windy', null),
]));
$presenter->injectEditStampForm(new EditStampForm($translator));
$presenter->run(new Request(
	'Nearby',
	'POST',
	['action' => 'default', 'lat' => '50.1', 'lng' => '14.4', 'do' => 'collectForm-submit'],
	['id' => '19', 'date' => '', 'comment' => '', 'ok' => 'stamp.saveToCollection'],
));
Assert::same([[50.1, 14.4, 20, [5, 19], null], [50.1, 14.4, 20, null, null]], $stampsModel->closestCalls);
$snippets = (array) $presenter->getPayload()->snippets;
Assert::same(['snippet--stamps', 'snippet--flashes'], array_keys($snippets));
Assert::contains('form.filter.all&nbsp;·&nbsp;1</a>', $snippets['snippet--stamps']); // the counts follow the collection (#71)
Assert::contains('form.filter.mine&nbsp;·&nbsp;1</a>', $snippets['snippet--stamps']);
Assert::contains('form.filter.missing&nbsp;·&nbsp;0</a>', $snippets['snippet--stamps']);


/**
 * 25 stamps around, ordered by distance; the stamps 1–5 collected.
 * @return array{array<int, array<string, mixed>>, array<int, CollectedStampDto>}
 */
function nearbyCountsFixture(): array
{
	$closest = [];
	foreach (range(1, 25) as $id) {
		$closest[$id] = ['id' => $id, 'name' => "Stamp $id", 'lat' => '50.7', 'lng' => '15.4', 'image' => null, 'distance' => 100.0 * $id, 'prettyDistance' => "$id km"];
	}
	$collection = [];
	foreach (range(1, 5) as $id) {
		$collection[$id] = new CollectedStampDto($id, "Stamp $id", 'type', 'region', new DateTime, '50.7', '15.4', null, null, null, null);
	}
	return [$closest, $collection];
}


// The switch counts the 20 nearest stamps whatever the filter: Vše · 20, Mám · the collected ones of them, Nemám · the rest (#71)
[$closest, $collection] = nearbyCountsFixture();
foreach (['all', 'collected', 'missing'] as $filter) {
	$stampsModel = new NearbyFakeStampsModel($closest);
	$html = renderNearby($stampsModel, ['user'], ['lat' => '50.1', 'lng' => '14.4', 'filter' => $filter], $collection);
	Assert::count($filter === 'all' ? 1 : 2, $stampsModel->closestCalls); // at most one extra query
	Assert::match('~<a href="/nearby/\?filter=all&amp;lat=50.1&amp;lng=14.4"[^>]*>Vše&nbsp;·&nbsp;20</a>~', $html);
	Assert::match('~<a href="/nearby/\?filter=collected&amp;lat=50.1&amp;lng=14.4"[^>]*>Mám&nbsp;·&nbsp;5</a>~', $html);
	Assert::match('~<a href="/nearby/\?(filter=missing&amp;)?lat=50.1&amp;lng=14.4"[^>]*>Nemám&nbsp;·&nbsp;15</a>~', $html);
	Assert::match('%A%</nav>%A%<p class="collection-switch-note text-muted">Počty z 20 nejbližších známek</p>%A%', $html); // what the counts refer to
}

// An anonymous user has no switch nor counts; no extra query (#71)
$stampsModel = new NearbyFakeStampsModel($closest);
$html = renderNearby($stampsModel, null, ['lat' => '50.1', 'lng' => '14.4'], $collection);
Assert::count(1, $stampsModel->closestCalls);
Assert::notContains('&nbsp;·&nbsp;', $html);
Assert::notContains('collection-switch-note', $html);

// No position, no counts (#71)
$stampsModel = new NearbyFakeStampsModel($closest);
$html = renderNearby($stampsModel, ['user'], [], $collection);
Assert::same([], $stampsModel->closestCalls);
Assert::notContains('&nbsp;·&nbsp;', $html);


// Adding a stamp by AJAX in "Nemám" redraws the counts: Mám +1, Nemám −1, Vše unchanged (#71)
$translator = PresenterTestHelpers::createTranslator();
$presenter = new NearbyPresenter($translator);
PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['Sec-Fetch-Site' => 'same-origin', 'X-Requested-With' => 'XMLHttpRequest']);
$presenter->injectStampsModel(new NearbyFakeStampsModel($closest));
$presenter->injectCollectionModel(new NearbyFakeCollectionModel($collection));
$presenter->injectEditStampForm(new EditStampForm($translator));
$presenter->run(new Request(
	'Nearby',
	'POST',
	['action' => 'default', 'lat' => '50.1', 'lng' => '14.4', 'do' => 'collectForm-submit'],
	['id' => '6', 'date' => '', 'comment' => '', 'ok' => 'stamp.saveToCollection'],
));
$snippet = ((array) $presenter->getPayload()->snippets)['snippet--stamps'];
Assert::contains('form.filter.all&nbsp;·&nbsp;20</a>', $snippet);
Assert::contains('form.filter.mine&nbsp;·&nbsp;6</a>', $snippet);
Assert::contains('form.filter.missing&nbsp;·&nbsp;14</a>', $snippet);
