<?php

/**
 * Test: CollectStamps trait shares one collect form for adding, editing and removing a stamp between presenters (#36, #48)
 */

declare(strict_types=1);

use App\Dto\CollectedStampDto;
use App\Forms\EditStampForm;
use App\Helpers\CollectionPhotos;
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

	/** @var ?callable(): void  called before a file is written, e.g. to record the files on the disk or fail */
	public $onSetFile = null;


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


	public function fetchFile(int $stampId, int $userId): ?string
	{
		return $this->collection[$stampId]->file ?? null;
	}


	public function setFile(int $stampId, int $userId, ?string $file): int
	{
		if ($this->onSetFile) {
			($this->onSetFile)();
		}
		$this->writes[] = ['setFile', $stampId, $userId, $file];
		if (!isset($this->collection[$stampId])) {
			return 0;
		}
		$this->collection[$stampId] = collected($stampId, file: $file);
		return 1;
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


	public function fetchClosest(float $lat, float $lng, ?int $num = 10, ?array $exclude = null, ?array $ids = null): array
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


	public function publicReplaceCollectionPhoto(int $stampId, int $userId, string $uploadedPath): void
	{
		$this->replaceCollectionPhoto($stampId, $userId, $uploadedPath);
	}


	public function publicDeleteCollectionPhoto(int $stampId, int $userId): void
	{
		$this->deleteCollectionPhoto($stampId, $userId);
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
	?CollectionPhotos $photos = null,
	array $files = [],
): Nette\Application\Response {
	assert($presenter instanceof HomepagePresenter || $presenter instanceof NearbyPresenter || $presenter instanceof StampPresenter);
	$translator = PresenterTestHelpers::createTranslator();
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser($roles), $headers);
	$presenter->injectCollectionModel($collectionModel);
	$presenter->injectStampsModel(new CollectFakeStampsModel);
	$presenter->injectEditStampForm(new EditStampForm($translator));
	$presenter->injectCollectionPhotos($photos ?? new CollectionPhotos(sys_get_temp_dir() . '/collect-stamps-none'));
	if ($presenter instanceof HomepagePresenter) {
		$presenter->injectFilterForm(new App\Forms\FilterForm($translator));
	}
	return $presenter->run(new Request($name, $post === null ? 'GET' : 'POST', $params, $post ?? [], $files));
}


function redirectUrl(Nette\Application\Response $response): Nette\Http\Url
{
	Assert::type(RedirectResponse::class, $response);
	assert($response instanceof RedirectResponse);
	return new Nette\Http\Url($response->getUrl());
}


function collected(int $id, ?string $date = '2024-05-01', ?string $comment = 'Windy', ?string $file = null): CollectedStampDto
{
	return new CollectedStampDto($id, "Stamp $id", null, null, null, '50.0', '14.0', null, $date === null ? null : new DateTime($date), $comment, $file);
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
Assert::same(['date', 'comment', 'photo', 'deletePhoto', 'id', 'ok', 'remove'], array_keys(iterator_to_array($form->getComponents())));
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


// Stamp detail: saving the detail stamp redirects back to the detail, a nearby row to its anchor (#48)
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

$presenter = new CollectTestStampPresenter($translator);
runCollect($presenter, 'Stamp', ['user'], new CollectFakeCollectionModel, [
	'action' => 'default',
	'id' => '1',
	'do' => 'collectForm-submit',
], ['id' => '7', 'date' => '', 'comment' => '', 'ok' => 'stamp.saveToCollection'], ['Sec-Fetch-Site' => 'same-origin', 'X-Requested-With' => 'XMLHttpRequest']);
Assert::false($presenter->isControlInvalid('detailCollection'));
Assert::true($presenter->isControlInvalid('stamp-7'));


// Removing a stamp from the collection deletes its photo after the row is gone; another stamp's photo stays (#19)
$photosDir = sys_get_temp_dir() . '/collect-stamps-photos-' . getmypid();
Nette\Utils\FileSystem::delete($photosDir);
register_shutdown_function(fn() => Nette\Utils\FileSystem::delete($photosDir));
$photos = new CollectionPhotos($photosDir);
Nette\Utils\FileSystem::createDir("$photosDir-upload");
register_shutdown_function(fn() => Nette\Utils\FileSystem::delete("$photosDir-upload"));
$upload = "$photosDir-upload/photo.jpg";
imagejpeg(imagecreatetruecolor(40, 30), $upload);
$photo5 = $photos->store($upload);
$photo6 = $photos->store($upload);

$collectionModel = new CollectFakeCollectionModel([5 => collected(5, file: $photo5), 6 => collected(6, file: $photo6)]);
redirectUrl(runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, $homepageParams, [
	'id' => '5',
	'date' => '',
	'comment' => '',
	'remove' => 'stamp.uncollect',
], photos: $photos));
Assert::same([['remove', 5, 1]], $collectionModel->writes);
Assert::null($photos->path($photo5));
Assert::notNull($photos->path($photo6));

