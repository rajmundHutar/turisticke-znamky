<?php

namespace App\Models;

use App\Dto\CollectedStampDto;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class CollectionModel
{

	public function __construct(protected Explorer $db)
	{
	}

	public function fetch(int $id, int $userId): ?ActiveRow
	{

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
	public function fetchByUser(?int $userId): ?array
	{

		$list = $this->db
			->table(\Table::Collection)
			->select('stamp_id.*, date, comment, file')
			->where('user_id', $userId)
			->fetchAll();

		return array_map(fn(ActiveRow $row) => CollectedStampDto::fromActiveRow($row), $list);
	}

	public function update(int $stampId, int $userId, array $data): void
	{

		$this->db
			->table(\Table::Collection)
			->where('stamp_id', $stampId)
			->where('user_id', $userId)
			->update($data);
	}

	public function add(int $stampId, int $userId, ?\DateTimeInterface $date, ?string $comment): void
	{

		$this->db
			->table(\Table::Collection)
			->insert([
				'stamp_id' => $stampId,
				'user_id' => $userId,
				'date' => $date,
				'comment' => $comment,
			]);
	}

	public function remove(int $stampId, int $userId): void
	{

		$this->db
			->table(\Table::Collection)
			->where('stamp_id', $stampId)
			->where('user_id', $userId)
			->delete();
	}

}
