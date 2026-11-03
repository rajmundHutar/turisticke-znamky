<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Mapy.cz API settings; the key lives in local.neon, outside git.
 */
final readonly class MapyConfig
{
	public function __construct(
		public readonly string $apiKey,
	) {
	}
}
