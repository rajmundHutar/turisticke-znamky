<?php

declare(strict_types=1);

namespace App\Templates\CollectionStats;

use Nette\Bridges\ApplicationLatte\Template;

class CollectionStatsDefaultTemplateParameters extends Template
{
	public int $collectedCount;
	public int $totalCount;
	public int $thisYearCollected;
	public array $biggestSetWithMaxGap;
	public array $mostConsecutiveItems;
}
