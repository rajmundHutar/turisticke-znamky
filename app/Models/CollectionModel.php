<?php


namespace App\Models;


use Nette\Database\Context;

class CollectionModel {

	/** @var Context */
	protected $db;

	public function __construct(Context $db) {
		$this->db = $db;
	}

	public function fetch(int $id, int $userId) {

		return $this->db
			->table(\Table::Collection)
			->where('stamp_id', $id)
			->where('user_id', $userId)
			->fetch();
	}

	public function fetchByUser(?int $userId) {

		return $this->db
			->table(\Table::Collection)
			->where('user_id', $userId)
			->fetchPairs('stamp_id');

	}

	public function toggleCollect(int $id, int $userId): bool {

		$stamp = $this->fetch($id, $userId);

		if ($stamp) {
			$stamp->delete();
			return false;
		}

		$this->db
			->table(\Table::Collection)
			->insert([
				'stamp_id' => $id,
				'user_id' => $userId,
			]);

		return true;

	}

}
