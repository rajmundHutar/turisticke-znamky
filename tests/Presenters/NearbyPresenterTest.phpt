<?php

/**
 * Test: NearbyPresenter collect form keeps the location (#2, #48)
 */

declare(strict_types=1);

use App\Dto\CollectedStampDto;
use App\Forms\EditStampForm;
use App\Forms\FormFactory;
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
	public function __construct()
	{
	}


	public function fetchClosest(float $lat, float $lng, ?int $num = 10, ?array $exclude = null): array
	{
		return [];
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
	$formFactory = new FormFactory($translator);
	$formFactory->register(new EditStampForm($translator));

	$presenter = new TestNearbyPresenter($translator);
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['Sec-Fetch-Site' => 'same-origin']); // signals are CSRF protected
	$presenter->injectStampsModel(new NearbyFakeStampsModel);
	$presenter->injectCollectionModel($collectionModel);
	$presenter->injectFormFactory($formFactory);
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
$formFactory = new FormFactory($translator);
$formFactory->register(new EditStampForm($translator));
$presenter = new NearbyPresenter($translator);
PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['X-Requested-With' => 'XMLHttpRequest']);
$presenter->injectStampsModel(new NearbyFakeStampsModel);
$presenter->injectCollectionModel(new NearbyFakeCollectionModel);
$presenter->injectFormFactory($formFactory);
$presenter->run(new Request('Nearby', 'GET', ['action' => 'default', 'lat' => '50.1', 'lng' => '14.4']));
$snippets = (array) $presenter->getPayload()->snippets;
Assert::same(['snippet--stamps'], array_keys($snippets));
Assert::contains('<form action="/nearby/?lat=50.1&amp;lng=14.4" method="post" id="frm-collectForm" class="ajax" data-naja-history="off">', $snippets['snippet--stamps']);
