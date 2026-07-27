<?php
requireLogin();
requireFeatureOrVip('keywords-distill');
require_once __DIR__ . "/../includes/geo.php";
$user = currentUser();
$pageTitle = "关键词蒸馏 - GEO优化";

try { dbExecute("CREATE TABLE IF NOT EXISTS `geo_keywords_manual` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`keyword` VARCHAR(200) NOT NULL,`category` VARCHAR(100) DEFAULT '',`created_at` DATETIME NOT NULL,INDEX `idx_user` (`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}
try { dbExecute("CREATE TABLE IF NOT EXISTS `geo_keywords_distill` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`keyword` VARCHAR(200) NOT NULL,`category` VARCHAR(100) DEFAULT '',`generated_at` DATETIME NOT NULL,INDEX `idx_user` (`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add_single") {
    $keyword = trim($_POST["keyword"] ?? "");
    if ($keyword) {
        dbExecute("INSERT INTO geo_keywords_manual (user_id, keyword, category, created_at) VALUES (?,?,?,NOW())",
            [$user["id"], $keyword, ""]);
        setFlash("success", "关键词【{$keyword}】已添加");
    }
    redirect("index.php?route=keywords-distill");
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add_batch") {
    $lines = explode("\n", trim($_POST["keywords"] ?? ""));
    $count = 0;
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        dbExecute("INSERT INTO geo_keywords_manual (user_id, keyword, category, created_at) VALUES (?,?,?,NOW())",
            [$user["id"], $line, ""]);
        $count++;
    }
    setFlash("success", "成功添加 {$count} 个关键词");
    redirect("index.php?route=keywords-distill");
}

if (isset($_GET["action"]) && $_GET["action"] === "clear") {
    dbExecute("DELETE FROM geo_keywords_distill WHERE user_id=?", [$user["id"]]);
    setFlash("success", "词库已清空");
    redirect("index.php?route=keywords-distill");
}
if (isset($_GET["action"]) && $_GET["action"] === "clear_manual") {
    dbExecute("DELETE FROM geo_keywords_manual WHERE user_id=?", [$user["id"]]);
    setFlash("success", "自定义关键词库已清空");
    redirect("index.php?route=keywords-distill");
}
if (isset($_GET["action"]) && $_GET["action"] === "delete_manual" && isset($_GET["id"])) {
    $kid = (int)$_GET["id"];
    dbExecute("DELETE FROM geo_keywords_manual WHERE id=? AND user_id=?", [$kid, $user["id"]]);
    redirect("index.php?route=keywords-distill");
}
if (isset($_GET["action"]) && $_GET["action"] === "dedup") {
    $rows = dbFetchAll("SELECT keyword, MIN(id) as min_id FROM geo_keywords_distill WHERE user_id=? GROUP BY keyword HAVING COUNT(*) > 1", [$user["id"]]);
    $cnt = 0;
    foreach ($rows as $r) {
        dbExecute("DELETE FROM geo_keywords_distill WHERE user_id=? AND keyword=? AND id != ?", [$user["id"], $r["keyword"], $r["min_id"]]);
        $cnt++;
    }
    $total = dbFetchOne("SELECT COUNT(*) as c FROM geo_keywords_distill WHERE user_id=?", [$user["id"]])["c"];
    setFlash("success", "去重完成，删除 " . $cnt . " 个重复词，当前共 " . $total . " 个词");
    redirect("index.php?route=keywords-distill");
}
if (isset($_GET["action"]) && $_GET["action"] === "append") {
    // 试用限额检查
    if (!isAdminOrAgent() && !isVip()) {
        $cfg = membershipGetConfig();
        $used = (int)(dbFetchOne("SELECT COUNT(*) as c FROM geo_keywords_distill WHERE user_id=?", [$user['id']])['c'] ?? 0);
        $limit = $cfg['trial_distill_limit'];
        if ($limit > 0 && $used >= $limit) {
            setFlash('error', "试用账户蒸馏词已达上限 {$limit} 个，请升级VIP");
            redirect("index.php?route=keywords-distill");
        }
    }
    geoInitTables();
    dbExecute("INSERT INTO geo_distill_queue (user_id,status,created_at) VALUES (?,'pending',NOW())", [$user["id"]]);
    if (getWorkerCount() < MAX_WORKERS) {
        runBackgroundProcess("background_distill.php", [$user["id"]]);
    }
    setFlash("success", "关键词生成已加入队列，后台处理中...");
    redirect("index.php?route=keywords-distill");
}

$mk = dbFetchAll("SELECT * FROM geo_keywords_manual WHERE user_id=? ORDER BY created_at DESC", [$user["id"]]);
$kws = dbFetchAll("SELECT * FROM geo_keywords_distill WHERE user_id=? ORDER BY category, keyword ", [$user["id"]]);
$sc = ["疑问词","产品词","场景词","地域词","需求词","对比词","竞品词","行业热词","组合词","通用词"];
$cats = [];
foreach ($sc as $s) { $cats[$s] = []; }
foreach ($kws as $kw) {
    $cat = $kw["category"] ?: "通用词";
    if (isset($cats[$cat])) $cats[$cat][] = $kw["keyword"];
}
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
    <div class="dashboard-card">
        <h3 style="margin:0 0 12px;font-size:15px;">自定义关键词</h3>
        <form method="post" action="index.php?route=keywords-distill" style="display:flex;gap:8px;flex-wrap:wrap;">
            <input type="hidden" name="action" value="add_single">
            <input type="text" name="keyword" required placeholder="关键词" style="flex:1;min-width:120px;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
            <button type="submit" class="btn btn-primary" style="padding:8px 16px;">添加</button>
        </form>
    </div>
    <div class="dashboard-card">
        <h3 style="margin:0 0 12px;font-size:15px;">批量添加关键词</h3>
        <form method="post" action="index.php?route=keywords-distill">
            <input type="hidden" name="action" value="add_batch">
            <textarea name="keywords" rows="3" placeholder="每行一个关键词" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;resize:vertical;"></textarea>
            <button type="submit" class="btn btn-primary" style="margin-top:8px;padding:8px 16px;">批量添加</button>
        </form>
    </div>
</div>

<?php if ($mk): ?>
<div class="dashboard-card" style="margin-bottom:16px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 style="margin:0;font-size:15px;color:#374151;">自定义关键词 <span style="font-weight:400;color:#9ca3af;font-size:12px;">(<?= count($mk) ?> 个)</span></h3>
        <a href="index.php?route=keywords-distill&action=clear_manual" class="btn" style="padding:4px 12px;font-size:12px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:6px;text-decoration:none;cursor:pointer;" onclick="return confirm('确定清空所有自定义关键词？')">清空全部</a>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach ($mk as $kw): ?>
        <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 8px 4px 12px;background:#eef2ff;border-radius:14px;font-size:13px;color:#4f46e5;border:1px solid #c7d2fe;">
            <?= h($kw["keyword"]) ?>
            <a href="index.php?route=keywords-distill&action=delete_manual&id=<?= $kw["id"] ?>" style="display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:50%;background:#c7d2fe;color:#4f46e5;font-size:12px;line-height:1;text-decoration:none;cursor:pointer;" onclick="return confirm('删除关键词【<?= h($kw["keyword"]) ?>】？')">×</a>
        </span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div style="display:flex;gap:8px;flex-wrap:wrap;">
<a href="index.php?route=keywords-distill&action=append" class="btn" style="padding:8px 16px;font-size:13px;background:#eef2ff;color:#4f46e5;border:1px solid #c7d2fe;border-radius:6px;text-decoration:none;cursor:pointer;" onclick="this.textContent='⏳ 生成中...';this.style.pointerEvents='none';">➕ 添加100个（蒸馏）</a>
<a href="index.php?route=keywords-distill&action=dedup" class="btn" style="padding:8px 16px;font-size:13px;background:#f0fdf4;color:#059669;border:1px solid #bbf7d0;border-radius:6px;text-decoration:none;cursor:pointer;">🔀 去掉重复</a>
<a href="index.php?route=keywords-distill&action=clear" class="btn" style="padding:8px 16px;font-size:13px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:6px;text-decoration:none;cursor:pointer;" onclick="return confirm('确定清空所有蒸馏关键词？')">🗑️ 清空词库</a>
</div>

<?php if ($error): ?>
<div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<?php foreach ($cats as $cat => $kws): ?>
<div class="dashboard-card" style="margin-bottom:16px;">
<h3 style="margin:0 0 12px;font-size:14px;color:#4f46e5;">🏷️ <?= h($cat) ?> <span style="font-weight:400;color:#9ca3af;font-size:12px;">(<?= count($kws) ?> 个)</span></h3>
    <div style="display:flex;flex-wrap:wrap;gap:6px;">
        <?php foreach ($kws as $kw): ?>
        <span style="display:inline-block;padding:4px 12px;background:#f3f4f6;border-radius:14px;font-size:13px;color:#374151;border:1px solid #e5e7eb;"><?= h($kw) ?></span>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
