-- AI Boost for Joomla — Component Install SQL v0.7.0

CREATE TABLE IF NOT EXISTS `#__aiboost_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `settings_json` MEDIUMTEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__aiboost_translations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `field_key` VARCHAR(100) NOT NULL,
  `lang_code` VARCHAR(10) NOT NULL,
  `field_value` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_field_lang` (`field_key`, `lang_code`),
  KEY `idx_field_key` (`field_key`),
  KEY `idx_lang_code` (`lang_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__aiboost_redirects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_url` VARCHAR(1000) NOT NULL,
  `to_url` VARCHAR(1000) NOT NULL,
  `redirect_type` SMALLINT UNSIGNED NOT NULL DEFAULT 301,
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_from_url` (`from_url`(191)),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__aiboost_url_scans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `total_urls` INT UNSIGNED NOT NULL DEFAULT 0,
  `done_urls` INT UNSIGNED NOT NULL DEFAULT 0,
  `current_url` VARCHAR(2000) NOT NULL DEFAULT '',
  `queue_json` MEDIUMTEXT NULL,
  `results_json` MEDIUMTEXT NULL,
  `error_message` VARCHAR(500) NOT NULL DEFAULT '',
  `started_at` DATETIME NOT NULL,
  `finished_at` DATETIME NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__aiboost_error_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` DATETIME NOT NULL,
  `severity` VARCHAR(16) NOT NULL DEFAULT 'info',
  `source` VARCHAR(100) NOT NULL DEFAULT '',
  `message` VARCHAR(1000) NOT NULL,
  `context_json` MEDIUMTEXT NULL,
  `request_id` VARCHAR(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__aiboost_404_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_url` VARCHAR(2000) NOT NULL,
  `referrer` VARCHAR(2000) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(500) NOT NULL DEFAULT '',
  `hits` INT UNSIGNED NOT NULL DEFAULT 1,
  `first_seen` DATETIME NOT NULL,
  `last_seen` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_request_url` (`request_url`(191)),
  KEY `idx_last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
