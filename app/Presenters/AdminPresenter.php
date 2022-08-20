<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Models\Traits\InjectImportModel;
use App\Models\Traits\InjectStampsModel;
use App\Presenters\Traits\InjectTranslator;
use Nette;

final class AdminPresenter extends Nette\Application\UI\Presenter {

	use InjectMenu,
		InjectTranslator,
		InjectStampsModel,
		InjectImportModel;

	public function actionImportStamps() {

		[$total, $new] = $this->importModel->import();
		$this->flashMessage($this->t('admin.{new}stampsOutOf{total}', total: $total, new: $new));
		$this->redirect('default');

	}

	public function actionImportImages() {

		$new = $this->importModel->importImages();
		$this->flashMessage($this->t('admin.{count}loadedImages', count: $new));
		$this->redirect('default');

	}
	
	public function renderDefault() {

		$lastLog = $this->importModel->fetchLastLog();

		$this->template->lastUpdate = $lastLog['date'];
		$this->template->stampsCount = $this->stampsModel->count();
		$this->template->imageCount = $this->stampsModel->fetchImageCount();

	}

}
