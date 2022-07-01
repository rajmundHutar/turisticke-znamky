<?php

namespace App\Models;

use Nette\Database\Explorer;
use Nette\Database\Table\Selection;

class StampsModel {

	protected Explorer $db;

	public function __construct(Explorer $db) {
		$this->db = $db;
	}

	public function search(array $filter, $limit = null, $offset = null): ?array {

		return $this->prepareQuery($filter)
			->limit($limit, $offset)
			->fetchAll() ?: null;

	}

	public function count(array $filter): int {
		return $this->prepareQuery($filter)->count('*');
	}

	private function prepareQuery(array $filter): Selection {

		$query = $this->db
			->table(\Table::Stamps)
			->order('id');

		if ($filter['withoutImage'] ?? null) {
			$query->where('image IS NULL');
		}

		if ($filter['search'] ?? null) {
			$query->whereOr([
				'id LIKE ?' => self::mysqlEscapeLike($filter['search'], 'right'),
				'name LIKE ?' => self::mysqlEscapeLike($filter['search']),
			]);
		}

		if ($filter['ids'] ?? null) {
			$query->where('id', $filter['ids']);
		}

		return $query;

	}

	public function fetchCount() {

		return $this->db
			->table(\Table::Stamps)
			->count('id');

	}

	public function fetchImageCount() {

		return $this->db
			->table(\Table::Stamps)
			->where('image IS NOT NULL')
			->count('id');
	}

	public function fetchClosest(float $lat, float $lng, ?int $num = 10) {

		return $this->db
			->table(\Table::Stamps)
			->select('*, SQRT(POW(ABS(? - lat), 2) + POW(ABS(? - lng), 2)) AS dist', $lat, $lng)
			->order('dist')
			->limit($num)
			->fetchAll();

	}

	private static function mysqlEscapeLike(string $string, string $wildcards = 'both'): string {

		switch ($wildcards) {
			case 'both':
				$format = '%%%s%%';
				break;
			case 'left':
				$format = '%%%s';
				break;
			case 'right':
				$format = '%s%%';
				break;
			default:
				throw new \UnexpectedValueException('Unknown parameter $wildcards');
		}

		// escape for use in LIKE
		// see https://github.com/dg/dibi/blob/dd2fd654be39a330394aa922b927b84500d26928/src/Dibi/Drivers/MySqliDriver.php#L320

		return sprintf(
			$format,
			addcslashes(
				str_replace('\\', '\\\\', $string)
				, "\x00\n\r'%_"
			)
		);

	}

}
