<?php

namespace App\Models;

use App\Dto\CollectedStampDto;
use App\Dto\StampDto;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class CollectionModel {

	protected Explorer $db;

	public function __construct(Explorer $db) {
		$this->db = $db;
	}

	public function fetch(int $id, int $userId): ?ActiveRow {

		return $this->db
			->table(\Table::Collection)
			->where('stamp_id', $id)
			->where('user_id', $userId)
			->fetch();
	}

	/**
	 * @param int|null $userId
	 * @return list<CollectedStampDto>
	 */
	public function fetchByUser(?int $userId): ?array {

		$list = $this->db
			->table(\Table::Collection)
			->select('stamp_id.*, date, comment, file')
			->where('user_id', $userId)
			->fetchAll();

		return array_map(fn(ActiveRow $row) => CollectedStampDto::fromActiveRow($row), $list);
	}

	public function update(int $stampId, int $userId, array $data): void {

		$this->db
			->table(\Table::Collection)
			->where('stamp_id', $stampId)
			->where('user_id', $userId)
			->update($data);

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
				'date' => new \DateTime(),
			]);

		return true;

	}

}
