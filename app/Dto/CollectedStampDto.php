<?php

declare(strict_types=1);

namespace App\Dto;

use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class CollectedStampDto
{
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly ?string $type,
		public readonly ?string $region,
		public readonly ?DateTime $createdAt,
		public readonly string $lat,
		public readonly string $lng,
		public readonly ?string $image,
		public readonly ?DateTime $date,
		public readonly ?string $comment,
		public readonly ?string $file,
	) {
	}

	/**
	 * @param  ActiveRow|\ArrayAccess<string, mixed>  $row  stamp joined with its collection record
	 */
	public static function fromActiveRow(\ArrayAccess $row): self
	{
		return new self(
			id: $row['id'],
			name: $row['name'],
			type: $row['type'],
			region: $row['region'],
			createdAt: $row['created_at'],
			lat: $row['lat'],
			lng: $row['lng'],
			image: $row['image'],
			date: $row['date'],
			comment: $row['comment'],
			file: $row['file'],
		);
	}

}
