-- JoomlaBoost Plugin - Installation SQL
-- Creates necessary database tables

-- Translation table for multi-language Schema.org fields
CREATE TABLE IF NOT EXISTS `#__joomlaboost_translations` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Multi-language translations for JoomlaBoost Schema.org fields';

-- Settings persistence table (already exists in SettingsPersistenceService, documenting here)
CREATE TABLE IF NOT EXISTS `#__joomlaboost_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `settings_json` MEDIUMTEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Plugin settings persistence across reinstalls';
