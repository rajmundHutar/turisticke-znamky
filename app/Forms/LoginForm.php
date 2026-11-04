<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;
use Nette\Localization\Translator;

final class LoginForm
{

	public function __construct(
		private Translator $translator,
	) {
	}


	/**
	 * Returns a new form on each call.
	 */
	public function create(callable $onSuccess): Form
	{
		$form = new Form;
		$form->setTranslator($this->translator);

		$form->addText('email', 'login.email');
		$form->addPassword('password', 'login.password');
		$form->addSubmit('ok', 'login.submit');

		$form->onSuccess[] = $onSuccess;

		return $form;
	}

}
