<?php

/**
 * Test: CollectionStats algorithms (#9)
 */

declare(strict_types=1);

use App\Dto\CollectedStampDto;
use App\Helpers\CollectionStats;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function stamp(int $id): CollectedStampDto
{
	return new CollectedStampDto($id, "Stamp $id", null, null, null, '0', '0', null, null, null, null);
}


/**
 * @param  list<int>  $ids
 * @return list<CollectedStampDto>
 */
function stamps(array $ids): array
{
	return array_map(stamp(...), $ids);
}


/**
 * @param  list<CollectedStampDto>  $items
 * @return list<int>
 */
function ids(array $items): array
{
	return array_map(fn(CollectedStampDto $item) => $item->id, $items);
}


// Most consecutive: the longest run of n, n+1, ...
Assert::same([10, 11, 12, 13], ids(CollectionStats::mostConsecutive(stamps([1, 2, 3, 10, 11, 12, 13, 20, 21]))));

// Most consecutive: on a tie the first (lowest) run wins
Assert::same([1, 2], ids(CollectionStats::mostConsecutive(stamps([1, 2, 5, 6]))));
Assert::same([3, 4, 5], ids(CollectionStats::mostConsecutive(stamps([1, 3, 4, 5, 7, 8, 9]))));

// Most consecutive: empty input and a single item
Assert::same([], CollectionStats::mostConsecutive([]));
Assert::same([7], ids(CollectionStats::mostConsecutive(stamps([7]))));

// Most consecutive: duplicate ids do not break the run
Assert::same([1, 2, 3], ids(CollectionStats::mostConsecutive(stamps([1, 2, 2, 3, 10, 11]))));

// Most consecutive: input does not have to be sorted
Assert::same([4, 5, 6], ids(CollectionStats::mostConsecutive(stamps([6, 1, 5, 9, 4]))));


// Lowest / highest stamp number
Assert::null(CollectionStats::lowest([]));
Assert::null(CollectionStats::highest([]));
Assert::same(3, CollectionStats::lowest(stamps([42, 3, 17]))?->id);
Assert::same(42, CollectionStats::highest(stamps([42, 3, 17]))?->id);
Assert::same(5, CollectionStats::lowest(stamps([5]))?->id);
Assert::same(5, CollectionStats::highest(stamps([5]))?->id);


// Percentage of collected stamps; no stamps at all is 0 %, not a division by zero
Assert::same(0.0, CollectionStats::percentage(0, 0));
Assert::same(25.0, CollectionStats::percentage(1, 4));
Assert::same(33.33, CollectionStats::percentage(1, 3));
