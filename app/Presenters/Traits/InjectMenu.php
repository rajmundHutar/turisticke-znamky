<?php

namespace App\Presenters\Traits;

use App\Controls\Menu;

trait InjectMenu {

	public function createComponentMenu() {
		return new Menu($this->translator);
	}

}
