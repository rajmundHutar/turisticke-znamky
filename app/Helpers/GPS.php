<?php

declare(strict_types=1);


namespace App\Helpers;


class GPS {

	public static function gpsDistance(float $lat1, float $lon1, float $lat2, float $lon2) {

		// See https://www.movable-type.co.uk/scripts/latlong.html
		$R = 6371000; // metres
		$sig1 = $lat1 * M_PI / 180; // sig, λ in radians
		$sig2 = $lat2 * M_PI / 180;
		$deltaSig = ($lat2 - $lat1) * M_PI/180;
		$deltaLambda = ($lon2 - $lon1) * M_PI/180;

		$a = sin($deltaSig / 2) * sin($deltaSig / 2) +
			cos($sig1) * cos($sig2) *
			sin($deltaLambda / 2) * sin($deltaLambda / 2);
		$c = 2 * atan2(sqrt($a), sqrt(1 - $a));

		return $R * $c; // in metres

	}

	public static function prettyDistance(float $distance): string {

		if ($distance < 100) {
			return "< 100 m";
		}

		if ($distance < 1000) {
			return (floor($distance / 100) * 100) . " m";
		}

		return floor($distance / 1000) . " km";

	}

}
