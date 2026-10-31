<?php

/**
 * Test: CollectionStatsPresenter requires a logged-in user
 */

declare(strict_types=1);

use App\Models\CollectionModel;
use App\Presenters\CollectionStatsPresenter;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/PresenterTestHelpers.php';


/** CollectionModel double without a database. */
final class FakeCollectionModel extends CollectionModel
{
	/** @var list<int|null> */
	public array $fetchedUsers = [];


	public function __construct()
	{
	}


	public function fetchByUser(?int $userId): ?array
	{
		$this->fetchedUsers[] = $userId;
		throw new LogicException('Stats rendered.');
	}
}


/** @param  list<string>|null  $roles */
function createStatsPresenter(?array $roles, FakeCollectionModel $collectionModel): CollectionStatsPresenter
{
	$presenter = new CollectionStatsPresenter(PresenterTestHelpers::createTranslator());
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser($roles));
	$presenter->injectCollectionModel($collectionModel);
	return $presenter;
}


// Anonymous user is redirected to the login page and no stats are fetched
$collectionModel = new FakeCollectionModel;
$presenter = createStatsPresenter(null, $collectionModel);
$response = $presenter->run(new Request('CollectionStats', 'GET', ['action' => 'default']));
Assert::type(RedirectResponse::class, $response);
Assert::same('Login', $presenter->getLastCreatedRequest()?->getPresenterName());
Assert::same('default', $presenter->getLastCreatedRequest()?->getParameter('action'));
Assert::same([], $collectionModel->fetchedUsers);


// Logged-in user gets stats for their own id
$collectionModel = new FakeCollectionModel;
$presenter = createStatsPresenter(['user'], $collectionModel);
Assert::exception(
	fn() => $presenter->run(new Request('CollectionStats', 'GET', ['action' => 'default'])),
	LogicException::class,
	'Stats rendered.',
);
Assert::same([1], $collectionModel->fetchedUsers);
