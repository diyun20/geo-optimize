<?php
requireLogin();
requireRole("admin");
require_once __DIR__ . "/../../includes/geo.php";
geoInitTables();
try { dbExecute("ALTER TABLE geo_articles ADD COLUMN `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'completed' AFTER `keywords`"); } catch (Exception $e) {}
try { dbExecute("ALTER TABLE geo_articles ADD COLUMN `started_at` DATETIME DEFAULT NULL AFTER `status`, ADD COLUMN `completed_at` DATETIME DEFAULT NULL AFTER `started_at`"); } catch (Exception $e) {}
try { dbExecute("ALTER TABLE geo_detect_results ADD COLUMN `started_at` DATETIME DEFAULT NULL AFTER `status`"); } catch (Exception $e) {}
$pageTitle = "任务队列 - GEO优化";

// 筛选参数
$statusFilter = trim($_GET['status'] ?? '');
$startDate    = trim($_GET['start_date'] ?? '');
$endDate      = trim($_GET['end_date'] ?? '');

$statusMap = ['processing'=>'进行中','pending'=>'排队中','completed'=>'已完成','failed'=>'失败'];
$reverseMap = ['进行中'=>'processing','排队中'=>'pending','已完成'=>'completed','失败'=>'failed'];
$dbStatus = $reverseMap[$statusFilter] ?? $statusFilter; // 兼容中文

// 构建参数化查询条件
$params = [];
$where  = '';
if ($dbStatus && isset($statusMap[$dbStatus])) {
    $where .= " AND q.status=?";
    $params[] = $dbStatus;
}
if ($startDate) {
    $where .= " AND q.created_at >= ?";
    $params[] = $startDate . ' 00:00:00';
}
if ($endDate) {
    $where .= " AND q.created_at <= ?";
    $params[] = $endDate . ' 23:59:59';
}

$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$queryLimit = 200; // 每个表查询上限，确保分页有足够数据
$order = " ORDER BY COALESCE(q.started_at,q.created_at) DESC LIMIT {$queryLimit}";

$scanRows = dbFetchAll("SELECT q.*,u.username,'品牌扫描' AS task_type FROM geo_scan_queue q LEFT JOIN users u ON q.user_id=u.id WHERE 1=1{$where}{$order}", $params);
$kwRows   = dbFetchAll("SELECT q.*,k.keyword,u.username,'关键词检测' AS task_type FROM geo_queue q LEFT JOIN geo_keywords k ON q.keyword_id=k.id LEFT JOIN users u ON q.user_id=u.id WHERE 1=1{$where}{$order}", $params);
$distRows = dbFetchAll("SELECT q.*,u.username,'关键词蒸馏' AS task_type FROM geo_distill_queue q LEFT JOIN users u ON q.user_id=u.id WHERE 1=1{$where}{$order}", $params);
$articleRows = dbFetchAll("SELECT q.id,q.user_id,q.status,q.topic AS keyword,q.created_at,q.started_at,q.completed_at,u.username,'文章生成' AS task_type FROM geo_articles q LEFT JOIN users u ON q.user_id=u.id WHERE 1=1{$where}{$order}", $params);
$detectRows = dbFetchAll("SELECT q.id,q.user_id,q.status,q.error,q.created_at,q.started_at,q.completed_at,q.question AS keyword,q.platform,q.brand,u.username,'GEO检测' AS task_type FROM geo_detect_results q LEFT JOIN users u ON q.user_id=u.id WHERE 1=1{$where}{$order}", $params);

$allRows = array_merge($scanRows, $kwRows, $distRows, $articleRows, $detectRows);
usort($allRows, function($a, $b) {
    $order = ['processing' => 0, 'pending' => 1];
    $pa = $order[$a['status']] ?? 2;
    $pb = $order[$b['status']] ?? 2;
    if ($pa !== $pb) return $pa <=> $pb;
    $ta = $a['started_at'] ?? $a['created_at'] ?? '';
    $tb = $b['started_at'] ?? $b['created_at'] ?? '';
    return strcmp($tb, $ta);
});
$totalRows = count($allRows);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$allRows = array_slice($allRows, ($page - 1) * $perPage, $perPage);

// 统计（无筛选时快速统计，有筛选时用筛选结果）
$cntActive = getWorkerCount();
$cntPending = count(dbFetchAll("SELECT id FROM geo_scan_queue WHERE status='pending'"))
            + count(dbFetchAll("SELECT id FROM geo_queue WHERE status='pending'"))
            + count(dbFetchAll("SELECT id FROM geo_distill_queue WHERE status='pending'"))
            + count(dbFetchAll("SELECT id FROM geo_articles WHERE status='pending'"))
            + count(dbFetchAll("SELECT id FROM geo_detect_results WHERE status='pending'"));

