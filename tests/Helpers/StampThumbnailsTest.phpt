<?php

/**
 * Test: StampThumbnails creates small WebP thumbnails of the stamp images and gives the list templates their URL (#52)
 */

declare(strict_types=1);

use App\Helpers\StampThumbnails;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$dir = sys_get_temp_dir() . '/stamp-thumbnails-' . getmypid();
Nette\Utils\FileSystem::delete($dir);
Nette\Utils\FileSystem::createDir($dir);
register_shutdown_function(fn() => Nette\Utils\FileSystem::delete($dir));


function createPng(string $path, int $width, int $height): void
{
	$image = imagecreatetruecolor($width, $height);
	imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 30, 30));
	imagepng($image, $path);
}


createPng("$dir/1-praded.png", 532, 300);
createPng("$dir/10-alfredka.png", 264, 300);
createPng("$dir/5-small.png", 100, 50);
file_put_contents("$dir/7-broken.png", 'not an image');
touch("$dir/8-empty.png");

$thumbnails = new StampThumbnails($dir . '/');


// GD with WebP is in the Docker image
Assert::true($thumbnails->isSupported());


// Only existing stamp images are accepted: no traversal, no directories, no extension in the name
Assert::same("$dir/1-praded.png", $thumbnails->getSourcePath('1-praded'));
foreach (['../1-praded', '..', 'thumbs/1-praded', '1-praded.png', '1-praded.jpg', 'Praded', '', '404-missing'] as $name) {
	Assert::null($thumbnails->getSourcePath($name), $name);
	Assert::exception(fn() => $thumbnails->create($name), InvalidArgumentException::class);
}


// A landscape image is scaled down into the 400 × 300 box, keeping the aspect ratio
$path = $thumbnails->create('1-praded');
Assert::same("$dir/thumbs/1-praded.webp", $path);
$info = getimagesize($path);
Assert::same([400, 226, 'image/webp'], [$info[0], $info[1], $info['mime']]);
Assert::same(['1-praded.webp'], array_map('basename', glob("$dir/thumbs/*") ?: [])); // no temporary file left

// a portrait one is limited by the height, a small one is not enlarged
Assert::same([264, 300], array_slice(getimagesize($thumbnails->create('10-alfredka')) ?: [], 0, 2));
Assert::same([100, 50], array_slice(getimagesize($thumbnails->create('5-small')) ?: [], 0, 2));

// a corrupt or empty source is an error, no thumbnail is written
Assert::exception(fn() => $thumbnails->create('7-broken'), RuntimeException::class);
Assert::false(is_file("$dir/thumbs/7-broken.webp"));
Assert::exception(fn() => $thumbnails->create('8-empty'), RuntimeException::class);
Assert::false(is_file("$dir/thumbs/8-empty.webp"));

// an image over the pixel limit is refused before it is decoded, so it cannot exhaust the memory
$limited = new StampThumbnails($dir, maxPixels: 532 * 300 - 1);
Assert::exception(fn() => $limited->create('1-praded'), RuntimeException::class, '~too large~');
Assert::same([100, 50], array_slice(getimagesize($limited->create('5-small')) ?: [], 0, 2));

// transparency is kept
$image = imagecreatetruecolor(40, 20);
imagealphablending($image, false);
imagesavealpha($image, true);
imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
imagefilledrectangle($image, 20, 0, 39, 19, (int) imagecolorallocate($image, 0, 0, 255));
imagepng($image, "$dir/9-alpha.png");
$thumb = imagecreatefromwebp($thumbnails->create('9-alpha'));
Assert::same(127, imagecolorsforindex($thumb, imagecolorat($thumb, 5, 10))['alpha']);
Assert::same(0, imagecolorsforindex($thumb, imagecolorat($thumb, 35, 10))['alpha']);


// The URL (relative to the base URL) is the static thumbnail when it exists, else the route generating it
Assert::same('images/stamps/thumbs/1-praded.webp', $thumbnails->getUrl('1-praded.png'));
Assert::same('thumb/7-broken', $thumbnails->getUrl('7-broken.png'));
Assert::same('images/stamps/404-missing.png', $thumbnails->getUrl('404-missing.png')); // no source, no route
Assert::same('images/stamps/stamp-5.jpg', $thumbnails->getUrl('stamp-5.jpg')); // not a name of an imported image: the original


// Without GD/WebP the templates use the original image, nothing is generated
$unsupported = new StampThumbnails($dir, supported: false);
Assert::false($unsupported->isSupported());
Assert::same('images/stamps/7-broken.png', $unsupported->getUrl('7-broken.png'));
Assert::same('images/stamps/thumbs/1-praded.webp', $unsupported->getUrl('1-praded.png')); // an existing thumbnail is still fine
Assert::exception(fn() => $unsupported->create('5-small'), RuntimeException::class);


// The Latte filter
$latte = new Latte\Engine;
$latte->setLoader(new Latte\Loaders\StringLoader);
$latte->addExtension($thumbnails);
Assert::same('images/stamps/thumbs/1-praded.webp', $latte->renderToString('{$image|stampThumb}', ['image' => '1-praded.png']));
