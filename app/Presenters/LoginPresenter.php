<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\LoginForm;
use App\Models\LoginThrottledException;
use App\Models\Traits\InjectLoginThrottle;
use App\Presenters\Traits\InjectFormFactory;
use App\Presenters\Traits\InjectMenu;
use App\Presenters\Traits\InjectTranslator;
use Nette;
use Nette\Security\AuthenticationException;

final class LoginPresenter extends Nette\Application\UI\Presenter
{

	use InjectMenu;
	use InjectTranslator;
	use InjectFormFactory;
	use InjectLoginThrottle;

	/** Key of the request stored by storeRequest() to return to after login */
	#[Nette\Application\Attributes\Persistent]
	public ?string $backlink = null;

	public function createComponentLoginForm()
	{

		return $this->formFactory->create(LoginForm::class, function($form, $values) {

			try {
				$this->loginThrottle->attempt($values->email, $this->getHttpRequest()->getRemoteAddress(), function () use ($values) {
					$this->user->setExpiration(null);
					$this->user->login($values->email, $values->password);
				});
				$this->flashMessage($this->t('login.loginSuccessful'), \Flash::SUCCESS);

			} catch (LoginThrottledException) {
				$this->flashMessage($this->t('login.tooManyAttempts'), \Flash::ERROR);
				$this->redirect('this');

			} catch (AuthenticationException $e) {
				bdump($e);
				$this->flashMessage($this->t('login.loginNotSuccessful'), \Flash::ERROR);
				$this->redirect('this');
			}

			if ($this->backlink !== null) {
				$this->restoreRequest($this->backlink);
			}
			$this->redirect('Homepage:');
		});
	}

}
