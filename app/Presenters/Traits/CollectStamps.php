<?php

declare(strict_types=1);

namespace App\Presenters\Traits;

use App\Forms\EditStampForm;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;

/**
 * Collect toggle and edit form of stamp cards, shared by the presenters listing stamps.
 * Requires InjectCollectionModel and InjectFormFactory; the action loads $userCollection,
 * so the forms have their defaults also while processing a submit.
 */
trait CollectStamps {

	protected array $userCollection = [];

	public function handleToggleCollect(int $id): void {
		if ($this->user->isLoggedIn()) {
			$this->collectionModel->toggleCollect($id, $this->user->getId());
			$this->redirectAfterToggleCollect($id);
		}
	}

	/**
	 * Redirects back after toggling a stamp; to the card's anchor by default.
	 */
	protected function redirectAfterToggleCollect(int $id): void {
		$this->redirect("this#stamp-{$id}");
	}

	public function createComponentEditStampForm(): Multiplier {
		return new Multiplier(
			fn(string $stampId) => $this->createEditStampForm((int) $stampId, "this#stamp-{$stampId}")
		);
	}

	/**
	 * Edit form of a collected stamp with its date and comment as defaults; redirects to $destination after saving.
	 */
	private function createEditStampForm(int $stampId, string $destination): Form {
		$defaults = [];
		if ($this->userCollection[$stampId] ?? null) {
			$defaults['date'] = $this->userCollection[$stampId]->date;
			$defaults['comment'] = $this->userCollection[$stampId]->comment;
		}

		return $this->formFactory->create(
			EditStampForm::class,
			$stampId,
			$defaults,
			function (Form $form, array $values) use ($destination) {
				$stampId = $values['id'];
				unset($values['id']);
				$this->collectionModel->update(
					(int) $stampId,
					$this->user->getId(),
					$values
				);
				$this->redirect($destination);
			}
		);
	}

}
