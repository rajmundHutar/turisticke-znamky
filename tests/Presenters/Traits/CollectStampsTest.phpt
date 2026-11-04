<?php

/**
 * Test: CollectStamps trait shares one collect form for adding, editing and removing a stamp between presenters (#36, #48)
 */

declare(strict_types=1);

use App\Dto\CollectedStampDto;
use App\Forms\EditStampForm;
use App\Models\CollectionModel;
use App\Models\StampsModel;
use App\Presenters\HomepagePresenter;
use App\Presenters\NearbyPresenter;
use App\Presenters\StampPresenter;
use App\Presenters\Traits\CollectStamps;
use Nette\Application\BadRequestException;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Forms\Controls\SubmitButton;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\DateTime;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../PresenterTestHelpers.php';
require __DIR__ . '/../../../app/Helpers/Flash.php'; // global class, loaded by RobotLoader in the app


/** CollectionModel double without a database; records every write. */
final class CollectFakeCollectionModel extends CollectionModel
{
	/** @var list<list<mixed>> operation, stamp id, user id and the written values */
	public array $writes = [];

	/** @var list<int> stamps stored after the action loaded the collection, e.g. by a double submit */
	public array $storedMeanwhile = [];


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
		return isset($this->collection[$stampId]) || in_array($stampId, $this->storedMeanwhile, true);
	}


	public function add(int $stampId, int $userId, ?DateTimeInterface $date, ?string $comment): void
	{
		$this->writes[] = ['add', $stampId, $userId, $date, $comment];
		$this->collection[$stampId] = collected($stampId);
	}


	public function update(int $stampId, int $userId, array $data): void
	{
		$this->writes[] = ['update', $stampId, $userId, $data];
	}


	public function remove(int $stampId, int $userId): void
	{
		$this->writes[] = ['remove', $stampId, $userId];
		unset($this->collection[$stampId]);
	}

}


/** StampsModel double without a database; knows the stamps 1 to 100. */
final class CollectFakeStampsModel extends StampsModel
{
	public function __construct()
	{
	}


	public function fetch(int $id): ?ActiveRow
	{
		if ($id < 1 || $id > 100) {
			return null;
		}
		$selection = (new ReflectionClass(Selection::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Selection::class, 'cache'))->setValue($selection, null);
		return new ActiveRow(['id' => $id], $selection);
	}


	/** The homepage lists the stamps 5, 6 and 7. */
	public function search(array $filter, $limit = null, $offset = null): array
	{
		return array_map(fn(int $id) => Nette\Utils\ArrayHash::from(['id' => $id, 'name' => "Stamp $id", 'image' => null]), [5, 6, 7]);
	}


	/** Of the 3 listed stamps those with the given ids, or the others. */
	public function count(array $filter = []): int
	{
		return isset($filter['ids']) ? count($filter['ids']) : 3 - count($filter['excludeIds'] ?? []);
	}


