<?php

declare(strict_types=1);

namespace App\Helpers;

class GPS
{

	public static function gpsDistance(float $lat1, float $lon1, float $lat2, float $lon2)
	{

		// See https://www.movable-type.co.uk/scripts/latlong.html
		$R = 6371000; // metres
		$sig1 = $lat1 * M_PI / 180; // sig, λ in radians
		$sig2 = $lat2 * M_PI / 180;
		$deltaSig = ($lat2 - $lat1) * M_PI / 180;
		$deltaLambda = ($lon2 - $lon1) * M_PI / 180;

		$a = sin($deltaSig / 2) * sin($deltaSig / 2) +
			cos($sig1) * cos($sig2) *
			sin($deltaLambda / 2) * sin($deltaLambda / 2);
		$c = 2 * atan2(sqrt($a), sqrt(1 - $a));

		return $R * $c; // in metres
	}

	/**
	 * Length of one degree of longitude relative to one degree of latitude at the given latitude.
	 */
	public static function lngScale(float $lat): float
	{

		return cos(deg2rad($lat));
	}

	/**
	 * Czech distance from metres: "350 m" below 1 km, "2,1 km" below 10 km, "15 km" above.
	 */
	public static function prettyDistance(float $distance): string
	{
		$metres = (int) round($distance, -1);
		if ($metres < 1000) {
			return "{$metres} m";
		}

		$kilometres = round($distance / 1000, 1);
		if ($kilometres < 10) {
			return number_format($kilometres, 1, ',', '') . ' km';
		}

		return round($distance / 1000) . ' km';
	}

}
