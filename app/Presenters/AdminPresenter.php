<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Models\Traits\InjectImportModel;
use App\Models\Traits\InjectStampsModel;
use App\Presenters\Traits\InjectMenu;
use App\Presenters\Traits\InjectTranslator;
use Nette;

final class AdminPresenter extends Nette\Application\UI\Presenter
{

	use InjectMenu;
	use InjectTranslator;
	use InjectStampsModel;
	use InjectImportModel;

	protected function startup()
	{

		parent::startup();
		if (!$this->user->isInRole('admin')) {
			$this->error('Admin role is required.', Nette\Http\IResponse::S403_Forbidden);
		}
	}

	public function handleImportStamps()
	{

		try {
			[$total, $new] = $this->importModel->import();
			$this->flashMessage($this->t('admin.{new}stampsOutOf{total}', ['total' => $total, 'new' => $new]));
		} catch (\RuntimeException $e) {
			$this->flashMessage($this->t('admin.importFailed{error}', ['error' => $e->getMessage()]), \Flash::ERROR);
		}
		$this->redirect('default');
	}

	public function handleImportImages()
	{

		$new = $this->importModel->importImages();
		$this->flashMessage($this->t('admin.{count}loadedImages', ['count' => $new]));
		$this->redirect('default');
	}

	public function renderDefault()
	{

		$lastLog = $this->importModel->fetchLastLog();

		$this->template->lastUpdate = $lastLog?->date;
		$this->template->stampsCount = $this->stampsModel->count();
		$this->template->imageCount = $this->stampsModel->fetchImageCount();
		$this->template->logs = $this->importModel->fetchAllLogs();
	}

}
