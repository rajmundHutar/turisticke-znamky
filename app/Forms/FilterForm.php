<?php

namespace App\Forms;

use Nette\Application\UI\Form;

class FilterForm {

	public static function create(array $labels) {

		$form = new Form();
		$form->setMethod('GET');
		$form->addText('search', 'Hledat')
			->getControlPrototype()
			->placeholder('Hledat');
		$form->addSelect('sort', 'Řadit', [
			null => 'Vzestupně',
			'-num' => 'Sestupně',
		]);
		$form->addSelect('label', 'Label', $labels)
			->setPrompt('Vyber...');
		$form->addSubmit('ok', 'OK');
		$form->onSuccess[] = function() {
			// Empty, will be handled in action method
		};

		return $form;

	}

}