// a stamp without a photo, or with a file already missing, is removed as before
foreach ([null, $photo5] as $file) {
	$collectionModel = new CollectFakeCollectionModel([5 => collected(5, file: $file)]);
	redirectUrl(runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, $homepageParams, [
		'id' => '5',
		'date' => '',
		'comment' => '',
		'remove' => 'stamp.uncollect',
	], photos: $photos));
	Assert::same([['remove', 5, 1]], $collectionModel->writes);
}
Assert::notNull($photos->path($photo6));


/** @return list<string> */
function storedPhotosBefore(string $dir): array
{
	return array_map('basename', glob("$dir/*") ?: []);
}


/** The stamp detail presenter with the photos and the fake model injected, for the photo methods of the trait. */
function photoPresenter(CollectFakeCollectionModel $collectionModel, CollectionPhotos $photos): CollectTestStampPresenter
{
	$presenter = new CollectTestStampPresenter(PresenterTestHelpers::createTranslator());
	$presenter->injectCollectionModel($collectionModel);
	$presenter->injectCollectionPhotos($photos);
	return $presenter;
}


// Replacing a photo: the new file is stored first, then the DB points to it, then the old file is deleted (#19)
$collectionModel = new CollectFakeCollectionModel([6 => collected(6, file: $photo6)]);
$seen = null;
$collectionModel->onSetFile = function () use ($photosDir, $photo6, &$seen): void {
	$seen = array_map('basename', glob("$photosDir/*") ?: []);
	Assert::contains($photo6, $seen); // the old file still exists while the DB is written
};
photoPresenter($collectionModel, $photos)->publicReplaceCollectionPhoto(6, 1, $upload);
Assert::count(2, $seen); // the old and the new one
[$op, $stampId, $userId, $newPhoto] = $collectionModel->writes[0];
Assert::same(['setFile', 6, 1], [$op, $stampId, $userId]);
Assert::notSame($photo6, $newPhoto);
Assert::notNull($photos->path($newPhoto));
Assert::null($photos->path($photo6));
Assert::same([$newPhoto], array_map('basename', glob("$photosDir/*") ?: []));

// a first photo of a stamp without one
$collectionModel = new CollectFakeCollectionModel([5 => collected(5)]);
photoPresenter($collectionModel, $photos)->publicReplaceCollectionPhoto(5, 1, $upload);
Assert::same('setFile', $collectionModel->writes[0][0]);
Assert::count(2, glob("$photosDir/*") ?: []);
$photo5 = $collectionModel->writes[0][3];

// an invalid image writes nothing to the DB and keeps the current photo
$collectionModel = new CollectFakeCollectionModel([6 => collected(6, file: $newPhoto)]);
file_put_contents("$photosDir-upload/text.jpg", 'not an image');
Assert::exception(fn() => photoPresenter($collectionModel, $photos)->publicReplaceCollectionPhoto(6, 1, "$photosDir-upload/text.jpg"), RuntimeException::class);
Assert::same([], $collectionModel->writes);
Assert::count(2, glob("$photosDir/*") ?: []);

// a failed DB write deletes the new file and keeps the old one
$collectionModel = new CollectFakeCollectionModel([6 => collected(6, file: $newPhoto)]);
$collectionModel->onSetFile = fn() => throw new Nette\Database\DriverException('Gone away');
Assert::exception(fn() => photoPresenter($collectionModel, $photos)->publicReplaceCollectionPhoto(6, 1, $upload), Nette\Database\DriverException::class);
Assert::notNull($photos->path($newPhoto));
Assert::count(2, glob("$photosDir/*") ?: []);


// a row removed meanwhile (no row updated) deletes the new file and fails, no orphan is left
$before = storedPhotosBefore($photosDir);
$collectionModel = new CollectFakeCollectionModel;
Assert::exception(fn() => photoPresenter($collectionModel, $photos)->publicReplaceCollectionPhoto(6, 1, $upload), RuntimeException::class, '~not collected~');
Assert::same($before, storedPhotosBefore($photosDir));


