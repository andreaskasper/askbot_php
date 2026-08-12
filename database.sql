-- ---------------------------------------------------------------------------
-- askbot_php - database schema
--
-- MariaDB 10.6+ / MySQL 8.0+, InnoDB, utf8mb4.
-- All timestamps are DATETIME in UTC. All money-ish values are DECIMAL.
-- Import:  mysql -u askbot -p askbot < database.sql
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Users
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`          VARCHAR(64)  NOT NULL,
  `slug`              VARCHAR(80)  NOT NULL,
  `email`             VARCHAR(190) NOT NULL,
  `email_verified_at` DATETIME     NULL DEFAULT NULL,
  `password_hash`     VARCHAR(255) NULL DEFAULT NULL,
  `karma`             INT          NOT NULL DEFAULT 1,
  `role`              ENUM('user','moderator','admin') NOT NULL DEFAULT 'user',
  `real_name`         VARCHAR(120) NOT NULL DEFAULT '',
  `bio_md`            TEXT         NULL,
  `bio_html`          TEXT         NULL,
  `website`           VARCHAR(255) NOT NULL DEFAULT '',
  `location`          VARCHAR(120) NOT NULL DEFAULT '',
  `country`           CHAR(2)      NOT NULL DEFAULT '',
  `show_country`      TINYINT(1)   NOT NULL DEFAULT 0,
  `avatar_url`        VARCHAR(255) NOT NULL DEFAULT '',
  `locale`            VARCHAR(5)   NOT NULL DEFAULT 'en',
  `timezone`          VARCHAR(64)  NOT NULL DEFAULT 'UTC',
  `totp_secret`       VARCHAR(64)  NULL DEFAULT NULL,
  `totp_enabled`      TINYINT(1)   NOT NULL DEFAULT 0,
  `email_digest`      ENUM('off','daily','weekly') NOT NULL DEFAULT 'daily',
  `email_on_answer`   TINYINT(1)   NOT NULL DEFAULT 1,
  `email_on_comment`  TINYINT(1)   NOT NULL DEFAULT 1,
  `question_count`    INT          NOT NULL DEFAULT 0,
  `answer_count`      INT          NOT NULL DEFAULT 0,
  `accepted_count`    INT          NOT NULL DEFAULT 0,
  `profile_views`     INT          NOT NULL DEFAULT 0,
  `is_suspended`      TINYINT(1)   NOT NULL DEFAULT 0,
  `suspended_until`   DATETIME     NULL DEFAULT NULL,
  `suspended_reason`  VARCHAR(255) NOT NULL DEFAULT '',
  `last_seen_at`      DATETIME     NULL DEFAULT NULL,
  `last_ip_hash`      CHAR(64)     NOT NULL DEFAULT '',
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_slug` (`slug`),
  KEY `idx_users_karma` (`karma`),
  KEY `idx_users_lastseen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- External identities (oauth2) and the local password provider marker.
CREATE TABLE IF NOT EXISTS `user_logins` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `provider`     VARCHAR(32)  NOT NULL,
  `provider_uid` VARCHAR(190) NOT NULL,
  `display_name` VARCHAR(190) NOT NULL DEFAULT '',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_logins` (`provider`,`provider_uid`),
  KEY `fk_user_logins_user` (`user_id`),
  CONSTRAINT `fk_user_logins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-time and long-lived tokens: email verification, password reset,
-- "remember me" cookies and personal API keys. Only hashes are stored.
CREATE TABLE IF NOT EXISTS `user_tokens` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `type`       ENUM('verify_email','reset_password','remember','api') NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `label`      VARCHAR(64) NOT NULL DEFAULT '',
  `expires_at` DATETIME NULL DEFAULT NULL,
  `used_at`    DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_tokens_hash` (`token_hash`),
  KEY `idx_user_tokens_user` (`user_id`,`type`),
  CONSTRAINT `fk_user_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Questions, answers, comments
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `questions` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`              VARCHAR(300) NOT NULL,
  `slug`               VARCHAR(320) NOT NULL,
  `body_md`            MEDIUMTEXT NOT NULL,
  `body_html`          MEDIUMTEXT NOT NULL,
  `author_id`          BIGINT UNSIGNED NULL DEFAULT NULL,
  `author_ip_hash`     CHAR(64) NOT NULL DEFAULT '',
  `tags`               VARCHAR(255) NOT NULL DEFAULT '',
  `score`              INT NOT NULL DEFAULT 0,
  `view_count`         INT NOT NULL DEFAULT 0,
  `answer_count`       INT NOT NULL DEFAULT 0,
  `comment_count`      INT NOT NULL DEFAULT 0,
  `favorite_count`     INT NOT NULL DEFAULT 0,
  `accepted_answer_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `duplicate_of_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
  `is_closed`          TINYINT(1) NOT NULL DEFAULT 0,
  `closed_reason`      VARCHAR(64) NOT NULL DEFAULT '',
  `closed_by_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
  `closed_at`          DATETIME NULL DEFAULT NULL,
  `is_locked`          TINYINT(1) NOT NULL DEFAULT 0,
  `is_wiki`            TINYINT(1) NOT NULL DEFAULT 0,
  `is_spam`            TINYINT(1) NOT NULL DEFAULT 0,
  `bounty_amount`      INT NOT NULL DEFAULT 0,
  `bounty_expires_at`  DATETIME NULL DEFAULT NULL,
  `revision`           INT NOT NULL DEFAULT 1,
  `last_activity_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity_by`   BIGINT UNSIGNED NULL DEFAULT NULL,
  `last_activity_type` VARCHAR(24) NOT NULL DEFAULT 'asked',
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`         DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_questions_activity` (`deleted_at`,`last_activity_at`),
  KEY `idx_questions_created` (`deleted_at`,`created_at`),
  KEY `idx_questions_score` (`deleted_at`,`score`),
  KEY `idx_questions_answers` (`deleted_at`,`answer_count`),
  KEY `idx_questions_author` (`author_id`),
  KEY `idx_questions_bounty` (`bounty_expires_at`),
  FULLTEXT KEY `ft_questions` (`title`,`body_md`,`tags`),
  CONSTRAINT `fk_questions_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `answers` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id`    BIGINT UNSIGNED NOT NULL,
  `author_id`      BIGINT UNSIGNED NULL DEFAULT NULL,
  `author_ip_hash` CHAR(64) NOT NULL DEFAULT '',
  `body_md`        MEDIUMTEXT NOT NULL,
  `body_html`      MEDIUMTEXT NOT NULL,
  `score`          INT NOT NULL DEFAULT 0,
  `comment_count`  INT NOT NULL DEFAULT 0,
  `is_accepted`    TINYINT(1) NOT NULL DEFAULT 0,
  `is_wiki`        TINYINT(1) NOT NULL DEFAULT 0,
  `is_spam`        TINYINT(1) NOT NULL DEFAULT 0,
  `revision`       INT NOT NULL DEFAULT 1,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_answers_question` (`question_id`,`deleted_at`,`score`),
  KEY `idx_answers_author` (`author_id`),
  FULLTEXT KEY `ft_answers` (`body_md`),
  CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_answers_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comments` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_type`  ENUM('question','answer') NOT NULL,
  `post_id`    BIGINT UNSIGNED NOT NULL,
  `author_id`  BIGINT UNSIGNED NULL DEFAULT NULL,
  `body_md`    VARCHAR(1000) NOT NULL,
  `body_html`  TEXT NOT NULL,
  `score`      INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_comments_post` (`post_type`,`post_id`,`deleted_at`),
  KEY `idx_comments_author` (`author_id`),
  CONSTRAINT `fk_comments_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Full edit history for questions, answers and tag wikis.
