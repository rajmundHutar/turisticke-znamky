<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

class LoginFormFactory {

	public static function create(callable $onSuccess) {

		$form = new Form();
		$form->addText('email', 'E-mail');
		$form->addPassword('password', 'Heslo');
		$form->addSubmit('ok', 'Přihlásit');

		$form->onSuccess[] = function($form, $values) use ($onSuccess) {
			$onSuccess($form, $values);
		};

		return $form;

	}

}
