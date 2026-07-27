CREATE TABLE IF NOT EXISTS agent_api_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agent_id INT UNSIGNED NOT NULL UNIQUE,
    doubao_key VARCHAR(255) DEFAULT '',
    doubao_endpoint VARCHAR(255) DEFAULT 'https://ark.cn-beijing.volces.com/api/compatible',
    doubao_model VARCHAR(100) DEFAULT 'doubao-seed-evolving',
    deepseek_key VARCHAR(255) DEFAULT '',
    deepseek_endpoint VARCHAR(255) DEFAULT 'https://api.deepseek.com/chat/completions',
    deepseek_model VARCHAR(100) DEFAULT 'deepseek-v4-flash',
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
