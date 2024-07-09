<?php

namespace App\Presenters;

use App\Dto\CollectedStampDto;
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

	public function renderDefault(): void
	{
		$collection = $this->collectionModel->fetchByUser($this->user->getId());
		$this->template->collectedCount = count($collection);
		$this->template->totalCount = $this->stampsModel->count();
		$this->template->thisYearCollected = count(array_filter($collection, fn (CollectedStampDto $stamp) => $stamp->date?->format('Y') === date('Y')));
		$this->template->biggestSetWithMaxGap = $this->findBiggestSetWithMaxGap($collection);
		$this->template->mostConsecutiveItems = $this->findMostConsecutiveItems($collection);
	}

	function findBiggestSetWithMaxGap($items, $maxGap = 10) {
		// Sort the items by their IDs
		usort($items, function($a, $b) {
			return $a->id <=> $b->id;
		});

		$currentSet = [];
		$biggestSet = [];

		foreach ($items as $item) {
			// Check if adding the item would break the max gap within the set
			if (empty($currentSet) || $item->id - $currentSet[0]->id <= $maxGap) {
				$currentSet[] = $item;
			} else {
				// If yes, compare the current set size with the biggest one found so far
				if (count($currentSet) > count($biggestSet)) {
					$biggestSet = $currentSet;
				}
				$currentSet = [$item]; // Start a new set with the current item
			}
		}

		// Check the last set after iterating through all items
		if (count($currentSet) > count($biggestSet) && $currentSet[count($currentSet) - 1]->id - $currentSet[0]->id <= $maxGap) {
			$biggestSet = $currentSet;
		}

		return $biggestSet;
	}

	function findMostConsecutiveItems($items) {
		// Sort the items by their IDs
		usort($items, function($a, $b) {
			return $a->id <=> $b->id;
		});

		$currentSet = [];
		$biggestSet = [];
		$currentStreak = 1; // Track the current consecutive streak

		foreach ($items as $item) {
			// Check if the current item's ID is consecutive to the last item
			if (empty($currentSet) || $item->id === $currentSet[count($currentSet) - 1]->id + 1) {
				$currentSet[] = $item;
				$currentStreak++; // Increase streak if consecutive
			} else {
				// If not consecutive, compare the current set size (streak) with the biggest one
				if ($currentStreak > count($biggestSet)) {
					$biggestSet = $currentSet;
				}
				$currentSet = [$item]; // Start a new set with the current item
				$currentStreak = 1; // Reset streak
			}
		}

		// Check the last set after iterating through all items
		if ($currentStreak > count($biggestSet)) {
			$biggestSet = $currentSet;
		}

		return $biggestSet;
	}
}
