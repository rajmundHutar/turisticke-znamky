<?php


namespace App\Models\Traits;


use App\Models\ImportModel;

trait InjectImportModel {

	/** @var ImportModel */
	protected $importModel;

	public function injectImportModel(ImportModel $importModel) {
		$this->importModel = $importModel;

	}

}