CREATE TABLE IF NOT EXISTS `post_revisions` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_type`  ENUM('question','answer','tag') NOT NULL,
  `post_id`    BIGINT UNSIGNED NOT NULL,
  `revision`   INT NOT NULL DEFAULT 1,
  `user_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
  `title`      VARCHAR(300) NOT NULL DEFAULT '',
  `body_md`    MEDIUMTEXT NOT NULL,
  `tags`       VARCHAR(255) NOT NULL DEFAULT '',
  `comment`    VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_revision` (`post_type`,`post_id`,`revision`),
  KEY `idx_revisions_user` (`user_id`),
  CONSTRAINT `fk_revisions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Tags
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `tags` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(48) NOT NULL,
  `slug`             VARCHAR(64) NOT NULL,
  `description_md`   TEXT NULL,
  `description_html` TEXT NULL,
  `question_count`   INT NOT NULL DEFAULT 0,
  `view_count`       INT NOT NULL DEFAULT 0,
  `is_locked`        TINYINT(1) NOT NULL DEFAULT 0,
  `revision`         INT NOT NULL DEFAULT 1,
  `created_by`       BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tags_name` (`name`),
  KEY `idx_tags_count` (`question_count`),
  CONSTRAINT `fk_tags_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `question_tags` (
  `question_id` BIGINT UNSIGNED NOT NULL,
  `tag_id`      BIGINT UNSIGNED NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`question_id`,`tag_id`),
  KEY `idx_question_tags_tag` (`tag_id`),
  CONSTRAINT `fk_qt_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qt_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "js" -> "javascript". Synonyms are rewritten on save and on search.
CREATE TABLE IF NOT EXISTS `tag_synonyms` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_name`   VARCHAR(48) NOT NULL,
  `target_tag_id` BIGINT UNSIGNED NOT NULL,
  `usage_count`   INT NOT NULL DEFAULT 0,
  `created_by`    BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tag_synonyms` (`source_name`),
  KEY `fk_tag_synonyms_target` (`target_tag_id`),
  CONSTRAINT `fk_tag_synonyms_target` FOREIGN KEY (`target_tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Votes, favorites, views
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `votes` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_type`  ENUM('question','answer','comment') NOT NULL,
  `post_id`    BIGINT UNSIGNED NOT NULL,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `value`      TINYINT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_votes` (`post_type`,`post_id`,`user_id`),
  KEY `idx_votes_user` (`user_id`,`created_at`),
  CONSTRAINT `fk_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `favorites` (
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `question_id` BIGINT UNSIGNED NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`question_id`),
  KEY `idx_favorites_question` (`question_id`),
  CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fav_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per viewer per question per day - keeps the view counter honest
-- without storing personal data (the viewer key is a salted hash).
CREATE TABLE IF NOT EXISTS `question_views` (
  `question_id` BIGINT UNSIGNED NOT NULL,
  `viewer_key`  CHAR(32) NOT NULL,
  `view_date`   DATE NOT NULL,
  PRIMARY KEY (`question_id`,`viewer_key`,`view_date`),
  CONSTRAINT `fk_views_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Reputation and badges
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `karma_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `reason`     VARCHAR(48) NOT NULL,
  `points`     INT NOT NULL,
  `post_type`  ENUM('question','answer','comment','none') NOT NULL DEFAULT 'none',
  `post_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
  `actor_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_karma_user` (`user_id`,`created_at`),
  KEY `idx_karma_dedup` (`user_id`,`reason`,`post_type`,`post_id`),
  CONSTRAINT `fk_karma_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `badges` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_name`    VARCHAR(48) NOT NULL,
  `name`        VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `level`       ENUM('bronze','silver','gold') NOT NULL DEFAULT 'bronze',
  `is_multiple` TINYINT(1) NOT NULL DEFAULT 0,
  `awarded_count` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_badges_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_badges` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `badge_id`   BIGINT UNSIGNED NOT NULL,
  `post_type`  ENUM('question','answer','comment','none') NOT NULL DEFAULT 'none',
  `post_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
  `is_seen`    TINYINT(1) NOT NULL DEFAULT 0,
  `awarded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_badge_post` (`user_id`,`badge_id`,`post_type`,`post_id`),
  KEY `idx_user_badges_badge` (`badge_id`),
  CONSTRAINT `fk_ub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ub_badge` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Moderation
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `flags` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_type`  ENUM('question','answer','comment','user') NOT NULL,
  `post_id`    BIGINT UNSIGNED NOT NULL,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `reason`     VARCHAR(32) NOT NULL,
  `note`       VARCHAR(500) NOT NULL DEFAULT '',
  `status`     ENUM('open','accepted','declined') NOT NULL DEFAULT 'open',
  `handled_by` BIGINT UNSIGNED NULL DEFAULT NULL,
  `handled_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_flags` (`post_type`,`post_id`,`user_id`),
  KEY `idx_flags_status` (`status`,`created_at`),
  CONSTRAINT `fk_flags_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Community close/reopen/delete votes. Enough votes trigger the action.
