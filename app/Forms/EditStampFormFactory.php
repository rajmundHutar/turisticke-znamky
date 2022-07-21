<?php

namespace App\Forms;

use Nette\Application\UI\Form;

class EditStampFormFactory {

	public static function create(
		int $stampId,
		array $defaults,
		callable $onSuccess
	) {

		$f = new Form;
		$f->addText('date', 'Datum');
		$f->addTextArea('comment', 'Komentář');
		$f->addHidden('id', $stampId);

		$f->addSubmit('ok', 'Uložit');

		$f->onSuccess[] = $onSuccess;

		$f->setDefaults($defaults);

		return $f;

	}

}