	public function fetchClosest(float $lat, float $lng, ?int $num = 10, ?array $exclude = null): array
	{
		return [];
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


/** Stops the request before rendering, so no templates are needed. */
final class CollectTestNearbyPresenter extends NearbyPresenter
{
	protected function beforeRender(): void
	{
		$this->terminate();
	}
}


/**
 * @param  list<string>|null  $roles
 * @param  array<string, string>  $params
 * @param  array<string, string>|null  $post  null = GET request
 * @param  array<string, string>  $headers
 */
function runCollect(
	Presenter $presenter,
	string $name,
	?array $roles,
	CollectFakeCollectionModel $collectionModel,
	array $params,
	?array $post = null,
	array $headers = ['Sec-Fetch-Site' => 'same-origin'], // signals and forms are CSRF protected
): Nette\Application\Response {
	assert($presenter instanceof HomepagePresenter || $presenter instanceof NearbyPresenter || $presenter instanceof StampPresenter);
	$translator = PresenterTestHelpers::createTranslator();
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser($roles), $headers);
	$presenter->injectCollectionModel($collectionModel);
	$presenter->injectStampsModel(new CollectFakeStampsModel);
	$presenter->injectEditStampForm(new EditStampForm($translator));
	if ($presenter instanceof HomepagePresenter) {
		$presenter->injectFilterForm(new App\Forms\FilterForm($translator));
	}
	return $presenter->run(new Request($name, $post === null ? 'GET' : 'POST', $params, $post ?? []));
}


function redirectUrl(Nette\Application\Response $response): Nette\Http\Url
{
	Assert::type(RedirectResponse::class, $response);
	assert($response instanceof RedirectResponse);
	return new Nette\Http\Url($response->getUrl());
}


function collected(int $id, ?string $date = '2024-05-01', ?string $comment = 'Windy'): CollectedStampDto
{
	return new CollectedStampDto($id, "Stamp $id", null, null, null, '50.0', '14.0', null, $date === null ? null : new DateTime($date), $comment, null);
}


$translator = PresenterTestHelpers::createTranslator();
$homepageParams = [
	'action' => 'default',
	'search' => 'Praha',
	'sort' => '-id',
	'onlyCollected' => '1',
	'paginator-page' => '3',
	'do' => 'collectForm-submit',
];


// The collect form is declared once, in the shared trait; the per-card forms and the GET toggle are gone (#48)
foreach ([HomepagePresenter::class, NearbyPresenter::class, StampPresenter::class] as $class) {
	Assert::contains(CollectStamps::class, class_uses($class), $class);
	Assert::same(
		(new ReflectionClass(CollectStamps::class))->getFileName(),
		(new ReflectionMethod($class, 'createComponentCollectForm'))->getFileName(),
		"$class::createComponentCollectForm",
	);
	foreach (['handleToggleCollect', 'createComponentEditStampForm', 'createComponentEditSingleStampForm'] as $method) {
		Assert::false(method_exists($class, $method), "$class::$method");
	}
}


// The form is a single component prefilled with today's date, with a hidden stamp id, a note and save and remove buttons (#48)
$presenter = new HomepagePresenter($translator);
runCollect($presenter, 'Homepage', ['user'], new CollectFakeCollectionModel, ['action' => 'default']);
$form = $presenter['collectForm'];
[$date, $ok, $remove] = [$form['date'], $form['ok'], $form['remove']];
assert($date instanceof TextInput && $ok instanceof SubmitButton && $remove instanceof SubmitButton);
Assert::same((new DateTime('today'))->format('j. n. Y'), $date->getValue());
Assert::same(['date', 'comment', 'id', 'ok', 'remove'], array_keys(iterator_to_array($form->getComponents())));
Assert::same('stamp.saveToCollection', $ok->getCaption());
Assert::same('stamp.uncollect', $remove->getCaption());


// Add: saving a stamp not in the collection inserts it with the date and the note, then redirects back to its row
// keeping filter, sort and page (#48)
$collectionModel = new CollectFakeCollectionModel;
$url = redirectUrl(runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, $homepageParams, [
	'id' => '5',
	'date' => '2. 6. 2024',
	'comment' => 'Sunny',
	'ok' => 'stamp.saveToCollection',
]));
Assert::count(1, $collectionModel->writes);
[$op, $stampId, $userId, $date, $comment] = $collectionModel->writes[0];
Assert::same(['add', 5, 1, 'Sunny'], [$op, $stampId, $userId, $comment]);
Assert::type(DateTimeInterface::class, $date);
Assert::same('2024-06-02', $date->format('Y-m-d'));
Assert::same('Praha', $url->getQueryParameter('search'));
Assert::same('-id', $url->getQueryParameter('sort'));
Assert::same('1', $url->getQueryParameter('onlyCollected'));
Assert::same('3', $url->getQueryParameter('paginator-page'));
Assert::same('stamp-5', $url->getFragment());
Assert::null($url->getQueryParameter('do'));


// Add of a stamp stored meanwhile (a double submit) updates it instead of inserting a duplicate row (#48)
$collectionModel = new CollectFakeCollectionModel;
$collectionModel->storedMeanwhile = [5];
$presenter = new HomepagePresenter($translator);
redirectUrl(runCollect($presenter, 'Homepage', ['user'], $collectionModel, $homepageParams, [
	'id' => '5',
	'date' => '2. 6. 2024',
	'comment' => 'Sunny',
	'ok' => 'stamp.saveToCollection',
]));
Assert::count(1, $collectionModel->writes);
Assert::same(['update', 5, 1], array_slice($collectionModel->writes[0], 0, 3));
Assert::same('Sunny', $collectionModel->writes[0][3]['comment']);
Assert::same('stamp.flash.saved', $presenter->getTemplate()->flashes[0]->message ?? null);


