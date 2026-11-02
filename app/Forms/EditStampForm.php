<?php

namespace App\Forms;

use Nette\Application\UI\Form;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\DateTime;

class EditStampForm extends BaseForm {

	private const DateFormat = 'Y-m-d';

	public function create(
		int $stampId,
		array $defaults,
		callable $onSuccess
	): Form {

		$this->form->addText('date', 'form.editStamp.date')
			->setNullable()
			->addCondition(Form::Filled)
				->addRule(
					fn(TextInput $input) => self::parseDate($input->getValue()) !== null,
					'form.editStamp.invalidDate'
				)
				->addFilter(fn(string $value) => self::parseDate($value));
		$this->form->addTextArea('comment', 'form.editStamp.description');
		$this->form->addHidden('id', $stampId);

		$this->form->addSubmit('ok', 'general.submit');

		$this->form->onSuccess[] = $onSuccess;

		// DateTime would render as 'Y-m-d H:i:s' and fail the validation on resubmit
		if (($defaults['date'] ?? null) instanceof \DateTimeInterface) {
			$defaults['date'] = $defaults['date']->format(self::DateFormat);
		}
		$this->form->setDefaults($defaults);

		return $this->form;

	}

	/**
	 * Returns the date at midnight, or null if the value is not a real Y-m-d date.
	 */
	private static function parseDate(string $value): ?DateTime {
		$date = DateTime::createFromFormat('!' . self::DateFormat, $value);
		return $date && $date->format(self::DateFormat) === $value ? $date : null;
	}

}
