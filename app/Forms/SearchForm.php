<?php

namespace App\Forms;

use Nette\Application\UI\Form;

class SearchForm {

	public static function create() {

		$form = new Form();
		$form->setMethod('GET');
		$form->addText('search', 'Hledat');
		$form->addSubmit('ok', 'Hledat');

		return $form;

	}

}
