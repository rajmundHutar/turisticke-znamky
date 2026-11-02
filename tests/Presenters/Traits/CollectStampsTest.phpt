<?php

/**
 * Test: CollectStamps trait shares the collect toggle and the card edit form between presenters (#36)
 */

declare(strict_types=1);

use App\Models\CollectionModel;
use App\Models\StampsModel;
use App\Presenters\HomepagePresenter;
use App\Presenters\NearbyPresenter;
use App\Presenters\StampPresenter;
use App\Presenters\Traits\CollectStamps;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../PresenterTestHelpers.php';


/** CollectionModel double without a database; records toggleCollect and update calls. */
final class CollectFakeCollectionModel extends CollectionModel
{
	/** @var list<array{int, int}> */
	public array $toggled = [];

	/** @var list<array{int, int, array<string, mixed>}> */
	public array $updates = [];


	public function __construct()
	{
	}


	public function fetchByUser(?int $userId): array
	{
		return [];
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


/** StampsModel double without a database; the stamp detail loads its stamp in the action. */
final class CollectFakeStampsModel extends StampsModel
{
	public function __construct()
	{
	}


	public function fetch(int $id): ActiveRow
	{
		$selection = (new ReflectionClass(Selection::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Selection::class, 'cache'))->setValue($selection, null);
		return new ActiveRow(['id' => $id], $selection);
	}
}


/** Stops the request before rendering, so no templates are needed. */
final class CollectTestStampPresenter extends StampPresenter
{
	protected function beforeRender(): void
	{
		$this->terminate();
	}
}


/**
 * @param  list<string>|null  $roles
 * @param  array<string, string>  $params
 */
function runCollect(Presenter $presenter, string $name, ?array $roles, CollectFakeCollectionModel $collectionModel, array $params): Nette\Application\Response
{
	assert($presenter instanceof HomepagePresenter || $presenter instanceof NearbyPresenter || $presenter instanceof StampPresenter);
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser($roles), ['Sec-Fetch-Site' => 'same-origin']); // signals are CSRF protected
	$presenter->injectCollectionModel($collectionModel);
	$presenter->injectStampsModel(new CollectFakeStampsModel);
	return $presenter->run(new Request($name, 'GET', $params));
}


function redirectUrl(Nette\Application\Response $response): Nette\Http\Url
{
	Assert::type(RedirectResponse::class, $response);
	assert($response instanceof RedirectResponse);
	return new Nette\Http\Url($response->getUrl());
}


// The toggle signal and the card edit form are declared once, in the shared trait
foreach ([HomepagePresenter::class, NearbyPresenter::class, StampPresenter::class] as $class) {
	Assert::contains(CollectStamps::class, class_uses($class), $class);
	foreach (['handleToggleCollect', 'createComponentEditStampForm'] as $method) {
		Assert::same(
			(new ReflectionClass(CollectStamps::class))->getFileName(),
			(new ReflectionMethod($class, $method))->getFileName(),
			"$class::$method",
		);
	}
}


$translator = PresenterTestHelpers::createTranslator();


// Homepage: toggling a stamp redirects back to its card, keeping filter, sort and page
$collectionModel = new CollectFakeCollectionModel;
$url = redirectUrl(runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, [
	'action' => 'default',
	'search' => 'Praha',
	'sort' => '-id',
	'onlyCollected' => '1',
	'paginator-page' => '3',
	'do' => 'toggleCollect',
	'id' => '5',
]));
Assert::same([[5, 1]], $collectionModel->toggled);
Assert::same('Praha', $url->getQueryParameter('search'));
Assert::same('-id', $url->getQueryParameter('sort'));
Assert::same('1', $url->getQueryParameter('onlyCollected'));
Assert::same('3', $url->getQueryParameter('paginator-page'));
Assert::same('stamp-5', $url->getFragment());
Assert::null($url->getQueryParameter('do'));


// Stamp detail: toggling the stamp redirects back to the detail, without an anchor
$collectionModel = new CollectFakeCollectionModel;
$url = redirectUrl(runCollect(new CollectTestStampPresenter($translator), 'Stamp', ['user'], $collectionModel, [
	'action' => 'default',
	'id' => '1',
	'do' => 'toggleCollect',
]));
Assert::same([[1, 1]], $collectionModel->toggled);
Assert::same('/stamp/1', $url->getPath());
Assert::same('', $url->getFragment());
Assert::null($url->getQueryParameter('do'));


// An anonymous user cannot toggle; the page goes on without a redirect
$collectionModel = new CollectFakeCollectionModel;
$response = runCollect(new CollectTestStampPresenter($translator), 'Stamp', null, $collectionModel, [
	'action' => 'default',
	'id' => '1',
	'do' => 'toggleCollect',
]);
Assert::notSame(RedirectResponse::class, $response::class);
Assert::same([], $collectionModel->toggled);
