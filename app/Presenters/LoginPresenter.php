<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\LoginFormFactory;
use Nette;
use Nette\Security\AuthenticationException;


final class LoginPresenter extends Nette\Application\UI\Presenter {

	use InjectMenu;

	public function createComponentLoginForm() {

		return LoginFormFactory::create(function($form, $values) {

			try {
				$this->user->setExpiration(null);
				$this->user->login($values->email, $values->password);
				$this->flashMessage('Uživatel přihlášen.', \Flash::SUCCESS);
			} catch (AuthenticationException $e) {
				dump($e);
				$this->flashMessage('Chybný e-mail nebo heslo.', \Flash::ERROR);
			}

			$this->redirect('Homepage:');

		});

	}

}
