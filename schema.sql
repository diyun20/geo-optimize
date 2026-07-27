CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) DEFAULT '',
  `qq` varchar(20) DEFAULT '',
  `wechat` varchar(50) DEFAULT '',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `show_email` tinyint(1) NOT NULL DEFAULT 1,
  `show_phone` tinyint(1) NOT NULL DEFAULT 1,
  `show_qq` tinyint(1) NOT NULL DEFAULT 1,
  `show_wechat` tinyint(1) NOT NULL DEFAULT 1,
  `role` enum('admin','agent','trial','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `agent_id` int(10) unsigned DEFAULT NULL,
  `banned` tinyint(1) NOT NULL DEFAULT 0,
  `balance` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'balance',
  `membership` varchar(20) NOT NULL DEFAULT 'vip',
  `membership_expire` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_agent` (`agent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agent_api_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `agent_id` int(10) unsigned NOT NULL,
  `doubao_key` varchar(255) DEFAULT '',
  `doubao_endpoint` varchar(255) DEFAULT 'https://ark.cn-beijing.volces.com/api/compatible',
  `doubao_model` varchar(100) DEFAULT 'doubao-seed-evolving',
  `deepseek_key` varchar(255) DEFAULT '',
  `deepseek_endpoint` varchar(255) DEFAULT 'https://api.deepseek.com/chat/completions',
  `deepseek_model` varchar(100) DEFAULT 'deepseek-v4-flash',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `agent_id` (`agent_id`),
  CONSTRAINT `agent_api_config_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `auto_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL DEFAULT '',
  `type` enum('scan','geo_detect') NOT NULL,
  `scope` enum('all','user') NOT NULL DEFAULT 'all',
  `target_user_id` int(10) unsigned DEFAULT NULL,
  `interval_hours` int(10) unsigned NOT NULL DEFAULT '0',
  `interval_minutes` int(10) unsigned NOT NULL DEFAULT '0',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `last_run_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `company_info` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `company_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'company_name',
  `company_abbr` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'company_abbr',
  `region` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `short_video_account` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `industry` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'industry',
  `products_services` text COLLATE utf8mb4_unicode_ci COMMENT 'products_services',
  `product_highlights` text COLLATE utf8mb4_unicode_ci COMMENT 'product_highlights',
  `brand_story` text COLLATE utf8mb4_unicode_ci COMMENT 'brand_story',
  `trust_endorsements` text COLLATE utf8mb4_unicode_ci COMMENT 'trust_endorsements',
  `user_pain_points` text COLLATE utf8mb4_unicode_ci COMMENT 'user_pain_points',
  `customer_cases` text COLLATE utf8mb4_unicode_ci COMMENT 'customer_cases',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `company_info_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `geo_api_providers` (
  `provider` varchar(50) NOT NULL,
  `api_key` varchar(255) DEFAULT '',
  `api_endpoint` varchar(255) DEFAULT '',
  `model` varchar(100) DEFAULT '',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geo_articles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `topic` varchar(500) NOT NULL DEFAULT '',
  `brand_name` varchar(200) NOT NULL DEFAULT '',
  `keywords` text,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'completed',
  `content` longtext NOT NULL,
  `created_at` datetime NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `geo_articles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geo_brand_scan` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `brand_visible` tinyint(1) NOT NULL DEFAULT '0',
  `brand_position` int(11) DEFAULT NULL,
  `keyword_count` int(11) NOT NULL DEFAULT '0',
  `doubao_count` int(11) NOT NULL DEFAULT '0',
  `deepseek_count` int(11) NOT NULL DEFAULT '0',
  `total_keywords` int(11) NOT NULL DEFAULT '0',
  `scan_percent` decimal(5,1) NOT NULL DEFAULT '0.0',
  `raw_response` mediumtext,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geo_brand_scan_details` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scan_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `keyword` varchar(200) NOT NULL,
  `keyword_index` int(11) NOT NULL DEFAULT '0',
  `platform` varchar(50) NOT NULL DEFAULT '',
  `mentioned` tinyint(1) NOT NULL DEFAULT '0',
  `rank_position` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_mention` (`user_id`,`mentioned`),
  KEY `idx_scan` (`scan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=178 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geo_daily_keyword_stats` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `record_date` date NOT NULL,
  `doubao_count` int(11) NOT NULL DEFAULT '0',
  `deepseek_count` int(11) NOT NULL DEFAULT '0',
  `total_keywords` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_date` (`user_id`,`record_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `geo_detect_results` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `question` text NOT NULL,
  `brand` varchar(200) NOT NULL,
  `result_text` text,
  `brand_mentioned` tinyint(1) NOT NULL DEFAULT '0',
  `brand_position` int(11) DEFAULT NULL,
  `platform` varchar(50) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT 'completed',
  `started_at` datetime DEFAULT NULL,
  `error` text,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geo_distill_queue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `error` text,
  `created_at` datetime NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geo_keywords` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `keyword` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'keyword',
  `brand_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'brand_name',
  `active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'active',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_active` (`user_id`,`active`),
  CONSTRAINT `geo_keywords_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `geo_keywords_distill` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `keyword` varchar(200) NOT NULL,
  `category` varchar(100) DEFAULT '',
  `generated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3603 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geo_keywords_manual` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `keyword` varchar(200) NOT NULL,
  `category` varchar(100) DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geo_queue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `keyword_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `keyword_id` (`keyword_id`),
  KEY `idx_status` (`status`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `geo_queue_ibfk_1` FOREIGN KEY (`keyword_id`) REFERENCES `geo_keywords` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `geo_results` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `keyword_id` int(10) unsigned NOT NULL,
  `brand_mentioned` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'brand_mentioned',
  `rank_position` int(11) DEFAULT NULL COMMENT 'rank_position',
  `response_snippet` text COLLATE utf8mb4_unicode_ci COMMENT 'response_snippet',
  `raw_response` text COLLATE utf8mb4_unicode_ci COMMENT 'raw_response',
  `checked_at` datetime NOT NULL COMMENT 'checked_at',
  PRIMARY KEY (`id`),
  KEY `idx_keyword_date` (`keyword_id`,`checked_at`),
  CONSTRAINT `geo_results_ibfk_1` FOREIGN KEY (`keyword_id`) REFERENCES `geo_keywords` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `geo_scan_queue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `error` text,
  `created_at` datetime NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geo_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `api_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'api_key',
  `api_endpoint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'https://ark.cn-beijing.volcengine.com/api/v3/chat/completions' COMMENT 'api_endpoint',
  `model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'doubao-pro-32k' COMMENT 'model',
  `cost_per_detect` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'cost_per_detect',
  `deepseek_api_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `deepseek_api_endpoint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'https://api.deepseek.com',
  `deepseek_model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'deepseek-chat',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `geo_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `idx_token` (`token`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `type` enum('recharge','consume','refund') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_type` (`user_id`,`type`),
  KEY `idx_date` (`created_at`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `trial_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `feature_key` varchar(50) NOT NULL,
  `feature_label` varchar(100) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `max_value` int(11) NOT NULL DEFAULT '0',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `feature_key` (`feature_key`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `virtual_collections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `amount` int(11) NOT NULL DEFAULT '0',
  `doubao_amount` int(11) NOT NULL DEFAULT '0',
  `deepseek_amount` int(11) NOT NULL DEFAULT '0',
  `admin_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `virtual_collections_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `virtual_collections_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

