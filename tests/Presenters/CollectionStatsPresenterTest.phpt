<?php

/**
 * Test: CollectionStatsPresenter requires a logged-in user; the stats page renders without stamps (#9)
 */

declare(strict_types=1);

use App\Dto\CollectedStampDto;
use App\Models\CollectionModel;
use App\Models\StampsModel;
use App\Presenters\CollectionStatsPresenter;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
use Nette\Application\Responses\TextResponse;
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


// Anonymous user is redirected to the login page with a backlink to the stored request and no stats are fetched (#22)
$collectionModel = new FakeCollectionModel;
$presenter = createStatsPresenter(null, $collectionModel);
$response = $presenter->run(new Request('CollectionStats', 'GET', ['action' => 'default']));
Assert::type(RedirectResponse::class, $response);
Assert::same('Login', $presenter->getLastCreatedRequest()?->getPresenterName());
Assert::same('default', $presenter->getLastCreatedRequest()?->getParameter('action'));
Assert::match('%[0-9a-z]{5}%', (string) $presenter->getLastCreatedRequest()?->getParameter('backlink'));
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


/** CollectionModel double returning the given collection. */
final class StatsCollectionModelWith extends CollectionModel
{
	/** @param  list<CollectedStampDto>  $collection */
	public function __construct(
		private array $collection,
	) {
	}


	public function fetchByUser(?int $userId): array
	{
		return $this->collection;
	}
}


/** StampsModel double with the given total count. */
final class StatsFakeStampsModel extends StampsModel
{
	public function __construct(
		private int $total,
	) {
	}


	public function count(array $filter = []): int
	{
		return $this->total;
	}
}


/** @param  list<CollectedStampDto>  $collection */
function renderStats(array $collection, int $totalCount): string
{
	$presenter = new CollectionStatsPresenter(PresenterTestHelpers::createTranslator());
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']));
	$presenter->injectCollectionModel(new StatsCollectionModelWith($collection));
	$presenter->injectStampsModel(new StatsFakeStampsModel($totalCount));
	$response = $presenter->run(new Request('CollectionStats', 'GET', ['action' => 'default']));
	Assert::type(TextResponse::class, $response);
	assert($response instanceof TextResponse);
	return (string) $response->getSource();
}


function statsStamp(int $id, string $name): CollectedStampDto
{
	return new CollectedStampDto($id, $name, null, null, null, '0', '0', null, null, null, null);
}


// No stamps at all and an empty collection: 0 %, no division by zero; lowest / highest show a dash without a link
$html = renderStats([], 0);
Assert::contains('0 / 0', $html);
Assert::contains("0\u{a0}%", $html);
Assert::same(2, substr_count($html, '<p class="title">–</p>'));
Assert::notContains('href="/stamp/', $html);
Assert::contains('stats.lowestNumber', $html);
Assert::contains('stats.highestNumber', $html);


// A collection: the lowest and highest numbers with names link to the stamp detail; duplicates do not break the run
$html = renderStats([statsStamp(42, 'Hrad Karlštejn'), statsStamp(3, 'Sněžka'), statsStamp(4, 'Říp'), statsStamp(4, 'Říp'), statsStamp(5, 'Ještěd')], 100);
Assert::contains('5 / 100', $html);
Assert::contains("5\u{a0}%", $html);
Assert::match('%A%<a href="/stamp/3">3</a>%A%<a href="/stamp/3">Sněžka</a>%A%stats.lowestNumber%A%<a href="/stamp/42">42</a>%A%<a href="/stamp/42">Hrad Karlštejn</a>%A%stats.highestNumber%A%', $html);
Assert::contains('title="3, 4, 5">3</p>', $html);


// Czech number format (#72): a decimal comma and a non-breaking space before %, thousands separated by a non-breaking space
$html = renderStats(array_map(fn(int $id) => statsStamp($id, "Známka $id"), range(1, 119)), 3103);
Assert::contains("119 / 3\u{a0}103", $html);
Assert::contains("3,83\u{a0}%", $html);
Assert::notContains('3.83', $html);