// Add without a date and a note stores nulls
$collectionModel = new CollectFakeCollectionModel;
runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, $homepageParams, [
	'id' => '5',
	'date' => '',
	'comment' => '',
	'ok' => 'stamp.saveToCollection',
]);
Assert::same([['add', 5, 1, null, null]], $collectionModel->writes);


// Add of an unknown stamp is a 404, nothing is stored
$collectionModel = new CollectFakeCollectionModel;
$e = Assert::exception(
	fn() => runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, $homepageParams, [
		'id' => '999',
		'date' => '',
		'comment' => '',
		'ok' => 'stamp.saveToCollection',
	]),
	BadRequestException::class,
);
Assert::same(404, $e->getCode());
Assert::same([], $collectionModel->writes);


// Edit: saving a stamp already in the collection updates its date and note (#48)
$collectionModel = new CollectFakeCollectionModel([5 => collected(5)]);
redirectUrl(runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, $homepageParams, [
	'id' => '5',
	'date' => '3. 6. 2024',
	'comment' => 'Foggy',
	'ok' => 'stamp.saveToCollection',
]));
Assert::count(1, $collectionModel->writes);
[$op, $stampId, $userId, $data] = $collectionModel->writes[0];
Assert::same(['update', 5, 1], [$op, $stampId, $userId]);
Assert::same(['date', 'comment'], array_keys($data));
Assert::same('2024-06-03', $data['date']->format('Y-m-d'));
Assert::same('Foggy', $data['comment']);


// Edit with an invalid date writes nothing; the error stays at the form and no redirect happens (#48)
$collectionModel = new CollectFakeCollectionModel([5 => collected(5)]);
$presenter = new HomepagePresenter($translator);
$response = runCollect($presenter, 'Homepage', ['user'], $collectionModel, $homepageParams, [
	'id' => '5',
	'date' => '31. 2. 2024',
	'comment' => 'Foggy',
	'ok' => 'stamp.saveToCollection',
]);
Assert::notSame(RedirectResponse::class, $response::class);
Assert::same([], $collectionModel->writes);
Assert::same(['form.editStamp.invalidDate'], $presenter['collectForm']->getErrors());


// Remove: the remove button of the (POST) form removes the stamp, even with an invalid date in the form (#48)
$collectionModel = new CollectFakeCollectionModel([5 => collected(5)]);
$url = redirectUrl(runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, $homepageParams, [
	'id' => '5',
	'date' => 'nonsense',
	'comment' => '',
	'remove' => 'stamp.uncollect',
]));
Assert::same([['remove', 5, 1]], $collectionModel->writes);
Assert::same('stamp-5', $url->getFragment());


// The form is POST only: a GET with the same parameters changes nothing (#48)
$collectionModel = new CollectFakeCollectionModel([5 => collected(5)]);
$response = runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, $homepageParams + [
	'id' => '5',
	'remove' => 'stamp.uncollect',
]);
Assert::notSame(RedirectResponse::class, $response::class);
Assert::same([], $collectionModel->writes);


// A cross-site POST changes nothing (CSRF)
foreach ([['remove' => 'stamp.uncollect'], ['ok' => 'stamp.saveToCollection']] as $button) {
	$collectionModel = new CollectFakeCollectionModel([5 => collected(5)]);
	$url = redirectUrl(runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, $homepageParams, [
		'id' => '5',
		'date' => '',
		'comment' => '',
	] + $button, ['Sec-Fetch-Site' => 'cross-site']));
	Assert::same([], $collectionModel->writes);
	Assert::null($url->getQueryParameter('do'));
}


// An anonymous user writes nothing and is sent to log in (#48)
foreach ([['remove' => 'stamp.uncollect'], ['ok' => 'stamp.saveToCollection']] as $button) {
	$collectionModel = new CollectFakeCollectionModel([5 => collected(5)]);
	$url = redirectUrl(runCollect(new CollectTestStampPresenter($translator), 'Stamp', null, $collectionModel, [
		'action' => 'default',
		'id' => '1',
		'do' => 'collectForm-submit',
	], ['id' => '5', 'date' => '', 'comment' => ''] + $button));
	Assert::same([], $collectionModel->writes);
	Assert::same('/login/', $url->getPath());
}


