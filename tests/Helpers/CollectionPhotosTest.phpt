<?php

/**
 * Test: CollectionPhotos stores the private trip photo of a collected stamp re-encoded without metadata,
 * under a random name, and resolves and deletes it safely (#19)
 */

declare(strict_types=1);

use App\Helpers\CollectionPhotos;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$dir = sys_get_temp_dir() . '/collection-photos-' . getmypid();
$uploads = "$dir-uploads";
Nette\Utils\FileSystem::delete($dir);
Nette\Utils\FileSystem::delete($uploads);
Nette\Utils\FileSystem::createDir($uploads);
register_shutdown_function(function () use ($dir, $uploads): void {
	Nette\Utils\FileSystem::delete($dir);
	Nette\Utils\FileSystem::delete($uploads);
});


function createImage(int $width, int $height): GdImage
{
	$image = imagecreatetruecolor($width, $height);
	imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 120, 200));
	return $image;
}


/** A JPEG carrying an EXIF APP1 segment with a GPS-like text, as a phone would write it. */
function createJpegWithExif(string $path, int $width, int $height): void
{
	ob_start();
	imagejpeg(createImage($width, $height));
	$jpeg = (string) ob_get_clean();
	$exif = "Exif\0\0" . "MM\0\x2A\0\0\0\x08" . 'GPSLatitude 49.1234 GPSLongitude 16.5678';
	file_put_contents($path, substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2));
}


$photos = new CollectionPhotos($dir . '/');


// A large phone JPEG with EXIF/GPS is scaled down to 1600 px on the long edge and re-encoded without any metadata;
// the directory is created on the first store
createJpegWithExif("$uploads/phone.jpg", 4000, 3000);
Assert::contains('GPSLatitude', (string) file_get_contents("$uploads/phone.jpg"));
Assert::notSame(false, getimagesize("$uploads/phone.jpg"));
$name = $photos->store("$uploads/phone.jpg");
Assert::match('~^[a-f0-9]{32}\.jpg$~', $name);
Assert::true(is_dir($dir));
Assert::same(0o755, fileperms($dir) & 0o777);
$stored = "$dir/$name";
Assert::same($stored, $photos->path($name));
$size = getimagesize($stored);
Assert::same([1600, 1200, IMAGETYPE_JPEG], [$size[0], $size[1], $size[2]]);
$bytes = (string) file_get_contents($stored);
Assert::notContains('Exif', $bytes);
Assert::notContains('GPS', $bytes);
Assert::notContains("\xFF\xE1", $bytes); // no APP1 segment at all
Assert::true(is_file("$uploads/phone.jpg")); // the upload is left to PHP to clean up


// A portrait is limited by its height; a small image is never upscaled
createJpegWithExif("$uploads/portrait.jpg", 1000, 2400);
$size = getimagesize((string) $photos->path($photos->store("$uploads/portrait.jpg")));
Assert::same([667, 1600], [$size[0], $size[1]]);

imagejpeg(createImage(800, 600), "$uploads/small.jpg");
$size = getimagesize((string) $photos->path($photos->store("$uploads/small.jpg")));
Assert::same([800, 600, IMAGETYPE_JPEG], [$size[0], $size[1], $size[2]]);


// PNG (also with transparency, flattened onto white) and WebP are accepted and stored as JPEG
$png = imagecreatetruecolor(300, 200);
imagealphablending($png, false);
imagesavealpha($png, true);
imagefill($png, 0, 0, (int) imagecolorallocatealpha($png, 0, 0, 0, 127));
imagepng($png, "$uploads/photo.png");
$stored = (string) $photos->path($photos->store("$uploads/photo.png"));
Assert::same(IMAGETYPE_JPEG, getimagesize($stored)[2]);
$rgb = imagecolorat(imagecreatefromjpeg($stored), 150, 100);
Assert::true(($rgb >> 16 & 0xFF) > 240 && ($rgb >> 8 & 0xFF) > 240 && ($rgb & 0xFF) > 240); // white, not black

imagewebp(createImage(300, 200), "$uploads/photo.webp");
Assert::same(IMAGETYPE_JPEG, getimagesize((string) $photos->path($photos->store("$uploads/photo.webp")))[2]);


// Every stored photo gets its own random name; no temporary files are left behind
$names = array_map('basename', glob("$dir/*") ?: []);
Assert::count(5, $names);
Assert::count(5, array_unique($names));
foreach ($names as $file) {
	Assert::match('~^[a-f0-9]{32}\.jpg$~', $file);
}


// GIF, a renamed text file, an empty, a missing and a corrupt file are refused, nothing is written
imagegif(createImage(50, 50), "$uploads/anim.gif");
file_put_contents("$uploads/text.jpg", 'not an image');
touch("$uploads/empty.jpg");
file_put_contents("$uploads/corrupt.jpg", "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xC0\x00\x11\x08\x00\x10\x00\x10\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01garbage");
foreach (['anim.gif', 'text.jpg', 'empty.jpg', 'missing.jpg', 'corrupt.jpg'] as $file) {
	Assert::exception(fn() => $photos->store("$uploads/$file"), RuntimeException::class);
}
Assert::count(5, glob("$dir/*") ?: []);


// Over the byte or the pixel limit is refused before decoding
Assert::exception(
	fn() => (new CollectionPhotos($dir, maxBytes: 100))->store("$uploads/small.jpg"),
	RuntimeException::class,
	'~too large~',
);
Assert::exception(
	fn() => (new CollectionPhotos($dir, maxPixels: 800 * 600 - 1))->store("$uploads/small.jpg"),
	RuntimeException::class,
	'~too large~',
);
Assert::count(5, glob("$dir/*") ?: []);


// The error message is short and carries no path of the upload nor the image data
$e = Assert::exception(fn() => $photos->store("$uploads/text.jpg"), RuntimeException::class);
Assert::notContains($uploads, $e->getMessage());


// path() accepts only an existing stored name: no traversal, no other names or extensions
foreach (['', '../' . $name, "$name/..", strtoupper($name), substr($name, 0, -4), substr($name, 0, -4) . '.png', str_repeat('a', 32) . '.jpg', "$name\0", 'x' . $name] as $invalid) {
	Assert::null($photos->path($invalid), $invalid);
}


// delete() removes the file; null, an invalid, a missing or an already deleted name are a no-op
Assert::true(is_file("$dir/$name"));
$photos->delete($name);
Assert::false(is_file("$dir/$name"));
Assert::null($photos->path($name));
file_put_contents("$dir/keep.txt", 'x');
foreach ([null, $name, '', '../' . basename($uploads) . '/small.jpg', 'keep.txt', str_repeat('b', 32) . '.jpg'] as $invalid) {
	Assert::noError(fn() => $photos->delete($invalid));
}
Assert::true(is_file("$dir/keep.txt"));
Assert::true(is_file("$uploads/small.jpg"));
