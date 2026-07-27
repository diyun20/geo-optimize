<?php
/**
 * 后台文章生成工作进程（CLI 模式）
 * 用法: php background_article.php [article_id]
 */
if (php_sapi_name() !== "cli") { http_response_code(403); exit; }

$docRoot = __DIR__;
require_once $docRoot . "/config/database.php";
require_once $docRoot . "/includes/functions.php";
require_once $docRoot . "/includes/db.php";
require_once $docRoot . "/includes/geo.php";

date_default_timezone_set("Asia/Hong_Kong");

if (!claimWorkerSlot()) {
    fwrite(STDOUT, "[" . date("H:i:s") . "] Worker pool full, queue item will wait\n");
    exit;
}

// Ensure articles table has status column
try { dbExecute("ALTER TABLE geo_articles ADD COLUMN `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'completed' AFTER `content`"); } catch (Exception $e) {}

$requestedId = isset($argv[1]) ? (int)$argv[1] : 0;

function processArticleTask(int $articleId): void {
    $article = dbFetchOne("SELECT * FROM geo_articles WHERE id=? AND status='pending'", [$articleId]);
    if (!$article) {
        fwrite(STDOUT, "[" . date("H:i:s") . "] No pending article #{$articleId}\n");
        return;
    }

    $userId = (int)$article["user_id"];
    dbExecute("UPDATE geo_articles SET status='processing', started_at=NOW() WHERE id=?", [$articleId]);

    // 检测是否是美化任务
    $meta = json_decode($article["keywords"], true);
    $isBeautify = is_array($meta) && ($meta['type'] ?? '') === 'beautify';

    if ($isBeautify) {
        // ── AI 美化任务 ──
        fwrite(STDOUT, "[" . date("H:i:s") . "] Beautifying article #{$articleId} for user #{$userId}...\n");
        $provider = $meta['provider'] ?? 'doubao';
        require_once __DIR__ . '/includes/geo.php';
        $cfg = geoGetApiProvider($provider);
        if (empty($cfg['api_key'])) {
            // fallback: try admin settings
            $settings = geoGetAdminSettings();
            $cfg = ['api_key'=>$settings['api_key']??'', 'api_endpoint'=>$settings['api_endpoint']??'', 'model'=>$settings['model']??''];
        }
        if (empty($cfg['api_key'])) {
            fwrite(STDERR, "[" . date("H:i:s") . "] Beautify failed: API Key not configured for {$provider}\n");
            dbExecute("UPDATE geo_articles SET status='failed', completed_at=NOW() WHERE id=?", [$articleId]);
            return;
        }

        $brand   = $article["brand_name"];
        $origTitle = $meta['original_title'] ?? $article["topic"];
        $origContent = $meta['original_content'] ?? '';

        $prompt  = "请根据以下要求，为我创作一篇自媒体热门文章: 文章类型:自媒体文章\n";
        $prompt .= "1.内容要有具体数据和案例支撑； 2.分点论述，每点都要有实操建议； 3.适当加入自己的观点和思考； 4.不要输出其他文章无关的信息；\n";
        $prompt .= "限制\n";
        $prompt .= "1、不要加网址跟电话等违规；\n";
        $prompt .= "2、禁止出现AI分析的话术跟语句，直接写出文章结果；\n";
        $prompt .= "3、不要输出其他文章内容无关的信息，不限于文章字数、提示词分析等无关信息；\n";
        $prompt .= "4、字数要求1000到2000字(不要在文章内显示本文有多少字)； 5、禁止虚构数据跟资料；\n";
        $prompt .= "6、不要违反广告法；\n\n";
        $prompt .= "品牌名称：{$brand}\n";
        $prompt .= "重要：请在文章中自然地融入品牌\"{$brand}\"的软性推广，以案例、使用场景或推荐的方式提及，不要生硬广告。\n";
        $prompt .= "以下是原文的标题和内容，请基于此进行优化重写：\n";
        $prompt .= "原标题：{$origTitle}\n";
        $prompt .= "原文内容：\n{$origContent}\n\n";
        $prompt .= "输出格式要求：第一行输出优化后的文章标题（不要加【标题：】等前缀），空一行后输出优化后的正文。";

        $payload = json_encode([
            "model" => $cfg['model'],
            "messages" => [
                ["role" => "system", "content" => "你是一位资深的自媒体文章创作专家，擅长撰写热门爆款文章。"],
                ["role" => "user", "content" => $prompt]
            ],
            "max_tokens" => 4096
        ]);
        $apiResult = geoApiCall($cfg['api_endpoint'], $cfg['api_key'], $payload, 180);

        if ($apiResult["error"]) {
            fwrite(STDERR, "[" . date("H:i:s") . "] Beautify API failed: {$apiResult["error"]}\n");
            dbExecute("UPDATE geo_articles SET status='failed', completed_at=NOW() WHERE id=?", [$articleId]);
            return;
        }
        if ($apiResult['http_code'] !== 200) {
            fwrite(STDERR, "[" . date("H:i:s") . "] Beautify API HTTP {$apiResult['http_code']}\n");
            dbExecute("UPDATE geo_articles SET status='failed', completed_at=NOW() WHERE id=?", [$articleId]);
            return;
        }

        $data = json_decode($apiResult["response"], true);
        $raw = $data['choices'][0]['message']['content'] ?? $data['output'][0]['content'][0]['text'] ?? $data['output'][0]['content'] ?? $data['content'] ?? '';
        if (empty($raw)) {
            fwrite(STDERR, "[" . date("H:i:s") . "] Beautify API returned empty content\n");
            dbExecute("UPDATE geo_articles SET status='failed', completed_at=NOW() WHERE id=?", [$articleId]);
            return;
        }

        // 解析：第一行标题，空行后正文
        $raw = trim($raw);
        $lines = explode("\n", $raw);
        $newTitle = trim(array_shift($lines));
        while (!empty($lines) && trim($lines[0]) === '') { array_shift($lines); }
        $newContent = trim(implode("\n", $lines));
        if (empty($newContent)) { $newContent = $raw; }
        if (empty($newTitle))   { $newTitle = $origTitle; }

        dbExecute("UPDATE geo_articles SET topic=?, content=?, status='completed', completed_at=NOW() WHERE id=?", [$newTitle, $newContent, $articleId]);
        fwrite(STDOUT, "[" . date("H:i:s") . "] Article #{$articleId} beautified successfully\n");
        return;
    }

    // ── AI 代写更新日志 ──
    $isChangelog = is_array($meta) && ($meta['type'] ?? '') === 'changelog';
    if ($isChangelog) {
        fwrite(STDOUT, "[" . date("H:i:s") . "] Generating changelog #{$articleId}...\n");
        $apiEndpoint = $meta['api_endpoint'] ?? '';
        $apiKey = $meta['api_key'] ?? '';
        $model = $meta['model'] ?? 'doubao-seed-evolving';
        $list = $meta['list'] ?? '';

        if (empty($apiKey)) {
            fwrite(STDERR, "[" . date("H:i:s") . "] Changelog failed: API Key not configured\n");
            dbExecute("UPDATE geo_articles SET status='failed', completed_at=NOW() WHERE id=?", [$articleId]);
            return;
        }

        $prompt = "分析以下文件变更，总结本次更新了哪些功能（不要列文件名，用中文描述，参考宝塔更新日志风格，分为【新增】和【优化】两类输出）：\n\n".$list;
        $payload = json_encode([
            "model" => $model,
            "messages" => [["role" => "user", "content" => $prompt]],
            "max_tokens" => 2000
        ]);

        $apiResult = geoApiCall($apiEndpoint, $apiKey, $payload, 120);
        if ($apiResult["error"]) {
            fwrite(STDERR, "[" . date("H:i:s") . "] Changelog API failed: {$apiResult["error"]}\n");
            dbExecute("UPDATE geo_articles SET status='failed', content=?, completed_at=NOW() WHERE id=?", ["API错误: ".$apiResult["error"], $articleId]);
            return;
        }
        if ($apiResult['http_code'] !== 200) {
            fwrite(STDERR, "[" . date("H:i:s") . "] Changelog API HTTP {$apiResult['http_code']}\n");
            dbExecute("UPDATE geo_articles SET status='failed', content=?, completed_at=NOW() WHERE id=?", ["API返回: ".$apiResult['http_code'], $articleId]);
            return;
        }

        $data = json_decode($apiResult["response"], true);
        $text = $data['choices'][0]['message']['content'] ?? '';
        if (empty($text)) {
            dbExecute("UPDATE geo_articles SET status='failed', completed_at=NOW() WHERE id=?", [$articleId]);
            return;
        }

        dbExecute("UPDATE geo_articles SET content=?, status='completed', completed_at=NOW() WHERE id=?", [trim($text), $articleId]);
        fwrite(STDOUT, "[" . date("H:i:s") . "] Changelog #{$articleId} generated\n");
        return;
    }

    // ── 普通文章生成 ──
    fwrite(STDOUT, "[" . date("H:i:s") . "] Generating article #{$articleId} for user #{$userId}...\n");

    $settings = geoGetAdminSettings();
    if (empty($settings["api_key"])) {
        fwrite(STDERR, "[" . date("H:i:s") . "] Failed: API Key not configured\n");
        dbExecute("UPDATE geo_articles SET status='failed', completed_at=NOW() WHERE id=?", [$articleId]);
        return;
    }

    $brand = $article["brand_name"];
    $topic = $article["topic"];
    $kwText = $article["keywords"];

    $prompt = "你是一个自媒体文章创作专家。\n\n";
    $prompt .= "品牌名称：{$brand}\n";
    $prompt .= "文章主题：{$topic}\n";
    if ($kwText) {
        $prompt .= "相关关键词：{$kwText}\n";
    }
    $prompt .= "\n请根据以下要求，为我创作一篇自媒体热门文章：\n\n";
    $prompt .= "文章类型:自媒体文章\n";
    $prompt .= "1.内容要有具体数据和案例支撑；\n";
    $prompt .= "2.分点论述，每点都要有实操建议；\n";
    $prompt .= "3.适当加入自己的观点和思考；\n";
    $prompt .= "4.不要输出其他文章无关的信息；\n\n";
    $prompt .= "限制\n";
    $prompt .= "1、不要加网址跟电话等违规；\n";
    $prompt .= "2、禁止出现AI分析的话术跟语句，直接写出文章结果；\n";
    $prompt .= "3、不要输出其他文章内容无关的信息，不限于文章字数、提示词分析等无关信息；\n";
    $prompt .= "4、字数要求1000到2000字(不要在文章内显示本文有多少字)；\n";
    $prompt .= "5、禁止虚构数据跟资料；\n";
    $prompt .= "6、不要违反广告法；\n";

    $payload = json_encode([
        "model" => $settings["model"],
        "messages" => [
            ["role" => "system", "content" => "你是一位资深的自媒体文章创作专家，擅长撰写符合GEO收录标准的高质量文章。"],
            ["role" => "user", "content" => $prompt]
        ],
        "max_tokens" => 4096
    ]);

    $apiResult = geoApiCall($settings["api_endpoint"], $settings["api_key"], $payload, 120);

    if ($apiResult["error"]) {
        fwrite(STDERR, "[" . date("H:i:s") . "] API failed: {$apiResult["error"]}\n");
        dbExecute("UPDATE geo_articles SET status='failed', completed_at=NOW() WHERE id=?", [$articleId]);
        return;
    }

    $data = json_decode($apiResult["response"], true);
    $content = $data["choices"][0]["message"]["content"] ?? $data["output"][0]["content"][0]["text"] ?? $data["output"][0]["content"] ?? $data["content"] ?? "";

    if (empty($content)) {
        fwrite(STDERR, "[" . date("H:i:s") . "] API returned empty content\n");
        dbExecute("UPDATE geo_articles SET status='failed', completed_at=NOW() WHERE id=?", [$articleId]);
        return;
    }

    dbExecute("UPDATE geo_articles SET content=?, status='completed', completed_at=NOW() WHERE id=?", [$content, $articleId]);
    fwrite(STDOUT, "[" . date("H:i:s") . "] Article #{$articleId} generated successfully\n");
}

if ($requestedId > 0) {
    processArticleTask($requestedId);
}

// Chain
$chained = 0;
while ($chained < MAX_WORKERS) {
    $next = dbFetchOne("SELECT id FROM geo_articles WHERE status='pending' ORDER BY id ASC LIMIT 1");
    if (!$next) break;
    if ((int)$next["id"] === $requestedId) { break; }
    processArticleTask((int)$next["id"]);
    $chained++;
}

fwrite(STDOUT, "[" . date("H:i:s") . "] Article worker exits\n");
