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

// lngScale shrinks longitude degrees towards the poles
Assert::same(1.0, GPS::lngScale(0.0));
Assert::true(abs(GPS::lngScale(50.0) - 0.6428) < 0.0001);

// Nearest-stamp ordering (StampsModel::fetchClosest): at 50° N a stamp 10 km east
// must rank closer than a stamp 12 km north, as their real distances say.
$lat = 50.0;
$lng = 15.0;
$east = [$lat, $lng + 0.1399]; // ~10 km
$north = [$lat + 0.1079, $lng]; // ~12 km
Assert::true(abs(GPS::gpsDistance($lat, $lng, ...$east) - 10_000) < 100);
Assert::true(abs(GPS::gpsDistance($lat, $lng, ...$north) - 12_000) < 100);
$approx = fn(array $p) => sqrt(($lat - $p[0]) ** 2 + (($lng - $p[1]) * GPS::lngScale($lat)) ** 2);
Assert::true($approx($east) < $approx($north));
