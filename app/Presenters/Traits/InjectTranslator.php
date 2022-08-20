<?php

declare(strict_types=1);

namespace App\Presenters\Traits;

use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;

trait InjectTranslator {

	protected Translator $translator;

	public function __construct(Translator $translator) {
		$this->translator = $translator;
		$this->onStartup[] = function(Presenter $presenter) {
			$presenter->template->setTranslator($this->translator);
		};
	}

	/**
	 * Translator shortcut
	 */
	protected function t($message, ...$args): string {
		return $this->translator->translate($message, ...$args);
	}

}
