<?php


namespace App\Models\Traits;


use App\Models\CollectionModel;

trait InjectCollectionModel {

	/** @var CollectionModel */
	protected $collectionModel;

	public function injectCollectionModel(CollectionModel $collectionModel) {
		$this->collectionModel = $collectionModel;

	}

}
