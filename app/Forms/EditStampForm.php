<?php

namespace App\Forms;

use Nette\Application\UI\Form;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\DateTime;

class EditStampForm extends BaseForm {

	private const DateFormat = 'j. n. Y';

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

		// DateTime would render as 'Y-m-d H:i:s'; show the same Czech format the datepicker uses
		if (($defaults['date'] ?? null) instanceof \DateTimeInterface) {
			$defaults['date'] = $defaults['date']->format(self::DateFormat);
		}
		$this->form->setDefaults($defaults);

		return $this->form;

	}

	/**
	 * Returns the date at midnight, or null if the value is not a real date.
	 * Accepts d. m. yyyy (leading zeros and spaces optional) and Y-m-d (old cached datepicker script).
	 */
	private static function parseDate(string $value): ?DateTime {
		if (preg_match('~^\s*(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\s*$~', $value, $m)) {
			[, $day, $month, $year] = $m;
		} elseif (preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $value, $m)) {
			[, $year, $month, $day] = $m;
		} else {
			return null;
		}
		return checkdate((int) $month, (int) $day, (int) $year)
			? DateTime::fromParts((int) $year, (int) $month, (int) $day)
			: null;
	}

}
