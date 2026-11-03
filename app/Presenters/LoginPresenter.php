<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\LoginForm;
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

			$ip = $this->getHttpRequest()->getRemoteAddress();
			if ($this->loginThrottle->isBlocked($values->email, $ip)) {
				$this->flashMessage($this->t('login.tooManyAttempts'), \Flash::ERROR);
				$this->redirect('this');
			}

			try {
				$this->user->setExpiration(null);
				$this->user->login($values->email, $values->password);
				$this->loginThrottle->reset($values->email, $ip);
				$this->flashMessage($this->t('login.loginSuccessful'), \Flash::SUCCESS);

			} catch (AuthenticationException $e) {
				bdump($e);
				$this->loginThrottle->recordFailure($values->email, $ip);
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
