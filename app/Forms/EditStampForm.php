<?php

namespace App\Forms;

use Nette\Application\UI\Form;

class EditStampForm extends BaseForm {

	public function create(
		int $stampId,
		array $defaults,
		callable $onSuccess
	): Form {

		$this->form->addText('date', 'form.editStamp.date');
		$this->form->addTextArea('comment', 'form.editStamp.description');
		$this->form->addHidden('id', $stampId);

		$this->form->addSubmit('ok', 'general.submit');

		$this->form->onSuccess[] = $onSuccess;

		$this->form->setDefaults($defaults);

		return $this->form;

	}

}