function statusBadge($s) {
    $map = ['进行中'=>['#4f46e5','#eef2ff'],'排队中'=>['#c2410c','#fff7ed'],'已完成'=>['#059669','#f0fdf4'],'失败'=>['#dc2626','#fef2f2']];
    $c = $map[$s] ?? ['#6b7280','#f3f4f6'];
    return '<span style="display:inline-block;padding:2px 10px;border-radius:9999px;font-size:12px;font-weight:600;background:'.$c[1].';color:'.$c[0].';">'.h($s).'</span>';
}

$tabs = [
    ''         => '全部',
    '进行中'   => '进行中',
    '排队中'   => '排队中',
    '已完成'   => '已完成',
    '失败'     => '失败',
];
// 取消任务
$cancelId = isset($_GET['cancel_id']) ? (int)$_GET['cancel_id'] : 0;
$cancelType = $_GET['cancel_type'] ?? '';
if ($cancelId > 0) {
$tables = ['scan'=>'geo_scan_queue','kw'=>'geo_queue','dist'=>'geo_distill_queue','article'=>'geo_articles'];
$tables['detect'] = 'geo_detect_results';
    if (isset($tables[$cancelType])) {
        dbExecute("UPDATE {$tables[$cancelType]} SET status='failed', error='用户已取消', completed_at=NOW() WHERE id=? AND status IN ('pending','processing')", [$cancelId]);
        setFlash('info', '任务已取消');
    }
    redirect('index.php?route=admin/queue');
}

// 清理失败任务
if (isset($_GET['action']) && $_GET['action'] === 'clean_failed') {
$total = 0;
    foreach (['geo_scan_queue', 'geo_queue', 'geo_distill_queue', 'geo_articles', 'geo_detect_results'] as $table) {
        $total += dbExecute("DELETE FROM {$table} WHERE status='failed'");
    }
    setFlash('info', "已清理 {$total} 条失败任务");
    redirect('index.php?route=admin/queue');
}
?>

<div class="admin-page-header">
    <h1>任务队列</h1>
    <p>当前运行: <?= $cntActive ?> / <?= MAX_WORKERS ?>&emsp;|&emsp;排队: <?= $cntPending ?></p>
</div>

<!-- 筛选栏 -->
<form method="get" action="index.php" style="background:#fff;border-radius:8px;padding:16px 20px;margin-bottom:16px;display:flex;flex-wrap:wrap;align-items:center;gap:10px;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
    <input type="hidden" name="route" value="admin/queue">
    <div style="display:flex;gap:4px;flex-wrap:wrap;">
        <?php foreach ($tabs as $k => $v):
            $active = ($k === $statusFilter);
            $bg = $active ? '#4f46e5' : '#f3f4f6';
            $fg = $active ? '#fff' : '#374151';
        ?>
            <a href="index.php?route=admin/queue<?= $k ? '&status='.urlencode($k) : '' ?>"
               style="display:inline-block;padding:6px 14px;border-radius:6px;font-size:13px;font-weight:600;background:<?= $bg ?>;color:<?= $fg ?>;text-decoration:none;transition:all 0.15s;"><?= h($v) ?></a>
        <?php endforeach; ?>
    </div>
    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
        <span style="font-size:13px;color:#6b7280;">从</span>
        <input type="date" name="start_date" value="<?= h($startDate) ?>" style="padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
        <span style="font-size:13px;color:#6b7280;">到</span>
        <input type="date" name="end_date" value="<?= h($endDate) ?>" style="padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
        <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">查询</button>
        <?php if ($statusFilter || $startDate || $endDate): ?>
            <a href="index.php?route=admin/queue" style="display:inline-block;padding:5px 12px;border-radius:6px;font-size:13px;background:#f3f4f6;color:#6b7280;text-decoration:none;border:1px solid #d1d5db;">重置</a>
        <?php endif; ?>
    </div>
</form>

<!-- 统计信息 -->
<div style="display:flex;gap:12px;margin-bottom:12px;flex-wrap:wrap;font-size:13px;color:#6b7280;">
    <span>共<strong><?= $totalRows ?></strong> 条，第 <strong><?= $page ?>/<?= $totalPages ?></strong> 页</span>
    <?php if ($statusFilter): ?><span>| 筛选 <strong><?= h($statusFilter) ?></strong></span><?php endif; ?>
    <?php if ($startDate): ?><span>| 从<strong><?= h($startDate) ?></strong></span><?php endif; ?>
    <?php if ($endDate): ?><span>| 到<strong><?= h($endDate) ?></strong></span><?php endif; ?>
    <?php if ($statusFilter === '失败'): ?>
        <a href="index.php?route=admin/queue&action=clean_failed" class="admin-btn admin-btn-sm" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;" onclick="return confirm('确认清理所有失败任务？')">一键删除</a>
    <?php endif; ?>
