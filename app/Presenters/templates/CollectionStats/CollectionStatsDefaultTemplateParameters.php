<?php

declare(strict_types=1);

namespace App\Templates\CollectionStats;

use Nette\Bridges\ApplicationLatte\Template;

class CollectionStatsDefaultTemplateParameters extends Template
{
	public int $collectedCount;
	public int $totalCount;
	public float $percentage;
	public int $thisYearCollected;
	/** @var list<\App\Dto\CollectedStampDto> */
	public array $mostConsecutiveItems;
	public ?\App\Dto\CollectedStampDto $lowestStamp;
	public ?\App\Dto\CollectedStampDto $highestStamp;
}