// Deleting a photo clears the DB first, then the file (#19); without a photo it changes nothing
$collectionModel = new CollectFakeCollectionModel([6 => collected(6, file: $newPhoto)]);
$collectionModel->onSetFile = fn() => Assert::notNull($photos->path($newPhoto));
photoPresenter($collectionModel, $photos)->publicDeleteCollectionPhoto(6, 1);
Assert::same([['setFile', 6, 1, null]], $collectionModel->writes);
Assert::null($photos->path($newPhoto));
Assert::notNull($photos->path($photo5));

$collectionModel = new CollectFakeCollectionModel([6 => collected(6)]);
photoPresenter($collectionModel, $photos)->publicDeleteCollectionPhoto(6, 1);
Assert::same([], $collectionModel->writes);


/** A file uploaded in the collect form; the error code of PHP's $_FILES. */
function uploaded(string $path, int $error = UPLOAD_ERR_OK): Nette\Http\FileUpload
{
	return new Nette\Http\FileUpload(['name' => basename($path), 'size' => (int) @filesize($path), 'tmp_name' => $path, 'error' => $error]);
}


/** @return list<string>  the basenames of the stored photos */
function storedPhotos(string $dir): array
{
	return array_map('basename', glob("$dir/*") ?: []);
}


Nette\Utils\FileSystem::delete($photosDir);
$photos = new CollectionPhotos($photosDir);
$logDir = "$photosDir-log";
Nette\Utils\FileSystem::createDir($logDir);
register_shutdown_function(fn() => Nette\Utils\FileSystem::delete($logDir));
Tracy\Debugger::$logDirectory = $logDir; // a rejected photo is logged
$okPost = ['id' => '5', 'date' => '2. 6. 2024', 'comment' => 'Sunny', 'ok' => 'stamp.saveToCollection'];
$ajax = ['Sec-Fetch-Site' => 'same-origin', 'X-Requested-With' => 'XMLHttpRequest'];


// The form uploads a photo: it is multipart, with a file input and a delete checkbox; the upload has no rules of its own,
// a rejected photo must not block saving the date and the note (#19)
$presenter = new HomepagePresenter($translator);
runCollect($presenter, 'Homepage', ['user'], new CollectFakeCollectionModel, ['action' => 'default']);
$form = $presenter['collectForm'];
Assert::same('multipart/form-data', $form->getElementPrototype()->enctype);
[$photo, $deletePhoto] = [$form['photo'], $form['deletePhoto']];
assert($photo instanceof Nette\Forms\Controls\UploadControl && $deletePhoto instanceof Nette\Forms\Controls\Checkbox);
Assert::same('stamp.photo.delete', $deletePhoto->getCaption());
Assert::same([], iterator_to_array($photo->getRules()));
Assert::contains('image/', $photo->getControlPrototype()->accept);


// Add with a photo: the stamp is added, then the photo stored and set (#19)
$collectionModel = new CollectFakeCollectionModel;
redirectUrl(runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, $homepageParams, $okPost, photos: $photos, files: ['photo' => uploaded($upload)]));
Assert::same(['add', 'setFile'], array_column($collectionModel->writes, 0));
$added = $collectionModel->writes[1][3];
Assert::same([5, 1], array_slice($collectionModel->writes[1], 1, 2));
Assert::same([$added], storedPhotos($photosDir));


// Edit with a new photo replaces the old one, the old file is gone (#19)
$collectionModel = new CollectFakeCollectionModel([5 => collected(5, file: $added)]);
redirectUrl(runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, $homepageParams, $okPost, photos: $photos, files: ['photo' => uploaded($upload)]));
Assert::same(['update', 'setFile'], array_column($collectionModel->writes, 0));
$replaced = $collectionModel->writes[1][3];
Assert::notSame($added, $replaced);
Assert::same([$replaced], storedPhotos($photosDir));


// Edit without a file keeps the photo, also with the deletePhoto checkbox unchecked (#19)
$collectionModel = new CollectFakeCollectionModel([5 => collected(5, file: $replaced)]);
redirectUrl(runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, $homepageParams, $okPost, photos: $photos, files: ['photo' => new Nette\Http\FileUpload(null)]));
Assert::same(['update'], array_column($collectionModel->writes, 0));
Assert::same([$replaced], storedPhotos($photosDir));


