<?php

namespace App\Presenters;

use App\Dto\CollectedStampDto;
use App\Helpers\CollectionStats;
use App\Models\Traits\InjectCollectionModel;
use App\Models\Traits\InjectStampsModel;
use App\Presenters\Traits\InjectMenu;
use App\Presenters\Traits\InjectTranslator;
use App\Templates\CollectionStats\CollectionStatsDefaultTemplateParameters;
use Nette\Application\UI\Presenter;

/**
 * @property-read CollectionStatsDefaultTemplateParameters $template
 */
class CollectionStatsPresenter extends Presenter
{
	use InjectMenu;
	use InjectTranslator;
	use InjectCollectionModel;
	use InjectStampsModel;

	protected function startup(): void
	{
		parent::startup();
		if (!$this->user->isLoggedIn()) {
			$this->redirect('Login:');
		}
	}

	public function renderDefault(): void
	{
		$collection = $this->collectionModel->fetchByUser($this->user->getId());
		$this->template->collectedCount = count($collection);
		$this->template->totalCount = $this->stampsModel->count();
		$this->template->thisYearCollected = count(array_filter($collection, fn(CollectedStampDto $stamp) => $stamp->date?->format('Y') === date('Y')));
		$this->template->percentage = CollectionStats::percentage($this->template->collectedCount, $this->template->totalCount);
		$this->template->mostConsecutiveItems = CollectionStats::mostConsecutive($collection);
		$this->template->lowestStamp = CollectionStats::lowest($collection);
		$this->template->highestStamp = CollectionStats::highest($collection);
	}
}
