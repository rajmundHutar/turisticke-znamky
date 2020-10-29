<?php

namespace App\Forms;

use Nette\Application\UI\Form;

class EditStampFormFactory {

	public static function create(int $stampId, array $defaults, callable $onSuccess, callable $onDelete) {

		$f = new Form;
		$f->addText('date', 'Datum');
		$f->addTextArea('comment', 'Komentář');
		$f->addHidden('id', $stampId);

		$f->addSubmit('ok', 'Uložit');
		$f->addSubmit('delete', 'Odebrat');

		$f->onSuccess[] = function(Form $form, array $values) use ($onSuccess, $onDelete) {
			if ($form['delete']->isSubmittedBy()) {
				$onDelete((int) $values['id']);
			} else {
				$onSuccess($form, $values);
			}
		};

		$f->setDefaults($defaults);

		return $f;

	}

}
