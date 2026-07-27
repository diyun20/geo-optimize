<?php
/**
 * 后台队列工作者
 * 在终端中运行: php queue_worker.php
 * 仅处理蒸馏和文章队列，不自动扫描关键词和品牌
 */

$docRoot = __DIR__;
require_once $docRoot . '/config/database.php';
require_once $docRoot . '/includes/functions.php';
require_once $docRoot . '/includes/db.php';
require_once $docRoot . '/includes/geo.php';

echo "[Worker] 启动 GEO 队列工作者...\n";
echo "[Worker] 按 Ctrl+C 停止\n\n";

date_default_timezone_set('Asia/Hong_Kong');
geoInitQueueTable();
geoInitTables();

$checkInterval = 2; // 秒

while (true) {
    // 蒸馏队列
    $distillTask = dbFetchOne("SELECT * FROM geo_distill_queue WHERE status='pending' ORDER BY id ASC LIMIT 1");
    if ($distillTask) {
        echo date('H:i:s') . " [DistillQ] Spawning worker for user #{$distillTask['user_id']}...\n";
        runBackgroundProcess('background_distill.php', [(int)$distillTask['user_id']]);
        usleep(500000);
    }

    // 文章生成队列
    $articleTask = dbFetchOne("SELECT id FROM geo_articles WHERE status='pending' ORDER BY id ASC LIMIT 1");
    if ($articleTask) {
        echo date('H:i:s') . " [ArticleQ] Spawning worker for article #{$articleTask['id']}...\n";
        runBackgroundProcess('background_article.php', [(int)$articleTask['id']]);
        usleep(500000);
    }

    sleep($checkInterval);
}
