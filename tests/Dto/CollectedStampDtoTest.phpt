<?php

/**
 * Test: CollectedStampDto accepts stamps without image, type, region and creation date (#4, #14)
 */

declare(strict_types=1);

use App\Dto\CollectedStampDto;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// ActiveRow cannot be created without a database; fromActiveRow() only needs array access to the joined row
$row = [
	'id' => 3100,
	'name' => 'New stamp from the feed',
	'type' => null,
	'region' => null,
	'created_at' => null,
	'lat' => '50.1',
	'lng' => '14.2',
	'image' => null,
	'date' => null,
	'comment' => null,
	'file' => null,
];

$dto = CollectedStampDto::fromActiveRow(new ArrayObject($row));
Assert::same(3100, $dto->id);
Assert::null($dto->image);
Assert::null($dto->type);
Assert::null($dto->region);
Assert::null($dto->createdAt);

// Filled values are kept
$created = new Nette\Utils\DateTime('2020-05-01');
$dto = CollectedStampDto::fromActiveRow(new ArrayObject(['image' => '1-a.png', 'type' => 'Hrad', 'region' => 'Morava', 'created_at' => $created] + $row));
Assert::same('1-a.png', $dto->image);
Assert::same('Hrad', $dto->type);
Assert::same('Morava', $dto->region);
Assert::same($created, $dto->createdAt);