// An expired session at an AJAX submit sends the browser to log in: the payload carries the login redirect,
// which Naja follows by a full page load thanks to data-naja-force-redirect on the form (#48)
$collectionModel = new CollectFakeCollectionModel([5 => collected(5)]);
$presenter = new CollectTestStampPresenter($translator);
$response = runCollect($presenter, 'Stamp', null, $collectionModel, [
	'action' => 'default',
	'id' => '1',
	'do' => 'collectForm-submit',
], ['id' => '5', 'date' => '', 'comment' => '', 'ok' => 'stamp.saveToCollection'], ['Sec-Fetch-Site' => 'same-origin', 'X-Requested-With' => 'XMLHttpRequest']);
Assert::type(Nette\Application\Responses\JsonResponse::class, $response);
Assert::same([], $collectionModel->writes);
Assert::same('/login/', (new Nette\Http\Url($presenter->getPayload()->redirect))->getPath());


// The old GET toggle no longer exists: no handler, nothing changes (#48)
$collectionModel = new CollectFakeCollectionModel;
Assert::exception(
	fn() => runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, [
		'action' => 'default',
		'do' => 'toggleCollect',
		'id' => '5',
	]),
	BadRequestException::class,
);
Assert::same([], $collectionModel->writes);


// Stamp detail: saving the detail stamp redirects back to the detail, a nearby card to its anchor (#48)
$collectionModel = new CollectFakeCollectionModel;
$url = redirectUrl(runCollect(new CollectTestStampPresenter($translator), 'Stamp', ['user'], $collectionModel, [
	'action' => 'default',
	'id' => '1',
	'do' => 'collectForm-submit',
], ['id' => '1', 'date' => '2. 6. 2024', 'comment' => '', 'ok' => 'stamp.saveToCollection']));
Assert::same('add', $collectionModel->writes[0][0]);
Assert::same('/stamp/1', $url->getPath());
Assert::same('', $url->getFragment());
Assert::null($url->getQueryParameter('do'));

$collectionModel = new CollectFakeCollectionModel([7 => collected(7)]);
$url = redirectUrl(runCollect(new CollectTestStampPresenter($translator), 'Stamp', ['user'], $collectionModel, [
	'action' => 'default',
	'id' => '1',
	'do' => 'collectForm-submit',
], ['id' => '7', 'date' => '', 'comment' => '', 'remove' => 'stamp.uncollect']));
Assert::same([['remove', 7, 1]], $collectionModel->writes);
Assert::same('/stamp/1', $url->getPath());
Assert::same('stamp-7', $url->getFragment());


// Nearby: saving keeps lat/lng and the anchor (#48)
$collectionModel = new CollectFakeCollectionModel;
$url = redirectUrl(runCollect(new CollectTestNearbyPresenter($translator), 'Nearby', ['user'], $collectionModel, [
	'action' => 'default',
	'lat' => '50.1',
	'lng' => '14.4',
	'do' => 'collectForm-submit',
], ['id' => '5', 'date' => '', 'comment' => '', 'ok' => 'stamp.saveToCollection']));
Assert::same('add', $collectionModel->writes[0][0]);
Assert::same('50.1', $url->getQueryParameter('lat'));
Assert::same('14.4', $url->getQueryParameter('lng'));
Assert::same('stamp-5', $url->getFragment());


