<?php

/**
 * Test: Mapy.cz links of the sales points, navigation to the coordinates and search by the name (#70)
 */

declare(strict_types=1);

use App\Helpers\MapyLinks;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// navigation to the coordinates, longitude first
Assert::same('https://mapy.cz/fnc/v1/route?end=15.9390975,49.5643003&navigate=true', MapyLinks::route(49.5643003, 15.9390975));

// search by the name, Czech diacritics, commas and spaces encoded
Assert::same(
	'https://mapy.cz/fnc/v1/search?query=Infocentrum%2C%20Havl%C3%AD%C4%8Dkovo%20n%C3%A1m.%20253%2F5%2C%20%C5%BD%C4%8F%C3%A1r%20nad%20S%C3%A1zavou',
	MapyLinks::search('Infocentrum, Havlíčkovo nám. 253/5, Žďár nad Sázavou'),
);
Assert::same('https://mapy.cz/fnc/v1/search?query=A%26B%3Dc', MapyLinks::search('A&B=c'));
