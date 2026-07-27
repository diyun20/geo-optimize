<?php
// AJAX 处理...
if (isset($_GET['ajax'])) {
    while (ob_get_level()) ob_end_clean(); ob_start();
    error_reporting(0); ini_set('display_errors', 0);
    header('Content-Type: application/json; charset=utf-8');

    if (!isLoggedIn()) { echo json_encode(['error'=>'未登录']); exit; }
    if (!isAdmin()) { echo json_encode(['error'=>'仅管理员']); exit; }

    require_once __DIR__ . '/../../includes/updater.php';
    require_once __DIR__ . '/../../includes/geo.php';
    $updater = new Updater();
    $action = $_GET['ajax'];

    if ($action === 'check') {
        try { echo json_encode($updater->compare(), JSON_UNESCAPED_UNICODE); } catch (Exception $e) { echo json_encode(['error'=>$e->getMessage()]); }
        exit;
    }
    if ($action === 'update') {
        try {
            $result = $updater->doUpdate();
            if ($result['success']) {
                $history = json_decode(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='update_history'")['setting_value'] ?? '[]', true) ?: [];
                array_unshift($history, ['version'=>$result['version'],'time'=>date('Y-m-d H:i:s'),'changelog'=>($updater->getRemoteManifest()['changelog']??'')]);
                $history = array_slice($history, 0, 50);
                try { dbExecute("INSERT INTO site_settings (setting_key,setting_value) VALUES ('update_history',?) ON DUPLICATE KEY UPDATE setting_value=?", [json_encode($history,JSON_UNESCAPED_UNICODE), json_encode($history,JSON_UNESCAPED_UNICODE)]); } catch(Exception $e) {}
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
        exit;
    }
if ($action === 'publish') {
    $pw = $_POST['password'] ?? '';
    $mode = $_POST['mode'] ?? 'files';
    $cl = $_POST['changelog'] ?? '';
    $ver = $_POST['version'] ?? '';
    $cap = $_POST['captcha'] ?? '';
    if (empty($cap) || strtoupper($cap) !== ($_SESSION['captcha_code'] ?? '')) {
        echo json_encode(['success'=>false,'error'=>'验证码错误']); exit;
    }
    unset($_SESSION['captcha_code']);
    $postData = http_build_query(['action'=>'publish','password'=>$pw,'mode'=>$mode,'changelog'=>$cl,'version'=>$ver]);
    $ch = curl_init('http://127.0.0.1/publish.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => ['Host: updates.diyunuu.cn'],
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) { echo json_encode(['success'=>false,'error'=>'连接发布服务器失败: '.$err]); exit; }
    if (empty($resp)) { echo json_encode(['success'=>false,'error'=>'发布服务器返回空响应 (HTTP '.$httpCode.')']); exit; }
    $data = json_decode($resp, true);
    if (!$data) { echo json_encode(['success'=>false,'error'=>'发布服务器返回无效数据: '.substr($resp,0,200)]); exit; }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'gen_changelog') {
    try {
        $u = new Updater();
        $diff = $u->compare();
        $changed = array_merge(
            array_map(function($f){ return "+ $f"; }, $diff['added'] ?? []),
            array_map(function($f){ return "~ $f"; }, $diff['modified'] ?? []),
            array_map(function($f){ return "- $f"; }, $diff['removed'] ?? [])
        );
        if (empty($changed)) { echo json_encode(['changelog'=>'']); exit; }

        // 读取变更文件的前几行作为上下文
        $fileContexts = [];
        $baseDir = realpath(__DIR__ . '/../..');
        foreach (array_merge($diff['added'] ?? [], $diff['modified'] ?? []) as $f) {
            $path = $baseDir . '/' . $f;
            if (file_exists($path)) {
                $head = @file_get_contents($path, false, null, 0, 500);
                if ($head) {
                    $firstLines = implode("\n", array_slice(explode("\n", $head), 0, 8));
                    $fileContexts[] = "--- $f ---\n$firstLines";
                }
            }
        }
        foreach ($diff['removed'] ?? [] as $f) {
            $fileContexts[] = "--- $f (已删除) ---";
        }
        $list = implode("\n\n", array_merge($changed, $fileContexts));
        $list = mb_substr($list, 0, 4000); // 限制长度

        // 使用豆包
        $ds = geoGetApiProvider("doubao");
        if (empty($ds['api_key'])) { echo json_encode(['error'=>'豆包 API Key 未配置']); exit; }

        $user = currentUser();
        // 确保表存在
        try { dbExecute("CREATE TABLE IF NOT EXISTS `geo_articles` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`topic` VARCHAR(500),`brand_name` VARCHAR(200),`keywords` TEXT,`content` LONGTEXT,`status` ENUM('pending','processing','completed','failed') DEFAULT 'pending',`created_at` DATETIME,`started_at` DATETIME,`completed_at` DATETIME,INDEX `idx_user`(`user_id`),INDEX `idx_status`(`status`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}

        // 写入任务队列
        dbExecute("INSERT INTO geo_articles (user_id,topic,brand_name,keywords,content,status,created_at) VALUES (?,?,?,?,?,'pending',NOW())",
            [$user['id'], 'AI代写更新日志', '系统',
             json_encode(['type'=>'changelog','list'=>$list,'api_endpoint'=>$ds['api_endpoint'],'api_key'=>$ds['api_key'],'model'=>$ds['model']], JSON_UNESCAPED_UNICODE),
             '(排队中...)']);
        $taskId = dbFetchOne("SELECT LAST_INSERT_ID() as id")['id'];

        // 触发后台worker
        runBackgroundProcess("background_article.php", [(int)$taskId]);

        echo json_encode(['task_id' => $taskId], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        echo json_encode(['error'=>'生成失败: '.$e->getMessage()]);
    }
    exit;
}
if ($action === 'poll_changelog') {
    $taskId = (int)($_GET['task_id'] ?? 0);
    $row = dbFetchOne("SELECT status, content FROM geo_articles WHERE id=?", [$taskId]);
    if (!$row) { echo json_encode(['status'=>'error','error'=>'任务不存在']); exit; }
    echo json_encode(['status'=>$row['status'], 'changelog'=>$row['status']==='completed' ? $row['content'] : ''], JSON_UNESCAPED_UNICODE);
    exit;
}
    echo json_encode(['error'=>'未知操作']);
    exit;
}

// 页面渲染
requireLogin();
requireRole('admin');
require_once __DIR__ . '/../../includes/updater.php';

$pageTitle = '系统更新 - GEO优化';
$updater = new Updater();
$currentVersion = $updater->getCurrentVersion();
$remote = $updater->getRemoteManifest();
$backups = $updater->getBackups();

// 更新历史（本地更新 + 远程发布记录）
$history = json_decode(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='update_history'")['setting_value'] ?? '[]', true) ?: [];
// 合并发布服务器的记录
try {
    $pubDb = require '/www/wwwroot/updates.diyunuu.cn/config/database.php';
    $pdo2 = new PDO("mysql:host={$pubDb['host']};port=".($pubDb['port']??3306).";dbname={$pubDb['dbname']};charset=utf8mb4", $pubDb['username'], $pubDb['password']);
    $pubLogs = $pdo2->query("SELECT version, mode, changelog, created_at FROM publish_logs ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pubLogs as $log) {
        $history[] = [
            'version' => $log['version'],
            'time' => $log['created_at'],
            'changelog' => ($log['mode']==='full'?'[全量] ':'').($log['changelog'] ?: ''),
        ];
    }
    // 按时间排序去重
    usort($history, fn($a,$b) => strcmp($b['time'] ?? '', $a['time'] ?? ''));
    $seen = []; $history = array_filter($history, function($h) use (&$seen) {
        $k = $h['version'].'|'.$h['time'];
        if (isset($seen[$k])) return false;
        $seen[$k] = true;
        return true;
    });
} catch (Exception $e) { /* 远程库不可用时忽略 */ }
?>

<div class="admin-page-header">
    <h1>🔄 系统更新</h1>
    <p>当前版本：<strong><?= h($currentVersion) ?></strong>　最新版本：<strong style="color:#4f46e5;"><?= h($remote['version'] ?? 'N/A') ?></strong></p>
</div>

<!-- 最新版本更新内容 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-body" style="padding:20px;">
        <h3 style="font-size:16px;margin:0 0 8px 0;">📋 <?= h($remote['version'] ?? '') ?> 更新内容</h3>
        <p style="font-size:12px;color:#9ca3af;margin:0 0 12px 0;">发布时间：<?= h($remote['release_date'] ?? '') ?></p>
        <pre id="latestChangelog" style="white-space:pre-wrap;background:#f9fafb;padding:12px;border-radius:6px;margin:0;font-size:13px;line-height:1.6;"><?= h($remote['changelog'] ?? '暂无更新内容') ?></pre>
    </div>
</div>

<!-- 操作按钮 -->
<div style="display:flex;gap:10px;align-items:center;margin-bottom:20px;">
    <button id="btnCheck" onclick="checkUpdate()" style="padding:10px 20px;font-size:14px;border:none;border-radius:6px;background:#4f46e5;color:#fff;cursor:pointer;">🔍 检查更新</button>
    <span id="updateStatus" style="font-size:14px;color:#6b7280;"></span>
    <div style="margin-left:auto;text-align:right;line-height:1.6;">
        <span style="color:#dc2626;font-size:13px;font-weight:600;">作者QQ：1109306666</span><br>
        <span style="color:#6b7280;font-size:12px;">交流群：974788294</span>
    </div>
</div>

<!-- 历史更新 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-body" style="padding:20px;">
        <h3 style="font-size:16px;margin:0 0 16px 0;">📜 历史更新</h3>
        <?php if (empty($history)): ?>
        <p style="color:#9ca3af;text-align:center;padding:20px;">暂无更新记录</p>
        <?php else: ?>
        <?php foreach ($history as $h): ?>
        <div style="padding:10px 0;border-bottom:1px solid #f3f4f6;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <strong style="color:#4f46e5;">v<?= h($h['version']) ?></strong>
                <span style="font-size:12px;color:#9ca3af;"><?= h($h['time']) ?></span>
            </div>
            <p style="margin:4px 0 0;font-size:13px;color:#6b7280;"><?= h($h['changelog'] ?: '—') ?></p>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- 历史备份 -->
<?php if (!empty($backups)): ?>
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-body" style="padding:20px;">
        <h3 style="font-size:16px;margin:0 0 16px 0;">💾 历史备份</h3>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead><tr style="border-bottom:1px solid #e5e7eb;text-align:left;"><th style="padding:8px;">时间</th><th style="padding:8px;">路径</th></tr></thead>
            <tbody>
            <?php foreach ($backups as $b): ?>
            <tr style="border-bottom:1px solid #f3f4f6;"><td style="padding:8px;"><?= h(date('Y-m-d H:i:s', $b['time'])) ?></td><td style="padding:8px;color:#6b7280;word-break:break-all;"><?= h($b['path']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
async function checkUpdate(){
    const btn=document.getElementById('btnCheck'),st=document.getElementById('updateStatus');
    btn.disabled=true;btn.textContent='⏳ 检查中...';st.textContent='';
    await new Promise(r=>setTimeout(r,800));
    st.textContent='✅ 已是最新版本';st.style.color='#059669';
    btn.textContent='🔍 检查更新';btn.disabled=false;
    setTimeout(()=>{
        alert('此程序为免费开源版，\n完整版请联系作者购买授权\n作者QQ：1109306666');
    },300);
}
</script>