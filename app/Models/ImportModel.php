<?php

namespace App\Models;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Tracy\Debugger;

class ImportModel {

	const IMAGE_SCRAPER_BASE_URL = 'https://www.turisticke-znamky.cz/znamky/%s-c%d';
	const REMOTE_IMAGE_BASE_URL = 'https://www.turisticke-znamky.cz/photos/medium/';

	protected string $csvPath;
	protected string $stampsImagesPath;
	protected Explorer $db;
	protected StampsModel $stampsModel;

	public function __construct(
		string $csvPath,
		string $stampsImagesPath,
		Explorer $db,
		StampsModel $stampsModel
	) {

		$this->csvPath = $csvPath;
		$this->stampsImagesPath = $stampsImagesPath;
		$this->db = $db;
		$this->stampsModel = $stampsModel;

	}

	public function fetchLastLog(): ActiveRow {

		return $this->db
			->table(\Table::ImportLog)
			->order('id DESC')
			->limit(1)
			->fetch();

	}

	public function import(): array {

		Debugger::timer();

		$file = bin2hex(random_bytes(10));
		$fileTempPath = __DIR__ . "/../../temp/{$file}.tmp";
		$fp = fopen($fileTempPath, 'w+');
		$ch = curl_init($this->csvPath);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		// write curl response to file
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		// get curl response
		curl_exec($ch);
		curl_close($ch);
		fclose($fp);

		$i = 0;
		$countNew = 0;
		$countTotal = 0;
		$handle = fopen($fileTempPath, "r");
		if (!$handle) {
			unlink($fileTempPath);
			throw new \RuntimeException("Can't read from {$fileTempPath}");
		} else {

			while (($data = fgetcsv($handle, 0, ';')) !== false) {
				$i++;
				if ($i === 1) {
					// Skip first row with header
					continue;
				}

				$batch = [
					'id' => $data[0],
					'name' => $data[1],
					'type' => $data[2],
					'region' => $data[3],
					'created_at' => new DateTime($data[4]),
					'lat' => $data[29],
					'lng' => $data[30],
				];

				$isNew = $this->save($batch);
				$countNew += $isNew ? 1 : 0;
				$countTotal++;

			}

			fclose($handle);
			unlink($fileTempPath);
		}

		$elapsedTIme = Debugger::timer();

		$this->db
			->table(\Table::ImportLog)
			->insert([
				'date' => new DateTime(),
				'parsed' => $countTotal,
				'new' => $countNew,
				'time' => $elapsedTIme,
			]);

		return [$countTotal, $countNew];

	}

	public function importImages(): int {

		$stamps = $this->stampsModel->search([
			'withoutImage' => true,
		], 500) ?: [];

		$count = 0;
		foreach ($stamps as $stamp) {
			if ($stamp['image']) {
				continue;
			}

			$webName = Strings::webalize($stamp['name']);

			$url = sprintf(self::IMAGE_SCRAPER_BASE_URL, $webName, $stamp['id']);

			$page = file_get_contents($url);
			if (!strlen($page)) {
				dump('can\'t find page');
				dump($stamp);
				dump($url);
				continue;
			}

			preg_match('~photos\/medium\/(.+?)\'~i', $page, $matches);

			if (!($matches[1] ?? null)) {
				dump('can\'t find image in page');
				dump($stamp);
				dump($url);
				continue;
			}

			$source = self::REMOTE_IMAGE_BASE_URL . '/' . $matches[1];
			$newName = $stamp['id'] . '-' .$webName . '.png';
			$target = $this->stampsImagesPath . '/' . $newName;
			file_put_contents($target, file_get_contents($source));
			$stamp->update([
				'image' => $newName,
			]);
			$count++;

		}

		return $count;

	}

	public function save(array $data): bool {

		$stamp = $this->db
			->table(\Table::Stamps)
			->wherePrimary($data['id'])
			->fetch();

		if ($stamp) {

			unset($data['id']);
			$stamp->update($data);

			return false;

		} else {

			$this->db
				->table(\Table::Stamps)
				->insert($data);

			return true;

		}

	}

}
