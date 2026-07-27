<?php
/**
 * 后台品牌扫描工作进程（CLI 模式）
 * 限制 MAX_WORKERS 个同时运行，满额排队
 * 用法: php background_scan.php [user_id]
 */
if (php_sapi_name() !== "cli") { http_response_code(403); exit; }

$docRoot = __DIR__;
require_once $docRoot . '/config/database.php';
require_once $docRoot . '/includes/functions.php';
require_once $docRoot . '/includes/db.php';
require_once $docRoot . '/includes/geo.php';

date_default_timezone_set("Asia/Hong_Kong");

// 注册工作进程--已达上限则直接退出
if (!claimWorkerSlot()) {
    fwrite(STDOUT, "[" . date("H:i:s") . "] Worker pool full (max " . MAX_WORKERS . "), queue item will wait\n");
    exit;
}

$requestedUserId = isset($argv[1]) ? (int)$argv[1] : 0;

/**
 * 执行一次品牌扫描（队列任务）
 */
function processScanTask(int $userId, string $label): void {
    $task = dbFetchOne(
        "SELECT * FROM geo_scan_queue WHERE user_id=? AND status='pending' ORDER BY id ASC LIMIT 1",
        [$userId]
    );
    if (!$task) {
        fwrite(STDOUT, "[" . date("H:i:s") . "] No pending scan for user #{$userId} ({$label})\n");
        return;
    }

    dbExecute("UPDATE geo_scan_queue SET status='processing', started_at=NOW() WHERE id=?", [$task["id"]]);
    fwrite(STDOUT, "[" . date("H:i:s") . "] Starting scan for user #{$userId} ({$label})...\n");

    geoInitTables();
    $result = geoBrandScan($userId);

    if (isset($result["error"])) {
        fwrite(STDERR, "[" . date("H:i:s") . "] Failed: {$result["error"]}\n");
        dbExecute("UPDATE geo_scan_queue SET status='failed', error=?, completed_at=NOW() WHERE id=?", [$result["error"], $task["id"]]);
        return;
    }

    dbExecute("UPDATE geo_scan_queue SET status='completed', completed_at=NOW() WHERE id=?", [$task["id"]]);
    fwrite(STDOUT, "[" . date("H:i:s") . "] Done: {$result["scan_percent"]}% (" . $result["keyword_count"] . "/" . $result["total"] . " keywords)\n");
}

// 1. 处理当前用户的扫描
if ($requestedUserId > 0) {
    processScanTask($requestedUserId, "direct");
}

// 2. 链式处理：队列里排队的用户也顺手扫了
$chained = 0;
while ($chained < MAX_WORKERS) {
    $next = dbFetchOne("SELECT * FROM geo_scan_queue WHERE status='pending' ORDER BY id ASC LIMIT 1");
    if (!$next) break;
    if ((int)$next["user_id"] === $requestedUserId) { break; }
    processScanTask((int)$next["user_id"], "chained");
    $chained++;
}

fwrite(STDOUT, "[" . date("H:i:s") . "] Worker exits\n");
