<?php

/**
 * Test: the form classes return a new translated Form with the expected fields on each call (#37)
 */

declare(strict_types=1);

use App\Forms\EditStampForm;
use App\Forms\FilterForm;
use App\Forms\LoginForm;
use Nette\Application\UI\Form;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../Presenters/PresenterTestHelpers.php';


$translator = PresenterTestHelpers::createTranslator();
$factories = [
	'login' => [fn() => (new LoginForm($translator))->create(fn() => null), ['email', 'password', 'ok']],
	'filter' => [fn() => (new FilterForm($translator))->create(['id' => 'form.filter.sortBy.id.asc']), ['search', 'sort', 'ok']],
	'editStamp' => [fn() => (new EditStampForm($translator))->create(null, [], fn() => null), ['date', 'comment', 'photo', 'deletePhoto', 'id', 'ok', 'remove']],
];

foreach ($factories as $name => [$create, $fields]) {
	$form = $create();
	Assert::type(Form::class, $form, $name);
	Assert::same($translator, $form->getTranslator(), $name);
	Assert::same($fields, array_keys(iterator_to_array($form->getComponents())), $name);
	Assert::notSame($form, $create(), $name);
}
