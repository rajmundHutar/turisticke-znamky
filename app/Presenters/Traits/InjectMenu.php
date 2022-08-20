<?php

namespace App\Presenters;

use App\Controls\Menu;
use Nette\Localization\Translator;

trait InjectMenu {

	public function createComponentMenu() {
		return new Menu($this->translator);
	}

}
