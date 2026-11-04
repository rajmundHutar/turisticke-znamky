<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Helpers\StampThumbnails;
use Nette;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\FileResponse;
use Nette\Application\Responses\RedirectResponse;
use Tracy\Debugger;

/**
 * Creates a missing stamp thumbnail on the first request and sends it (#52);
 * without WebP support or with a broken image it redirects to the original image.
 */
final class ThumbnailPresenter implements Nette\Application\IPresenter
{
	public function __construct(
		private StampThumbnails $thumbnails,
		private Nette\Http\IRequest $httpRequest,
		private Nette\Http\IResponse $httpResponse,
		private Nette\Http\Session $session,
	) {
	}


	public function run(Nette\Application\Request $request): Nette\Application\Response
	{
		$this->session->close(); // releases the lock of a started session, so the parallel requests of a list are not serialized

		$name = (string) $request->getParameter('name');
		$source = $this->thumbnails->getSourcePath($name) ?? throw new BadRequestException("Unknown stamp image '{$name}'.");

		if ($this->thumbnails->isSupported()) {
			try {
				$path = $this->thumbnails->getThumbnailPath($name);
				if (!is_file($path)) {
					$path = $this->thumbnails->create($name);
				}
				$this->httpResponse->setExpiration('1 year'); // replaces Pragma and Expires sent by the session
				$this->httpResponse->setHeader('Cache-Control', 'private, max-age=31536000'); // the response may carry a session cookie
				return new FileResponse($path, basename($path), 'image/webp', forceDownload: false);
			} catch (\Throwable $e) {
				Debugger::log("Stamp thumbnail {$name}: {$e->getMessage()}", Debugger::WARNING);
			}
		}

		return new RedirectResponse($this->httpRequest->getUrl()->getBaseUrl() . 'images/stamps/' . basename($source));
	}
}
