ALTER TABLE virtual_collections ADD COLUMN IF NOT EXISTS doubao_amount INT NOT NULL DEFAULT 0 AFTER amount;
ALTER TABLE virtual_collections ADD COLUMN IF NOT EXISTS deepseek_amount INT NOT NULL DEFAULT 0 AFTER doubao_amount;
