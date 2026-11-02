<?php

/**
 * Test: StampFeedReader parses the turisticke-znamky.cz XML feed into normalized CZ stamps
 */

declare(strict_types=1);

use App\Models\StampFeedReader;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$reader = new StampFeedReader(__DIR__ . '/fixtures/tourist-stamps.xml');
$stamps = iterator_to_array($reader->read(), false);

// Only CZ stamps with a number and a location are returned; non-CZ ones are ignored
Assert::same([1, 2], array_column($stamps, 'id'));

// CZ stamps without a number or a location are counted as skipped
Assert::same(2, $reader->getSkippedCount());

// Stamp with all fields; the unknown element is ignored
Assert::same([
	'id' => 1,
	'source_id' => 3535,
	'name' => 'Praděd',
	'lat' => '50.0832',
	'lng' => '17.2310',
	'description' => 'Nejvyšší hora Moravy & Slezska.',
	'detail_url' => 'https://turisticke-znamky.cz/items/3535',
	'website' => 'https://www.praded.cz',
	'wikipedia' => 'https://cs.wikipedia.org/wiki/Prad%C4%9Bd',
	'facebook' => 'https://www.facebook.com/praded',
	'image_url' => 'https://turisticke-znamky.cz/storage/item_images/medium/praded.png',
	'salespoints' => [
		[
			'source_id' => 789,
			'name' => 'Informační centrum',
			'lat' => '50.0800',
			'lng' => '17.2300',
			'website' => 'https://ic.example.cz',
			'sort' => 1,
			'system' => false,
		],
		[
			'source_id' => 790,
			'name' => 'E-shop',
			'lat' => null,
			'lng' => null,
			'website' => null,
			'sort' => null,
			'system' => true,
		],
	],
], $stamps[0]);

// Stamp with only the required fields: optional values are null, no salespoints
Assert::same([
	'id' => 2,
	'source_id' => 4000,
	'name' => 'Minimal',
	'lat' => '49.1',
	'lng' => '16.2',
	'description' => null,
	'detail_url' => null,
	'website' => null,
	'wikipedia' => null,
	'facebook' => null,
	'image_url' => null,
	'salespoints' => [],
], $stamps[1]);


// Unsupported schema version
Assert::exception(
	fn() => iterator_to_array((new StampFeedReader(__DIR__ . '/fixtures/wrong-schema.xml'))->read()),
	RuntimeException::class,
	'%a%schema_version%a%',
);

// Unexpected root element (e.g. an HTML error page)
Assert::exception(
	fn() => iterator_to_array((new StampFeedReader(__DIR__ . '/fixtures/wrong-root.xml'))->read()),
	RuntimeException::class,
	'%a%root%a%',
);

// Malformed XML
Assert::exception(
	fn() => iterator_to_array((new StampFeedReader(__DIR__ . '/fixtures/invalid.xml'))->read()),
	RuntimeException::class,
	'Invalid XML%a%',
);

// Missing file
Assert::exception(
	fn() => iterator_to_array((new StampFeedReader(__DIR__ . '/fixtures/missing.xml'))->read()),
	RuntimeException::class,
	'%a%missing.xml%a%',
);
