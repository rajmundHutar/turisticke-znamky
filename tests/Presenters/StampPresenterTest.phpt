<?php

/**
 * Test: StampPresenter edit forms store the values and redirect back (#3, #29)
 */

declare(strict_types=1);

use App\Dto\CollectedStampDto;
use App\Forms\EditStampForm;
use App\Forms\FormFactory;
use App\Models\CollectionModel;
use App\Presenters\StampPresenter;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
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
	['id' => '7', 'date' => '2024-05-01', 'comment' => 'Windy', 'ok' => 'general.submit'],
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
	['id' => '1', 'date' => '2024-05-02', 'comment' => 'Foggy', 'ok' => 'general.submit'],
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
Assert::same('2024-05-01', $form['date']->getValue());
Assert::same('Windy', $form['comment']->getValue());
