<?php

declare(strict_types=1);

namespace App\Presenters\Traits;

use App\Forms\EditStampForm;
use App\Helpers\CollectionPhotos;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;

/**
 * One collect form (POST) for adding, editing and removing a stamp, shared by the presenters listing stamps.
 * Requires InjectCollectionModel, InjectStampsModel and InjectTranslator; the action loads
 * $userCollection, so the form knows whether the stamp is collected while processing a submit.
 */
trait CollectStamps
{
	use RedirectToLogin;

	protected array $userCollection = [];

	protected EditStampForm $editStampForm;

	protected CollectionPhotos $collectionPhotos;


	public function injectEditStampForm(EditStampForm $editStampForm): void
	{
		$this->editStampForm = $editStampForm;
	}


	public function injectCollectionPhotos(CollectionPhotos $collectionPhotos): void
	{
		$this->collectionPhotos = $collectionPhotos;
	}

	/**
	 * Fields: date (Czech d. m. yyyy, today by default), comment, hidden id of the stamp;
	 * buttons: ok saves (adds or updates), remove removes the stamp from the collection.
	 */
	public function createComponentCollectForm(): Form
	{
		$form = $this->editStampForm->create(
			null,
			['date' => new DateTime('today')],
			function (Form $form, array $values): void {
				if (!$this->user->isLoggedIn()) {
					$this->redirectToLogin();
				}
				$stampId = (int) $values['id'];
				$remove = $form->isSubmitted() === $form['remove'];
				$userId = $this->user->getId();

				if ($remove) {
					$photo = $this->collectionModel->fetchFile($stampId, $userId);
					$this->collectionModel->remove($stampId, $userId);
					$this->collectionPhotos->delete($photo); // after the row, a failure leaves an orphan file, not a broken reference
					$flash = 'stamp.flash.removed';
				} elseif (isset($this->userCollection[$stampId]) || $this->collectionModel->isCollected($stampId, $userId)) {
					// checked again right before writing: a double submit must not insert a duplicate row
					$this->collectionModel->update($stampId, $userId, ['date' => $values['date'], 'comment' => $values['comment']]);
					$flash = 'stamp.flash.saved';
				} else {
					if (!$this->stampsModel->fetch($stampId)) {
						$this->error();
					}
					$this->collectionModel->add($stampId, $userId, $values['date'], $values['comment']);
					$flash = 'stamp.flash.added';
				}
				$this->flashMessage($this->t($flash), \Flash::SUCCESS);

				if (!$this->isAjax()) {
					$this->redirectAfterCollectionChange($stampId);
				}
				$this->userCollection = $this->collectionModel->fetchByUser($userId); // rendered in this request
				$this->payload->stampId = $stampId;
				$this->payload->collected = !$remove;
				$this->redrawControl('flashes');
				$this->redrawAfterCollectionChange($stampId);
			}
		);
		$form->onError[] = function (): void {
			if ($this->isAjax()) {
				$this->redrawControl('collectSheet');
			}
		};
		return $form;
	}

	/**
	 * Stores the uploaded trip photo of the collected stamp, replacing the current one (#19): the new file is written first,
	 * then the DB points to it, then the old file is deleted; a failed DB write deletes the new file.
	 * @throws \RuntimeException when the upload is not a valid image (CollectionPhotos::store())
	 */
	protected function replaceCollectionPhoto(int $stampId, int $userId, string $uploadedPath): void
	{
		$old = $this->collectionModel->fetchFile($stampId, $userId);
		$new = $this->collectionPhotos->store($uploadedPath);
		try {
			$this->collectionModel->setFile($stampId, $userId, $new);
		} catch (\Throwable $e) {
			$this->collectionPhotos->delete($new);
			throw $e;
		}
		$this->collectionPhotos->delete($old);
	}

	/**
	 * Deletes the trip photo of the collected stamp (#19): the DB first, then the file.
	 */
	protected function deleteCollectionPhoto(int $stampId, int $userId): void
	{
		$old = $this->collectionModel->fetchFile($stampId, $userId);
		if ($old !== null) {
			$this->collectionModel->setFile($stampId, $userId, null);
			$this->collectionPhotos->delete($old);
		}
	}

	/**
	 * Redirects back after a change of the collection; to the stamp's anchor by default.
	 */
	protected function redirectAfterCollectionChange(int $id): void
	{
		$this->redirect("this#stamp-{$id}");
	}

	/**
	 * Redraws the changed stamp after an AJAX change of the collection: its dynamic snippet in the 'stamps' area.
	 */
	protected function redrawAfterCollectionChange(int $id): void
	{
		$this->redrawControl('stamps');
		$this->redrawControl("stamp-{$id}");
	}

}
