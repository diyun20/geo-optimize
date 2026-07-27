<?php
/**
 * GEO queue worker
 * CLI:  php cron_worker.php
 * HTTP: curl http://site/cron_worker.php?key=xxx
 * 瀹濆璁″垝浠诲姟: curl -s http://geo.diyunuu.cn/cron_worker.php?key=geo_cron_2024
 */
$secretKey = "geo_cron_2024";
$isCli = (php_sapi_name() === "cli");
if ($isCli) {
    // CLI mode: no key required
} else {
    header("Content-Type: text/plain; charset=utf-8");
    $inputKey = $_GET["key"] ?? "";
    if ($inputKey !== $secretKey) { echo "Access denied\n"; http_response_code(403); exit; }
    set_time_limit(55);
    ignore_user_abort(true);
    session_write_close();
}

$maxRunTime = 50;
$startTime = time();
$docRoot = __DIR__;

require_once $docRoot . '/config/database.php';
require_once $docRoot . '/includes/functions.php';
require_once $docRoot . '/includes/db.php';
require_once $docRoot . '/includes/geo.php';

date_default_timezone_set('Asia/Hong_Kong');
geoInitQueueTable();
geoInitTables();

// Task: Batch process keyword detection for non-admin users
$qProcessing = dbFetchOne("SELECT id FROM geo_queue WHERE status='processing' LIMIT 1");
if (!$qProcessing) {
    $pendingUsers = dbFetchAll("SELECT DISTINCT user_id FROM geo_queue WHERE status='pending' ORDER BY user_id");
    foreach ($pendingUsers as $pu) {
        if (time() - $startTime > $maxRunTime) break;
        $uid = (int)$pu['user_id'];
        $uinfo = dbFetchOne("SELECT role FROM users WHERE id=?", [$uid]);
        if ($uinfo && $uinfo['role'] === 'admin') { echo date('H:i:s') . " [Queue] Skipping admin #{$uid}\n"; continue; }
        $pendingCount = dbFetchOne("SELECT COUNT(*) as c FROM geo_queue WHERE user_id=? AND status='pending'", [$uid])['c'];
        if ($pendingCount == 0) continue;
        echo date('H:i:s') . " [Queue] User #{$uid}: {$pendingCount} pending keywords\n";
        dbExecute("UPDATE geo_queue SET status='processing' WHERE user_id=? AND status='pending'", [$uid]);
        $batchResult = geoDetectKeywordsBatch($uid, 120);
        if (isset($batchResult['error'])) {
            echo date('H:i:s') . " [Queue] Batch failed: {$batchResult['error']}\n";
            dbExecute("UPDATE geo_queue SET status='failed', completed_at=NOW() WHERE user_id=? AND status='processing'", [$uid]);
        } else {
            echo date('H:i:s') . " [Queue] Batch done: {$batchResult['success']} success, {$batchResult['failed']} failed\n";
            dbExecute("UPDATE geo_queue SET status='completed', completed_at=NOW() WHERE user_id=? AND status='processing'", [$uid]);
        }
    }
}

// Task: Process keyword distillation
$distillTask = dbFetchOne("SELECT * FROM geo_distill_queue WHERE status='pending' ORDER BY id ASC LIMIT 1");
if ($distillTask && (time() - $startTime) < $maxRunTime) {
    echo date('H:i:s') . " [Distill] Generating keywords for user #{$distillTask['user_id']}...\n";
    dbExecute("UPDATE geo_distill_queue SET status='processing', started_at=NOW() WHERE id=?", [$distillTask['id']]);
    $result = geoGenerateKeywords((int)$distillTask['user_id']);
    if (isset($result['error'])) {
        echo date('H:i:s') . " [Distill] Failed: {$result['error']}\n";
        dbExecute("UPDATE geo_distill_queue SET status='failed', error=?, completed_at=NOW() WHERE id=?", [$result['error'], $distillTask['id']]);
    } else {
        echo date('H:i:s') . " [Distill] Done: {$result['count']} keywords\n";
        dbExecute("UPDATE geo_distill_queue SET status='completed', completed_at=NOW() WHERE id=?", [$distillTask['id']]);
    }
}


