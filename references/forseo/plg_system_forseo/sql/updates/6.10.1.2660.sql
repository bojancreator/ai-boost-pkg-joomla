# 4SEO - Database support

# Tables are created here. Column updates are handled in package installation script file.

### 4SEO dataset

### 4SEO keystore
CREATE TABLE IF NOT EXISTS `#__forseo_keystore`
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

### 4SEO configurations
CREATE TABLE IF NOT EXISTS `#__forseo_config`
(
    `id`              int unsigned                                                    NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `scope`           VARCHAR(40)                                                     NOT NULL DEFAULT 'default',
    `key`             VARCHAR(150)                                                    NOT NULL,
    `value`           VARCHAR(16000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `large_value`     MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci     NOT NULL,
    `user_id`         int                                                             NOT NULL DEFAULT 0,
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

### URLs collected for crawling
CREATE TABLE IF NOT EXISTS `#__forseo_collected_urls`
(
    `id`               int unsigned                                                    NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `crawled_by`       VARCHAR(32)                                                     NULL     DEFAULT '' COMMENT 'UUIDV1 of crawler process',
    `crawl_started_at` DATETIME                                                        NULL COMMENT 'Timestamp of crawl start',
    `crawl_timeout_at` DATETIME                                                        NULL COMMENT 'Timestamp of when crawl should be done',
    `status`           SMALLINT                                                        NOT NULL DEFAULT 0 COMMENT '0: ok, 1: crawl error, 400+: error',
    `target`           SMALLINT                                                        NOT NULL DEFAULT 0 COMMENT '0: internal, 1: external',
    `url`              VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci   NOT NULL COMMENT 'Indexable URL representation',
    `full_url`         VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Full URL as stored',
    `referrers`        VARCHAR(14000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'JSON list of referrers',
    `click_depth`      SMALLINT                                                        NOT NULL DEFAULT -1 COMMENT 'Clicks to reach this URL',
    `attempts`         TINYINT                                                         NOT NULL DEFAULT 0,
    `priority`         TINYINT                                                         NOT NULL DEFAULT 0 COMMENT 'Crawl priority, 0 is normal',

    PRIMARY KEY (`id`),
    UNIQUE KEY (`url`),
    KEY (`crawled_by`),
    KEY (`crawl_timeout_at`),
    KEY (`priority`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### URLs excluded from crawling
CREATE TABLE IF NOT EXISTS `#__forseo_excluded_urls`
(
    `id`       int unsigned                                                   NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `url`      VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Indexable URL representation',
    `full_url` VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full URL as stored',

    PRIMARY KEY (`id`),
    UNIQUE KEY (`url`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Main pages storage
CREATE TABLE IF NOT EXISTS `#__forseo_pages`
(
    `id`              int unsigned                                                   NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `status`          SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: ok, 400+: error',
    `perf_status`     TINYINT                                                        NOT NULL DEFAULT 0 COMMENT '0: no data, 1: ok, 2: failing',
    `url`             VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Indexable version of requested URL, with any query var',
    `full_url`        VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full requested URL as stored',
    `non_sef_vars`    VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'json of non_sef vars from path',
    `input_vars`      VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'json of non_sef vars set as input',
    `query`           VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'json of query vars',
    `content_id`      VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Indexable id of the content displayed by this page',
    `full_content_id` VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full content id as stored',
    `lang`            VARCHAR(50)                                                    NOT NULL DEFAULT '' COMMENT 'Language tag as used by platform',
    `page`            VARCHAR(50)                                                    NOT NULL DEFAULT '' COMMENT 'Display page id, Itemid on Joomla',
    `extension`       VARCHAR(50)                                                    NOT NULL DEFAULT '' COMMENT 'Extension creating the page',
    `view`            VARCHAR(50)                                                    NOT NULL DEFAULT '' COMMENT 'Extension view',
    `layout`          VARCHAR(50)                                                    NOT NULL DEFAULT '' COMMENT 'Extension layout',
    `item_id`         VARCHAR(190)                                                   NOT NULL DEFAULT '' COMMENT 'Id of the item viewed, can be array as json',
    `content_lang`    VARCHAR(40)                                                    NOT NULL DEFAULT '' COMMENT 'Language tag for content',
    `hash`            VARCHAR(40)                                                    NOT NULL DEFAULT '' COMMENT 'Content hash',
    `hash_links`      VARCHAR(40)                                                    NOT NULL DEFAULT '' COMMENT 'Links in content hash',
    `hash_images`     VARCHAR(40)                                                    NOT NULL DEFAULT '' COMMENT 'Images in content hash',
    `scheme`          VARCHAR(40)                                                    NOT NULL DEFAULT '' COMMENT 'Scheme used when page data was stored',
    `host`            VARCHAR(100)                                                   NOT NULL DEFAULT '' COMMENT 'Host used when page data was stored',
    `click_depth`     SMALLINT                                                       NOT NULL DEFAULT -1 COMMENT 'Clicks to reach page, -1 = no click, page is not linked',
    `last_hit`        DATETIME                                                       NOT NULL COMMENT 'Last request for that page',
    `hits`            int unsigned                                                   NOT NULL DEFAULT 0 COMMENT 'Number of requests',
    `canonical_mode`  SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: auto, 1: user',
    `canonical_user`  SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: included, 1: excluded',
    `canonical_auto`  SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: included, 1: excluded',
    `sitemap_mode`    SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: auto, 1: user',
    `sitemap_user`    SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: included, 1: excluded',
    `sitemap_auto`    SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: included, 1: excluded',
    `crawled_at`      DATETIME                                                       NOT NULL,
    `modified_at`     DATETIME,
    `enabled`         TINYINT                                                        NOT NULL DEFAULT 1 COMMENT '1: enabled, 0: disabled',

    PRIMARY KEY (`id`),
    UNIQUE KEY (`url`),
    KEY (`lang`),
    KEY (`content_id`),
    KEY (`extension`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Pages aliases storage
CREATE TABLE IF NOT EXISTS `#__forseo_pages_aliases`
(
    `id`         int unsigned                                                   NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `url`        VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Indexable version of aliased URL, with any query var',
    `full_url`   VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full URL',
    `alias`      VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Indexable version of aliased URL, with any query var',
    `full_alias` VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full alias URL',
    `last_hit`   DATETIME COMMENT 'Last execution of this alias',
    `hits`       int unsigned                                                   NOT NULL DEFAULT 0 COMMENT 'Number of hits of this alias',
    `enabled`    TINYINT                                                        NOT NULL DEFAULT 1 COMMENT '1: enabled, 0: disabled',

    PRIMARY KEY (`id`),
    KEY (`url`),
    UNIQUE KEY (`alias`),
    KEY (`hits`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Main images storage
CREATE TABLE IF NOT EXISTS `#__forseo_images`
(
    `id`            int unsigned                                                   NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `status`        SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: ok, 400+: error',
    `url`           VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Indexable version of image URL, with any query var',
    `full_url`      VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full image URL as found in page',
    `page_url`      VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Indexable version of the URL of the page where this image is located',
    `page_full_url` VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full URL of the page where this image is located',
    `target`        SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: internal, 1: external',
    `data`          VARCHAR(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Image information as JSON',
    `sitemap_mode`  SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: auto, 1: user',
    `sitemap_user`  SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: included, 1: excluded',
    `sitemap_auto`  SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: included, 1: excluded',
    `crawled_at`    DATETIME                                                       NOT NULL,
    `modified_at`   DATETIME,

    PRIMARY KEY (`id`),
    UNIQUE KEY `main` (`page_url`, `url`),
    KEY (`url`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Links
CREATE TABLE IF NOT EXISTS `#__forseo_links`
(
    `id`              int unsigned                                                   NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `status`          SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: ok, 400+: error',
    `target`          SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: internal, 1: external',
    `url`             VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Indexable version of requested URL, with any query var',
    `full_url`        VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full requested URL as stored',
    `scheme`          VARCHAR(40)                                                    NOT NULL DEFAULT '' COMMENT 'Scheme used when page data was stored',
    `host`            VARCHAR(100)                                                   NOT NULL DEFAULT '' COMMENT 'Host used when page data was stored',
    `final_url`       VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Indexable version of the final redirect',
    `full_final_url`  VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full URL for final redirect',
    `redirects_count` SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '',
    `last_hit`        DATETIME                                                       NOT NULL COMMENT 'Last request for that page',
    `hits`            int unsigned                                                   NOT NULL DEFAULT 0 COMMENT 'Number of requests',

    PRIMARY KEY (`id`),
    UNIQUE KEY (`url`),
    KEY (`final_url`),
    KEY (`host`),
    KEY (`hits`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Pages in error storage
CREATE TABLE IF NOT EXISTS `#__forseo_errors`
(
    `id`       int unsigned                                                   NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `status`   SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT 'HTTP status',
    `message`  VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Optional error message, for internal errors',
    `target`   SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: internal, 1: external',
    `source`   SMALLINT                                                       NOT NULL DEFAULT 0 COMMENT '0: internal, 1: external, 2: crawl, 3: unknown',
    `url`      VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Indexable URL representation',
    `full_url` VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full URL as stored',
    `last_hit` DATETIME                                                       NOT NULL COMMENT 'Last request for that page',
    `hits`     int unsigned                                                   NOT NULL DEFAULT 0 COMMENT 'Number of requests',
    PRIMARY KEY (`id`),
    KEY (`url`) COMMENT 'Not unique, allow multiple status per url',
    KEY (`last_hit`) COMMENT 'To help get more recent errors first',
    KEY (`hits`) COMMENT 'To sort by largest number of hits'
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Custom Meta data
CREATE TABLE IF NOT EXISTS `#__forseo_custom_meta`
(
    `id`                 int unsigned                                                    NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `source`             TINYINT                                                         NOT NULL DEFAULT 0 COMMENT '0: user, 1: built in, 100: import sh404SEF',
    `content_id`         VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci   NOT NULL COMMENT 'Indexable id of the content displayed by this page',
    `url`                VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Indexable version of URL. Secondary id, only there if custom meta stored for a duplicate.',
    `data`               VARCHAR(14000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Meta data content as JSON',
    `status_title`       SMALLINT                                                        NOT NULL DEFAULT 0 COMMENT '0: auto, 1: platform, 2: custom, 3: none',
    `status_description` SMALLINT                                                        NOT NULL DEFAULT 0 COMMENT '0: auto, 1: platform, 2: custom, 3: none',
    `hash_title`         VARCHAR(40)                                                     NOT NULL DEFAULT '' COMMENT 'Page title hash',
    `hash_description`   VARCHAR(40)                                                     NOT NULL DEFAULT '' COMMENT 'Meta desc hash',
    `crawled_at`         DATETIME                                                        NOT NULL,
    `enabled`            TINYINT                                                         NOT NULL DEFAULT 1 COMMENT '1: enabled, 0: disabled',
    PRIMARY KEY (`id`),
    KEY (`content_id`),
    KEY (`url`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Custom OGP/TCards
CREATE TABLE IF NOT EXISTS `#__forseo_custom_social`
(
    `id`         int unsigned                                                    NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `source`     TINYINT                                                         NOT NULL DEFAULT 0 COMMENT '0: user, 1: built in, 100: import sh404SEF',
    `content_id` VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci   NOT NULL COMMENT 'Indexable id of the content displayed by this page',
    `url`        VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Indexable version of URL. Secondary id, only there if custom social data stored for a duplicate.',
    `data`       VARCHAR(14000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Custom social data content as JSON',
    `crawled_at` DATETIME                                                        NOT NULL,
    `enabled`    TINYINT                                                         NOT NULL DEFAULT 1 COMMENT '1: enabled, 0: disabled',
    PRIMARY KEY (`id`),
    KEY (`content_id`),
    KEY (`url`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Store for referrers
CREATE TABLE IF NOT EXISTS `#__forseo_referrers`
(
    `id`       int unsigned                                                   NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `url`      VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Indexable URL representation',
    `full_url` VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full URL as stored',

    PRIMARY KEY (`id`),
    UNIQUE KEY (`url`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Alternate URLs of the main ones
DROP TABLE IF EXISTS `#__forseo_alternate_urls`;

### Pages referrers
CREATE TABLE IF NOT EXISTS `#__forseo_referrers_pages`
(
    `id`          int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `referrer_id` int unsigned NOT NULL COMMENT 'Id in the referrers table',
    `referree_id` int unsigned NOT NULL COMMENT 'Id in the pages table',

    PRIMARY KEY (`id`),
    KEY (`referrer_id`),
    KEY (`referree_id`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Links referrers
CREATE TABLE IF NOT EXISTS `#__forseo_referrers_links`
(
    `id`          int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `referrer_id` int unsigned NOT NULL COMMENT 'Id in the referrers table',
    `referree_id` int unsigned NOT NULL COMMENT 'Id in the pages table',

    PRIMARY KEY (`id`),
    KEY (`referrer_id`),
    KEY (`referree_id`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Errors referrers
CREATE TABLE IF NOT EXISTS `#__forseo_referrers_errors`
(
    `id`          int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `referrer_id` int unsigned NOT NULL COMMENT 'Id in the referrers table',
    `referree_id` int unsigned NOT NULL COMMENT 'Id in the errors table',

    PRIMARY KEY (`id`),
    KEY (`referrer_id`),
    KEY (`referree_id`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### SEO rules definitions
CREATE TABLE IF NOT EXISTS `#__forseo_rules`
(
    `id`            int unsigned                                                    NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `type`          SMALLINT                                                        NOT NULL DEFAULT 0 COMMENT 'See Data - Rule object',
    `source`        TINYINT                                                         NOT NULL DEFAULT 0 COMMENT '0: user, 1: built in, 100: import sh404SEF',
    `title`         VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci   NOT NULL COMMENT 'Custom title',
    `rule`          VARCHAR(14000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'JSON rule definition',
    `last_hit`      DATETIME                                                        NULL COMMENT 'Last use of that rule',
    `hits`          int unsigned                                                    NOT NULL DEFAULT 0 COMMENT 'Number of use of that rule',
    `enabled`       TINYINT                                                         NOT NULL DEFAULT 1 COMMENT '1: enabled, 0: disabled',
    `valid`         TINYINT                                                         NOT NULL DEFAULT 1 COMMENT 'Not implemented, future use. 0: invalid, 1: valid',
    `enabled_after` DATETIME                                                        NULL COMMENT 'Optional start enable datetime',
    `enabled_until` DATETIME                                                        NULL COMMENT 'Optional enable until dateime',
    `ordering`      int                                                             NOT NULL DEFAULT 0 COMMENT 'Ordering within type',

    PRIMARY KEY (`id`),
    UNIQUE KEY `type_ordering` (`type`, `ordering`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Sitemaps generation and use
CREATE TABLE IF NOT EXISTS `#__forseo_sitemaps`
(
    `id`                  int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `type`                TINYINT      NOT NULL DEFAULT 0 COMMENT '0: content, 1: news, 3: images, 4: videos',
    `file_type`           TINYINT      NOT NULL DEFAULT 0 COMMENT '0: index, 1: partial',
    `lang`                VARCHAR(50)  NOT NULL DEFAULT '' COMMENT 'Language tag as used by platform',
    `crawl_id`            VARCHAR(40)  NOT NULL COMMENT 'Id of crawl data used to generate the sitemap',
    `hash`                VARCHAR(40)  NOT NULL COMMENT 'SHA1 hash of the file content',
    `created_at`          DATETIME     NULL COMMENT 'Sitemap generation completion',
    `url_count`           int unsigned NOT NULL DEFAULT 0 COMMENT 'Total number of URLs in the sitemap',
    `processed_url_count` int unsigned NOT NULL DEFAULT 0 COMMENT 'Total number of URLs already added to the sitemap',
    `image_count`         int unsigned NOT NULL DEFAULT 0 COMMENT 'Total number of images in the sitemap',
    `serial`              int unsigned NOT NULL DEFAULT 0 COMMENT 'If partial, serial number in series, not used otherwise',
    `google_submitted_at` DATETIME     NULL COMMENT 'When this sitemap was submitted to Google',
    `google_last_fetch`   DATETIME     NULL COMMENT 'Last time this sitemap was fetched by Google Bot',
    `google_fetches`      int unsigned NOT NULL DEFAULT 0 COMMENT 'Total number of fetches of that sitemap by Google Bot',
    `bing_submitted_at`   DATETIME     NULL COMMENT 'When this sitemap was submitted to Google',
    `bing_last_fetch`     DATETIME     NULL COMMENT 'Last time this sitemap was fetched by Bing Bot',
    `bing_fetches`        int unsigned NOT NULL DEFAULT 0 COMMENT 'Total number of fetches of that sitemap by Bing Bot',
    `state`               TINYINT      NOT NULL DEFAULT 0 COMMENT '0: ready, 1: stale, 2: in_progress',
    `enabled`             TINYINT      NOT NULL DEFAULT 1 COMMENT '1: enabled, 0: disabled',

    PRIMARY KEY (`id`),
    KEY (`created_at`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Sitemaps manual exclusions
CREATE TABLE IF NOT EXISTS `#__forseo_sitemaps_includes`
(
    `id`           int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `url`          VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Indexable version of URL. Secondary id, only there if custom meta stored for a duplicate.',
    `sitemap_mode` SMALLINT     NOT NULL DEFAULT 0 COMMENT '0: auto, 1: user',
    `sitemap_user` SMALLINT     NOT NULL DEFAULT 0 COMMENT '0: included, 1: excluded',
    PRIMARY KEY (`id`),
    KEY (`url`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__forseo_canonical_includes`
(
    `id`             int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `url`            VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Indexable version of URL. Secondary id, only there if custom meta stored for a duplicate.',
    `canonical_mode` SMALLINT     NOT NULL DEFAULT 0 COMMENT '0: auto, 1: user',
    `canonical_user` SMALLINT     NOT NULL DEFAULT 0 COMMENT '0: included, 1: excluded',
    PRIMARY KEY (`id`),
    KEY (`url`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Performance data
CREATE TABLE IF NOT EXISTS `#__forseo_perf_data`
(
    `id`       int unsigned                                                   NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `url`      VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Indexable URL representation',
    `full_url` VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full URL as stored',
    `fid`      int unsigned    DEFAULT 0 COMMENT 'FID value in 1/1000 ms',
    `inp`      int unsigned    DEFAULT 0 COMMENT 'INP value in 1/1000 ms',
    `lcp`      int unsigned    DEFAULT 0 COMMENT 'LCP value in 1/1000 ms',
    `cls`      int unsigned    DEFAULT 0 COMMENT 'CLS value in 1/1000 ms',
    `ttfb`     int unsigned    DEFAULT 0 COMMENT 'TTFB value in 1/1000 ms',
    `ts`       BIGINT unsigned DEFAULT 0 COMMENT 'ms since Epoch',
    `device`   TINYINT         DEFAULT 0 COMMENT '0: mobile, 1: desktop',


    PRIMARY KEY (`id`),
    KEY url_ts (`url`, `ts`),
    KEY (`fid`),
    KEY (`inp`),
    KEY (`lcp`),
    KEY (`cls`),
    KEY (`ttfb`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__forseo_perf_data_agg`
(
    `id`          int unsigned                                                   NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `state`       TINYINT                                                        NOT NULL DEFAULT 0 COMMENT '0: ready, 1: not enough data',
    `url`         VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Indexable URL representation',
    `full_url`    VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full URL as stored',
    `fid`         int unsigned                                                            DEFAULT 0 COMMENT 'FID value in 1/1000 ms',
    `fid_count`   int unsigned                                                   NOT NULL DEFAULT 0 COMMENT 'FID data points',
    `inp`         int unsigned                                                            DEFAULT 0 COMMENT 'INP value in 1/1000 ms',
    `inp_count`   int unsigned                                                   NOT NULL DEFAULT 0 COMMENT 'INP data points',
    `lcp`         int unsigned                                                            DEFAULT 0 COMMENT 'LCP value in 1/1000 ms',
    `lcp_count`   int unsigned                                                   NOT NULL DEFAULT 0 COMMENT 'LCP data points',
    `cls`         int unsigned                                                            DEFAULT 0 COMMENT 'CLS value in 1/1000 ms',
    `cls_count`   int unsigned                                                   NOT NULL DEFAULT 0 COMMENT 'CLS data points',
    `ttfb`        int unsigned                                                            DEFAULT 0 COMMENT 'TTFB value in 1/1000 ms',
    `ttfb_count`  int unsigned                                                   NOT NULL DEFAULT 0 COMMENT 'TTFB data points',
    `modified_at` DATETIME                                                       NOT NULL,
    `device`      TINYINT                                                                 DEFAULT 0 COMMENT '0: mobile, 1: desktop',

    PRIMARY KEY (`id`),
    KEY (`url`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

### Dismissable messages
CREATE TABLE IF NOT EXISTS `#__forseo_messages`
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

## Google Search Console data
CREATE TABLE IF NOT EXISTS `#__forseo_gsc_daily_sched`
(
    `id`              int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `date`            DATE         NOT NULL COMMENT 'Pacific Time date for which data is stored',
    `status`          SMALLINT     NOT NULL DEFAULT 0 COMMENT '0: pending, 1: no data available, 2: ok, 3: failed',
    `locked_by`       VARCHAR(32)  NULL     DEFAULT '' COMMENT 'UUIDV1 of crawler process',
    `lock_started_at` DATETIME     NULL COMMENT 'Timestamp of crawl start',
    `lock_timeout_at` DATETIME     NULL COMMENT 'Timestamp of when crawl should be done',
    `retry_after`     DATETIME     NULL COMMENT 'Timestamp after which can retry fetching',
    `attempts`        TINYINT      NOT NULL DEFAULT 0 COMMENT 'Number of (failed) attempts already',
    `priority`        TINYINT      NOT NULL DEFAULT 0 COMMENT 'Retry priority, zero is normal',

    PRIMARY KEY (`id`),
    UNIQUE KEY (`date`),
    KEY (`locked_by`),
    KEY (`lock_timeout_at`),
    KEY (`priority`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__forseo_gsc_daily_raw`
(
    `id`               int unsigned                                                   NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `fetched_at`       DATETIME                                                       NULL COMMENT 'Timestamp of when this data row was retrieved from GSC',
    `date`             DATE                                                           NOT NULL COMMENT 'Pacific Time date for which data is stored',
    `page`             VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Indexable URL representation',
    `page_full`        VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full URL as stored',
    `query`            VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'Full URL as stored',
    `impressions`      INT unsigned                                                   NOT NULL DEFAULT 0,
    `clicks`           INT unsigned                                                   NOT NULL DEFAULT 0,
    `ctr`              FLOAT                                                          NOT NULL DEFAULT 0,
    `device`           VARCHAR(100)                                                   NOT NULL DEFAULT '',
    `country`          VARCHAR(30)                                                    NOT NULL DEFAULT '',
    `searchAppearance` VARCHAR(100)                                                   NOT NULL DEFAULT '',

    PRIMARY KEY (`id`),
    KEY (`page`),
    KEY `main` (`date`, `query`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__forseo_gsc_daily_agg`
(
    `id`               int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
    `date`             DATE         NOT NULL COMMENT 'Pacific Time date for which data is stored',
    `impressions`      INT unsigned NOT NULL DEFAULT 0,
    `clicks`           INT unsigned NOT NULL DEFAULT 0,
    `ctr`              FLOAT        NOT NULL DEFAULT 0,
    `position`         FLOAT        NOT NULL DEFAULT 0,
    `device`           VARCHAR(100) NOT NULL DEFAULT '',
    `country`          VARCHAR(30)  NOT NULL DEFAULT '',
    `searchAppearance` VARCHAR(100) NOT NULL DEFAULT '',

    PRIMARY KEY (`id`),
    UNIQUE KEY (`date`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  DEFAULT COLLATE = utf8_unicode_ci;
