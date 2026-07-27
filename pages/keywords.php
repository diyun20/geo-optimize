<?php
requireLogin();
requireFeatureOrVip('keywords');
require_once __DIR__ . "/../includes/geo.php";
$user = currentUser();
$pageTitle = "GEO检测";

try { dbExecute("ALTER TABLE users ADD COLUMN `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `role`"); } catch (Exception $e) {}
try { dbExecute("CREATE TABLE IF NOT EXISTS `transactions` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`type` ENUM('recharge','consume','refund') NOT NULL,`amount` DECIMAL(10,2) NOT NULL,`description` VARCHAR(255) DEFAULT NULL,`created_at` DATETIME NOT NULL,INDEX `idx_user` (`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (Exception $e) {}
try { dbExecute("ALTER TABLE geo_detect_results ADD COLUMN `started_at` DATETIME DEFAULT NULL AFTER `status`"); } catch (Exception $e) {}
try { dbExecute("CREATE TABLE IF NOT EXISTS `geo_detect_results` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`question` TEXT NOT NULL,`brand` VARCHAR(200) NOT NULL,`result_text` TEXT DEFAULT NULL,`brand_mentioned` TINYINT(1) NOT NULL DEFAULT 0,`brand_position` INT DEFAULT NULL,`platform` VARCHAR(50) NOT NULL DEFAULT '',`status` VARCHAR(20) NOT NULL DEFAULT 'pending',`error` TEXT DEFAULT NULL,`created_at` DATETIME NOT NULL,`completed_at` DATETIME DEFAULT NULL,INDEX `idx_user` (`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "detect") {
    // 防重复提交
    $submitKey = 'detect_' . md5(json_encode($_POST));
    if (isset($_SESSION[$submitKey]) && $_SESSION[$submitKey] > time() - 3) {
        redirect("index.php?route=keywords");
    }
    $_SESSION[$submitKey] = time();

    $question = trim($_POST["question"] ?? "");
    $brand = trim($_POST["brand"] ?? "");
    $platforms = $_POST["platforms"] ?? [];
    if ($question && $brand && $platforms) {
        $allowed = ["豆包"];
        $allowed = ["豆包", "DeepSeek"];
        $settings = geoGetAdminSettings();
        $cost = (float)($settings["cost_per_detect"] ?? 0);
        $platformCount = count(array_intersect($platforms, $allowed));
        $totalCost = $cost;
        if ($cost > 0) {
            $bal = dbFetchOne("SELECT balance FROM users WHERE id=?", [$user["id"]])["balance"] ?? 0;
            if ($bal < $totalCost) {
                setFlash("error", "余额不足，本次检测需 ¥" . number_format($totalCost, 2) . "，当前余额 ¥" . number_format($bal, 2));
                redirect("index.php?route=keywords");
                exit;
            }
            dbExecute("UPDATE users SET balance = balance - ? WHERE id=?", [$totalCost, $user["id"]]);
            dbExecute("INSERT INTO transactions (user_id,type,amount,description,created_at) VALUES (?,'consume',?,?,?)", [$user["id"], $totalCost, "GEO检测：" . $brand, date("Y-m-d H:i:s")]);
        }
        foreach ($platforms as $platform) {
            $platform = trim($platform);
            if (!in_array($platform, $allowed, true)) continue;
            $id = dbInsert("INSERT INTO geo_detect_results (user_id, question, brand, platform, status, created_at) VALUES (?,?,?,?,'pending',NOW())", [$user["id"], $question, $brand, $platform]);
            runBackgroundProcess("background_detect.php", [$id]);
        }
        setFlash("info", "检测已加入队列 (" . count($platforms) . "个平台)，后台处理中...");
    } else {
        setFlash("error", "请填写问题内容、品牌名称，并至少选择一个 AI 平台");
    }
    redirect("index.php?route=keywords");
}

if (isset($_GET["action"]) && $_GET["action"] === "delete_detect" && isset($_GET["id"])) {
    $did = (int)$_GET["id"];
    dbExecute("DELETE FROM geo_detect_results WHERE id=? AND user_id=?", [$did, $user["id"]]);
    redirect("index.php?route=keywords");
}
if (isset($_GET["action"]) && $_GET["action"] === "update_detect" && isset($_GET["question"]) && isset($_GET["brand"])) {
    $uq = $_GET["question"];
    $ub = $_GET["brand"];
    $platformRows = dbFetchAll("SELECT DISTINCT platform FROM geo_detect_results WHERE user_id=? AND question=? AND brand=? AND platform!=''", [$user["id"], $uq, $ub]);
    $up = [];
    foreach ($platformRows as $pr) { $up[] = $pr["platform"]; }
    if (!$up) $up = ["豆包"];
    if (!$up) $up = ["豆包", "DeepSeek"];
    $s2 = geoGetAdminSettings();
    $c2 = (float)($s2["cost_per_detect"] ?? 0);
    $tc2 = $c2;
    if ($c2 > 0) {
        $b2 = dbFetchOne("SELECT balance FROM users WHERE id=?", [$user["id"]])["balance"] ?? 0;
        if ($b2 < $tc2) {
            setFlash("error", "余额不足，更新检测需 ¥" . number_format($tc2, 2) . "，当前余额 ¥" . number_format($b2, 2));
            redirect("index.php?route=keywords");
            exit;
        }
        dbExecute("UPDATE users SET balance = balance - ? WHERE id=?", [$tc2, $user["id"]]);
        dbExecute("INSERT INTO transactions (user_id,type,amount,description,created_at) VALUES (?,'consume',?,?,?)", [$user["id"], $tc2, "GEO更新检测：" . $ub, date("Y-m-d H:i:s")]);
    }
    dbExecute("DELETE FROM geo_detect_results WHERE user_id=? AND question=? AND brand=?", [$user["id"], $uq, $ub]);
    foreach ($up as $pu) {
        $nid = dbInsert("INSERT INTO geo_detect_results (user_id, question, brand, platform, status, created_at) VALUES (?,?,?,?,'pending',NOW())", [$user["id"], $uq, $ub, $pu]);
        runBackgroundProcess("background_detect.php", [$nid]);
    }
    setFlash("info", "更新检测已加入队列 (" . count($up) . "个平台)");
    redirect("index.php?route=keywords");
}

$allResults = dbFetchAll("SELECT * FROM geo_detect_results WHERE user_id=? ORDER BY created_at DESC LIMIT 50", [$user["id"]]);
$platforms = ["豆包"];
$platforms = ["豆包", "DeepSeek"];
$iconMap = ["豆包" => "images/doubao.png", "DeepSeek" => "images/deepseek.png"];
$costPerDetect = (float)(geoGetAdminSettings()["cost_per_detect"] ?? 0);

// Group by (question, brand) and merge platform results
$grouped = [];
foreach ($allResults as $r) {
    $key = $r["question"] . "|||" . $r["brand"];
    if (!isset($grouped[$key])) {
        $grouped[$key] = ["question" => $r["question"], "brand" => $r["brand"], "created_at" => $r["created_at"], "id" => $r["id"], "platforms" => [], "platforms_all" => [], "status" => ""];
    }
    $statusOrder = ["processing" => 0, "pending" => 1, "failed" => 2, "completed" => 3];
    $curStatus = $grouped[$key]["status"];
    $rStatus = $r["status"];
    if (!$curStatus || ($statusOrder[$rStatus] ?? 9) < ($statusOrder[$curStatus] ?? 9)) {
        $grouped[$key]["status"] = $rStatus;
    }
    if ($r["platform"]) {
        $grouped[$key]["platforms_all"][$r["platform"]] = true;
    }
    if ($r["status"] === "completed" && $r["platform"]) {
        $grouped[$key]["platforms"][$r["platform"]] = ["pos" => $r["brand_position"], "m" => $r["brand_mentioned"]];
    }
}
?>

<div class="page-header">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="margin:0;">GEO检测</h1>
            <p style="margin:4px 0 0;">检测品牌在 AI 搜索结果中的提及情况</p>
        </div>
        <button onclick="document.getElementById('detectModal').style.display='flex'" class="btn btn-primary" style="padding:10px 20px;font-size:14px;cursor:pointer;">+ 新建检测</button>
    </div>
</div>

<?php if ($grouped): ?>
<div style="margin-top:20px;">
    <?php foreach ($grouped as $g): ?>
    <div class="dashboard-card" style="margin-bottom:10px;padding:12px 14px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
            <div style="font-size:14px;font-weight:600;color:#1f2937;line-height:1.5;flex:1;"><?= h($g["question"]) ?></div>
            <a href="index.php?route=keywords&action=update_detect&question=<?= urlencode($g["question"]) ?>&brand=<?= urlencode($g["brand"]) ?>" onclick="var uc=<?= $costPerDetect ?>;return confirm('本次更新将消耗 ¥' + uc.toFixed(2) + ' 元，是否继续？')" style="flex-shrink:0;padding:3px 10px;border:1px solid #bbf7d0;border-radius:5px;font-size:12px;color:#059669;text-decoration:none;">更新</a>
            <a href="#" onclick="editDetect('<?= h($g["question"]) ?>','<?= h($g["brand"]) ?>');return false;" style="flex-shrink:0;padding:3px 10px;border:1px solid #bfdbfe;border-radius:5px;font-size:12px;color:#3b82f6;text-decoration:none;">修改</a>
            <a href="index.php?route=keywords&action=delete_detect&id=<?= $g["id"] ?>" onclick="return confirm('删除这条记录？')" style="flex-shrink:0;padding:3px 10px;border:1px solid #fecaca;border-radius:5px;font-size:12px;color:#dc2626;text-decoration:none;">删除</a>
        </div>
        <div style="display:grid;grid-template-columns:1fr auto 1fr;align-items:center;margin-top:6px;">
            <div style="font-size:13px;color:#6b7280;">
                品牌：<strong style="color:#374151;"><?= h($g["brand"]) ?></strong>
                <?php $statusMap = ["processing" => ["检测中","#2563eb","#eff6ff"],"pending" => ["排队中","#c2410c","#fff7ed"],"failed" => ["失败","#dc2626","#fef2f2"],"completed" => ["已完成","#059669","#f0fdf4"]]; $st = $statusMap[$g["status"]] ?? null; if ($st): ?>
                <span style="display:inline-block;margin-left:8px;padding:1px 8px;border-radius:9999px;font-size:11px;font-weight:600;background:<?= $st[2] ?>;color:<?= $st[1] ?>;vertical-align:middle;"><?= $st[0] ?></span>
                <?php endif; ?>
                <span style="color:#9ca3af;font-size:11px;margin-left:6px;"><?= h($g["created_at"]) ?></span>
            </div>
            <div style="display:flex;gap:12px;align-items:center;">
                <?php foreach ($platforms as $pf): ?>
                <?php $pd = $g["platforms"][$pf] ?? null; $mentioned = $pd ? $pd["m"] : null; ?>
                <div style="display:flex;align-items:center;gap:4px;">
                    <img src="<?= $iconMap[$pf] ?>" style="width:22px;height:22px;border-radius:50%;" alt="<?= $pf ?>">
                    <span style="font-size:11px;font-weight:600;color:<?= $mentioned === 1 ? '#059669' : '#9ca3af' ?>;"><?= $mentioned === 1 ? '已收录' : '未收录' ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div style="text-align:center;padding:60px 20px;color:#9ca3af;">
    <div style="font-size:48px;margin-bottom:12px;">🔍</div>
    <div style="font-size:16px;margin-bottom:4px;">暂无检测记录</div>
    <div style="font-size:13px;">点击右上角「新建检测」开始第一次检测</div>
</div>
<?php endif; ?>

<div id="detectModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:28px;width:480px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
            <h2 style="margin:0;font-size:17px;color:#0F3460;">新建检测</h2>
            <button onclick="document.getElementById('detectModal').style.display='none'" style="border:none;background:none;font-size:20px;cursor:pointer;color:#9ca3af;padding:0;line-height:1;">×</button>
        </div>
        <form method="post" action="index.php?route=keywords">
            <input type="hidden" name="action" value="detect">
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;color:#374151;">AI 提问的问题</label>
                <textarea name="question" required rows="2" placeholder="例如：推荐几个好用的SEO工具" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-sizing:border-box;resize:vertical;"></textarea>
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;color:#374151;">品牌名称</label>
                <input type="text" name="brand" required placeholder="例如：GEO优化" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-sizing:border-box;">
            </div>
            <div style="margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                    <label style="font-size:13px;font-weight:600;color:#374151;">AI 平台</label>
                    <?php if ($costPerDetect > 0): ?>
                    <span style="color:#dc2626;font-size:12px;">本次检测消耗 ¥<?= number_format($costPerDetect, 2) ?> 元</span>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:4px;">
                    <?php foreach ($platforms as $pf): ?>
                    <label style="display:flex;align-items:center;gap:6px;font-size:14px;color:#374151;cursor:pointer;">
                        <input type="checkbox" name="platforms[]" value="<?= $pf ?>" checked style="width:16px;height:16px;cursor:pointer;">
                        <img src="<?= $iconMap[$pf] ?>" style="width:20px;height:20px;border-radius:50%;" alt="<?= $pf ?>">
                        <?= $pf ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;padding:10px;font-size:14px;cursor:pointer;" onclick="this.textContent='⏳ 提交中...';this.style.pointerEvents='none';">开始检测</button>
        </form>
    </div>
</div>

<script>
function editDetect(question, brand) {
    document.querySelector('#detectModal textarea[name="question"]').value = question;
    document.querySelector('#detectModal input[name="brand"]').value = brand;
    document.getElementById('detectModal').style.display = 'flex';
}
setInterval(function(){
    var sp = document.querySelectorAll("span");
    for(var i=0;i<sp.length;i++) {
        if(sp[i].textContent.indexOf("排队中")>=0 || sp[i].textContent.indexOf("检测中")>=0) {
            location.reload();
            return;
        }
    }
}, 5000);
</script>



