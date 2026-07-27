ALTER TABLE users MODIFY COLUMN role ENUM('admin','agent','trial','user') NOT NULL DEFAULT 'user';
CREATE TABLE IF NOT EXISTS trial_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feature_key VARCHAR(50) NOT NULL UNIQUE,
    feature_label VARCHAR(100) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO trial_settings (feature_key, feature_label, enabled) VALUES
('distill', '关键词批量蒸馏', 1),
('detect', 'GEO检测', 1),
('article_generate', '文章一键生成', 1),
('article_publish', '文章多平台推送', 1),
('video_script', '视频脚本一键生成', 1),
('video_analyze', '视频号分析', 1);
