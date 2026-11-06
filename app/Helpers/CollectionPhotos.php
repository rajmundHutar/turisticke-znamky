<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Private trip photos of the collected stamps (#19), stored outside www/ (data/photos/, denied by data/.htaccess)
 * and sent only to their owner by PhotoPresenter. The DB column tz_collection.file holds the random basename,
 * which also versions the photo URL.
 */
final class CollectionPhotos
{
	/** the long edge of a stored photo; the browser resizes to it before uploading */
	public const MaxEdge = 1600;

	private const Quality = 85;

	/** memory of a decoded truecolor GD image, with a margin */
	private const BytesPerPixel = 5;

	private const NamePattern = '~^[a-f0-9]{32}\.jpg$~D';

	private string $dir;


	/**
	 * @param  int  $maxBytes  larger uploads are refused
	 * @param  int  $maxPixels  larger images are refused before decoding, so they can't exhaust the memory limit;
	 *                          the browser uploads at most MaxEdge × MaxEdge
	 */
	public function __construct(
		string $dir,
		private int $maxBytes = 15_000_000,
		private int $maxPixels = 8_000_000,
	) {
		$this->dir = rtrim($dir, '/');
	}


	/**
	 * Re-encodes the uploaded JPEG, PNG or WebP image as a JPEG of at most MaxEdge px on the long edge (without any metadata,
	 * so no GPS position survives) and stores it under a new random name.
	 * @return string  the basename of the stored photo
	 * @throws \RuntimeException when the file is not a valid image of an accepted type, is too large or cannot be written
	 */
	public function store(string $uploadedPath): string
	{
		$bytes = @filesize($uploadedPath); // @ - the error is thrown below
		if ($bytes === false || $bytes === 0) {
			throw new \RuntimeException('Cannot read the photo.');
		} elseif ($bytes > $this->maxBytes) {
			throw new \RuntimeException("The photo is too large ({$bytes} B).");
		}

		$size = @getimagesize($uploadedPath);
		$decode = match ($size[2] ?? null) {
			IMAGETYPE_JPEG => imagecreatefromjpeg(...),
			IMAGETYPE_PNG => imagecreatefrompng(...),
			IMAGETYPE_WEBP => imagecreatefromwebp(...),
			default => throw new \RuntimeException('The photo is not a JPEG, PNG or WebP image.'),
		};
		if ($size[0] * $size[1] > $this->maxPixels) {
			throw new \RuntimeException("The photo is too large ({$size[0]} × {$size[1]} px).");
		} elseif ($size[0] * $size[1] * self::BytesPerPixel > self::freeMemory()) {
			throw new \RuntimeException("Not enough memory to decode the photo ({$size[0]} × {$size[1]} px).");
		}

		$image = @$decode($uploadedPath);
		if ($image === false) {
			throw new \RuntimeException('Cannot decode the photo.');
		}

		$width = imagesx($image);
		$height = imagesy($image);
		$scale = min(1, self::MaxEdge / max($width, $height));
		$photoWidth = max(1, (int) round($width * $scale));
		$photoHeight = max(1, (int) round($height * $scale));

		$photo = imagecreatetruecolor($photoWidth, $photoHeight);
		imagefill($photo, 0, 0, (int) imagecolorallocate($photo, 255, 255, 255)); // transparency becomes white in JPEG
		imagecopyresampled($photo, $image, 0, 0, 0, 0, $photoWidth, $photoHeight, $width, $height);

		// written to a temporary file and renamed, so a concurrent request never sees a half-written photo
		if (!is_dir($this->dir) && !@mkdir($this->dir, 0o755, true) && !is_dir($this->dir)) {
			throw new \RuntimeException('Cannot create the photo directory.');
		}
		$name = bin2hex(random_bytes(16)) . '.jpg';
		$target = "{$this->dir}/{$name}";
		$temp = "{$target}.tmp";
		if (!@imagejpeg($photo, $temp, self::Quality) || !@rename($temp, $target)) {
			@unlink($temp);
			throw new \RuntimeException('Cannot write the photo.');
		}
		return $name;
	}


	/** The memory left under memory_limit in bytes, PHP_INT_MAX when unlimited. */
	private static function freeMemory(): int
	{
		$limit = trim((string) ini_get('memory_limit'));
		if ($limit === '' || (int) $limit <= 0) {
			return PHP_INT_MAX;
		}
		$bytes = (int) $limit * match (strtoupper(substr($limit, -1))) {
			'G' => 1024 ** 3,
			'M' => 1024 ** 2,
			'K' => 1024,
			default => 1,
		};
		return $bytes - memory_get_usage(true); // the limit is enforced on the memory allocated from the system
	}


	/** Path of the stored photo, or null when the name is invalid or the file missing. */
	public function path(string $name): ?string
	{
		$path = "{$this->dir}/{$name}";
		return preg_match(self::NamePattern, $name) && is_file($path) ? $path : null;
	}


	/** Deletes the stored photo; an empty, invalid or missing name is ignored. */
	public function delete(?string $name): void
	{
		$path = $name === null ? null : $this->path($name);
		if ($path !== null) {
			@unlink($path); // @ - a file deleted meanwhile is fine
		}
	}
}