// AJAX: no redirect; the stamp's snippet, the stamp list and the flash messages are redrawn, the payload names the stamp (#48);
// on the homepage also the switch Vše / Mám / Nemám with the counts after the change (#50)
$cases = [
	[new CollectFakeCollectionModel, ['ok' => 'stamp.saveToCollection'], 'add', true, 'stamp.flash.added', 1],
	[new CollectFakeCollectionModel([5 => collected(5)]), ['ok' => 'stamp.saveToCollection'], 'update', true, 'stamp.flash.saved', 1],
	[new CollectFakeCollectionModel([5 => collected(5)]), ['remove' => 'stamp.uncollect'], 'remove', false, 'stamp.flash.removed', 0],
];
foreach ($cases as [$collectionModel, $button, $op, $collected, $flash, $collectedCount]) {
	$presenter = new HomepagePresenter($translator);
	$response = runCollect($presenter, 'Homepage', ['user'], $collectionModel, $homepageParams, [
		'id' => '5',
		'date' => '2. 6. 2024',
		'comment' => 'Sunny',
	] + $button, ['Sec-Fetch-Site' => 'same-origin', 'X-Requested-With' => 'XMLHttpRequest']);
	Assert::notSame(RedirectResponse::class, $response::class, $op);
	Assert::same($op, $collectionModel->writes[0][0]);
	Assert::same(5, $presenter->getPayload()->stampId, $op);
	Assert::same($collected, $presenter->getPayload()->collected, $op);
	Assert::same($flash, $presenter->getTemplate()->flashes[0]->message ?? null, $op);
	// the rendered payload carries just the redrawn snippets (rendering marks them valid again)
	Assert::type(Nette\Application\Responses\JsonResponse::class, $response);
	$snippets = (array) $presenter->getPayload()->snippets;
	$keys = array_keys($snippets);
	sort($keys);
	Assert::same(['snippet--collectionSwitch', 'snippet--flashes', 'snippet--stamp-5'], $keys, $op); // not the other stamps of the page
	Assert::contains('id="stamp-5"', $snippets['snippet--stamp-5'], $op);
	Assert::contains("form.filter.mine&nbsp;·&nbsp;$collectedCount<", $snippets['snippet--collectionSwitch'], $op);
	Assert::contains('form.filter.missing&nbsp;·&nbsp;' . (3 - $collectedCount) . '<', $snippets['snippet--collectionSwitch'], $op);
	Assert::contains("3 / $collectedCount", $snippets['snippet--collectionSwitch'], $op); // listed (the fake lists 3 always) / matching in "Mám"
	Assert::contains($flash, $snippets['snippet--flashes'] ?? '', $op);
	Assert::false(isset($snippets['snippet--collectSheet']), $op);
}


// AJAX with an invalid date: nothing stored, only the sheet with the error is redrawn (#48)
$collectionModel = new CollectFakeCollectionModel;
$presenter = new HomepagePresenter($translator);
runCollect($presenter, 'Homepage', ['user'], $collectionModel, $homepageParams, [
	'id' => '5',
	'date' => '31. 2. 2024',
	'comment' => '',
	'ok' => 'stamp.saveToCollection',
], ['Sec-Fetch-Site' => 'same-origin', 'X-Requested-With' => 'XMLHttpRequest']);
Assert::same([], $collectionModel->writes);
$snippets = (array) $presenter->getPayload()->snippets;
Assert::same(['snippet--collectSheet'], array_keys($snippets));
Assert::contains('form.editStamp.invalidDate', $snippets['snippet--collectSheet']);
Assert::contains('value="31. 2. 2024"', $snippets['snippet--collectSheet']);
Assert::contains('aria-invalid="true"', $snippets['snippet--collectSheet']); // bundle.js focuses the invalid input


// Stamp detail via AJAX: the detail's own collection block is redrawn when its stamp changes (#48)
$presenter = new CollectTestStampPresenter($translator);
runCollect($presenter, 'Stamp', ['user'], new CollectFakeCollectionModel, [
	'action' => 'default',
	'id' => '1',
	'do' => 'collectForm-submit',
], ['id' => '1', 'date' => '', 'comment' => '', 'ok' => 'stamp.saveToCollection'], ['Sec-Fetch-Site' => 'same-origin', 'X-Requested-With' => 'XMLHttpRequest']);
Assert::true($presenter->isControlInvalid('detailCollection'));
Assert::true($presenter->isControlInvalid('detailRibbon'));

$presenter = new CollectTestStampPresenter($translator);
runCollect($presenter, 'Stamp', ['user'], new CollectFakeCollectionModel, [
	'action' => 'default',
	'id' => '1',
	'do' => 'collectForm-submit',
], ['id' => '7', 'date' => '', 'comment' => '', 'ok' => 'stamp.saveToCollection'], ['Sec-Fetch-Site' => 'same-origin', 'X-Requested-With' => 'XMLHttpRequest']);
Assert::false($presenter->isControlInvalid('detailCollection'));
Assert::true($presenter->isControlInvalid('stamp-7'));
