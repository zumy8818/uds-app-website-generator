-- hearing_data テーブル作成
-- ロリポップ MySQL (LAA1380072-udswebgen) で実行すること

CREATE TABLE IF NOT EXISTS `hearing_data` (
    `id`           INT              NOT NULL AUTO_INCREMENT,
    `client_name`  VARCHAR(255)     NOT NULL DEFAULT '',
    `hearing_json` LONGTEXT         NOT NULL COMMENT 'ヒアリングJSON',
    `status`       VARCHAR(50)      NOT NULL DEFAULT 'pending',
    `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
