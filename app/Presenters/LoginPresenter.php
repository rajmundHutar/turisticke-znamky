<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\LoginForm;
use App\Models\LoginThrottledException;
use App\Models\Traits\InjectLoginThrottle;
use App\Presenters\Traits\InjectMenu;
use App\Presenters\Traits\InjectTranslator;
use Nette;
use Nette\Security\AuthenticationException;

final class LoginPresenter extends Nette\Application\UI\Presenter
{

	use InjectMenu;
	use InjectTranslator;
	use InjectLoginThrottle;
	private const FailedLoginSection = 'failedLogin';

	/** Key of the request stored by storeRequest() to return to after login */
	#[Nette\Application\Attributes\Persistent]
	public ?string $backlink = null;

	private LoginForm $loginForm;


	public function injectLoginForm(LoginForm $loginForm): void
	{
		$this->loginForm = $loginForm;
	}


	public function createComponentLoginForm()
	{

		$form = $this->loginForm->create(function($form, $values) {

			try {
				$this->loginThrottle->attempt($values->email, $this->getHttpRequest()->getRemoteAddress(), function () use ($values) {
					$this->user->setExpiration(null);
					$this->user->login($values->email, $values->password);
				});
				$this->flashMessage($this->t('login.loginSuccessful'), \Flash::SUCCESS);

			} catch (LoginThrottledException) {
				$this->flashMessage($this->t('login.tooManyAttempts'), \Flash::ERROR);
				$this->rememberFailedEmail($values->email);
				$this->redirect('this');

			} catch (AuthenticationException $e) {
				bdump($e);
				$this->flashMessage($this->t('login.loginNotSuccessful'), \Flash::ERROR);
				$this->rememberFailedEmail($values->email);
				$this->redirect('this');
			}

			if ($this->backlink !== null) {
				$this->restoreRequest($this->backlink);
			}
			$this->redirect('Homepage:');
		});

		// prefill the e-mail of the previous failed attempt once, the password is never kept
		$email = $this->getSession(self::FailedLoginSection)->get('email');
		if ($email !== null && $this->getRequest()->isMethod('GET')) {
			$this->getSession(self::FailedLoginSection)->remove('email');
			$form->setDefaults(['email' => $email]);
			$form->getComponent('password')->setHtmlAttribute('autofocus', true);
		}

		return $form;
	}

	/**
	 * Keeps the e-mail of a failed login for the next render of the form, in the session rather than the URL.
	 */
	private function rememberFailedEmail(string $email): void
	{
		$this->getSession(self::FailedLoginSection)->set('email', $email, '5 minutes');
	}

}
