<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\EditStampForm;
use App\Models\Traits\InjectCollectionModel;
use App\Models\Traits\InjectStampsModel;
use App\Presenters\Traits\InjectFormFactory;
use App\Presenters\Traits\InjectMenu;
use App\Presenters\Traits\InjectTranslator;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;

class StampPresenter extends Presenter
{

	use InjectTranslator;
	use InjectMenu;
	use InjectFormFactory;
	use InjectStampsModel;
	use InjectCollectionModel;

	const NearbyStamps = 20;

	protected ?int $stampId = null;
	protected ?ActiveRow $stamp;
	protected array $userCollection = [];

	public function actionDefault(?string $id = null): void
	{
		$this->stampId = (int)$id;
	}

	public function handleToggleCollect(int $id)
	{
		if ($this->user->isLoggedIn()) {
			$this->collectionModel->toggleCollect($id, $this->user->getId());
			$this->redirect("this");
		}
	}

	public function renderDefault(): void
	{

		$this->stamp = $this->stampsModel->fetch($this->stampId);
		$this->template->stamp = $this->stamp;
		$this->template->closest = $this->stampsModel->fetchClosest(
			(float)$this->stamp['lat'],
			(float)$this->stamp['lng'],
			self::NearbyStamps,
			[$this->stampId]
		);
		$this->userCollection = $this->collectionModel->fetchByUser($this->user->getId());
		$this->template->collection = $this->userCollection;

	}

	public function createComponentEditSingleStampForm()
	{
		$defaults = [];
		if ($this->userCollection[$this->stampId] ?? null) {
			$defaults['date'] = $this->userCollection[$this->stampId]->date;
			$defaults['comment'] = $this->userCollection[$this->stampId]->comment;
		}
		return $this->formFactory->create(
			EditStampForm::class,
			(int)$this->stampId,
			$defaults,
			function (
				Form $form,
				array $values
			) {

				$stampId = $values['id'];
				unset($values['id']);
				$this->collectionModel->update(
					(int)$stampId,
					$this->user->getId(),
					$values
				);

			});

	}

	public function createComponentEditStampForm()
	{
		return new Multiplier(function (string $stampId) {

			$defaults = [];
			if ($this->userCollection[$stampId] ?? null) {
				$defaults['date'] = $this->userCollection[$stampId]->date;
				$defaults['comment'] = $this->userCollection[$stampId]->comment;
			}

			return $this->formFactory->create(
				EditStampForm::class,
				(int)$stampId,
				$defaults,
				function (
					Nette\Application\UI\Form $form,
					array $values
				) {

					$stampId = $values['id'];
					unset($values['id']);
					$this->collectionModel->update(
						(int)$stampId,
						$this->user->getId(),
						$values
					);

				});

		});
	}

}
