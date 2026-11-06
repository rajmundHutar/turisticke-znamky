<?php

/**
 * Test: PhotoPresenter sends the private trip photo of a collected stamp only to its logged-in owner (#19)
 */

declare(strict_types=1);

use App\Helpers\CollectionPhotos;
use App\Models\CollectionModel;
use App\Presenters\PhotoPresenter;
use App\Router\RouterFactory;
use Nette\Application\BadRequestException;
use Nette\Application\Request;
use Nette\Application\Responses\FileResponse;
use Nette\Http\UrlScript;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/PresenterTestHelpers.php';


$dir = sys_get_temp_dir() . '/photo-presenter-' . getmypid();
Nette\Utils\FileSystem::delete($dir);
Nette\Utils\FileSystem::createDir($dir);
register_shutdown_function(fn() => Nette\Utils\FileSystem::delete($dir));

$photos = new CollectionPhotos("$dir/photos");
imagejpeg(imagecreatetruecolor(40, 30), "$dir/upload.jpg");
$file = $photos->store("$dir/upload.jpg");
$version = substr($file, 0, -4);
$missing = str_repeat('c', 32) . '.jpg'; // in the DB, but not on the disk


/** Records the headers instead of sending them (the real response can't be read back in CLI). */
final class PhotoRecordingHttpResponse implements Nette\Http\IResponse
{
	/** @var array<string, ?string> */
	public array $headers = [];
	public ?string $expiration = 'not set';


	public function setCode(int $code, ?string $reason = null): static
	{
		return $this;
	}


	public function getCode(): int
	{
		return 200;
	}


	public function setHeader(string $name, ?string $value): static
	{
		$this->headers[$name] = $value;
		return $this;
	}


	public function addHeader(string $name, string $value): static
	{
		return $this->setHeader($name, $value);
	}


	public function setContentType(string $type, ?string $charset = null): static
	{
		return $this;
	}


	public function redirect(string $url, int $code = self::S302_Found): void
	{
	}


	public function setExpiration(?string $expire): static
	{
		$this->expiration = $expire;
		return $this;
	}


	public function isSent(): bool
	{
		return false;
	}


	public function getHeader(string $header): ?string
	{
		return $this->headers[$header] ?? null;
	}


	public function getHeaders(): array
	{
		return $this->headers;
	}


	public function setCookie(string $name, string $value, mixed $expire, ?string $path = null, ?string $domain = null, ?bool $secure = null, ?bool $httpOnly = null): static
	{
		return $this;
	}


	public function deleteCookie(string $name, ?string $path = null, ?string $domain = null, ?bool $secure = null): void
	{
	}
}


/** Session double recording whether it was closed (released its lock). */
final class PhotoRecordingSession extends Nette\Http\Session
{
	public bool $closed = false;


	public function __construct()
	{
	}


	public function close(): void
	{
		$this->closed = true;
	}
}


/** CollectionModel double: the user 1 has the stamps 5 (with a photo) and 6 (without), the user 2 the stamp 7 (with a photo). */
final class PhotoFakeCollectionModel extends CollectionModel
{
	/** @param  array<int, array<int, ?string>>  $files  user id => stamp id => file */
	public function __construct(
		private array $files,
	) {
	}


	public function fetchFile(int $stampId, int $userId): ?string
	{
		return $this->files[$userId][$stampId] ?? null;
	}
}


/** @return array{Nette\Application\Response, PhotoRecordingHttpResponse, PhotoRecordingSession} */
function runPhoto(CollectionPhotos $photos, ?array $roles, string $stampId, string $version, array $files): array
{
	$httpResponse = new PhotoRecordingHttpResponse;
	$session = new PhotoRecordingSession;
	$presenter = new PhotoPresenter($photos, new PhotoFakeCollectionModel($files), PresenterTestHelpers::createUser($roles), $httpResponse, $session);
	return [$presenter->run(new Request('Photo', 'GET', ['stampId' => $stampId, 'v' => $version])), $httpResponse, $session];
}


$files = [1 => [5 => $file, 6 => null, 8 => $missing], 2 => [7 => $file]];


// The owner gets the JPEG, cacheable for a week by the browser only (the URL changes with a new photo), never sniffed
[$response, $httpResponse, $session] = runPhoto($photos, ['user'], '5', $version, $files);
Assert::type(FileResponse::class, $response);
assert($response instanceof FileResponse);
Assert::same("$dir/photos/$file", $response->getFile());
Assert::same('image/jpeg', $response->getContentType());
Assert::same('7 days', $httpResponse->expiration); // replaces Pragma and Expires of the session cache limiter
Assert::same('private, max-age=604800', $httpResponse->headers['Cache-Control']);
Assert::same('nosniff', $httpResponse->headers['X-Content-Type-Options']);
Assert::true($session->closed); // parallel requests don't wait for the session lock


// Anonymous, another user's stamp, a stamp without a photo, a wrong version, a missing file: 404, no caching headers
$cases = [
	'anonymous' => [null, '5', $version],
	'another user' => [['user'], '7', $version],
	'not collected' => [['user'], '9', $version],
	'no photo' => [['user'], '6', $version],
	'wrong version' => [['user'], '5', str_repeat('a', 32)],
	'old version with extension' => [['user'], '5', $file],
	'empty version' => [['user'], '5', ''],
	'missing file' => [['user'], '8', substr($missing, 0, -4)],
];
foreach ($cases as $label => [$roles, $stampId, $v]) {
	$httpResponse = null;
	$e = Assert::exception(function () use ($photos, $roles, $stampId, $v, $files, &$httpResponse): void {
		$httpResponse = new PhotoRecordingHttpResponse;
		$presenter = new PhotoPresenter($photos, new PhotoFakeCollectionModel($files), PresenterTestHelpers::createUser($roles), $httpResponse, new PhotoRecordingSession);
		$presenter->run(new Request('Photo', 'GET', ['stampId' => $stampId, 'v' => $v]));
	}, BadRequestException::class);
	Assert::same(404, $e->getCode(), $label);
	Assert::same([], $httpResponse->headers, $label);
}


// The route: photo/<stampId>/<v> without an extension, both ways
$router = RouterFactory::createRouter();
$httpRequest = new Nette\Http\Request(new UrlScript("http://localhost/photo/5/$version", '/index.php'));
$params = $router->match($httpRequest);
Assert::equal(['presenter' => 'Photo', 'action' => 'default', 'stampId' => '5', 'v' => $version], $params);
Assert::same(
	"http://localhost/photo/5/$version",
	$router->constructUrl(['presenter' => 'Photo', 'action' => 'default', 'stampId' => 5, 'v' => $version], new UrlScript('http://localhost/index.php', '/index.php')),
);
Assert::false(isset($router->match(new Nette\Http\Request(new UrlScript("http://localhost/photo/5/$file", '/index.php')))['v'])); // with an extension it is no photo URL
