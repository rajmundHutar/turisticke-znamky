<?php

namespace App\Forms;

use Nette\Application\UI\Form;
use Nette\Localization\Translator;

final class FilterForm
{

	public function __construct(
		private Translator $translator,
	) {
	}


	/**
	 * Returns a new form on each call.
	 */
	public function create(array $sortBy): Form
	{
		$form = new Form;
		$form->setTranslator($this->translator);

		$form->setMethod('GET');
		$form->addText('search', 'form.filter.search')
			->getControlPrototype()
			->placeholder('form.filter.search');
		$form->addSelect('sort', 'form.filter.sortBy', $sortBy);
		$form->addSubmit('ok', 'form.filter.submit');
		$form->onSuccess[] = function() {
			// Empty, will be handled in action method
		};

		return $form;
	}

}
