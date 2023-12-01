<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Models\Traits\InjectCollectionModel;
use App\Models\Traits\InjectStampsModel;
use App\Presenters\Traits\InjectTranslator;
use Nette\Application\UI\Presenter;

class NearbyPresenter extends Presenter
{

	use InjectTranslator;
	use InjectMenu;
	use InjectStampsModel;
	use InjectCollectionModel;
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
}
