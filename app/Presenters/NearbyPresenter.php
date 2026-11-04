<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Models\Traits\InjectCollectionModel;
use App\Models\Traits\InjectStampsModel;
use App\Presenters\Traits\CollectStamps;
use App\Presenters\Traits\InjectMenu;
use App\Presenters\Traits\InjectTranslator;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Presenter;

class NearbyPresenter extends Presenter
{

	use InjectStampsModel;
	use InjectCollectionModel;
	use InjectTranslator;
	use InjectMenu;
	use CollectStamps;

	public const Filters = ['all', 'collected', 'missing'];

	/** The switch Vše / Mám / Nemám of a logged-in user, "Nemám" by default (#51) */
	#[Persistent]
	public string $filter = 'missing';

	protected ?string $lat = null;
	protected ?string $lng = null;

	public function actionDefault(?string $lat = null, ?string $lng = null)
	{
		if ($lat && $lng) {
			$this->lat = $lat;
			$this->lng = $lng;
		}
		if ($this->user->isLoggedIn()) {
			$this->userCollection = $this->collectionModel->fetchByUser($this->user->getId());
		}
	}

	public function renderDefault(): void
	{
		$filter = $this->user->isLoggedIn() && in_array($this->filter, self::Filters, true) ? $this->filter : 'missing';
		$collectedIds = array_keys($this->userCollection);

		// in render, after a collect form signal changed the collection
		$stamps = [];
		if ($this->lat !== null && $this->lng !== null) {
			$stamps = $this->stampsModel->fetchClosest(
				(float) $this->lat,
				(float) $this->lng,
				20,
				$this->user->isLoggedIn() && $filter === 'missing' ? $collectedIds : null,
				$this->user->isLoggedIn() && $filter === 'collected' ? $collectedIds : null,
			);
		}

		$this->template->stamps = $stamps;
		$this->template->collection = $this->userCollection;
		$this->template->located = $this->lat !== null && $this->lng !== null;
		$this->template->activeFilter = $filter;

		// the whole 'stamps' snippet on every AJAX request (also after a collect form submit, not just the changed stamp):
		// the collect sheet lives inside it, so its form action gets lat/lng once the location is known
		if ($this->isAjax()) {
			$this->redrawControl('stamps');
		}
	}
}
