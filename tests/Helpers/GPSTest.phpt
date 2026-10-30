<?php

declare(strict_types=1);

use App\Helpers\GPS;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// Same point
Assert::same(0.0, GPS::gpsDistance(50.0, 14.0, 50.0, 14.0));

// Prague (Old Town Square) -> Brno (Freedom Square), ~186 km
$distance = GPS::gpsDistance(50.0875, 14.4213, 49.1951, 16.6079);
Assert::true($distance > 185_000 && $distance < 187_000);

// Symmetric
Assert::same(
	GPS::gpsDistance(50.0875, 14.4213, 49.1951, 16.6079),
	GPS::gpsDistance(49.1951, 16.6079, 50.0875, 14.4213),
);

Assert::same('< 100 m', GPS::prettyDistance(0));
Assert::same('< 100 m', GPS::prettyDistance(99.9));
Assert::same('100 m', GPS::prettyDistance(100));
Assert::same('900 m', GPS::prettyDistance(999));
Assert::same('1 km', GPS::prettyDistance(1000));
Assert::same('12 km', GPS::prettyDistance(12_999));
