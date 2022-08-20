<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;
use Nette\Localization\Translator;

class FormFactory {

	protected Translator $translator;
	protected array $forms;

	public function __construct(Translator $translator) {
		$this->translator = $translator;
	}

	public function register(BaseForm $form) {
		$this->forms[get_class($form)] = $form;
	}

	public function create(string $formClass, ...$args): Form {
		$form = $this->forms[$formClass] ?? null;
		if (!$form) {
			throw new \UnexpectedValueException("Not registered form {$formClass}");
		}

		$newForm = new $formClass($this->translator);
		return $newForm->create(...$args);
	}

}
