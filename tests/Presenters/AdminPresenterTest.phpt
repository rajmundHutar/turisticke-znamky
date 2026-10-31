<?php

/**
 * Test: AdminPresenter requires the admin role; imports are signals
 */

declare(strict_types=1);

use App\Models\ImportModel;
use App\Models\StampsModel;
use App\Presenters\AdminPresenter;
use Nette\Application\BadRequestException;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/PresenterTestHelpers.php';


/** ImportModel double without a database; records which imports were triggered. */
final class FakeImportModel extends ImportModel
{
	/** @var list<string> */
	public array $calls = [];


	public function __construct()
	{
	}


	public function import(): array
	{
		$this->calls[] = 'import';
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
function runAdmin(?array $roles, array $params, FakeImportModel $importModel, array $headers = []): Nette\Application\Response
{
	$presenter = new AdminPresenter(PresenterTestHelpers::createTranslator());
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser($roles), $headers);
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


// Anonymous and non-admin users get 403 for every action and signal, and nothing is imported
foreach ([null, ['user']] as $roles) {
	foreach ($requests as $params) {
		$importModel = new FakeImportModel;
		$e = Assert::exception(
			fn() => runAdmin($roles, $params, $importModel, $sameOrigin),
			BadRequestException::class,
		);
		Assert::same(403, $e->getCode());
		Assert::same([], $importModel->calls);
	}
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
