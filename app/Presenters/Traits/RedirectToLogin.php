<?php

declare(strict_types=1);

namespace App\Presenters\Traits;

use Nette\Application\UI\Presenter;

trait RedirectToLogin
{
	/**
	 * Redirects to the login page with a backlink to the current page.
	 * Only a plain GET of the page is stored, never a signal (in the URL or a POST body `_do`):
	 * replayed after login from our own login page, it would pass the same-origin check (CSRF).
	 */
	protected function redirectToLogin(): never
	{
		$request = $this->getRequest();
		$request->setParameters(array_diff_key($request->getParameters(), [Presenter::SignalKey => null]));
		$request->setMethod('GET');
		$request->setPost([]);
		$request->setFiles([]);
		$this->redirect('Login:', ['backlink' => $this->storeRequest()]);
	}
}
