<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\EditStampForm;
use App\Models\Traits\InjectCollectionModel;
use App\Models\Traits\InjectStampsModel;
use App\Presenters\Traits\InjectFormFactory;
use App\Presenters\Traits\InjectTranslator;
use Nette\Application\UI\Multiplier;
use Nette\Application\UI\Presenter;

class DetailPresenter extends Presenter
{

	use InjectTranslator;
	use InjectMenu;
	use InjectFormFactory;
	use InjectStampsModel;
	use InjectCollectionModel;

	const NearbyStamps = 20;

	public function handleToggleCollect(int $id)
	{
		if ($this->user->isLoggedIn()) {
			$this->collectionModel->toggleCollect($id, $this->user->getId());
			$this->redirect("this#stamp-{$id}", $this->getFilter());
		}
	}
	public function renderDefault(string $id): void {

		$this->template->stamp = $stamp =  $this->stampsModel->fetch((int)$id);
		$this->template->closest = $this->stampsModel->fetchClosest(
			(float) $stamp['lat'],
			(float) $stamp['lng'],
			self::NearbyStamps,
			[$stamp['id']]
		);
		$this->template->collection = $this->collectionModel->fetchByUser($this->user->getId());;

	}

	public function createComponentEditStampForm()
	{
		return new Multiplier(function (string $stampId) {

			$defaults = [];
			if ($this->userCollection[$stampId] ?? null) {
				$defaults['date'] = $this->userCollection[$stampId]['date'];
				$defaults['comment'] = $this->userCollection[$stampId]['comment'];
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
