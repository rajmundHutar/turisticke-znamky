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

class NearbyPresenter extends Presenter
{

	use InjectStampsModel;
	use InjectCollectionModel;
	use InjectTranslator;
	use InjectMenu;
	use InjectFormFactory;
	protected array $closest = [];

	public function actionDefault(?string $lat = null, ?string $lng = null)
	{
		if ($lat && $lng) {
			$this->closest = $this->stampsModel->fetchClosest((float) $lat, (float) $lng, 20);
		}
	}

	public function handleToggleCollect(int $id)
	{
		if ($this->user->isLoggedIn()) {
			$this->collectionModel->toggleCollect($id, $this->user->getId());
			$this->redirect("this#stamp-{$id}", $this->getFilter());
		}
	}

	public function renderDefault(): void
	{
		$this->template->stamps = $this->closest;
		$this->template->collection = $this->collectionModel->fetchByUser($this->user->getId());

		if ($this->isAjax()) {
			$this->redrawControl('stamps');
		}
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

		});
	}
}
