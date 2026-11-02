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
use Nette\Database\Table\ActiveRow;

class StampPresenter extends Presenter
{

	use InjectTranslator;
	use InjectMenu;
	use InjectFormFactory;
	use InjectStampsModel;
	use InjectCollectionModel;
	use CollectStamps;

	public const NearbyStamps = 20;

	protected ?int $stampId = null;
	protected ?ActiveRow $stamp;

	public function actionDefault(?string $id = null): void
	{
		$this->stampId = (int) $id;
		$this->stamp = $this->stampsModel->fetch($this->stampId);
		if (!$this->stamp) {
			$this->error();
		}
		$this->userCollection = $this->collectionModel->fetchByUser($this->user->getId());
	}

	protected function redirectAfterToggleCollect(int $id): void
	{
		$this->redirect('this');
	}

	public function renderDefault(): void
	{

		$this->template->stamp = $this->stamp;
		$this->template->closest = $this->stampsModel->fetchClosest(
			(float) $this->stamp['lat'],
			(float) $this->stamp['lng'],
			self::NearbyStamps,
			[$this->stampId]
		);
		$this->template->salespoints = $this->stampsModel->fetchSalespoints($this->stampId);
		$this->template->collection = $this->userCollection;
	}

	public function createComponentEditSingleStampForm()
	{
		return $this->createEditStampForm((int) $this->stampId, 'this');
	}

}