</div>

<!-- 结果 -->
<div class="admin-card">
    <div class="admin-card-body" style="padding:0;">
    <div class="admin-table-wrap">
    <table class="admin-table">
        <thead><tr><th>类型</th><th>用户</th><th>状态</th><th>开始时间</th><th>完成时间</th><th>备注</th><th style="text-align:center;">操作</th></tr></thead>
        <tbody>
        <?php if (empty($allRows)): ?>
            <tr><td colspan="7" style="text-align:center;padding:40px 0;color:#9ca3af;">暂无匹配记录</td></tr>
        <?php else: foreach ($allRows as $r):
            $status = $statusMap[$r['status']] ?? $r['status'];
            $startTime = $r['started_at'] ?? $r['created_at'] ?? '-';
            $endTime = $r['completed_at'] ?? '-';
            $note = $r['keyword'] ?? ($r['error'] ? mb_substr($r['error'], 0, 60) : '');
            // GEO检测额外显示平台+品牌
            if (($r['task_type'] ?? '') === 'GEO检测') {
                $extras = [];
                if (!empty($r['platform'])) $extras[] = '🔍' . h($r['platform']);
                if (!empty($r['brand'])) $extras[] = '🏷' . h($r['brand']);
                $note = implode(' ', $extras) . ($note ? ' — ' . h($note) : '');
            }
        ?>
            <tr>
                <td><?= h($r['task_type'] ?? '-') ?></td>
                <td><?= h($r['username'] ?: '-') ?></td>
                <td><?= statusBadge($status) ?></td>
                <td style="color:#9ca3af;font-size:13px;"><?= h($startTime) ?></td>
                <td style="color:#9ca3af;font-size:13px;"><?= h($endTime) ?></td>
                <td style="color:#9ca3af;font-size:12px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($note) ?></td>
                <td style="text-align:center;white-space:nowrap;"><?php if (in_array($r['status'], ['pending','processing'])): ?><a href="index.php?route=admin/queue&cancel_id=<?= $r['id'] ?>&cancel_type=<?= $r['task_type'] === '品牌扫描' ? 'scan' : ($r['task_type'] === '关键词检测' ? 'kw' : ($r['task_type'] === '关键词蒸馏' ? 'dist' : ($r['task_type'] === '文章生成' ? 'article' : 'detect'))) ?>" class="admin-btn admin-btn-sm" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;" onclick="return confirm('确认取消该任务？')">取消</a><?php else: ?><span style="color:#d1d5db;">-</span><?php endif; ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:16px;font-size:13px;">
    <?php if ($page > 1): ?>
        <a href="index.php?route=admin/queue<?= $statusFilter?'&status='.urlencode($statusFilter):'' ?><?= $startDate?'&start_date='.urlencode($startDate):'' ?><?= $endDate?'&end_date='.urlencode($endDate):'' ?>&page=1" style="padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;text-decoration:none;color:#374151;">首页</a>
        <a href="index.php?route=admin/queue<?= $statusFilter?'&status='.urlencode($statusFilter):'' ?><?= $startDate?'&start_date='.urlencode($startDate):'' ?><?= $endDate?'&end_date='.urlencode($endDate):'' ?>&page=<?= $page-1 ?>" style="padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;text-decoration:none;color:#374151;">上一页</a>
    <?php endif; ?>
    <?php
    $startP = max(1, $page - 2);
    $endP = min($totalPages, $page + 2);
    for ($p = $startP; $p <= $endP; $p++):
        $active = $p === $page;
    ?>
        <a href="index.php?route=admin/queue<?= $statusFilter?'&status='.urlencode($statusFilter):'' ?><?= $startDate?'&start_date='.urlencode($startDate):'' ?><?= $endDate?'&end_date='.urlencode($endDate):'' ?>&page=<?= $p ?>" style="padding:6px 12px;border-radius:6px;text-decoration:none;<?= $active ? 'background:#4f46e5;color:#fff;' : 'border:1px solid #d1d5db;color:#374151;' ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
        <a href="index.php?route=admin/queue<?= $statusFilter?'&status='.urlencode($statusFilter):'' ?><?= $startDate?'&start_date='.urlencode($startDate):'' ?><?= $endDate?'&end_date='.urlencode($endDate):'' ?>&page=<?= $page+1 ?>" style="padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;text-decoration:none;color:#374151;">下一页</a>
        <a href="index.php?route=admin/queue<?= $statusFilter?'&status='.urlencode($statusFilter):'' ?><?= $startDate?'&start_date='.urlencode($startDate):'' ?><?= $endDate?'&end_date='.urlencode($endDate):'' ?>&page=<?= $totalPages ?>" style="padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;text-decoration:none;color:#374151;">末页</a>
    <?php endif; ?>
</div>
<?php endif; ?>
