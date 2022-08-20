<?php

namespace App\Models;

use App\Helpers\GPS;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\Strings;

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

	public function count(array $filter = []): int {
		return $this->prepareQuery($filter)->count('*');
	}

	private function prepareQuery(array $filter): Selection {

		$query = $this->db
			->table(\Table::Stamps);

		if ($filter['withoutImage'] ?? null) {
			$query->where('image IS NULL');
		}

		if ($filter['search'] ?? null) {
			$query->whereOr([
				'id LIKE ?' => self::mysqlEscapeLike($filter['search'], 'right'),
				'name LIKE ?' => self::mysqlEscapeLike($filter['search']),
			]);
		}

		if ($filter['label'] ?? null) {
			if ($filter['label'] == 'NEZAŘAZENO') {
				$query->whereOr([
					'type = ?' => $filter['label'],
					'type' => '',
				]);
			} else {
				$query->where('type',  $filter['label']);
			}
		}

		switch ($filter['sort'] ?? null) {
			case '-num':
				$query->order('id DESC');
				break;
			default:
				$query->order('id');
		}

		if ($filter['ids'] ?? null) {
			$query->where('id', $filter['ids']);
		}

		return $query;

	}

	public function fetch(int $id): ?ActiveRow {

		return $this->db
			->table(\Table::Stamps)
			->wherePrimary($id)
			->fetch();

	}

	public function fetchLabels(): array {

		$labels = $this->db
			->table(\Table::Stamps)
			->select('DISTINCT type')
			->where('type != ""')
			->order('type')
			->fetchPairs('type', 'type');

		return array_map(function($item) {
			return Strings::firstUpper(Strings::lower($item));
		}, $labels);

	}

	public function fetchImageCount(): int {

		return $this->db
			->table(\Table::Stamps)
			->where('image IS NOT NULL')
			->count('id');
	}

	public function fetchClosest(float $lat, float $lng, ?int $num = 10, ?array $exclude = null): ?array {

		$query = $this->db
			->table(\Table::Stamps)
			->select('*, SQRT(POW(ABS(? - lat), 2) + POW(ABS(? - lng), 2)) AS dist', $lat, $lng)
			->order('dist')
			->limit($num);

		if ($exclude) {
			$query->where('id NOT IN ?', $exclude);
		}

		$rows = $query->fetchAll();

		$closest = [];
		foreach ($rows as $key => $item) {
			$item = $item->toArray();
			$item['distance'] = GPS::gpsDistance(
				$lat,
				$lng,
				(float) $item['lat'],
				(float) $item['lng']
			);
			$item['prettyDistance'] = GPS::prettyDistance($item['distance']);
			$closest[$key] = $item;
		}

		return $closest;

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
