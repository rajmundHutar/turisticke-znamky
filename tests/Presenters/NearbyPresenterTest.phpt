<?php

/**
 * Test: NearbyPresenter edit forms and toggleCollect signal (#2)
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
use Nette\Utils\DateTime;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/PresenterTestHelpers.php';


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
}


/** CollectionModel double without a database; records toggleCollect calls. */
final class NearbyFakeCollectionModel extends CollectionModel
{
	/** @var list<array{int, int}> */
	public array $toggled = [];

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


	public function toggleCollect(int $id, int $userId): bool
	{
		$this->toggled[] = [$id, $userId];
		return true;
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


// Edit form of a collected stamp gets the DTO's date and comment as defaults
$date = new DateTime('2024-05-01');
$collectionModel = new NearbyFakeCollectionModel([
	5 => new CollectedStampDto(5, 'Sněžka', 'type', 'region', new DateTime, '50.7', '15.7', 'img.jpg', $date, 'Windy', null),
]);
$presenter = createNearbyPresenter($collectionModel);
$presenter->run(new Request('Nearby', 'GET', ['action' => 'default', 'lat' => '50.1', 'lng' => '14.4']));
$form = $presenter['editStampForm-5'];
Assert::same('1. 5. 2024', $form['date']->getValue()); // rendered as a Czech date (#11)
Assert::same('Windy', $form['comment']->getValue());


// toggleCollect signal toggles the stamp and redirects back keeping lat/lng
$collectionModel = new NearbyFakeCollectionModel;
$presenter = createNearbyPresenter($collectionModel);
$response = $presenter->run(new Request('Nearby', 'GET', [
	'action' => 'default',
	'lat' => '50.1',
	'lng' => '14.4',
	'do' => 'toggleCollect',
	'id' => '5',
]));
Assert::same([[5, 1]], $collectionModel->toggled);
Assert::type(RedirectResponse::class, $response);
assert($response instanceof RedirectResponse);
$url = new Nette\Http\Url($response->getUrl());
Assert::same('50.1', $url->getQueryParameter('lat'));
Assert::same('14.4', $url->getQueryParameter('lng'));
Assert::same('stamp-5', $url->getFragment());
Assert::null($url->getQueryParameter('do'));


// Submitting the edit form stores the values and redirects back (no stale collection rendered), keeping lat/lng
$collectionModel = new NearbyFakeCollectionModel;
$presenter = createNearbyPresenter($collectionModel);
$response = $presenter->run(new Request(
	'Nearby',
	'POST',
	['action' => 'default', 'lat' => '50.1', 'lng' => '14.4', 'do' => 'editStampForm-5-submit'],
	['id' => '5', 'date' => '2. 6. 2024', 'comment' => 'Sunny', 'ok' => 'general.submit'],
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
