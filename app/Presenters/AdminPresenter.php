<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Controls\Menu;
use App\Models\Traits\InjectImportModel;
use App\Models\Traits\InjectStampsModel;
use Nette;

final class AdminPresenter extends Nette\Application\UI\Presenter {

	use InjectMenu,
		InjectStampsModel,
		InjectImportModel;

	public function actionImportStamps() {

		[$total, $new] = $this->importModel->import();
		$this->flashMessage(sprintf('Načteno %d známek, z toho nových %d', $total, $new));
		$this->redirect('default');

	}

	public function actionImportImages() {

		$new = $this->importModel->importImages();
		$this->flashMessage(sprintf('Načteno %d nových obrázků', $new));
		$this->redirect('default');

	}
	
	public function renderDefault() {

		$lastLog = $this->importModel->fetchLastLog();

		$this->template->lastUpdate = $lastLog['date'];
		$this->template->stampsCount = $this->stampsModel->fetchCount();
		$this->template->imageCount = $this->stampsModel->fetchImageCount();

	}

}
