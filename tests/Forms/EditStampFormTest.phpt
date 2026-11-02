<?php

/**
 * Test: EditStampForm validates the date and converts it to DateTime or null (#11)
 */

declare(strict_types=1);

use App\Forms\EditStampForm;
use Nette\Application\Request;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\DateTime;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../Presenters/PresenterTestHelpers.php';


/** Hosts the form, so it can be submitted through a regular request. */
final class EditStampFormTestPresenter extends Presenter
{
	/** @var array<string, mixed>|null values passed to onSuccess, null = not called */
	public ?array $received = null;


	protected function createComponentForm(): Form
	{
		$form = (new EditStampForm(PresenterTestHelpers::createTranslator()))->create(5, [], function (Form $form, array $values): void {
			$this->received = $values;
		});
		return $form;
	}


	protected function beforeRender(): void
	{
		$this->terminate();
	}
}


/**
 * Submits the form with the given date; returns the values passed to onSuccess (null = not called) and the form.
 * @return array{?array<string, mixed>, Form}
 */
function submitDate(string $date): array
{
	$presenter = new EditStampFormTestPresenter;
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']), ['Sec-Fetch-Site' => 'same-origin']);
	$presenter->run(new Request(
		'EditStampFormTest',
		'POST',
		['action' => 'default', 'do' => 'form-submit'],
		['id' => '5', 'date' => $date, 'comment' => 'Windy', 'ok' => 'general.submit'],
	));
	return [$presenter->received, $presenter['form']];
}


// Empty date is stored as null
[$values] = submitDate('');
Assert::same(['date' => null, 'comment' => 'Windy', 'id' => '5'], $values);


// A valid Czech date (d. m. yyyy, with or without leading zeros and spaces) is converted to DateTime at midnight;
// Y-m-d is still accepted for browsers with the old cached datepicker script
foreach (['2. 1. 2024', '2.1.2024', '02. 01. 2024', '02.01.2024', ' 2. 1. 2024 ', '2024-01-02'] as $valid) {
	[$values] = submitDate($valid);
	Assert::type(DateTimeInterface::class, $values['date'], $valid);
	Assert::same('2024-01-02 00:00:00', $values['date']->format('Y-m-d H:i:s'), $valid);
}


// Invalid format or impossible date: form error, onSuccess not called
foreach (['2. 1.', '2/1/2024', '1. 13. 2024', '31. 2. 2024', 'yesterday', '2024-02-31', '2024-1-2', '2024-01-02 00:00:00'] as $invalid) {
	[$values, $form] = submitDate($invalid);
	Assert::null($values, $invalid);
	Assert::same(['form.editStamp.invalidDate'], $form->getErrors(), $invalid);
}


// A DateTime default renders as a Czech date, so resubmitting the unchanged form passes validation
$form = (new EditStampForm(PresenterTestHelpers::createTranslator()))->create(5, ['date' => new DateTime('2024-05-01')], fn() => null);
$input = $form['date'];
assert($input instanceof TextInput);
Assert::same('1. 5. 2024', $input->getControl()->value);
