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

	public function render() {

		$this->template->setTranslator($this->translator);
		$this->template->setFile(__DIR__ . '/Menu.latte');
		$this->template->render();

	}

}
