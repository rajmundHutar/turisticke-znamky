<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;
use Nette\Localization\Translator;

abstract class BaseForm extends Form {

	protected Form $form;

	public function __construct(Translator $translator) {

		$this->form = new Form();
		$this->form->setTranslator($translator);

	}

}
