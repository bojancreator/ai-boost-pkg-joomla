# 4SEF - Database support

# Tables are created here. Column updates are handled in package installation script file.

### 4SEF dataset

### 4SEF keystore
CREATE TABLE IF NOT EXISTS `#__forsef_keystore`
(
    `id`              int unsigned                                                    NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `scope`           VARCHAR(40)                                                     NOT NULL DEFAULT 'default',
    `key`             VARCHAR(150)                                                    NOT NULL,
    `value`           VARCHAR(16000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `large_value`     MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci     NOT NULL,
    `user_id`         int                                                             NOT NULL DEFAULT 0,
    `version`         int unsigned                                                    NOT NULL DEFAULT 0 COMMENT 'Future use',
    `lock`            CHAR(40)                                                        NOT NULL DEFAULT '' COMMENT 'Exclusive use of a value',
    `lock_expires_at` DATETIME                                                        NULL,
    `format`          TINYINT                                                         NOT NULL DEFAULT 1,
    `modified_at`     DATETIME                                                        NOT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `main` (`scope`, `key`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### 4SEF configurations
CREATE TABLE IF NOT EXISTS `#__forsef_config`
(
    `id`              int unsigned                                                    NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `scope`           VARCHAR(40)                                                     NOT NULL DEFAULT 'default',
    `key`             VARCHAR(150)                                                    NOT NULL,
    `value`           VARCHAR(16000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `large_value`     MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci     NOT NULL,
    `user_id`         int unsigned                                                    NOT NULL DEFAULT 0,
    `version`         int unsigned                                                    NOT NULL DEFAULT 0 COMMENT 'Future use',
    `lock`            CHAR(40)                                                        NOT NULL DEFAULT '',
    `lock_expires_at` DATETIME                                                        NULL,
    `format`          TINYINT                                                         NOT NULL DEFAULT 1,
    `modified_at`     DATETIME                                                        NOT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `main` (`scope`, `key`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Dismissable messages
CREATE TABLE IF NOT EXISTS `#__forsef_messages`
(
    `id`            int unsigned                                                   NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `user_id`       int                                                            NOT NULL DEFAULT 0,
    `scope`         VARCHAR(40)                                                    NOT NULL DEFAULT 'default' COMMENT 'Scope within the table',
    `msg_id`        VARCHAR(120)                                                   NOT NULL COMMENT 'Unique ID within the scope',
    `type`          VARCHAR(40)                                                    NOT NULL DEFAULT '3_info' COMMENT 'options: 1_danger, 2_warning, 3_info',
    `dismiss_type`  TINYINT unsigned                                               NOT NULL DEFAULT 1 COMMENT '0: not dismissable, 1: dismissable, 2: postponable',
    `postpone_spec` VARCHAR(190)                                                   NOT NULL COMMENT 'Dismiss postpone period specification',
    `title`         VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT 'Message title',
    `body`          VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Message HTML body',
    `created_at`    DATETIME                                                       NOT NULL,
    `dismissed_at`  DATETIME,
    `show_at`       DATETIME                                                       NOT NULL,
    `state`         TINYINT                                                        NOT NULL DEFAULT 0 COMMENT '0: created, 1: pending, 2: dismissed, 3: closed',

    PRIMARY KEY (`id`),
    KEY `main` (`scope`, `msg_id`),
    KEY (`show_at`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### SEF/Non-SEF pair in legacy sh404SEF format
CREATE TABLE IF NOT EXISTS `#__forsef_urls`
(
    `id`          int unsigned                                                   NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `sef`         VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `base_path`   VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `extra_path`  VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci   NOT NULL DEFAULT '',
    `nonsef`      VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `base_nonsef` VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `platform`    VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `custom`      TINYINT                                                        NOT NULL DEFAULT 0 COMMENT '0: auto, 1: custom',
    `duplicate`   TINYINT                                                        NOT NULL DEFAULT 0 COMMENT '0: canonical, 1: duplicate',
    `extension`   VARCHAR(255)                                                   NOT NULL DEFAULT '',
    `hits`        int                                                            NOT NULL DEFAULT 0,
    `last_hit`    DATETIME                                                       NULL,
    `state`       TINYINT                                                        NOT NULL DEFAULT 1 COMMENT '0: disabled, 1: enabled. Future use.',

    PRIMARY KEY (`id`),
    KEY `sef` (`sef` (190)),
    KEY `base_path` (`base_path` (190)),
    KEY `custom` (`custom`),
    KEY `duplicate` (`duplicate`),
    KEY `nonsef` (`nonsef` (190)),
    KEY `base_nonsef` (`base_nonsef` (190)),
    KEY `extension` (`extension` (190))

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### SEF/Non-SEF pair in legacy sh404SEF format
CREATE TABLE IF NOT EXISTS `#__forsef_redirects`
(
    `id`       int unsigned                                                   NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `source`   VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `target`   VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `hits`     int                                                            NOT NULL DEFAULT 0,
    `last_hit` DATETIME                                                       NULL,
    `state`    TINYINT                                                        NOT NULL DEFAULT 1 COMMENT '0: disabled, 1: enabled. Future use.',

    PRIMARY KEY (`id`),
    KEY `source` (`source` (190)),
    KEY `target` (`target` (190))

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### shURLs
CREATE TABLE IF NOT EXISTS `#__forsef_legacy_pageids`
(
    `id`     int unsigned                                                   NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `newurl` VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Non-sef URL target',
    `pageid` VARCHAR(255)                                                   NOT NULL DEFAULT '' COMMENT 'shURL',
    `type`   TINYINT(3)                                                     NOT NULL DEFAULT 0 COMMENT 'Not used',
    `hits`   int                                                            NOT NULL DEFAULT 0,

    PRIMARY KEY (`id`),
    KEY `newurl` (`newurl` (190)),
    KEY `alias` (`pageid` (190)),
    KEY `type` (`type`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Hits stats
CREATE TABLE IF NOT EXISTS `#__forsef_stats_dailies`
(
    `id`           int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `period_start` DATETIME     NOT NULL COMMENT 'UTC date time of the collection period start',
    `hits`         int unsigned DEFAULT 0 COMMENT 'Number of hits for the period',
    `hits_bots`    int unsigned DEFAULT 0 COMMENT 'Number of hits by bots for the period',
    `hits_se`      int unsigned DEFAULT 0 COMMENT 'Number of hits by search engines for the period',

    PRIMARY KEY (`id`),
    KEY (`period_start`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;
