<?php

/**
 * Test: AdminPresenter requires the admin role, anonymous users go to login; imports are signals
 */

declare(strict_types=1);

use App\Models\ImportModel;
use App\Models\StampsModel;
use App\Presenters\AdminPresenter;
use Nette\Application\BadRequestException;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
use Nette\Http\Session;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/PresenterTestHelpers.php';
require __DIR__ . '/../../app/Helpers/Flash.php'; // global class, loaded by RobotLoader in the app


/** ImportModel double without a database; records which imports were triggered. */
final class FakeImportModel extends ImportModel
{
	/** @var list<string> */
	public array $calls = [];

	public ?RuntimeException $importException = null;


	public function __construct()
	{
	}


	public function import(): array
	{
		$this->calls[] = 'import';
		if ($this->importException) {
			throw $this->importException;
		}
		return [10, 2];
	}


	public function importImages(): int
	{
		$this->calls[] = 'importImages';
		return 3;
	}


	public function fetchLastLog(): Nette\Database\Table\ActiveRow
	{
		throw new LogicException('Not expected in this test.');
	}
}


/**
 * @param  list<string>|null  $roles
 * @param  array<string, string>  $params
 * @param  array<string, string>  $headers
 */
function runAdmin(?array $roles, array $params, FakeImportModel $importModel, array $headers = [], ?AdminPresenter $presenter = null, ?Session $session = null): Nette\Application\Response
{
	$presenter ??= new AdminPresenter(PresenterTestHelpers::createTranslator());
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser($roles), $headers, $session);
	$presenter->injectImportModel($importModel);
	$presenter->injectStampsModel((new ReflectionClass(StampsModel::class))->newInstanceWithoutConstructor());
	return $presenter->run(new Request('Admin', 'GET', $params));
}


$requests = [
	'default' => ['action' => 'default'],
	'importStamps signal' => ['action' => 'default', 'do' => 'importStamps'],
	'importImages signal' => ['action' => 'default', 'do' => 'importImages'],
	'old importStamps action' => ['action' => 'importStamps'],
	'old importImages action' => ['action' => 'importImages'],
];
$sameOrigin = ['Sec-Fetch-Site' => 'same-origin'];


// Anonymous users are redirected to the login page with a backlink to the stored request, and nothing is imported (#22)
foreach ($requests as $params) {
	$importModel = new FakeImportModel;
	$presenter = new AdminPresenter(PresenterTestHelpers::createTranslator());
	$response = runAdmin(null, $params, $importModel, $sameOrigin, $presenter);
	Assert::type(RedirectResponse::class, $response);
	Assert::same('Login', $presenter->getLastCreatedRequest()?->getPresenterName());
	Assert::same('default', $presenter->getLastCreatedRequest()?->getParameter('action'));
	Assert::match('%[0-9a-z]{5}%', (string) $presenter->getLastCreatedRequest()?->getParameter('backlink'));
	Assert::same([], $importModel->calls);
}


// A signal is never stored for after login: following an attacker's link anonymously and then logging in
// would replay it from our own login page, passing the same-origin check (#22)
foreach (['importStamps', 'importImages'] as $signal) {
	$importModel = new FakeImportModel;
	$presenter = new AdminPresenter(PresenterTestHelpers::createTranslator());
	$session = new Session(new Nette\Http\Request(new Nette\Http\UrlScript('http://localhost/')), new Nette\Http\Response);
	runAdmin(null, ['action' => 'default', 'do' => $signal], $importModel, ['Sec-Fetch-Site' => 'cross-site'], $presenter, $session);
	$backlink = (string) $presenter->getLastCreatedRequest()?->getParameter('backlink');
	$stored = $session->getSection('Nette.Application/requests')->get($backlink)[1];
	Assert::type(Request::class, $stored);
	Assert::same(['action' => 'default'], $stored->getParameters());
	Assert::exception( // replaying the stored request as admin only renders the page
		fn() => runAdmin(['admin'], $stored->getParameters(), $importModel, $sameOrigin),
		LogicException::class,
		'Not expected in this test.',
	);
	Assert::same([], $importModel->calls);
}


