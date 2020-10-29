<?php


namespace App\Models\Traits;


use App\Models\StampsModel;

trait InjectStampsModel {

	/** @var StampsModel */
	protected $stampsModel;

	public function injectStampsModel(StampsModel $stampsModel) {
		$this->stampsModel = $stampsModel;

	}

}
