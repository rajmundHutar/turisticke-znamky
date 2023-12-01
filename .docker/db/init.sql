CREATE DATABASE tz;
USE tz;

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `tz_collection`;
CREATE TABLE `tz_collection` (
                                 `id` int(11) NOT NULL AUTO_INCREMENT,
                                 `stamp_id` int(11) NOT NULL,
                                 `user_id` int(11) NOT NULL,
                                 `date` datetime DEFAULT NULL,
                                 `comment` text COLLATE utf8mb4_czech_ci,
                                 PRIMARY KEY (`id`),
                                 KEY `stamp_id` (`stamp_id`),
                                 KEY `user_id` (`user_id`),
                                 CONSTRAINT `tz_collection_ibfk_4` FOREIGN KEY (`stamp_id`) REFERENCES `tz_stamps` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                 CONSTRAINT `tz_collection_ibfk_5` FOREIGN KEY (`user_id`) REFERENCES `tz_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `tz_import_log`;
CREATE TABLE `tz_import_log` (
                                 `id` int(11) NOT NULL AUTO_INCREMENT,
                                 `date` datetime NOT NULL,
                                 `parsed` int(11) NOT NULL,
                                 `new` int(11) NOT NULL,
                                 `time` int(11) NOT NULL,
                                 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `tz_stamps`;
CREATE TABLE `tz_stamps` (
                             `id` int(11) NOT NULL AUTO_INCREMENT,
                             `name` text COLLATE utf8mb4_czech_ci NOT NULL,
                             `type` varchar(255) COLLATE utf8mb4_czech_ci NOT NULL,
                             `region` varchar(255) COLLATE utf8mb4_czech_ci NOT NULL,
                             `created_at` date NOT NULL,
                             `lat` varchar(255) COLLATE utf8mb4_czech_ci NOT NULL,
                             `lng` varchar(255) COLLATE utf8mb4_czech_ci NOT NULL,
                             `image` text COLLATE utf8mb4_czech_ci,
                             PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `tz_users`;
CREATE TABLE `tz_users` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `email` varchar(255) COLLATE utf8mb4_czech_ci NOT NULL,
                            `password` varchar(255) COLLATE utf8mb4_czech_ci NOT NULL,
                            `role` varchar(255) COLLATE utf8mb4_czech_ci NOT NULL DEFAULT 'member',
                            PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
-- 2023-11-07 16:47:31
