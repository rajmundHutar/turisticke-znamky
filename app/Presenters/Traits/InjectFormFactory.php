<?php

declare(strict_types=1);

namespace App\Presenters\Traits;

use App\Forms\FormFactory;

trait InjectFormFactory {

	protected FormFactory $formFactory;

	public function injectFormFactory(FormFactory $formFactory): void {
		$this->formFactory = $formFactory;
	}

}
