<?php

declare(strict_types=1);

namespace App\Controls;

use Nette\Application\UI\Control;

class Menu extends Control {

	public function render() {

		$this->template->setFile(__DIR__ . '/Menu.latte');
		$this->template->render();

	}

}