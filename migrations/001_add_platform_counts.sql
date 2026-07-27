-- 为 geo_brand_scan 添加分平台统计字段
ALTER TABLE geo_brand_scan ADD COLUMN IF NOT EXISTS doubao_count INT NOT NULL DEFAULT 0 AFTER keyword_count;
ALTER TABLE geo_brand_scan ADD COLUMN IF NOT EXISTS deepseek_count INT NOT NULL DEFAULT 0 AFTER doubao_count;
ALTER TABLE geo_brand_scan ADD COLUMN IF NOT EXISTS total_keywords INT NOT NULL DEFAULT 0 AFTER deepseek_count;
ALTER TABLE geo_brand_scan ADD COLUMN IF NOT EXISTS scan_percent DECIMAL(5,1) NOT NULL DEFAULT 0.0 AFTER total_keywords;
ALTER TABLE geo_brand_scan MODIFY raw_response MEDIUMTEXT;
