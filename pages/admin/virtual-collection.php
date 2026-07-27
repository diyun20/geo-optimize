<?php
requireLogin();
requireAdminOrAgent();
require_once __DIR__ . "/../../includes/geo.php";

try { dbExecute("CREATE TABLE IF NOT EXISTS `virtual_collections` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL UNIQUE,`amount` INT NOT NULL DEFAULT 0,`doubao_amount` INT NOT NULL DEFAULT 0,`deepseek_amount` INT NOT NULL DEFAULT 0,`admin_id` INT UNSIGNED NOT NULL,`created_at` DATETIME NOT NULL,`updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}

$admin = currentUser();

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save_virtual") {
    $dbAmts  = $_POST["doubao_amount"] ?? [];
    $dsAmts  = $_POST["deepseek_amount"] ?? [];
    $allUids = array_unique(array_merge(array_keys($dbAmts), array_keys($dsAmts)));
    foreach ($allUids as $uid) {
        $uid  = (int)$uid;
        $dbAmt = (int)($dbAmts[$uid] ?? 0);
        $dsAmt = (int)($dsAmts[$uid] ?? 0);
        $total = $dbAmt + $dsAmt;
        if ($uid <= 0) continue;
        $exists = dbFetchOne("SELECT id FROM virtual_collections WHERE user_id=?", [$uid]);
        if ($exists) {
            dbExecute("UPDATE virtual_collections SET amount=?, doubao_amount=?, deepseek_amount=?, admin_id=?, updated_at=NOW() WHERE user_id=?", [$total, $dbAmt, $dsAmt, $admin["id"], $uid]);
        } else {
            dbExecute("INSERT INTO virtual_collections (user_id,amount,doubao_amount,deepseek_amount,admin_id,created_at) VALUES (?,?,?,?,?,NOW())", [$uid, $total, $dbAmt, $dsAmt, $admin["id"]]);
        }
    }
    setFlash("success", "虚拟收录量已更新");
    redirect("index.php?route=admin/virtual-collection");
}

$agentFilter = isAgent() ? "AND u.agent_id=" . (int)$admin['id'] : "";
$users = dbFetchAll("SELECT u.id,u.username,u.email,
    COALESCE(v.amount,0) AS virtual_amount,
    COALESCE(v.doubao_amount,0) AS v_db,
    COALESCE(v.deepseek_amount,0) AS v_ds,
    COALESCE((SELECT doubao_count FROM geo_brand_scan WHERE user_id=u.id ORDER BY id DESC LIMIT 1),0) AS real_db,
    COALESCE((SELECT deepseek_count FROM geo_brand_scan WHERE user_id=u.id ORDER BY id DESC LIMIT 1),0) AS real_ds
FROM users u LEFT JOIN virtual_collections v ON u.id=v.user_id WHERE u.role!='admin' {$agentFilter} ORDER BY u.id");

$pageTitle = "虚拟收录管理";
$dbColor = '#4f46e5'; $dsColor = '#10b981';
?>
<div class="page-header">
    <h1>虚拟收录管理</h1>
    <p>按平台为用户增加虚拟收录量：<span style="color:<?=$dbColor?>;">● 豆包</span> / <span style="color:<?=$dsColor?>;">● DeepSeek</span></p>
</div>

<div class="dashboard-card">
    <form method="post" action="index.php?route=admin/virtual-collection">
        <input type="hidden" name="action" value="save_virtual">
        <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:50px;text-align:center;">ID</th>
                    <th style="text-align:center;">用户名</th>
                    <th style="text-align:center;">豆包真实</th>
                    <th style="text-align:center;">豆包虚拟</th>
                    <th style="text-align:center;">DS真实</th>
                    <th style="text-align:center;">DS虚拟</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="6" style="text-align:center;padding:40px 0;color:#9ca3af;">暂无非管理员用户</td></tr>
                <?php else: foreach ($users as $u):
                    $realDb = (int)$u["real_db"]; $vDb = (int)$u["v_db"];
                    $realDs = (int)$u["real_ds"]; $vDs = (int)$u["v_ds"];
                ?>
                <tr>
                    <td style="text-align:center;"><?= $u["id"] ?></td>
                    <td style="text-align:center;"><?= h($u["username"]) ?></td>
                    <td style="color:#4f46e5;text-align:center;font-weight:600;"><?= $realDb ?></td>
                    <td style="text-align:center;">
                        <input type="number" name="doubao_amount[<?= $u["id"] ?>]" value="<?= $vDb ?>" step="1" style="width:80px;padding:6px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;text-align:center;">
                    </td>
                    <td style="color:#10b981;text-align:center;font-weight:600;"><?= $realDs ?></td>
                    <td style="text-align:center;">
                        <input type="number" name="deepseek_amount[<?= $u["id"] ?>]" value="<?= $vDs ?>" step="1" style="width:80px;padding:6px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;text-align:center;">
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        <?php if (!empty($users)): ?>
        <div style="margin-top:16px;text-align:right;">
            <button type="submit" class="btn btn-primary" style="padding:8px 24px;font-size:14px;cursor:pointer;">保存全部</button>
        </div>
        <?php endif; ?>
    </form>
</div>