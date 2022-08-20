<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\LoginForm;
use App\Presenters\Traits\InjectFormFactory;
use App\Presenters\Traits\InjectTranslator;
use Nette;
use Nette\Security\AuthenticationException;

final class LoginPresenter extends Nette\Application\UI\Presenter {

	use InjectMenu,
		InjectTranslator,
		InjectFormFactory;

	public function createComponentLoginForm() {

		return $this->formFactory->create(LoginForm::class, function($form, $values) {

			try {

				$this->user->setExpiration(null);
				$this->user->login($values->email, $values->password);
				$this->flashMessage($this->t('login.loginSuccessful'), \Flash::SUCCESS);

			} catch (AuthenticationException $e) {

				bdump($e);
				$this->flashMessage($this->t('login.loginNotSuccessful'), \Flash::ERROR);

			}

			$this->redirect('Homepage:');

		});

	}

}