// The same for a signal sent in a cross-site POST body (_do): only a plain GET of the page is stored (#22)
$importModel = new FakeImportModel;
$presenter = new AdminPresenter(PresenterTestHelpers::createTranslator());
$session = new Session(new Nette\Http\Request(new Nette\Http\UrlScript('http://localhost/')), new Nette\Http\Response);
PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(null), ['Sec-Fetch-Site' => 'cross-site'], $session);
$presenter->injectImportModel($importModel);
$presenter->injectStampsModel((new ReflectionClass(StampsModel::class))->newInstanceWithoutConstructor());
$presenter->run(new Request('Admin', 'POST', ['action' => 'default'], ['_do' => 'importStamps'], ['file' => 'x']));
$backlink = (string) $presenter->getLastCreatedRequest()?->getParameter('backlink');
$stored = $session->getSection('Nette.Application/requests')->get($backlink)[1];
Assert::type(Request::class, $stored);
Assert::same('GET', $stored->getMethod());
Assert::same(['action' => 'default'], $stored->getParameters());
Assert::same([], $stored->getPost());
Assert::same([], $stored->getFiles());
Assert::same([], $importModel->calls);


// Logged-in users without the admin role get 403 for every action and signal, and nothing is imported
foreach ($requests as $params) {
	$importModel = new FakeImportModel;
	$e = Assert::exception(
		fn() => runAdmin(['user'], $params, $importModel, $sameOrigin),
		BadRequestException::class,
	);
	Assert::same(403, $e->getCode());
	Assert::same([], $importModel->calls);
}


// Imports are signals, not GET actions
$reflection = new ReflectionClass(AdminPresenter::class);
Assert::false($reflection->hasMethod('actionImportStamps'));
Assert::false($reflection->hasMethod('actionImportImages'));
Assert::true($reflection->hasMethod('handleImportStamps'));
Assert::true($reflection->hasMethod('handleImportImages'));


// Admin passes the check; the signal runs and redirects
foreach (['importStamps' => 'import', 'importImages' => 'importImages'] as $signal => $call) {
	$importModel = new FakeImportModel;
	$response = runAdmin(['admin'], ['action' => 'default', 'do' => $signal], $importModel, $sameOrigin);
	Assert::type(RedirectResponse::class, $response);
	Assert::same([$call], $importModel->calls);
}


// A failed import (download or feed error) is shown as an error flash message instead of crashing
$importModel = new FakeImportModel;
$importModel->importException = new RuntimeException('Download of https://example.com/feed.xml failed: HTTP status 404');
$presenter = new AdminPresenter(PresenterTestHelpers::createTranslator());
$response = runAdmin(['admin'], ['action' => 'default', 'do' => 'importStamps'], $importModel, $sameOrigin, $presenter);
Assert::type(RedirectResponse::class, $response);
$flashes = $presenter->getTemplate()->flashes;
Assert::count(1, $flashes);
Assert::same(Flash::ERROR, $flashes[0]->type);
Assert::same('admin.importFailed{error}', $flashes[0]->message);


// Admin can open the default page (rendering is deferred, so no DB access is needed beyond the stubs)
Assert::exception(
	fn() => runAdmin(['admin'], ['action' => 'default'], new FakeImportModel),
	LogicException::class,
	'Not expected in this test.',
);


// Cross-origin signal is rejected by Nette (redirect without running the signal)
$importModel = new FakeImportModel;
$response = runAdmin(['admin'], ['action' => 'default', 'do' => 'importStamps'], $importModel, ['Sec-Fetch-Site' => 'cross-site']);
Assert::type(RedirectResponse::class, $response);
Assert::same([], $importModel->calls);