// Task: Process GEO detect queue
$detectTask = dbFetchOne("SELECT * FROM geo_detect_results WHERE status='pending' ORDER BY id ASC LIMIT 1");
if ($detectTask && (time() - $startTime) < $maxRunTime) {
    echo date('H:i:s') . " [Detect] Processing detect #{$detectTask['id']}...\n";
    dbExecute("UPDATE geo_detect_results SET status='processing' WHERE id=?", [$detectTask['id']]);
    $settings = geoGetAdminSettings();
    if (!empty($settings["api_key"])) {
        $prompt = "用户搜索了：" . $detectTask["question"] . "\n\n请先回答用户的搜索问题。\n\n回答完成后，请在最后一行单独输出：\n品牌提及：[是/否]\n\n注意：请判断「" . $detectTask["brand"] . "」是否在你的回答中被提及或推荐。";
        $payload = json_encode(["model" => $settings["model"], "messages" => [["role" => "system", "content" => "你是一个 AI 搜索助手，请如实回答用户问题。"], ["role" => "user", "content" => $prompt]], "max_tokens" => 4096]);
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $settings["api_endpoint"], CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bearer " . $settings["api_key"]], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err || $httpCode !== 200) {
            dbExecute("UPDATE geo_detect_results SET status='failed', error=?, completed_at=NOW() WHERE id=?", [$err ?: "HTTP $httpCode", $detectTask['id']]);
            echo date('H:i:s') . " [Detect] Failed: " . ($err ?: "HTTP $httpCode") . "\n";
        } else {
            $data = json_decode($response, true);
            $rt = $data["choices"][0]["message"]["content"] ?? $data["output"][0]["content"][0]["text"] ?? $data["output"][0]["content"] ?? $data["content"] ?? "";
            $bm = (int)(mb_stripos($rt, $detectTask["brand"]) !== false || preg_match("/品牌提及：是/u", $rt));
            $bp = null; if (preg_match("/品牌排名：(\d+)/u", $rt, $m)) $bp = (int)$m[1];
            dbExecute("UPDATE geo_detect_results SET status='completed', result_text=?, brand_mentioned=?, brand_position=?, platform='豆包', completed_at=NOW() WHERE id=?", [$rt, $bm, $bp, $detectTask['id']]);
            echo date('H:i:s') . " [Detect] Done\n";
        }
    } else {
        dbExecute("UPDATE geo_detect_results SET status='failed', error='API Key not configured', completed_at=NOW() WHERE id=?", [$detectTask['id']]);
        echo date('H:i:s') . " [Detect] No API key\n";
    }
}

// Task: Process brand scan queue
// Reset tasks stuck as processing for > 30 min
$stuckScan = dbFetchOne("SELECT id,user_id FROM geo_scan_queue WHERE status='processing' AND started_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE) LIMIT 1");
if ($stuckScan) {
    echo date('H:i:s') . " [Scan] Resetting stuck task #{$stuckScan['id']} (user #{$stuckScan['user_id']})\n";
    dbExecute("UPDATE geo_scan_queue SET status='pending',error=NULL,started_at=NULL,completed_at=NULL WHERE id=?", [$stuckScan['id']]);
}


