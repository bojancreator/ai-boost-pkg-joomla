-- AI Boost for Joomla — Component Installation SQL
-- Creates #__aiboost_settings and #__aiboost_translations tables

CREATE TABLE IF NOT EXISTS `#__aiboost_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `settings_json` MEDIUMTEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI Boost plugin settings persistence';

CREATE TABLE IF NOT EXISTS `#__aiboost_translations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `field_key` VARCHAR(100) NOT NULL COMMENT 'Field identifier (e.g., org_name, schema_description)',
  `lang_code` VARCHAR(10) NOT NULL COMMENT 'ISO language code (e.g., en, sr, ru, fr)',
  `field_value` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_field_lang` (`field_key`, `lang_code`),
  KEY `idx_field_key` (`field_key`),
  KEY `idx_lang_code` (`lang_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI Boost multi-language field translations';

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI Boost central error / event log';
