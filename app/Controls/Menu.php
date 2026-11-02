<?php

declare(strict_types=1);

namespace App\Controls;

use Nette\Application\UI\Control;
use Nette\Localization\Translator;

class Menu extends Control {

	protected Translator $translator;

	public function __construct(Translator $translator) {
		$this->translator = $translator;
	}

	public function handleLogout(): void {

		$presenter = $this->getPresenter();
		$presenter->getUser()->logout(true);
		// the homepage root, so persistent filters (e.g. "My collection") don't survive the logout
		$presenter->redirectUrl($presenter->getHttpRequest()->getUrl()->getBaseUrl());

	}

	public function render() {

		$this->template->setTranslator($this->translator);
		$this->template->setFile(__DIR__ . '/Menu.latte');
		$this->template->render();

	}

}
