<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

class LoginForm extends BaseForm {

	public function create(callable $onSuccess) {

		$this->form->addText('email', 'login.email');
		$this->form->addPassword('password', 'login.password');
		$this->form->addSubmit('ok', 'general.submit');

		$this->form->onSuccess[] = $onSuccess;

		return $this->form;

	}

}