// Direct scan processing (no background process needed)
$scanTask = dbFetchOne("SELECT id,user_id FROM geo_scan_queue WHERE status='pending' ORDER BY id ASC LIMIT 1");
if ($scanTask) {
    echo date("H:i:s") . " [Scan] Processing scan for user #{$scanTask["user_id"]}...\n";
    dbExecute("UPDATE geo_scan_queue SET status='processing', started_at=NOW() WHERE id=?", [$scanTask["id"]]);

    try {
        geoInitTables();
        $result = geoBrandScan((int)$scanTask["user_id"]);

        if (isset($result["error"])) {
            echo date("H:i:s") . " [Scan] Failed: {$result["error"]}\n";
            dbExecute("UPDATE geo_scan_queue SET status='failed', error=?, completed_at=NOW() WHERE id=?", [$result["error"], $scanTask["id"]]);
        } else {
            echo date("H:i:s") . " [Scan] Done: {$result["scan_percent"]}%\n";
            dbExecute("UPDATE geo_scan_queue SET status='completed', completed_at=NOW() WHERE id=?", [$scanTask["id"]]);
        }
    } catch (Exception $e) {
        echo date("H:i:s") . " [Scan] Exception: " . $e->getMessage() . "\n";
        dbExecute("UPDATE geo_scan_queue SET status='failed', error=?, completed_at=NOW() WHERE id=?", [$e->getMessage(), $scanTask["id"]]);
    } catch (Error $e) {
        echo date("H:i:s") . " [Scan] Fatal: " . $e->getMessage() . "\n";
        dbExecute("UPDATE geo_scan_queue SET status='failed', error=?, completed_at=NOW() WHERE id=?", [$e->getMessage(), $scanTask["id"]]);
    }
}
// Task: Auto tasks (scan/geo_detect)
if ((time() - $startTime) < $maxRunTime) {
    $autoTasks = dbFetchAll("SELECT * FROM auto_tasks WHERE enabled=1");
    foreach ($autoTasks as $at) {
        if (time() - $startTime > $maxRunTime) break;
        $intervalSec = ($at["interval_hours"] * 3600) + ($at["interval_minutes"] * 60);
        if ($intervalSec <= 0) continue;
        $lastRun = $at["last_run_at"] ? strtotime($at["last_run_at"]) : 0;
        if (time() - $lastRun < $intervalSec) continue;
        echo date("H:i:s") . " [AutoTask] Running {$at["type"]}: {$at["name"]}\n";
        dbExecute("UPDATE auto_tasks SET last_run_at=NOW() WHERE id=?", [$at["id"]]);
        if ($at["type"] === "scan") {
            $targetUsers = $at["scope"] === "user"
                ? [["id" => $at["target_user_id"]]]
                : dbFetchAll("SELECT id FROM users WHERE role!='admin'");
            // 读取排除天数设置
            $exDays = (int)(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='scan_exclude_days'")['setting_value'] ?? 0);
            $exDate = $exDays > 0 ? date('Y-m-d H:i:s', strtotime("-{$exDays} days")) : null;
            foreach ($targetUsers as $tu) {
                $uid = (int)$tu["id"];
                // 排除 N 天内注册的用户
                if ($exDate && $at["scope"] !== "user") {
                    $cu = dbFetchOne("SELECT created_at FROM users WHERE id=?", [$uid]);
                    if ($cu && $cu["created_at"] >= $exDate) {
                        echo date("H:i:s") . " [AutoTask] Skip user #{$uid} (注册于{$exDays}天内)\n";
                        continue;
                    }
                }
                $company = dbFetchOne("SELECT id FROM company_info WHERE user_id=?", [$uid]);
                if (!$company) continue;
                echo date("H:i:s") . " [AutoTask] Scan user #{$uid}\n";
                if (!dbFetchOne("SELECT id FROM geo_scan_queue WHERE user_id=? AND status IN ('pending','processing')", [$uid])) {
                    dbExecute("INSERT INTO geo_scan_queue (user_id,status,created_at) VALUES (?,'pending',NOW())", [$uid]);
                }
            }
        } elseif ($at["type"] === "geo_detect") {
        } elseif ($at["type"] === "daily_keyword_snapshot") {
            $snapUsers = $at["scope"] === "user"
                ? [["id" => $at["target_user_id"]]]
                : dbFetchAll("SELECT DISTINCT user_id AS id FROM geo_brand_scan");
            foreach ($snapUsers as $su) {
                $suid = (int)$su["id"];
                $latest = dbFetchOne("SELECT doubao_count, deepseek_count FROM geo_brand_scan WHERE user_id=? ORDER BY id DESC LIMIT 1", [$suid]);
                if ($latest) {
                    $dbc = (int)($latest["doubao_count"] ?? 0);
                    $dsc = (int)($latest["deepseek_count"] ?? 0);
                    $total = $dbc + $dsc;
                    dbExecute("INSERT INTO geo_daily_keyword_stats (user_id, record_date, doubao_count, deepseek_count, total_keywords, created_at) VALUES (?, CURDATE(), ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE doubao_count=VALUES(doubao_count), deepseek_count=VALUES(deepseek_count), total_keywords=VALUES(total_keywords)", [$suid, $dbc, $dsc, $total]);
                    echo date("H:i:s") . " [Snapshot] User #{$suid}: doubao={$dbc} deepseek={$dsc} total={$total}\n";
                }
            }
        }
    }
}

echo date('H:i:s') . " [Done] Finished\n";

