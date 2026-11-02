-- Stamps imported from the turisticke-znamky.cz XML feed (#14)
-- Run manually on production before deploying.

ALTER TABLE `tz_stamps`
	MODIFY `type` varchar(255) COLLATE utf8mb4_czech_ci NULL,
	MODIFY `region` varchar(255) COLLATE utf8mb4_czech_ci NULL,
	MODIFY `created_at` date NULL,
	ADD `source_id` int(11) NULL AFTER `id`,
	ADD `description` text COLLATE utf8mb4_czech_ci NULL,
	ADD `detail_url` text COLLATE utf8mb4_czech_ci NULL,
	ADD `website` text COLLATE utf8mb4_czech_ci NULL,
	ADD `wikipedia` text COLLATE utf8mb4_czech_ci NULL,
	ADD `facebook` text COLLATE utf8mb4_czech_ci NULL,
	ADD `image_url` text COLLATE utf8mb4_czech_ci NULL;

CREATE TABLE `tz_salespoints` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`stamp_id` int(11) NOT NULL,
	`source_id` int(11) NOT NULL,
	`name` text COLLATE utf8mb4_czech_ci NOT NULL,
	`lat` varchar(255) COLLATE utf8mb4_czech_ci NULL,
	`lng` varchar(255) COLLATE utf8mb4_czech_ci NULL,
	`website` text COLLATE utf8mb4_czech_ci NULL,
	`sort` int(11) NULL,
	`system` tinyint(1) NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `stamp_id` (`stamp_id`),
	CONSTRAINT `tz_salespoints_stamp` FOREIGN KEY (`stamp_id`) REFERENCES `tz_stamps` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
