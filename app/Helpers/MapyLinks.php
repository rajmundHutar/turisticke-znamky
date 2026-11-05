<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Links to Mapy.cz, see https://developer.mapy.com/en/further-uses-of-mapycz/url-mapy-cz/
 */
final class MapyLinks
{
	/**
	 * Route to the point from the current location; the mobile app starts the navigation.
	 */
	public static function route(float $lat, float $lng): string
	{
		return "https://mapy.cz/fnc/v1/route?end={$lng},{$lat}&navigate=true";
	}


	public static function search(string $query): string
	{
		return 'https://mapy.cz/fnc/v1/search?query=' . rawurlencode($query);
	}
}
