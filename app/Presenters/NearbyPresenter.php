<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Models\Traits\InjectCollectionModel;
use App\Models\Traits\InjectStampsModel;
use App\Presenters\Traits\CollectStamps;
use App\Presenters\Traits\InjectFormFactory;
use App\Presenters\Traits\InjectMenu;
use App\Presenters\Traits\InjectTranslator;
use Nette\Application\UI\Presenter;

class NearbyPresenter extends Presenter
{

	use InjectStampsModel;
	use InjectCollectionModel;
	use InjectTranslator;
	use InjectMenu;
	use InjectFormFactory;
	use CollectStamps;
	protected array $closest = [];
	protected ?string $lat = null;
	protected ?string $lng = null;

	public function actionDefault(?string $lat = null, ?string $lng = null)
	{
		if ($lat && $lng) {
			$this->closest = $this->stampsModel->fetchClosest((float) $lat, (float) $lng, 20);
			$this->lat = $lat;
			$this->lng = $lng;
		}
		if ($this->user->isLoggedIn()) {
			$this->userCollection = $this->collectionModel->fetchByUser($this->user->getId());
		}
	}

	public function renderDefault(): void
	{
		$this->template->stamps = $this->closest;
		$this->template->collection = $this->userCollection;
		$this->template->lat = $this->lat;
		$this->template->lng = $this->lng;

		if ($this->isAjax()) {
			$this->redrawControl('stamps');
		}
	}
}
