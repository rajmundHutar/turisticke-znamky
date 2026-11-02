<?php

/**
 * Test: HomepagePresenter persistent params and edit form redirect (#29)
 */

declare(strict_types=1);

use App\Forms\EditStampForm;
use App\Forms\FormFactory;
use App\Models\CollectionModel;
use App\Presenters\HomepagePresenter;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
use Nette\Application\UI\ComponentReflection;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/PresenterTestHelpers.php';


/** CollectionModel double without a database; records update calls. */
final class HomepageFakeCollectionModel extends CollectionModel
{
	/** @var list<array{int, int, array<string, mixed>}> */
	public array $updates = [];


	public function __construct()
	{
	}


	public function fetchByUser(?int $userId): array
	{
		return [];
	}


	public function update(int $stampId, int $userId, array $data): void
	{
		$this->updates[] = [$stampId, $userId, $data];
	}
}


// Persistent params are declared via #[Persistent]; the @persistent annotation is deprecated since nette/application 3.3
Assert::noError(function () {
	Assert::same(
		['search', 'sort', 'label', 'onlyCollected'],
		array_keys((new ComponentReflection(HomepagePresenter::class))->getPersistentParams()),
	);
});


// Submitting the edit form stores the values and redirects back (no stale collection rendered), keeping filter, sort and page (#29)
$translator = PresenterTestHelpers::createTranslator();
$formFactory = new FormFactory($translator);
$formFactory->register(new EditStampForm($translator));
$collectionModel = new HomepageFakeCollectionModel;

$presenter = new HomepagePresenter($translator);
PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['Sec-Fetch-Site' => 'same-origin']); // signals are CSRF protected
$presenter->injectCollectionModel($collectionModel);
$presenter->injectFormFactory($formFactory);
// no stamps model nor templates: the request must end by the redirect before rendering

$response = $presenter->run(new Request(
	'Homepage',
	'POST',
	[
		'action' => 'default',
		'search' => 'Praha',
		'sort' => '-date',
		'onlyCollected' => '1',
		'paginator-page' => '3',
		'do' => 'editStampForm-5-submit',
	],
	['id' => '5', 'date' => '2. 6. 2024', 'comment' => 'Sunny', 'ok' => 'general.submit'],
));
Assert::count(1, $collectionModel->updates);
[$stampId, $userId, $data] = $collectionModel->updates[0];
Assert::same([5, 1, 'Sunny'], [$stampId, $userId, $data['comment']]);
Assert::type(DateTimeInterface::class, $data['date']);
Assert::same('2024-06-02', $data['date']->format('Y-m-d'));
Assert::type(RedirectResponse::class, $response);
assert($response instanceof RedirectResponse);
$url = new Nette\Http\Url($response->getUrl());
Assert::same('Praha', $url->getQueryParameter('search'));
Assert::same('-date', $url->getQueryParameter('sort'));
Assert::same('1', $url->getQueryParameter('onlyCollected'));
Assert::same('3', $url->getQueryParameter('paginator-page'));
Assert::same('stamp-5', $url->getFragment());
Assert::null($url->getQueryParameter('do'));
