<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Helpers\CollectionPhotos;
use App\Models\CollectionModel;
use Nette;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\FileResponse;

/**
 * Sends the private trip photo of a collected stamp to its logged-in owner, a 404 to anyone else (#19).
 * The URL carries the photo's name as the version, so a new photo gets a new URL and the old one can be cached.
 */
final class PhotoPresenter implements Nette\Application\IPresenter
{
	public function __construct(
		private CollectionPhotos $photos,
		private CollectionModel $collectionModel,
		private Nette\Security\User $user,
		private Nette\Http\IResponse $httpResponse,
		private Nette\Http\Session $session,
	) {
	}


	public function run(Nette\Application\Request $request): Nette\Application\Response
	{
		$userId = $this->user->isLoggedIn() ? $this->user->getId() : null;
		$this->session->close(); // releases the lock of the session read for the login, so parallel requests are not serialized

		$stampId = (int) $request->getParameter('stampId');
		$file = $userId === null ? null : $this->collectionModel->fetchFile($stampId, (int) $userId);
		$path = ($file === null || $file !== $request->getParameter('v') . '.jpg' ? null : $this->photos->path($file)) ?? throw new BadRequestException("No photo of the stamp {$stampId}.");

		$this->httpResponse->setExpiration('7 days'); // replaces Pragma and Expires sent by the session
		$this->httpResponse->setHeader('Cache-Control', 'private, max-age=604800'); // the owner's browser only
		$this->httpResponse->setHeader('X-Content-Type-Options', 'nosniff');
		return new FileResponse($path, $file, 'image/jpeg', forceDownload: false);
	}
}
