<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Streams the turisticke-znamky.cz tourist stamp XML feed and yields normalized Czech stamps.
 * @see https://turisticke-znamky.cz/api/feeds/tourist-stamps.md
 */
final class StampFeedReader
{
	private const ROOT = 'tourist_stamp_feed';
	private const SCHEMA_VERSION = '1';
	private const COUNTRY = 'CZ';

	private int $skippedCount = 0;


	public function __construct(
		private readonly string $path,
	) {
	}


	/**
	 * Yields CZ stamps that have a number (our stamp id) and a location; other countries are ignored.
	 * Throws \RuntimeException on an unreadable file, invalid XML or an unsupported feed.
	 * @return \Generator<int, array{id: int, source_id: int, name: string, lat: string, lng: string, description: ?string, detail_url: ?string, website: ?string, wikipedia: ?string, facebook: ?string, image_url: ?string, salespoints: list<array{source_id: int, name: string, lat: ?string, lng: ?string, website: ?string, sort: ?int, system: bool}>}>
	 */
	public function read(): \Generator
	{
		$this->skippedCount = 0;

		if (!is_file($this->path) || !is_readable($this->path)) {
			throw new \RuntimeException("Cannot read the stamp feed file {$this->path}.");
		}

		$useInternalErrors = libxml_use_internal_errors(true);
		libxml_clear_errors();
		$reader = new \XMLReader;

		try {
			if (!$reader->open($this->path)) {
				throw new \RuntimeException("Cannot open the stamp feed file {$this->path}.");
			}

			// root element
			while ($reader->read() && $reader->nodeType !== \XMLReader::ELEMENT);
			$this->checkXmlErrors();
			if ($reader->name !== self::ROOT) {
				throw new \RuntimeException("Unexpected root element <{$reader->name}> in the stamp feed, expected <" . self::ROOT . '>.');
			}
			$schemaVersion = $reader->getAttribute('schema_version');
			if ($schemaVersion !== self::SCHEMA_VERSION) {
				throw new \RuntimeException("Unsupported stamp feed schema_version \"{$schemaVersion}\", expected \"" . self::SCHEMA_VERSION . '".');
			}

			$hasNode = $reader->read();
			while ($hasNode) {
				if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'tourist_stamp') {
					$hasNode = $reader->read();
					continue;
				}

				$element = simplexml_load_string($reader->readOuterXml());
				$this->checkXmlErrors();
				if ($element === false) {
					throw new \RuntimeException('Invalid XML in the stamp feed: cannot parse <tourist_stamp>.');
				}
				$hasNode = $reader->next();

				$stamp = $this->normalize($element);
				if ($stamp !== null) {
					yield $stamp;
				}
			}
			$this->checkXmlErrors();

		} finally {
			$reader->close();
			libxml_clear_errors();
			libxml_use_internal_errors($useInternalErrors);
		}
	}


	/**
	 * Number of CZ stamps skipped by the last read() because they lack a number or a location.
	 */
	public function getSkippedCount(): int
	{
		return $this->skippedCount;
	}


	/**
	 * @return array{id: int, source_id: int, name: string, lat: string, lng: string, description: ?string, detail_url: ?string, website: ?string, wikipedia: ?string, facebook: ?string, image_url: ?string, salespoints: list<array{source_id: int, name: string, lat: ?string, lng: ?string, website: ?string, sort: ?int, system: bool}>}|null
	 */
	private function normalize(\SimpleXMLElement $stamp): ?array
	{
		if (self::text($stamp, 'country', 'code') !== self::COUNTRY) {
			return null;
		}

		$number = self::text($stamp, 'number');
		$lat = self::text($stamp, 'location', 'latitude');
		$lng = self::text($stamp, 'location', 'longitude');
		if ($number === null || $lat === null || $lng === null) {
			$this->skippedCount++;
			return null;
		}

		$salespoints = [];
		foreach ($stamp->salespoints->salespoint ?? [] as $salespoint) {
			$sort = (string) $salespoint['sort'];
			$salespoints[] = [
				'source_id' => (int) $salespoint['id'],
				'name' => (string) $salespoint->name,
				'lat' => self::text($salespoint, 'location', 'latitude'),
				'lng' => self::text($salespoint, 'location', 'longitude'),
				'website' => self::text($salespoint, 'website'),
				'sort' => $sort === '' ? null : (int) $sort,
				'system' => (string) $salespoint['system'] === 'true',
			];
		}

		return [
			'id' => (int) $number,
			'source_id' => (int) $stamp['id'],
			'name' => (string) $stamp->name,
			'lat' => $lat,
			'lng' => $lng,
			'description' => self::text($stamp, 'description'),
			'detail_url' => self::text($stamp, 'links', 'detail'),
			'website' => self::text($stamp, 'links', 'website'),
			'wikipedia' => self::text($stamp, 'links', 'wikipedia'),
			'facebook' => self::text($stamp, 'links', 'facebook'),
			'image_url' => self::text($stamp, 'primary_image', 'url'),
			'salespoints' => $salespoints,
		];
	}


	/**
	 * Text of the nested child element, or null when it is missing.
	 */
	private static function text(\SimpleXMLElement $element, string ...$path): ?string
	{
		foreach ($path as $name) {
			if (!isset($element->$name)) {
				return null;
			}
			$element = $element->$name;
		}
		return (string) $element;
	}


	private function checkXmlErrors(): void
	{
		$error = libxml_get_errors()[0] ?? null;
		if ($error !== null) {
			throw new \RuntimeException(sprintf(
				'Invalid XML in the stamp feed on line %d: %s',
				$error->line,
				trim($error->message),
			));
		}
	}

}
