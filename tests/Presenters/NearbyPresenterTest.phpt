<?php

/**
 * Test: NearbyPresenter collect form keeps the location (#2, #48); the list of stamp rows with distances,
 * the location status and the switch Vše / Mám / Nemám (#51)
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
		return $this->closest;
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
Assert::contains('<form action="/nearby/?lat=50.1&amp;lng=14.4" method="post" id="frm-collectForm" class="ajax" data-naja-history="off" data-naja-force-redirect>', $snippets['snippet--stamps']);


$closestStamps = [
	19 => ['id' => 19, 'name' => 'Osada Rezek', 'lat' => '50.7', 'lng' => '15.4', 'image' => null, 'distance' => 2120.0, 'prettyDistance' => '2,1 km'],
];


/**
 * Renders the Nearby page (a full page load) with the stamp 5 collected; returns the HTML.
 * @param  list<string>|null  $roles
 * @param  array<string, string>  $params
 */
function renderNearby(NearbyFakeStampsModel $stampsModel, ?array $roles, array $params): string
{
	$translator = new App\Helpers\Translator;
	$presenter = new NearbyPresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser($roles));
	$presenter->injectStampsModel($stampsModel);
	$presenter->injectCollectionModel(new NearbyFakeCollectionModel([
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
Assert::same([[50.1, 14.4, 20, [5], null]], $stampsModel->closestCalls);
Assert::match('~<article class="stamp-row" id="stamp-19">.*class="stamp-row-name">Osada Rezek</span>\s*<span class="stamp-row-distance">.*2,1 km</span>~s', $html);
Assert::match('~<li class="is-active">\s*<a href="/nearby/\?lat=50.1&amp;lng=14.4"[^>]*>Nemám</a>~', $html);
Assert::match('~<a href="/nearby/\?filter=all&amp;lat=50.1&amp;lng=14.4"[^>]*>Vše</a>~', $html);
Assert::match('~<a href="/nearby/\?filter=collected&amp;lat=50.1&amp;lng=14.4"[^>]*>Mám</a>~', $html);
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
Assert::same([[50.1, 14.4, 20, null, [5]]], $stampsModel->closestCalls);
Assert::match('~<li class="is-active">\s*<a [^>]*>Mám</a>~', $html);

$stampsModel = new NearbyFakeStampsModel($closestStamps);
$html = renderNearby($stampsModel, ['user'], ['lat' => '50.1', 'lng' => '14.4', 'filter' => 'all']);
Assert::same([[50.1, 14.4, 20, null, null]], $stampsModel->closestCalls);
Assert::match('~<li class="is-active">\s*<a [^>]*>Vše</a>~', $html);


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
