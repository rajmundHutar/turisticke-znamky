<?php

declare(strict_types=1);

namespace App\Helpers;

use Latte;

/**
 * Small WebP thumbnails of the stamp images for the lists (#52).
 * A thumbnail is created on import, or on the first request of /thumb/<name> (ThumbnailPresenter),
 * and stored in thumbs/<name>.webp next to the images, so later pages load it as a static file.
 */
final class StampThumbnails extends Latte\Extension
{
	/** the box the image is scaled down into: twice the largest list row (176 × 100 CSS px), about the nearby card width */
	public const Width = 400;
	public const Height = 300;

	private const Quality = 80;

	/** an imported image name, see ImportModel::importImages() */
	private const NamePattern = '[a-z0-9-]+';

	private string $imagesDir;
	private bool $supported;


	/**
	 * @param  int  $maxPixels  larger images are refused before decoding, so they can't exhaust the memory limit
	 */
	public function __construct(
		string $imagesDir,
		?bool $supported = null,
		private int $maxPixels = 25_000_000,
	) {
		$this->imagesDir = rtrim($imagesDir, '/');
		$this->supported = $supported ?? (function_exists('imagewebp') && defined('IMG_WEBP') && (imagetypes() & IMG_WEBP));
	}


	/** Whether GD can write WebP. */
	public function isSupported(): bool
	{
		return $this->supported;
	}


	/** Path of the source image of the name (file name without extension), or null when it is invalid or missing. */
	public function getSourcePath(string $name): ?string
	{
		$path = "{$this->imagesDir}/{$name}.png";
		return preg_match('~^' . self::NamePattern . '$~D', $name) && is_file($path) ? $path : null;
	}


	public function getThumbnailPath(string $name): string
	{
		return "{$this->imagesDir}/thumbs/{$name}.webp";
	}


	/**
	 * URL of the thumbnail of the image file name, relative to the base URL: the static file when it exists,
	 * else the route creating it, or the original image when it cannot be created.
	 */
	public function getUrl(string $image): string
	{
		if (!preg_match('~^(' . self::NamePattern . ')\.png$~D', $image, $m)) {
			return "images/stamps/{$image}";
		} elseif (is_file($this->getThumbnailPath($m[1]))) {
			return "images/stamps/thumbs/{$m[1]}.webp";
		}
		return $this->supported && $this->getSourcePath($m[1]) !== null ? "thumb/{$m[1]}" : "images/stamps/{$image}";
	}


	/**
	 * Creates (or replaces) the thumbnail of the image and returns its path.
	 * @throws \InvalidArgumentException for an invalid name or a missing image
	 * @throws \RuntimeException when the image cannot be read or the thumbnail written
	 */
	public function create(string $name): string
	{
		$source = $this->getSourcePath($name);
		if ($source === null) {
			throw new \InvalidArgumentException("Unknown stamp image '{$name}'.");
		} elseif (!$this->supported) {
			throw new \RuntimeException('GD with WebP support is not available.');
		}

		$size = @getimagesize($source); // @ - the error is thrown below
		if ($size === false) {
			throw new \RuntimeException("Cannot read the stamp image {$source}.");
		} elseif ($size[0] * $size[1] > $this->maxPixels) {
			throw new \RuntimeException("The stamp image {$source} is too large ({$size[0]} × {$size[1]} px).");
		}

		$data = @file_get_contents($source);
		$image = $data === false || $data === '' ? false : @imagecreatefromstring($data);
		if ($image === false) {
			throw new \RuntimeException("Cannot read the stamp image {$source}.");
		}

		$width = imagesx($image);
		$height = imagesy($image);
		$scale = min(1, self::Width / $width, self::Height / $height);
		$thumbWidth = max(1, (int) round($width * $scale));
		$thumbHeight = max(1, (int) round($height * $scale));

		$thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
		imagealphablending($thumb, false);
		imagesavealpha($thumb, true);
		imagefill($thumb, 0, 0, (int) imagecolorallocatealpha($thumb, 0, 0, 0, 127));
		imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

		// written to a temporary file and renamed, so a concurrent request never sees a half-written thumbnail
		$target = $this->getThumbnailPath($name);
		$dir = dirname($target);
		if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
			throw new \RuntimeException("Cannot create the directory {$dir}.");
		}
		$temp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
		if (!@imagewebp($thumb, $temp, self::Quality) || !@rename($temp, $target)) {
			@unlink($temp);
			throw new \RuntimeException("Cannot write the thumbnail {$target}.");
		}
		return $target;
	}


	public function getFilters(): array
	{
		return ['stampThumb' => $this->getUrl(...)];
	}
}
