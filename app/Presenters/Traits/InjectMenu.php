<?php

namespace App\Presenters;

use App\Controls\Menu;

trait InjectMenu {

	public function createComponentMenu() {

		return new Menu();

	}

}