// "Smazat fotku" deletes the photo on save (#19)
$collectionModel = new CollectFakeCollectionModel([5 => collected(5, file: $replaced)]);
$presenter = new HomepagePresenter($translator);
redirectUrl(runCollect($presenter, 'Homepage', ['user'], $collectionModel, $homepageParams, $okPost + ['deletePhoto' => 'on'], photos: $photos));
Assert::same(['update', 'setFile'], array_column($collectionModel->writes, 0));
Assert::null($collectionModel->writes[1][3]);
Assert::same([], storedPhotos($photosDir));
Assert::same(['stamp.flash.saved'], array_map(fn($flash) => $flash->message, $presenter->getTemplate()->flashes));


// An invalid photo or a failed upload: the date and the note are saved, the photo is not, an error flash says so
// and a short warning is logged (#19)
file_put_contents("$photosDir-upload/text.jpg", 'not an image');
$cases = [
	[uploaded("$photosDir-upload/text.jpg"), 'stamp.photo.saveFailed'],
	[uploaded('', UPLOAD_ERR_PARTIAL), 'stamp.photo.saveFailed'],
	[uploaded('', UPLOAD_ERR_INI_SIZE), 'stamp.photo.tooLarge'], // over upload_max_filesize
	[uploaded('', UPLOAD_ERR_FORM_SIZE), 'stamp.photo.tooLarge'],
];
foreach ($cases as [$file, $photoFlash]) {
	$stored = $photos->store($upload);
	$collectionModel = new CollectFakeCollectionModel([5 => collected(5, file: $stored)]);
	$presenter = new HomepagePresenter($translator);
	$response = runCollect($presenter, 'Homepage', ['user'], $collectionModel, $homepageParams, $okPost, $ajax, photos: $photos, files: ['photo' => $file]);
	Assert::same(['update'], array_column($collectionModel->writes, 0));
	Assert::same('Sunny', $collectionModel->writes[0][3]['comment']);
	Assert::same([$stored], storedPhotos($photosDir));
	Assert::same(
		[['stamp.flash.saved', Flash::SUCCESS], [$photoFlash, Flash::ERROR]],
		array_map(fn($flash) => [$flash->message, $flash->type], $presenter->getTemplate()->flashes),
	);
	Assert::same(5, $presenter->getPayload()->stampId); // the sheet closes, the flash shows the error
	$photos->delete($stored);
}
$log = (string) file_get_contents("$logDir/warning.log");
Assert::contains('stamp 5', $log);
Assert::contains('user 1', $log);
Assert::contains('not a JPEG, PNG or WebP', $log);
Assert::contains((string) UPLOAD_ERR_INI_SIZE, $log);


// An invalid photo of a new stamp still adds the stamp (#19)
$collectionModel = new CollectFakeCollectionModel;
$invalid = uploaded("$photosDir-upload/text.jpg");
redirectUrl(runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, $homepageParams, $okPost, photos: $photos, files: ['photo' => $invalid]));
Assert::same(['add'], array_column($collectionModel->writes, 0));
Assert::same([], storedPhotos($photosDir));


// Remove with a photo deletes it; an attached invalid file or a checked delete box is ignored, only the id is validated (#19)
$stored = $photos->store($upload);
$collectionModel = new CollectFakeCollectionModel([5 => collected(5, file: $stored)]);
redirectUrl(runCollect(new HomepagePresenter($translator), 'Homepage', ['user'], $collectionModel, $homepageParams, [
	'id' => '5',
	'date' => 'nonsense',
	'comment' => '',
	'deletePhoto' => 'on',
	'remove' => 'stamp.uncollect',
], photos: $photos, files: ['photo' => uploaded("$photosDir-upload/text.jpg")]));
Assert::same([['remove', 5, 1]], $collectionModel->writes);
Assert::same([], storedPhotos($photosDir));


// A POST over post_max_size comes with an empty POST and no files, so also without the form's signal (_do is in the body):
// a flash says the photo is too large; back to the page, or by AJAX the flashes are redrawn and the sheet closes (#19)
$oversizedParams = array_diff_key($homepageParams, ['do' => true]);
$collectionModel = new CollectFakeCollectionModel([5 => collected(5)]);
$presenter = new HomepagePresenter($translator);
$url = redirectUrl(runCollect($presenter, 'Homepage', ['user'], $collectionModel, $oversizedParams, [], ['Sec-Fetch-Site' => 'same-origin', 'Content-Length' => '30000000']));
Assert::same([], $collectionModel->writes);
Assert::null($url->getQueryParameter('do'));
Assert::same('Praha', $url->getQueryParameter('search'));
Assert::same([['stamp.photo.tooLarge', Flash::ERROR]], array_map(fn($flash) => [$flash->message, $flash->type], $presenter->getTemplate()->flashes));

