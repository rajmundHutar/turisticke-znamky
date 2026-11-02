<?php

/**
 * Test: StampPresenter edit form of a nearby stamp can be submitted (#3)
 */

declare(strict_types=1);

use App\Forms\EditStampForm;
use App\Forms\FormFactory;
use App\Models\CollectionModel;
use App\Presenters\StampPresenter;
use Nette\Application\Request;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/PresenterTestHelpers.php';


/** CollectionModel double without a database; records update calls. */
final class StampFakeCollectionModel extends CollectionModel
{
	/** @var list<array{int, int, array<string, mixed>}> */
	public array $updates = [];


	public function __construct()
	{
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


$translator = PresenterTestHelpers::createTranslator();
$formFactory = new FormFactory($translator);
$formFactory->register(new EditStampForm($translator));
$collectionModel = new StampFakeCollectionModel;

$presenter = new TestStampPresenter($translator);
PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['Sec-Fetch-Site' => 'same-origin']); // signals are CSRF protected
$presenter->injectCollectionModel($collectionModel);
$presenter->injectFormFactory($formFactory);

// Submitting the edit form of a nearby stamp card stores the values (no TypeError in the success callback)
$presenter->run(new Request(
	'Stamp',
	'POST',
	['action' => 'default', 'id' => '1', 'do' => 'editStampForm-7-submit'],
	['id' => '7', 'date' => '2024-05-01', 'comment' => 'Windy', 'ok' => 'general.submit'],
));
Assert::count(1, $collectionModel->updates);
[$stampId, $userId, $data] = $collectionModel->updates[0];
Assert::same([7, 1, 'Windy'], [$stampId, $userId, $data['comment']]);
Assert::type(DateTimeInterface::class, $data['date']); // converted from Y-m-d (#11)
Assert::same('2024-05-01', $data['date']->format('Y-m-d'));