CREATE TABLE IF NOT EXISTS `close_votes` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` BIGINT UNSIGNED NOT NULL,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `action`      ENUM('close','reopen','delete') NOT NULL DEFAULT 'close',
  `reason`      VARCHAR(64) NOT NULL DEFAULT '',
  `duplicate_of_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_close_votes` (`question_id`,`user_id`,`action`),
  CONSTRAINT `fk_cv_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
  `action`     VARCHAR(48) NOT NULL,
  `target`     VARCHAR(64) NOT NULL DEFAULT '',
  `data_json`  TEXT NULL,
  `ip_hash`    CHAR(64) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Notifications, subscriptions, messages
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `type`       VARCHAR(32) NOT NULL,
  `actor_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
  `post_type`  ENUM('question','answer','comment','user','none') NOT NULL DEFAULT 'none',
  `post_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
  `title`      VARCHAR(255) NOT NULL DEFAULT '',
  `url`        VARCHAR(500) NOT NULL DEFAULT '',
  `read_at`    DATETIME NULL DEFAULT NULL,
  `mailed_at`  DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`,`read_at`,`created_at`),
  KEY `idx_notifications_mail` (`mailed_at`,`created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `target_type` ENUM('question','tag','user') NOT NULL,
  `target_id`   BIGINT UNSIGNED NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subscriptions` (`user_id`,`target_type`,`target_id`),
  KEY `idx_subscriptions_target` (`target_type`,`target_id`),
  CONSTRAINT `fk_subs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `to_user_id`   BIGINT UNSIGNED NOT NULL,
  `subject`      VARCHAR(200) NOT NULL,
  `body_md`      TEXT NOT NULL,
  `body_html`    TEXT NOT NULL,
  `read_at`      DATETIME NULL DEFAULT NULL,
  `deleted_by_sender`   TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_by_receiver` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_to` (`to_user_id`,`read_at`,`created_at`),
  CONSTRAINT `fk_msg_to` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outgoing mail queue. The "mailer" bot drains it so a slow SMTP server
-- never blocks a web request.
CREATE TABLE IF NOT EXISTS `mail_queue` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `to_email`    VARCHAR(190) NOT NULL,
  `to_name`     VARCHAR(120) NOT NULL DEFAULT '',
  `subject`     VARCHAR(255) NOT NULL,
  `body_html`   MEDIUMTEXT NOT NULL,
  `body_text`   MEDIUMTEXT NOT NULL,
  `status`      ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts`    INT NOT NULL DEFAULT 0,
  `last_error`  VARCHAR(500) NOT NULL DEFAULT '',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at`     DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mail_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Infrastructure
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `config` (
  `key_name`   VARCHAR(64) NOT NULL,
  `value_text` TEXT NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket`     VARCHAR(120) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rate_bucket` (`bucket`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `uploads` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
  `filename`   VARCHAR(255) NOT NULL,
  `path`       VARCHAR(255) NOT NULL,
  `mime`       VARCHAR(100) NOT NULL,
  `bytes`      INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_uploads_user` (`user_id`),
  CONSTRAINT `fk_uploads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Schema version marker used by html/app/app.php migrate
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version`    VARCHAR(32) NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
