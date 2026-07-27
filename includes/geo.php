<?php
function geoStripos($h, $n) { return function_exists("mb_stripos") ? mb_stripos($h, $n) : stripos($h, $n); }
function geoSubstr($s, $start, $len = null) { return function_exists("mb_substr") ? mb_substr($s, $start, $len) : ($len === null ? substr($s, $start) : substr($s, $start, $len)); }


/** HTTP POST using PHP cURL (replaces exec/curl.exe) */
function geoHttpApi(string $endpoint, string $apiKey, string $payload, int $timeout = 120): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return ['error' => $err, 'http_code' => 0, 'response' => ''];
    // Save per-keyword details to geo_brand_scan_details
    try { dbExecute("CREATE TABLE IF NOT EXISTS `geo_brand_scan_details` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`scan_id` INT UNSIGNED NOT NULL,`user_id` INT UNSIGNED NOT NULL,`keyword` VARCHAR(200) NOT NULL,`keyword_index` INT NOT NULL DEFAULT 0,`platform` VARCHAR(50) NOT NULL DEFAULT '',`mentioned` TINYINT(1) NOT NULL DEFAULT 0,`rank_position` INT DEFAULT NULL,`created_at` DATETIME NOT NULL,INDEX `idx_user_mention` (`user_id`,`mentioned`),INDEX `idx_scan` (`scan_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}
    $detailTime = date("Y-m-d H:i:s");
    $allKeywordList = [];
    foreach ($keywords as $rk) { $allKeywordList[] = $rk["keyword"]; }
    foreach ($customKws as $rk) { $allKeywordList[] = $rk["keyword"]; }
    
    // Save 豆包 results
    foreach ($doubaoLines as $lineNum) {
        $idx = $lineNum - 1;
        if (isset($allKeywordList[$idx])) {
            dbExecute("INSERT INTO geo_brand_scan_details (scan_id,user_id,keyword,keyword_index,platform,mentioned,rank_position,created_at) VALUES (?,?,?,?,?,?,?,?)",
                [$scanDetailId, $userId, $allKeywordList[$idx], $idx + 1, 'doubao', 1, $idx + 1, $detailTime]);
        }
    }
    // Save DeepSeek results
    foreach ($deepseekLines as $lineNum) {
        $idx = $lineNum - 1;
        if (isset($allKeywordList[$idx])) {
            $existing = dbFetchOne("SELECT id FROM geo_brand_scan_details WHERE scan_id=? AND keyword_index=? AND platform=?", [$scanDetailId, $idx + 1, 'deepseek']);
            if (!$existing) {
                dbExecute("INSERT INTO geo_brand_scan_details (scan_id,user_id,keyword,keyword_index,platform,mentioned,rank_position,created_at) VALUES (?,?,?,?,?,?,?,?)",
                    [$scanDetailId, $userId, $allKeywordList[$idx], $idx + 1, 'deepseek', 1, $idx + 1, $detailTime]);
            }
        }
    }
    

    return ['error' => '', 'http_code' => $httpCode, 'response' => $response];
}

function geoInitTables(): void {
    try {
        geoInitApiProviders();
        dbExecute("CREATE TABLE IF NOT EXISTS `geo_keywords` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`keyword` VARCHAR(200) NOT NULL,`brand_name` VARCHAR(200) DEFAULT \"\",`active` TINYINT(1) NOT NULL DEFAULT 1,`created_at` DATETIME NOT NULL,FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        dbExecute("CREATE TABLE IF NOT EXISTS `geo_results` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`keyword_id` INT UNSIGNED NOT NULL,`brand_mentioned` TINYINT(1) NOT NULL DEFAULT 0,`rank_position` INT DEFAULT NULL,`response_snippet` TEXT DEFAULT NULL,`raw_response` MEDIUMTEXT DEFAULT NULL,`checked_at` DATETIME NOT NULL,FOREIGN KEY (`keyword_id`) REFERENCES `geo_keywords`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        dbExecute("CREATE TABLE IF NOT EXISTS `geo_scan_queue` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',`error` TEXT DEFAULT NULL,`created_at` DATETIME NOT NULL,`started_at` DATETIME DEFAULT NULL,`completed_at` DATETIME DEFAULT NULL,INDEX `idx_status` (`status`),INDEX `idx_user` (`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        dbExecute("CREATE TABLE IF NOT EXISTS `geo_distill_queue` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',`error` TEXT DEFAULT NULL,`created_at` DATETIME NOT NULL,`started_at` DATETIME DEFAULT NULL,`completed_at` DATETIME DEFAULT NULL,INDEX `idx_status` (`status`),INDEX `idx_user` (`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        dbExecute("CREATE TABLE IF NOT EXISTS `geo_brand_scan` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`brand_visible` TINYINT(1) NOT NULL DEFAULT 0,`brand_position` INT DEFAULT NULL,`keyword_count` INT NOT NULL DEFAULT 0,`total_keywords` INT NOT NULL DEFAULT 0,`scan_percent` DECIMAL(5,1) NOT NULL DEFAULT 0.0,`raw_response` MEDIUMTEXT DEFAULT NULL,`created_at` DATETIME NOT NULL,INDEX `idx_user_date` (`user_id`,`created_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        dbExecute("CREATE TABLE IF NOT EXISTS `geo_daily_keyword_stats` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`record_date` DATE NOT NULL,`doubao_count` INT NOT NULL DEFAULT 0,`deepseek_count` INT NOT NULL DEFAULT 0,`total_keywords` INT NOT NULL DEFAULT 0,`created_at` DATETIME NOT NULL,UNIQUE KEY `uk_user_date` (`user_id`,`record_date`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        dbExecute("CREATE TABLE IF NOT EXISTS `auto_tasks` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`name` VARCHAR(200) NOT NULL DEFAULT '',`type` ENUM('scan','geo_detect','daily_keyword_snapshot') NOT NULL,`scope` ENUM('all','user') NOT NULL DEFAULT 'all',`target_user_id` INT UNSIGNED DEFAULT NULL,`interval_hours` INT UNSIGNED NOT NULL DEFAULT 0,`interval_minutes` INT UNSIGNED NOT NULL DEFAULT 0,`enabled` TINYINT(1) NOT NULL DEFAULT 1,`last_run_at` DATETIME DEFAULT NULL,`created_at` DATETIME NOT NULL,`updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,INDEX `idx_type` (`type`),INDEX `idx_enabled` (`enabled`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    }
 catch (Exception $e) {}
}

function geoGetAdminSettings(): array {
    $dp = geoGetApiProvider("doubao");
    $dsp = geoGetApiProvider("deepseek");
    $detectPrice = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='detect_price'")['setting_value'] ?? '0.00';
    return ["api_key"=>$dp["api_key"],"api_endpoint"=>$dp["api_endpoint"],"model"=>$dp["model"],"cost_per_detect"=>$detectPrice,"deepseek_api_key"=>$dsp["api_key"],"deepseek_api_endpoint"=>$dsp["api_endpoint"],"deepseek_model"=>$dsp["model"]];
}

/** 获取用户实际使用的API配置：代理商名下必须走代理商key，无key则报错 */
function geoGetEffectiveApiSettings(int $userId): array {
    $user = dbFetchOne("SELECT agent_id FROM users WHERE id=?", [$userId]);
    if ($user && $user['agent_id']) {
        $agentCfg = dbFetchOne("SELECT * FROM agent_api_config WHERE agent_id=?", [$user['agent_id']]);
        if ($agentCfg && !empty($agentCfg['doubao_key'])) {
            $detectPrice = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='detect_price'")['setting_value'] ?? '0.00';
            return [
                "api_key"               => $agentCfg['doubao_key'],
                "api_endpoint"          => $agentCfg['doubao_endpoint'],
                "model"                 => $agentCfg['doubao_model'],
                "cost_per_detect"       => $detectPrice,
                "deepseek_api_key"      => $agentCfg['deepseek_key'],
                "deepseek_api_endpoint" => $agentCfg['deepseek_endpoint'],
                "deepseek_model"        => $agentCfg['deepseek_model'],
                "source"                => "agent",
            ];
        }
        // 代理商名下用户，代理商未配置key则返回空
        return ["api_key"=>"","api_endpoint"=>"","model"=>"","cost_per_detect"=>$detectPrice,"deepseek_api_key"=>"","deepseek_api_endpoint"=>"","deepseek_model"=>"","source"=>"agent_none"];
    }
    // 直管用户用全局
    $s = geoGetAdminSettings();
    $s['source'] = 'global';
    return $s;
}

function geoInitApiProviders(): void {
    try {
        dbExecute("CREATE TABLE IF NOT EXISTS `geo_api_providers` (`provider` VARCHAR(50) NOT NULL PRIMARY KEY,`api_key` VARCHAR(255) DEFAULT '',`api_endpoint` VARCHAR(255) DEFAULT '',`model` VARCHAR(100) DEFAULT '',`updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
}

function geoGetApiProvider(string $provider): array {
    $row = dbFetchOne("SELECT * FROM geo_api_providers WHERE provider=?", [$provider]);
    if ($row) return $row;
    $defaults = [
        'doubao' => ['api_endpoint' => 'https://ark.cn-beijing.volces.com/api/v3/chat/completions', 'model' => 'doubao-seed-evolving'],
        'deepseek' => ['api_endpoint' => 'https://api.deepseek.com/chat/completions', 'model' => 'deepseek-v4-flash'],
    ];
    $d = $defaults[$provider] ?? [];
    return ['provider' => $provider, 'api_key' => '', 'api_endpoint' => $d['api_endpoint'] ?? '', 'model' => $d['model'] ?? ''];
}

function geoSaveApiProvider(string $provider, string $apiKey, string $endpoint, string $model): void {
    geoInitApiProviders();
    dbExecute("INSERT INTO geo_api_providers (provider,api_key,api_endpoint,model,updated_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE api_key=?,api_endpoint=?,model=?,updated_at=NOW()", [$provider, $apiKey, $endpoint, $model, $apiKey, $endpoint, $model]);
}
function geoSaveAdminSettings(string $apiKey, string $endpoint, string $model, float $cost, string $deepseekApiKey = '', string $deepseekEndpoint = '', string $deepseekModel = ''): void {
    // 已迁移到 geo_api_providers，此函数保留兼容
    geoSaveApiProvider("doubao", $apiKey, $endpoint, $model);
    geoSaveApiProvider("deepseek", $deepseekApiKey, $deepseekEndpoint, $deepseekModel);
}

function geoDetectKeyword(int $keywordId): array {
    set_time_limit(300);
    $kw = dbFetchOne("SELECT * FROM geo_keywords WHERE id=?", [$keywordId]);
    if (!$kw) return ["error"=>"keyword does not exist"];
    $userId = $kw["user_id"];
    $settings = geoGetAdminSettings();
    if (empty($settings["api_key"])) return ["error"=>"system API key not configured"];
    $cost = (float)($settings["cost_per_detect"] ?? 0);
    if ($cost > 0) {
        $bal = dbFetchOne("SELECT balance FROM users WHERE id=?", [$userId])["balance"] ?? 0;
        if ($bal < $cost) return ["error"=>"insufficient balance, need ".number_format($cost,2).", current ".number_format($bal,2)];
        dbExecute("UPDATE users SET balance = balance - ? WHERE id=?", [$cost, $userId]);
        dbExecute("INSERT INTO transactions (user_id,type,amount,description,created_at) VALUES (?,\"consume\",?,\"GEO keyword detection\",?)", [$userId, $cost, date("Y-m-d H:i:s")]);
    }
    $prompt = "List the most well-known brands or companies in the field of " . $kw["keyword"] . ". If " . $kw["brand_name"] . " is mentioned, specify its rank and describe the company's products or services.";
    $payload = json_encode(["model"=>$settings["model"],"messages"=>[["role"=>"system","content"=>"You are a professional industry analysis assistant."],["role"=>"user","content"=>$prompt]],"max_tokens"=>1024]);
    $apiResult = geoApiCall($settings["api_endpoint"], $settings["api_key"], $payload, 240);
    $httpCode = $apiResult["http_code"];
    $response = $apiResult["response"];
    if ($apiResult["error"]) return ["error" => "API request failed: " . $apiResult["error"]];    if ($httpCode !== 200) return ["error"=>"API returned HTTP {$httpCode}: ".($response ? substr($response,0,200) : "(empty)")];
    if (!$response) return ["error"=>"API returned empty response"];
    $data = json_decode($response, true);
    $content = $data["choices"][0]["message"]["content"] ?? $data["output"][0]["content"][0]["text"] ?? $data["output"][0]["content"] ?? $data["content"] ?? "";
    if (empty($content)) return ["error"=>"API returned empty content"];
    $brandMentioned = geoStripos($content, $kw["brand_name"]) !== false;
    $position = null;
    if ($brandMentioned) { foreach (explode("\n", $content) as $i => $line) { if (geoStripos($line, $kw["brand_name"]) !== false) { $position = $i + 1; break; } } }
    $now = date("Y-m-d H:i:s");
    dbExecute("INSERT INTO geo_results (keyword_id,brand_mentioned,rank_position,response_snippet,raw_response,checked_at) VALUES (?,?,?,?,?,?)", [$keywordId, $brandMentioned ? 1 : 0, $position, geoSubstr($content,0,500), $content, $now]);
    dbExecute("UPDATE geo_keywords SET active=1 WHERE id=?", [$keywordId]);
    return ["mentioned"=>$brandMentioned,"position"=>$position,"snippet"=>geoSubstr($content,0,500),"raw"=>$content,"time"=>$now,"cost"=>$cost > 0 ? $cost : 0];
}


/** Cross-platform API call using PHP curl */
function geoApiCall(string $url, string $apiKey, string $payload, int $timeout = 240): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bearer " . $apiKey],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return ["response" => $response, "http_code" => $httpCode, "error" => $error];
}

function geoInitQueueTable(): void {
    try { dbExecute("CREATE TABLE IF NOT EXISTS \`geo_queue\` (\`id\` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\`keyword_id\` INT UNSIGNED NOT NULL,\`user_id\` INT UNSIGNED NOT NULL,\`status\` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',\`error\` TEXT DEFAULT NULL,\`created_at\` DATETIME NOT NULL,\`started_at\` DATETIME DEFAULT NULL,\`completed_at\` DATETIME DEFAULT NULL,FOREIGN KEY (\`keyword_id\`) REFERENCES \`geo_keywords\`(\`id\`) ON DELETE CASCADE,INDEX \`idx_status\` (\`status\`),INDEX \`idx_user\` (\`user_id\`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}
}

function geoEnqueue(int $keywordId, int $userId): void {
    geoInitQueueTable();
    $p = dbFetchOne("SELECT id FROM geo_queue WHERE keyword_id=? AND status IN ('pending','processing')", [$keywordId]);
    if (!$p) { dbExecute("INSERT INTO geo_queue (keyword_id,user_id,created_at) VALUES (?,?,?)", [$keywordId, $userId, date('Y-m-d H:i:s')]); }
}

function geoGetQueueStatus(int $keywordId): array {
    geoInitQueueTable();
    $q = dbFetchOne("SELECT * FROM geo_queue WHERE keyword_id=? ORDER BY id DESC LIMIT 1", [$keywordId]);
    return $q ?: ['status' => 'none'];
}

function geoWorkerPing(): ?array {
    return dbFetchOne("SELECT * FROM geo_queue WHERE status='processing' ORDER BY started_at DESC LIMIT 1");
}

/** GEO optimization keyword generation (based on company info, calls Doubao API to generate 200 keywords) */



/** Run brand scan API calls with given config */
function geoBrandScan(int $userId): array {
    set_time_limit(300);
    $company = dbFetchOne("SELECT * FROM company_info WHERE user_id=?", [$userId]);
    if (!$company || empty($company["company_name"])) return ["error" => "Please fill in company name first"];
    $settings = geoGetEffectiveApiSettings($userId);
    if (empty($settings["api_key"])) return ["error" => "API Key not configured"];
   $brand = $company["company_name"];
   $abbr = $company["company_abbr"] ?: $brand;
   $industry = $company["industry"] ?: "Internet";
    $douyin = $company["short_video_account"] ?? "";
    // Parse product names from products_services field
    $productsRaw = $company["products_services"] ?? "";
    $products = [];
    if (!empty($productsRaw)) {
        foreach (preg_split('/\r\n|\r|\n/', $productsRaw) as $line) {
            $t = trim($line); if ($t !== '') $products[] = $t;
        }
    }
    $keywords = dbFetchAll("SELECT keyword FROM geo_keywords_distill WHERE user_id=? ORDER BY id ", [$userId]);
    $customKws = dbFetchAll("SELECT keyword FROM geo_keywords_manual WHERE user_id=? ORDER BY id", [$userId]);
    $allKeywords = array_merge($keywords, $customKws);
    if (empty($allKeywords)) return ["error" => "No keywords found. Please add keywords or generate distilled keywords first."];
    $totalKeywords = count($allKeywords);
    $batchSize = 100;
    $chunks = array_chunk($allKeywords, $batchSize);
    // Build brand description: include products as context but keep checkNames simple
    // Products help AI understand the business, but aren't used as literal match targets
    $brandDesc = "\"" . $abbr . "\" (" . $brand;
    if ($douyin) $brandDesc .= ", 抖音: " . $douyin;
    if (!empty($products)) $brandDesc .= ", 产品: " . implode("、", $products);
    $brandDesc .= ")";
    $checkNamesArr = ["\"" . $abbr . "\"", "\"" . $brand . "\""];
    if ($douyin) $checkNamesArr[] = "\"" . $douyin . "\"";
    $checkNames = implode(" or ", $checkNamesArr);
    // Doubao prompt: slightly broader wording
    $promptDB = "You are an AI search analysis tool. The brand " . $brandDesc . " operates in the " . $industry . " industry.\n\n";
    $promptDB .= "Below is a list of GEO long-tail keywords related to this brand.\n";
    $promptDB .= "For each keyword, determine if a user searching for that term in an AI search engine would see " . $checkNames . " mentioned or recommended in the AI response.\n\n";
    $promptDB .= "Return ONLY the line numbers of keywords where the brand would appear in search results.\n";
    $promptDB .= "Format: one line number per line, like: 1\n3\n7\n12\n";
    $promptDB .= "If none, return only: NONE\n\n";
    $promptDB .= "Keywords:\n";

    // DeepSeek prompt: original conservative wording
    $promptDS = "You are an AI search analysis tool. The brand " . $brandDesc . " operates in the " . $industry . " industry.\n\n";
    $promptDS .= "Below is a list of GEO long-tail keywords related to this brand.\n";
    $promptDS .= "For each keyword, determine if a user searching for that term in an AI search engine (like Doubao) would see " . $checkNames . " mentioned in the AI response.\n\n";
    $promptDS .= "Return ONLY the line numbers of keywords where " . $checkNames . " would appear in search results.\n";
    $promptDS .= "Format: one line number per line, like: 1\n3\n7\n12\n";
    $promptDS .= "If none, return only: NONE\n\n";
    $promptDS .= "Keywords:\n";

    // Run brand scan with Doubao API
    list($doubaoLines, $doubaoRaw, $doubaoFailed, $doubaoErrors) = geoBrandScanApiRun($chunks, $promptDB, $settings["api_key"], $settings["api_endpoint"], $settings["model"]);

    // Run brand scan with DeepSeek API if configured
    $hasDeepseek = !empty($settings["deepseek_api_key"]);
    $deepseekLines = [];
    $deepseekRaw = [];
    $deepseekFailed = 0;
    if ($hasDeepseek) {
        list($deepseekLines, $deepseekRaw, $deepseekFailed, $_) = geoBrandScanApiRun($chunks, $promptDS, $settings["deepseek_api_key"], $settings["deepseek_api_endpoint"], $settings["deepseek_model"]);
    }

    // Combine results
    $allMentionedLines = array_merge($doubaoLines, $deepseekLines);
    $allRawResponses = array_merge($doubaoRaw, $deepseekRaw);
    $failedCount = $doubaoFailed + $deepseekFailed;
    $doubaoCount = count($doubaoLines);
    $deepseekCount = count($deepseekLines);

    if (count($chunks) > 0 && $failedCount >= count($chunks) * ($hasDeepseek ? 2 : 1)) {
        $errors = array_merge($doubaoErrors, []);
        return ["error" => "All API requests failed: " . implode("; ", array_slice($errors, 0, 3))];
    }

    $allMentionedLines = array_unique($allMentionedLines);
    sort($allMentionedLines);
    $mentionedCount = count($allMentionedLines);
    $indexPercent = 0;
    $rawResponse = implode("\n---\n", $allRawResponses);
    try { dbExecute("CREATE TABLE IF NOT EXISTS `geo_brand_scan` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`brand_visible` TINYINT(1) NOT NULL DEFAULT 0,`brand_position` INT DEFAULT NULL,`keyword_count` INT NOT NULL DEFAULT 0,`doubao_count` INT NOT NULL DEFAULT 0,`deepseek_count` INT NOT NULL DEFAULT 0,`total_keywords` INT NOT NULL DEFAULT 0,`scan_percent` DECIMAL(5,1) NOT NULL DEFAULT 0.0,`raw_response` MEDIUMTEXT DEFAULT NULL,`created_at` DATETIME NOT NULL,INDEX `idx_user_date` (`user_id`,`created_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}
    $now = date("Y-m-d H:i:s");
    $scanDetailId = dbInsert("INSERT INTO geo_brand_scan (user_id,brand_visible,brand_position,keyword_count,doubao_count,deepseek_count,total_keywords,scan_percent,raw_response,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)", [
        $userId, $mentionedCount > 0 ? 1 : 0, $mentionedCount > 0 ? $allMentionedLines[0] : null, $mentionedCount, $doubaoCount, $deepseekCount, $totalKeywords, $indexPercent, $rawResponse, $now
    ]);

    // 逐词平台明细
    try { dbExecute("CREATE TABLE IF NOT EXISTS geo_brand_scan_details (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,scan_id INT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,keyword VARCHAR(200) NOT NULL,keyword_index INT NOT NULL DEFAULT 0,platform VARCHAR(50) NOT NULL DEFAULT '',mentioned TINYINT(1) NOT NULL DEFAULT 0,rank_position INT DEFAULT NULL,created_at DATETIME NOT NULL,INDEX idx_user_mention (user_id,mentioned),INDEX idx_scan (scan_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}
    $doubaoSet = array_flip($doubaoLines);
    $deepseekSet = array_flip($deepseekLines);
    $detailNow = date("Y-m-d H:i:s");
    foreach ($allKeywords as $idx => $kw) {
        $lineNum = $idx + 1;
        $keywordText = is_array($kw) ? ($kw["keyword"] ?? "") : $kw;
        if (isset($doubaoSet[$lineNum])) {
            dbInsert("INSERT INTO geo_brand_scan_details (scan_id,user_id,keyword,keyword_index,platform,mentioned,rank_position,created_at) VALUES (?,?,?,?,?,?,?,?)", [$scanDetailId, $userId, $keywordText, $lineNum, 'doubao', 1, $lineNum, $detailNow]);
        }
        if (isset($deepseekSet[$lineNum])) {
            dbInsert("INSERT INTO geo_brand_scan_details (scan_id,user_id,keyword,keyword_index,platform,mentioned,rank_position,created_at) VALUES (?,?,?,?,?,?,?,?)", [$scanDetailId, $userId, $keywordText, $lineNum, 'deepseek', 1, $lineNum, $detailNow]);
        }
    }

    return [
        "visible" => $mentionedCount > 0,
        "position" => $allMentionedLines[0] ?? null,
        "keyword_count" => $mentionedCount,
        "scan_percent" => $indexPercent,
        "total" => $totalKeywords,
        "raw" => geoSubstr($rawResponse, 0, 500),
        "time" => $now,
    ];
}function geoGenerateKeywords(int $userId): array {
    set_time_limit(900);
    $company = dbFetchOne("SELECT * FROM company_info WHERE user_id=?", [$userId]);
    if (!$company || empty($company["company_name"])) return ["error" => "Please fill in company name in enterprise info first"];
    $settings = geoGetAdminSettings();
    if (empty($settings["api_key"])) return ["error" => "API Key not configured"];
    
    $prompt = "你是一个研究中国用户AI搜索习惯的GEO优化专家。你的任务是模拟真实中国网民在AI搜索平台上的搜索行为，生成200个高质量关键词。

企业信息：
- 公司名称：{$company['company_name']}
- 公司简称：{$company['company_abbr']}
- 地区：{$company['region']}
- 行业：{$company['industry']}
- 产品服务：{$company['products_services']}
- 产品亮点：{$company['product_highlights']}
- 品牌故事：{$company['brand_story']}
- 信任背书：{$company['trust_endorsements']}
- 用户痛点：{$company['user_pain_points']}
- 客户案例：{$company['customer_cases']}

【核心要求】从真实用户的搜索问题角度出发，模拟中国网民在AI搜索时会输入的问题和关键词。

关键词类别（每类5-30个）：
1. 疑问词 - 用户真实会搜的问题，如XX怎么样、XX多少钱、XX怎么用、XX靠谱吗
2. 产品词 - 用户搜索具体产品或服务时用的词
3. 场景词 - 用户在什么情况下会搜这个词，如XX怎么办、XX之后怎么处理
4. 地域词 - 带上城市或地区的搜索词
5. 需求词 - 体现用户真实需求的短语
6. 对比词 - 用户做选择时会搜的词，如XX值得买吗、XX和XX的区别
7. 竞品词 - 用户找替代品时会搜的词
8. 行业热词 - 行业最新关注的话题
9. 组合词 - 需求+地域+产品的组合搜索
10. 通用词 - 2-4个字的行业通用热门搜索词

【格式】每行一个关键词|类别

【明确要求】
- 像普通人那样说话，口语化、大白话
- 从用户搜索问题的角度出发，想想用户遇到问题时会怎么打字搜
- 每个关键词4-10个字，短词为主
- 疑问词要占30%以上

【严禁出现】
- 解决方案、服务平台、系统、企业级 这类企业腔词汇
- 抽象的行业术语
- 听起来像AI凑数的词

总200个。";

    $payload = json_encode(["model" => $settings["model"], "messages" => [["role" => "system", "content" => "You are a professional GEO optimization analyst."], ["role" => "user", "content" => $prompt]], "max_tokens" => 4096]);
    $apiResult = geoApiCall($settings["api_endpoint"], $settings["api_key"], $payload, 480);
    $httpCode = $apiResult["http_code"];
    $response = $apiResult["response"];
    if ($apiResult["error"]) return ["error" => "API request failed: " . $apiResult["error"]];
    if ($httpCode !== 200) return ["error" => "API returned HTTP " . $httpCode];
    if (!$response) return ["error" => "API returned empty"];
    $data = json_decode($response, true);
    $content = $data["choices"][0]["message"]["content"] ?? $data["output"][0]["content"][0]["text"] ?? $data["output"][0]["content"] ?? $data["content"] ?? "";

    $lines = explode("\n", $content);
    $kws = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        if (strpos($line, "|") !== false) {
            $parts = explode("|", $line, 2);
            $kw = trim($parts[0]);
            $cat = trim($parts[1] ?? "");
        } else {
            $kw = preg_replace("/^\d+[\.\]\s]+/", "", $line);
            $cat = "";
        }
        $kw = preg_replace("/^\d+[\.\]\s]+/", "", $kw);
        if (strlen($kw) < 3 || strlen($kw) > 50) continue;
        $kws[] = ["keyword" => $kw, "category" => $cat];
    }
    $kws = array_slice($kws, 0, 500);
    try { dbExecute("CREATE TABLE IF NOT EXISTS `geo_keywords_distill` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`keyword` VARCHAR(200) NOT NULL,`category` VARCHAR(100) DEFAULT \"\",`generated_at` DATETIME NOT NULL,INDEX `idx_user` (`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}
    dbExecute("DELETE FROM geo_keywords_distill WHERE user_id=?", [$userId]);
    $now = date("Y-m-d H:i:s");
    foreach ($kws as $kw) { dbExecute("INSERT INTO geo_keywords_distill (user_id,keyword,category,generated_at) VALUES (?,?,?,?)", [$userId, $kw["keyword"], $kw["category"], $now]); }
    return ["count" => count($kws)];
}

/** Auto brand scan: check all users, auto-update those not scanned for over 1 hour */

function geoDetectKeywordsBatch(int $userId, int $timeout = 120): array {
    set_time_limit($timeout + 30);
    $settings = geoGetAdminSettings();
    if (empty($settings["api_key"])) return ["success" => 0, "failed" => 1, "errors" => ["API Key not configured"]];
    
    $keywords = dbFetchAll("SELECT * FROM geo_keywords WHERE user_id=? AND active=1 ORDER BY id", [$userId]);
    if (empty($keywords)) return ["success" => 0, "failed" => 0, "errors" => ["No active keywords"]];
    
    $costPerDetect = (float)($settings["cost_per_detect"] ?? 0);
    $totalCost = $costPerDetect * count($keywords);
    $user = dbFetchOne("SELECT balance FROM users WHERE id=?", [$userId]);
    $balance = (float)($user["balance"] ?? 0);
    
    if ($costPerDetect > 0 && $balance < $totalCost) {
        return ["success" => 0, "failed" => count($keywords), "errors" => ["Insufficient balance: need " . number_format($totalCost, 2) . ", have " . number_format($balance, 2)]];
    }
    
    // Deduct cost upfront
    if ($costPerDetect > 0) {
        $desc = "Batch detect " . count($keywords) . " keywords";
        dbExecute("UPDATE users SET balance = balance - ? WHERE id=?", [$totalCost, $userId]);
        dbExecute("INSERT INTO transactions (user_id,type,amount,description,created_at) VALUES (?,?,?,?,?)", [$userId, 'consume', $totalCost, $desc, date("Y-m-d H:i:s")]);
        
    }
    
    // Create curl handles
    $handles = [];
    $multi = curl_multi_init();
    $kwMap = [];
    
    foreach ($keywords as $kw) {
        $prompt = "List the most well-known brands or companies in the field of " . $kw["keyword"] . ". If " . $kw["brand_name"] . " is mentioned, specify its rank.";
        $payload = json_encode(["model" => $settings["model"], "messages" => [["role" => "system", "content" => "You are a professional industry analysis assistant."], ["role" => "user", "content" => $prompt]], "max_tokens" => 1024], JSON_INVALID_UTF8_SUBSTITUTE);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $settings["api_endpoint"],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $settings["api_key"]
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        curl_multi_add_handle($multi, $ch);
        $handles[(int)$kw["id"]] = $ch;
        $kwMap[(int)$kw["id"]] = $kw;
    }
    
    // Execute all requests in parallel
    $running = null;
    do {
        $status = curl_multi_exec($multi, $running);
        if ($running) curl_multi_select($multi, 1);
    } while ($running > 0 && $status === CURLM_OK);
    
    // Process results
    $success = 0;
    $failed = 0;
    $now = date("Y-m-d H:i:s");
    
    foreach ($kwMap as $kid => $kw) {
        $ch = $handles[$kid];
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($response)) {
            $failed++;
            continue;
        }
        
        $data = json_decode($response, true);
        $content = $data["choices"][0]["message"]["content"] ?? $data["output"][0]["content"][0]["text"] ?? $data["output"][0]["content"] ?? $data["content"] ?? "";
        
        if (empty($content)) { $failed++; continue; }
        
        $brandMentioned = geoStripos($content, $kw["brand_name"]) !== false;
        $position = null;
        if ($brandMentioned) {
            foreach (explode("\n", $content) as $i => $line) {
                if (geoStripos($line, $kw["brand_name"]) !== false) { $position = $i + 1; break; }
            }
        }
        
        dbExecute("INSERT INTO geo_results (keyword_id,brand_mentioned,rank_position,response_snippet,raw_response,checked_at) VALUES (?,?,?,?,?,?)", 
            [$kid, $brandMentioned ? 1 : 0, $position, geoSubstr($content, 0, 500), $content, $now]);
        dbExecute("UPDATE geo_keywords SET active=1 WHERE id=?", [$kid]);
        $success++;
    }
    
    curl_multi_close($multi);
    return ["success" => $success, "failed" => $failed, "total_cost" => $costPerDetect > 0 ? $costPerDetect * $success : 0];
}


/** Render indexed long-tail keywords below the dashboard chart */



/** Generate 100 keywords and APPEND to existing ones (no deletion) */

/** Normalize category name to standard list */
function geoNormalizeCategory($cat, $stdCats) {
    if (empty($cat)) return "";
    foreach ($stdCats as $sc) {
        if (mb_stripos($cat, $sc) !== false) return $sc;
    }
    return "";
}
function geoGenerateKeywordsAppend(int $userId): array {
    set_time_limit(480);
    $company = dbFetchOne("SELECT * FROM company_info WHERE user_id=?", [$userId]);
    if (!$company || empty($company["company_name"])) return ["error" => "请先在企业信息中填写公司名称"];
    $settings = geoGetAdminSettings();
    if (empty($settings["api_key"])) return ["error" => "API Key 未配置"];
    
    
        

    $prompt = "你是一个研究中国用户AI搜索习惯的GEO优化专家。你的任务是模拟真实中国网民在AI搜索平台上的搜索行为，生成100个高质量关键词。

企业信息：
- 公司名称：{$company['company_name']}
- 公司简称：{$company['company_abbr']}
- 地区：{$company['region']}
- 行业：{$company['industry']}
- 产品服务：{$company['products_services']}
- 产品亮点：{$company['product_highlights']}
- 品牌故事：{$company['brand_story']}
- 信任背书：{$company['trust_endorsements']}
- 用户痛点：{$company['user_pain_points']}
- 客户案例：{$company['customer_cases']}

【核心要求】从真实用户的搜索问题角度出发，模拟中国网民在AI搜索时会输入的问题和关键词。

关键词类别（每类10个，平均分布）：
1. 疑问词 - 用户真实会搜的问题，如XX怎么样、XX多少钱、XX怎么用、XX靠谱吗
2. 产品词 - 用户搜索具体产品或服务时用的词
3. 场景词 - 用户在什么情况下会搜这个词，如XX怎么办、XX之后怎么处理
4. 地域词 - 带上城市或地区的搜索词
5. 需求词 - 体现用户真实需求的短语
6. 对比词 - 用户做选择时会搜的词，如XX值得买吗、XX和XX的区别
7. 竞品词 - 用户找替代品时会搜的词
8. 行业热词 - 行业最新关注的话题
9. 组合词 - 需求+地域+产品的组合搜索
10. 通用词 - 2-4个字的行业通用热门搜索词

【格式】每行一个关键词|类别

【明确要求】
- 像普通人那样说话，口语化、大白话
- 从用户搜索问题的角度出发，想想用户遇到问题时会怎么打字搜
- 每个关键词4-10个字，短词为主
- 每类关键词数量尽量平均，各10个左右

【严禁出现】
- 解决方案、服务平台、系统、企业级 这类企业腔词汇
- 抽象的行业术语
- 听起来像AI凑数的词

总100个。";
    $payload = json_encode(["model" => $settings["model"], "messages" => [["role" => "system", "content" => "You are a professional GEO optimization analyst."], ["role" => "user", "content" => $prompt]], "max_tokens" => 4096]);
    $apiResult = geoApiCall($settings["api_endpoint"], $settings["api_key"], $payload, 360);
    $httpCode = $apiResult["http_code"];
    $response = $apiResult["response"];
    if ($apiResult["error"]) return ["error" => "API request failed: " . $apiResult["error"]];
    if ($httpCode !== 200) return ["error" => "API returned HTTP " . $httpCode];
    if (!$response) return ["error" => "API returned empty"];
    $data = json_decode($response, true);
    $content = $data["choices"][0]["message"]["content"] ?? $data["output"][0]["content"][0]["text"] ?? $data["output"][0]["content"] ?? $data["content"] ?? "";
    if (empty($content)) return ["error" => "API returned empty content"];

    $lines = explode("\n", $content);
    $kws = [];
    $currentCat = "";
    // Standard 10 categories for matching
    $stdCats = ["疑问词","产品词","场景词","地域词","需求词","对比词","竞品词","行业热词","组合词","通用词"];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Check if this line is a category header (e.g., "疑问词：" or "疑问词:")
        $headerCat = "";
        foreach ($stdCats as $sc) {
            if (preg_match("/^" . preg_quote($sc, "/") . "[：:]/u", $line)) {
                $headerCat = $sc;
                break;
            }
        }
        if ($headerCat) {
            $currentCat = $headerCat;
            continue;
        }
        
        // Try pipe-separated format: keyword|category
        if (strpos($line, "|") !== false) {
            $parts = explode("|", $line, 2);
            $kw = trim($parts[0]);
            $cat = trim($parts[1] ?? "");
            // Normalize category
            $cat = geoNormalizeCategory($cat, $stdCats);
        } else {
            // Strip number prefix
            $kw = preg_replace("/^\d+[\.\]\s]+/", "", $line);
            $cat = $currentCat;
        }
        $kw = preg_replace("/^\d+[\.\]\s]+/", "", $kw);
        if (strlen($kw) < 2 || strlen($kw) > 50) continue;
        if (empty($kw)) continue;
        $kws[] = ["keyword" => $kw, "category" => $cat];
    }
    
    // Assign any empty categories to current category or 通用词
    foreach ($kws as &$kw) {
        if (empty($kw["category"])) {
            $kw["category"] = "通用词";
        }
    }
    unset($kw);
    
        
$kws = array_slice($kws, 0, 100);

    $now = date("Y-m-d H:i:s");
    $inserted = 0;
    foreach ($kws as $kw) {
        dbExecute("INSERT INTO geo_keywords_distill (user_id,keyword,category,generated_at) VALUES (?,?,?,?)", [$userId, $kw["keyword"], $kw["category"], $now]);
        $inserted++;
    }
    return ["count" => $inserted];
}
function geoAutoBrandScan(): void {
    $users = dbFetchAll("SELECT c.user_id FROM company_info c LEFT JOIN geo_brand_scan s ON c.user_id=s.user_id GROUP BY c.user_id HAVING MAX(s.created_at) IS NULL OR MAX(s.created_at) < DATE_SUB(NOW(), INTERVAL 8 HOUR)");
    foreach ($users as $u) {
        $uid = (int)$u["user_id"];
        $pending = dbFetchOne("SELECT id FROM geo_scan_queue WHERE user_id=? AND status IN ('pending','processing')", [$uid]);
        if ($pending) continue;
        $recent = dbFetchOne("SELECT id FROM geo_scan_queue WHERE user_id=? AND created_at > DATE_SUB(NOW(), INTERVAL 8 HOUR) LIMIT 1", [$uid]);
        if ($recent) continue;
        dbExecute("INSERT INTO geo_scan_queue (user_id,status,created_at) VALUES (?,'pending',NOW())", [$uid]);
        appLog("Auto enqueued scan for user #{$uid}", "INFO");
    }
    }








/**
 * Run brand scan batches IN PARALLEL using curl_multi_exec.
 */
function geoBrandScanApiRun(array $chunks, string $promptPrefix, string $apiKey, string $endpoint, string $model, string $systemPrompt = "You are a precise AI search analysis tool. Return ONLY line numbers, one per line, nothing else."): array {
    $multi = curl_multi_init();
    $handles = [];
    $mentionedLines = [];
    $rawResponses = [];
    $failedCount = 0;
    $errors = [];

    $globalOffset = 0;
    foreach ($chunks as $i => $chunk) {
        $kwLines = [];
        foreach ($chunk as $j => $kw) {
            $lineNum = $globalOffset + $j + 1;
            $keywordText = is_array($kw) ? ($kw["keyword"] ?? "") : $kw;
            $kwLines[] = $lineNum . ". " . $keywordText;
        }
        $globalOffset += count($chunk);

        $prompt = $promptPrefix . implode("\n", $kwLines);
        $payload = json_encode([
            "model" => $model,
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => $prompt]
            ],
            "max_tokens" => 4096
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bearer " . $apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[] = ["handle" => $ch, "idx" => $i];
    }

    $running = null;
    do {
        curl_multi_exec($multi, $running);
        if ($running > 0) curl_multi_select($multi, 10);
    } while ($running > 0);

    foreach ($handles as $info) {
        $ch = $info["handle"];
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            $failedCount++;
            $errors[] = $error ?: "HTTP " . $httpCode;
            continue;
        }

        $rawResponses[] = $response;
        $data = json_decode($response, true);
        $content = $data["choices"][0]["message"]["content"] ?? $data["output"][0]["content"][0]["text"] ?? $data["output"][0]["content"] ?? $data["content"] ?? "";
        $content = trim($content);

        if (strtoupper($content) !== "NONE" && $content !== "无" && !empty($content)) {
            preg_match_all("/^\d+/m", $content, $lineMatches);
            foreach ($lineMatches[0] as $ln) {
                $mentionedLines[] = (int)$ln;
            }
        }
    }

    curl_multi_close($multi);
    sort($mentionedLines);
    return [$mentionedLines, $rawResponses, $failedCount, $errors];
}


function renderIndexedKeywords($userId, $scanResult) {
    try { dbExecute("CREATE TABLE IF NOT EXISTS `geo_brand_scan_details` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`scan_id` INT UNSIGNED NOT NULL,`user_id` INT UNSIGNED NOT NULL,`keyword` VARCHAR(200) NOT NULL,`keyword_index` INT NOT NULL DEFAULT 0,`platform` VARCHAR(50) NOT NULL DEFAULT '',`mentioned` TINYINT(1) NOT NULL DEFAULT 0,`rank_position` INT DEFAULT NULL,`created_at` DATETIME NOT NULL,INDEX `idx_user_mention` (`user_id`,`mentioned`),INDEX `idx_scan` (`scan_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}
    $details = dbFetchAll("SELECT keyword, platform, rank_position FROM geo_brand_scan_details WHERE user_id=? AND mentioned=1 ORDER BY rank_position ASC LIMIT 30", [$userId]);

    if (empty($details)) {
        if (!$scanResult || empty($scanResult["raw_response"])) return;
        $indexedLines = [];
        foreach (explode("\n", $scanResult["raw_response"]) as $l) {
            $l = trim($l);
            if (is_numeric($l)) $indexedLines[] = (int)$l;
        }
        if (empty($indexedLines)) return;
        $d = dbFetchAll("SELECT keyword FROM geo_keywords_distill WHERE user_id=? ORDER BY id ", [$userId]);
        $m = dbFetchAll("SELECT keyword FROM geo_keywords_manual WHERE user_id=? ORDER BY id", [$userId]);
        $allKws = array_merge($d, $m);
        foreach ($indexedLines as $num) {
            $idx = $num - 1;
            if (isset($allKws[$idx])) $details[] = ["keyword" => $allKws[$idx]["keyword"], "platform" => "", "rank_position" => $num];
        }
        $details = array_slice($details, 0, 15);
    }

    if (empty($details)) return;

    echo '<div style="margin-top:20px;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.08);overflow:hidden;">';
    echo '<div style="padding:14px 16px;border-bottom:1px solid #f3f4f6;font-size:14px;font-weight:600;color:#374151;">被收录长尾关键词 <span style="font-weight:400;font-size:12px;color:#9ca3af;">' . count($details) . '个</span></div>';
    echo '<div style="padding:8px 16px;"><table style="width:100%;border-collapse:collapse;font-size:13px;">';
    echo '<thead><tr style="border-bottom:1px solid #f3f4f6;"><th style="padding:8px 6px;text-align:left;color:#9ca3af;font-weight:500;width:40px;">#</th><th style="padding:8px 6px;text-align:left;color:#9ca3af;font-weight:500;">关键词</th><th style="padding:8px 6px;text-align:center;color:#9ca3af;font-weight:500;width:80px;">收录平台</th></tr></thead><tbody>';
    foreach ($details as $row) {
        $platforms = !empty($row["platform"]) ? explode(",", $row["platform"]) : [];
        $badge = "";
        if (in_array("doubao", $platforms)) $badge .= '<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:#eef2ff;color:#4f46e5;margin:1px;">豆包</span>';
        if (in_array("deepseek", $platforms)) $badge .= '<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:#fef3c7;color:#d97706;margin:1px;">DeepSeek</span>';
        if (empty($badge)) $badge = '<span style="color:#9ca3af;font-size:11px;">-</span>';

        echo '<tr style="border-bottom:1px solid #f9fafb;"><td style="padding:8px 6px;color:#6b7280;font-size:11px;">' . (int)$row["rank_position"] . '</td><td style="padding:8px 6px;color:#374151;">' . h($row["keyword"]) . '</td><td style="padding:8px 6px;text-align:center;">' . $badge . '</td></tr>';
    }
    echo '</tbody></table></div></div>';
}
