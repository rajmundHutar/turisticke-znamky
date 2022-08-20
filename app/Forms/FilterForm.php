<?php

namespace App\Forms;

use Nette\Application\UI\Form;

class FilterForm extends BaseForm {

	public function create(array $labels): Form {

		$this->form->setMethod('GET');
		$this->form->addText('search', 'form.filter.search')
			->getControlPrototype()
			->placeholder('form.filter.search');
		$this->form->addSelect('sort', 'form.filter.sortBy', [
			null => 'form.filter.sortBy.asc',
			'-num' => 'form.filter.sortBy.desc',
		]);
		$this->form->addSelect('label', 'form.filter.label', $labels)
			->setPrompt('general.chooseSomething');
		$this->form->addSubmit('ok', 'general.submit');
		$this->form->onSuccess[] = function() {
			// Empty, will be handled in action method
		};

		return $this->form;

	}

}