$presenter = new HomepagePresenter($translator);
$response = runCollect($presenter, 'Homepage', ['user'], $collectionModel, $oversizedParams, [], $ajax + ['Content-Length' => '30000000']);
Assert::same([], $collectionModel->writes);
Assert::true($presenter->getPayload()->collectFailed);
$snippets = (array) $presenter->getPayload()->snippets;
Assert::same(['snippet--flashes'], array_keys($snippets));
Assert::contains('stamp.photo.tooLarge', $snippets['snippet--flashes']);

// ... the same on the stamp detail and Nearby
$response = runCollect(new CollectTestStampPresenter($translator), 'Stamp', ['user'], $collectionModel, ['action' => 'default', 'id' => '1'], [], [
	'Sec-Fetch-Site' => 'same-origin',
	'Content-Length' => '30000000',
]);
Assert::same('/stamp/1', redirectUrl($response)->getPath());
$response = runCollect(new CollectTestNearbyPresenter($translator), 'Nearby', ['user'], $collectionModel, ['action' => 'default', 'lat' => '50.1', 'lng' => '14.4'], [], [
	'Sec-Fetch-Site' => 'same-origin',
	'Content-Length' => '30000000',
]);
Assert::same('50.1', redirectUrl($response)->getQueryParameter('lat'));
Assert::same([], $collectionModel->writes);

// ... a POST with data is no oversized one
$presenter = new HomepagePresenter($translator);
redirectUrl(runCollect($presenter, 'Homepage', ['user'], $collectionModel, $homepageParams, $okPost, ['Sec-Fetch-Site' => 'same-origin', 'Content-Length' => '300']));
Assert::same(['stamp.flash.saved'], array_map(fn($flash) => $flash->message, $presenter->getTemplate()->flashes));


// The collect button carries the URL of the current photo for the sheet, only for a collected stamp with a photo (#19)
$stored = $photos->store($upload);
$presenter = new HomepagePresenter($translator);
runCollect($presenter, 'Homepage', ['user'], new CollectFakeCollectionModel([5 => collected(5, file: $stored), 6 => collected(6)]), $homepageParams, $okPost, $ajax, photos: $photos);
$snippet = ((array) $presenter->getPayload()->snippets)['snippet--stamp-5'];
Assert::contains('data-photo="/photo/5/' . basename($stored, '.jpg') . '"', $snippet);
$photos->delete($stored);

$presenter = new HomepagePresenter($translator);
runCollect($presenter, 'Homepage', ['user'], new CollectFakeCollectionModel([5 => collected(5)]), $homepageParams, $okPost, $ajax, photos: $photos);
Assert::notContains('data-photo', ((array) $presenter->getPayload()->snippets)['snippet--stamp-5']);


// The sheet: multipart, the photo preview, file input and delete checkbox, labels and a remove confirmation for a stamp
// with a photo, all switched by bundle.js from the button's data-photo (#19)
$presenter = new HomepagePresenter($translator);
runCollect($presenter, 'Homepage', ['user'], new CollectFakeCollectionModel, $homepageParams, ['id' => '5', 'date' => '31. 2. 2024', 'comment' => '', 'ok' => 'stamp.saveToCollection'], $ajax);
$sheet = ((array) $presenter->getPayload()->snippets)['snippet--collectSheet'];
Assert::contains('enctype="multipart/form-data"', $sheet);
Assert::match('~<input type="file" name="photo"[^>]* accept="image/\*"~', $sheet);
Assert::match('~<input type="checkbox" name="deletePhoto"~', $sheet);
Assert::contains('class="collect-sheet-photo-preview"', $sheet);
Assert::contains('data-label-add="stamp.photo.add"', $sheet);
Assert::contains('data-label-change="stamp.photo.change"', $sheet);
Assert::contains('data-confirm="stamp.uncollectConfirm"', $sheet);
Assert::contains('data-confirm-photo="stamp.uncollectConfirmPhoto"', $sheet);
Assert::contains('data-photo-unreadable="stamp.photo.unreadable"', $sheet);
Assert::contains('data-photo-too-large="stamp.photo.tooLarge"', $sheet);
Assert::contains('data-photo-processing="stamp.photo.processing"', $sheet);
