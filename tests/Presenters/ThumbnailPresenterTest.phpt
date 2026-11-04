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
touch("$dir/8-empty.png");
Tracy\Debugger::$logDirectory = $dir; // failures are logged


/** Records the headers instead of sending them (the real response can't be read back in CLI). */
final class RecordingHttpResponse implements Nette\Http\IResponse
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
final class RecordingSession extends Nette\Http\Session
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


/** @return array{Nette\Application\Response, RecordingHttpResponse, RecordingSession} */
function runThumbnail(StampThumbnails $thumbnails, string $name): array
{
	$httpRequest = new Nette\Http\Request(new UrlScript('http://localhost/app/thumb/' . $name, '/app/index.php'));
	$httpResponse = new RecordingHttpResponse;
	$session = new RecordingSession;
	$presenter = new ThumbnailPresenter($thumbnails, $httpRequest, $httpResponse, $session);
	return [$presenter->run(new Request('Thumbnail', 'GET', ['name' => $name])), $httpResponse, $session];
}


function assertRedirectToOriginal(string $name, array $result): void
{
	[$response, $httpResponse, $session] = $result;
	Assert::type(RedirectResponse::class, $response);
	assert($response instanceof RedirectResponse);
	Assert::same("http://localhost/app/images/stamps/$name.png", $response->getUrl());
	Assert::same(302, $response->getCode());
	Assert::same([], $httpResponse->headers); // no long cache of the redirect
	Assert::same('not set', $httpResponse->expiration);
	Assert::true($session->closed);
}


// A valid name: the thumbnail is created and sent as WebP, cacheable only by the browser (a session cookie may be sent)
[$response, $httpResponse, $session] = runThumbnail(new StampThumbnails($dir), '1-praded');
Assert::type(FileResponse::class, $response);
assert($response instanceof FileResponse);
Assert::same("$dir/thumbs/1-praded.webp", $response->getFile());
Assert::same('image/webp', $response->getContentType());
Assert::true(is_file("$dir/thumbs/1-praded.webp"));
Assert::same('1 year', $httpResponse->expiration); // replaces Pragma and Expires of the session cache limiter
Assert::same('private, max-age=31536000', $httpResponse->headers['Cache-Control']);
Assert::true($session->closed); // parallel thumbnail requests don't wait for the session lock

// An unknown or invalid name is a 404
foreach (['404-missing', '../1-praded', '1-praded.png'] as $name) {
	$e = Assert::exception(fn() => runThumbnail(new StampThumbnails($dir), $name), BadRequestException::class);
	Assert::same(404, $e->getCode());
}

// Without GD/WebP support, or with a corrupt, empty or too large image, it redirects to the original
assertRedirectToOriginal('1-praded', runThumbnail(new StampThumbnails($dir, supported: false), '1-praded'));
Nette\Utils\FileSystem::delete("$dir/thumbs");
assertRedirectToOriginal('1-praded', runThumbnail(new StampThumbnails($dir, maxPixels: 1000), '1-praded'));
assertRedirectToOriginal('7-broken', runThumbnail(new StampThumbnails($dir), '7-broken'));
assertRedirectToOriginal('8-empty', runThumbnail(new StampThumbnails($dir), '8-empty'));
Assert::same([], glob("$dir/thumbs/*") ?: []);

// a failure is logged as one short line
$log = (string) file_get_contents("$dir/warning.log");
Assert::count(3, explode("\n", trim($log)));
Assert::match('~7-broken: Cannot read~', $log);
Assert::notContains('Stack trace', $log);
