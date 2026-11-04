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
	) {
	}


	public function run(Nette\Application\Request $request): Nette\Application\Response
	{
		$name = (string) $request->getParameter('name');
		$source = $this->thumbnails->getSourcePath($name) ?? throw new BadRequestException("Unknown stamp image '{$name}'.");

		if ($this->thumbnails->isSupported()) {
			try {
				$path = $this->thumbnails->getThumbnailPath($name);
				if (!is_file($path)) {
					$path = $this->thumbnails->create($name);
				}
				$this->httpResponse->setHeader('Cache-Control', 'public, max-age=31536000');
				return new FileResponse($path, basename($path), 'image/webp', forceDownload: false);
			} catch (\RuntimeException $e) {
				Debugger::log($e, Debugger::WARNING);
			}
		}

		return new RedirectResponse($this->httpRequest->getUrl()->getBaseUrl() . 'images/stamps/' . basename($source));
	}
}
