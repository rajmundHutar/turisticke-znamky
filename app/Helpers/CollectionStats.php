<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Dto\CollectedStampDto;

/**
 * Pure statistics over a user's collected stamps.
 */
final class CollectionStats
{
	/**
	 * Longest run of consecutive stamp ids (n, n+1, ...); on a tie the first (lowest) run wins.
	 * @param  CollectedStampDto[]  $items
	 * @return list<CollectedStampDto>
	 */
	public static function mostConsecutive(array $items): array
	{
		usort($items, fn(CollectedStampDto $a, CollectedStampDto $b) => $a->id <=> $b->id);

		$currentSet = [];
		$biggestSet = [];
		foreach ($items as $item) {
			$last = $currentSet[count($currentSet) - 1] ?? null;
			if ($last !== null && $item->id === $last->id) {
				continue; // duplicate id
			}
			if ($last !== null && $item->id !== $last->id + 1) {
				$currentSet = [];
			}
			$currentSet[] = $item;
			if (count($currentSet) > count($biggestSet)) {
				$biggestSet = $currentSet;
			}
		}

		return $biggestSet;
	}


	/** Percentage of collected stamps rounded to 2 decimals; 0 when there are no stamps. */
	public static function percentage(int $collected, int $total): float
	{
		return $total === 0 ? 0.0 : round($collected / $total * 100, 2);
	}
}
