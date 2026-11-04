<?php

/**
 * Test: ThumbnailPresenter generates a missing stamp thumbnail on the first request, or sends the original without WebP support (#52)
 */

declare(strict_types=1);

use App\Helpers\StampThumbnails;
use App\Presenters\ThumbnailPresenter;
use Nette\Application\BadRequestException;
use Nette\Application\Request;
use Nette\Application\Responses\FileResponse;
use Nette\Application\Responses\RedirectResponse;
use Nette\Http\UrlScript;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$dir = sys_get_temp_dir() . '/thumbnail-presenter-' . getmypid();
Nette\Utils\FileSystem::delete($dir);
Nette\Utils\FileSystem::createDir($dir);
register_shutdown_function(fn() => Nette\Utils\FileSystem::delete($dir));
imagepng(imagecreatetruecolor(532, 300), "$dir/1-praded.png");
file_put_contents("$dir/7-broken.png", 'not an image');
Tracy\Debugger::$logDirectory = $dir; // the corrupt image is logged


function runThumbnail(StampThumbnails $thumbnails, string $name): Nette\Application\Response
{
	$httpRequest = new Nette\Http\Request(new UrlScript('http://localhost/app/thumb/' . $name, '/app/index.php'));
	$presenter = new ThumbnailPresenter($thumbnails, $httpRequest, new Nette\Http\Response);
	return $presenter->run(new Request('Thumbnail', 'GET', ['name' => $name]));
}


// A valid name: the thumbnail is created and sent as WebP
$response = runThumbnail(new StampThumbnails($dir), '1-praded');
Assert::type(FileResponse::class, $response);
assert($response instanceof FileResponse);
Assert::same("$dir/thumbs/1-praded.webp", $response->getFile());
Assert::same('image/webp', $response->getContentType());
Assert::true(is_file("$dir/thumbs/1-praded.webp"));

// An unknown or invalid name is a 404
foreach (['404-missing', '../1-praded', '1-praded.png'] as $name) {
	$e = Assert::exception(fn() => runThumbnail(new StampThumbnails($dir), $name), BadRequestException::class);
	Assert::same(404, $e->getCode());
}

// Without GD/WebP support, or with a corrupt image, it redirects to the original
$response = runThumbnail(new StampThumbnails($dir, supported: false), '1-praded');
Assert::type(RedirectResponse::class, $response);
assert($response instanceof RedirectResponse);
Assert::same('http://localhost/app/images/stamps/1-praded.png', $response->getUrl());
Assert::same(302, $response->getCode());

$response = runThumbnail(new StampThumbnails($dir), '7-broken');
assert($response instanceof RedirectResponse);
Assert::same('http://localhost/app/images/stamps/7-broken.png', $response->getUrl());
Assert::false(is_file("$dir/thumbs/7-broken.webp"));
