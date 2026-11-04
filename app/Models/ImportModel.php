<?php

namespace App\Models;

use App\Helpers\StampThumbnails;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Tracy\Debugger;

class ImportModel
{

	public const IMAGES_PER_RUN = 500;
	public const SALESPOINTS_CHUNK = 500;

	public function __construct(
		protected string $feedUrl,
		protected string $stampsImagesPath,
		protected Explorer $db,
		protected StampThumbnails $thumbnails,
	) {
	}

	public function fetchLastLog(): ?ActiveRow
	{

		return $this->db
			->table(\Table::ImportLog)
			->order('id DESC')
			->limit(1)
			->fetch();
	}

	public function fetchAllLogs(): array
	{
		return $this->db
			->table(\Table::ImportLog)
			->order('date DESC')
			->fetchAll();
	}

	/**
	 * Imports CZ stamps and their salespoints from the XML feed.
	 * @return array{int, int} [imported stamps, new stamps]
	 * @throws \RuntimeException when the feed cannot be downloaded or parsed
	 */
	public function import(): array
	{

		Debugger::timer();

		$file = bin2hex(random_bytes(10));
		$fileTempPath = __DIR__ . "/../../temp/{$file}.xml";

		try {
			$this->download($this->feedUrl, $fileTempPath, 120);
			[$countTotal, $countNew] = $this->importFeed(new StampFeedReader($fileTempPath));
		} finally {
			if (is_file($fileTempPath)) {
				unlink($fileTempPath);
			}
		}

		$this->db
			->table(\Table::ImportLog)
			->insert([
				'date' => new DateTime,
				'parsed' => $countTotal,
				'new' => $countNew,
				'time' => (int) round(Debugger::timer()),
			]);

		return [$countTotal, $countNew];
	}

	/**
	 * Upserts the stamps and replaces their salespoints in one transaction.
	 * @return array{int, int} [imported stamps, new stamps]
	 */
	private function importFeed(StampFeedReader $reader): array
	{

		$existingIds = array_flip($this->db
			->table(\Table::Stamps)
			->fetchPairs(null, 'id'));

		$countNew = 0;
		$stampIds = [];
		$salespoints = [];

		$this->db->beginTransaction();
		try {
			foreach ($reader->read() as $stamp) {
				$id = $stamp['id'];
				foreach ($stamp['salespoints'] as $salespoint) {
					$salespoints[] = ['stamp_id' => $id] + $salespoint;
				}
				unset($stamp['salespoints'], $stamp['id']);

				if (isset($existingIds[$id])) {
					$this->db
						->table(\Table::Stamps)
						->wherePrimary($id)
						->update($stamp);
				} else {
					$this->db
						->table(\Table::Stamps)
						->insert(['id' => $id] + $stamp);
					$existingIds[$id] = true;
					$countNew++;
				}
				$stampIds[$id] = $id;
			}

			foreach (array_chunk($stampIds, self::SALESPOINTS_CHUNK) as $chunk) {
				$this->db
					->table(\Table::Salespoints)
					->where('stamp_id', $chunk)
					->delete();
			}
			foreach (array_chunk($salespoints, self::SALESPOINTS_CHUNK) as $chunk) {
				$this->db
					->table(\Table::Salespoints)
					->insert($chunk);
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return [count($stampIds), $countNew];
	}

	/**
	 * Downloads missing stamp images from their feed URLs.
	 * @return int number of downloaded images
	 */
	public function importImages(): int
	{

		$stamps = $this->db
			->table(\Table::Stamps)
			->where('image', null)
			->where('image_url IS NOT NULL')
			->order('id')
			->limit(self::IMAGES_PER_RUN);

		$count = 0;
		$failed = 0;
		foreach ($stamps as $stamp) {
			$newName = $stamp['id'] . '-' . Strings::webalize($stamp['name']) . '.png';
			$target = rtrim($this->stampsImagesPath, '/') . '/' . $newName;

			try {
				$this->download($stamp['image_url'], $target, 30);
			} catch (\RuntimeException $e) {
				Debugger::log($e, Debugger::WARNING);
				$failed++;
				continue;
			}

			$stamp->update([
				'image' => $newName,
			]);
			$count++;
			$this->createThumbnail(pathinfo($newName, PATHINFO_FILENAME));
		}

		if ($failed) {
			Debugger::log("Stamp images: {$count} downloaded, {$failed} failed.", Debugger::WARNING);
		}

		return $count;
	}

	/**
	 * Creates the list thumbnail of a downloaded image; a failure is only logged, the list then creates it on demand (#52).
	 */
	private function createThumbnail(string $name): void
	{
		if (!$this->thumbnails->isSupported()) {
			return;
		}
		try {
			$this->thumbnails->create($name);
		} catch (\Throwable $e) {
			Debugger::log($e, Debugger::WARNING);
		}
	}

	/**
	 * Downloads the URL into the target file; on failure deletes the file and throws.
	 * @throws \RuntimeException
	 */
	private function download(string $url, string $target, int $timeout): void
	{

		$fp = fopen($target, 'w');
		if (!$fp) {
			throw new \RuntimeException("Cannot write to {$target}.");
		}

		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_FILE => $fp,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => $timeout,
		]);
		$ok = curl_exec($ch);
		$error = curl_error($ch);
		$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		unset($ch);
		fclose($fp);

		$message = match (true) {
			$ok === false => "Download of {$url} failed: {$error}",
			$status !== 200 => "Download of {$url} failed: HTTP status {$status}",
			filesize($target) === 0 => "Download of {$url} failed: empty response",
			default => null,
		};

		if ($message !== null) {
			unlink($target);
			throw new \RuntimeException($message);
		}
	}

}
