<?php

namespace App\Forms;

use Nette\Application\UI\Form;

class FilterForm extends BaseForm {

	public function create(array $sortBy): Form {

		$this->form->setMethod('GET');
		$this->form->addText('search', 'form.filter.search')
			->getControlPrototype()
			->placeholder('form.filter.search');
		$this->form->addSelect('sort', 'form.filter.sortBy', $sortBy);
		$this->form->addSubmit('ok', 'general.submit');
		$this->form->onSuccess[] = function() {
			// Empty, will be handled in action method
		};

		return $this->form;

	}

}
