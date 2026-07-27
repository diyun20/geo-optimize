<?php
/**
 * 后台关键词蒸馏工作进程（CLI 模式）
 * 用法: php background_distill.php [user_id]
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

$requestedUserId = isset($argv[1]) ? (int)$argv[1] : 0;

function processDistillTask(int $userId) {
    $task = dbFetchOne(
        "SELECT * FROM geo_distill_queue WHERE user_id=? AND status='pending' ORDER BY id ASC LIMIT 1",
        [$userId]
    );
    if (!$task) {
        fwrite(STDOUT, "[" . date("H:i:s") . "] No pending distill for user #{$userId}\n");
        return;
    }

    dbExecute("UPDATE geo_distill_queue SET status='processing', started_at=NOW() WHERE id=?", [$task["id"]]);
    fwrite(STDOUT, "[" . date("H:i:s") . "] Starting keyword generation for user #{$userId}...\n");

    geoInitTables();
    $result = geoGenerateKeywordsAppend($userId);

    if (isset($result["error"])) {
        fwrite(STDERR, "[" . date("H:i:s") . "] Failed: {$result["error"]}\n");
        dbExecute("UPDATE geo_distill_queue SET status='failed', error=?, completed_at=NOW() WHERE id=?", [$result["error"], $task["id"]]);
        return;
    }

    dbExecute("UPDATE geo_distill_queue SET status='completed', completed_at=NOW() WHERE id=?", [$task["id"]]);
    fwrite(STDOUT, "[" . date("H:i:s") . "] Done: {$result["count"]} keywords generated\n");
}

if ($requestedUserId > 0) {
    processDistillTask($requestedUserId);
}

// Chain: pick up other pending distill tasks
$chained = 0;
while ($chained < MAX_WORKERS) {
    $next = dbFetchOne("SELECT * FROM geo_distill_queue WHERE status='pending' ORDER BY id ASC LIMIT 1");
    if (!$next) break;
    if ((int)$next["user_id"] === $requestedUserId) { break; }
    processDistillTask((int)$next["user_id"]);
    $chained++;
}

fwrite(STDOUT, "[" . date("H:i:s") . "] Distill worker exits\n");
