<?php
requireLogin();
requireRole("admin");
require_once __DIR__ . "/../../includes/geo.php";
geoInitTables();
$pageTitle = "自动任务 - GEO优化";
$users = dbFetchAll("SELECT id, username FROM users WHERE role!='admin' ORDER BY username");

try { dbExecute("CREATE TABLE IF NOT EXISTS `auto_tasks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL DEFAULT '',
  `type` ENUM('scan','geo_detect') NOT NULL,
  `scope` ENUM('all','user') NOT NULL DEFAULT 'all',
  `target_user_id` INT UNSIGNED DEFAULT NULL,
  `interval_hours` INT UNSIGNED NOT NULL DEFAULT 0,
  `interval_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `last_run_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}

$action = $_POST["action"] ?? $_GET["action"] ?? "";
// -- CRUD handlers --
if ($action === "add" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $type = $_POST["type"] ?? "scan";
    $scope = $_POST["scope"] ?? "all";
    $targetUid = $scope === "user" ? (int)($_POST["target_user_id"] ?? 0) : 0;
    $hours = max(0, (int)($_POST["hours"] ?? 0));
    $minutes = max(0, (int)($_POST["minutes"] ?? 0));
    if ($name) {
        dbExecute("INSERT INTO auto_tasks (name,type,scope,target_user_id,interval_hours,interval_minutes,created_at) VALUES (?,?,?,?,?,?,NOW())",
            [$name, $type, $scope, $targetUid ?: null, $hours, $minutes]);
        setFlash("success", "任务已添加");
    } else { setFlash("error", "请填写任务名称"); }
    redirect("index.php?route=admin/auto-task");
}
if ($action === "edit" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $id = (int)($_POST["id"] ?? 0);
    $name = trim($_POST["name"] ?? "");
    $type = $_POST["type"] ?? "scan";
    $scope = $_POST["scope"] ?? "all";
    $targetUid = $scope === "user" ? (int)($_POST["target_user_id"] ?? 0) : 0;
    $hours = max(0, (int)($_POST["hours"] ?? 0));
    $minutes = max(0, (int)($_POST["minutes"] ?? 0));
    if ($id && $name) {
        dbExecute("UPDATE auto_tasks SET name=?,type=?,scope=?,target_user_id=?,interval_hours=?,interval_minutes=? WHERE id=?",
            [$name, $type, $scope, $targetUid ?: null, $hours, $minutes, $id]);
        setFlash("success", "任务已更新");
    }
    redirect("index.php?route=admin/auto-task");
}
if ($action === "toggle" && isset($_GET["id"])) {
    $id = (int)$_GET["id"];
    $t = dbFetchOne("SELECT id,enabled FROM auto_tasks WHERE id=?", [$id]);
    if ($t) { dbExecute("UPDATE auto_tasks SET enabled=? WHERE id=?", [$t["enabled"] ? 0 : 1, $id]); setFlash("success", "任务状态已更改"); }
    redirect("index.php?route=admin/auto-task");
}
if ($action === "delete" && isset($_GET["id"])) {
    dbExecute("DELETE FROM auto_tasks WHERE id=?", [(int)$_GET["id"]]);
    setFlash("success", "任务已删除");
    redirect("index.php?route=admin/auto-task");
}
if ($action === "run" && isset($_GET["id"])) {
    $at = dbFetchOne("SELECT * FROM auto_tasks WHERE id=?", [(int)$_GET["id"]]);
    if ($at) {
        $users = $at["scope"] === "user"
            ? [["id" => $at["target_user_id"]]]
            : dbFetchAll("SELECT id FROM users WHERE role!='admin'");
        $count = 0;
        foreach ($users as $u) {
            $uid = (int)$u["id"];
            if ($at["type"] === "scan") {
                $qid = dbInsert("INSERT INTO geo_scan_queue (user_id,status,created_at) VALUES (?,'pending',NOW())", [$uid]);
                runBackgroundProcess("background_scan.php", [$qid]);
                $count++;
            } elseif ($at["type"] === "geo_detect") {
                $kws = dbFetchAll("SELECT id FROM geo_keywords WHERE user_id=? AND active=1", [$uid]);
                foreach ($kws as $kw) {
                    dbExecute("INSERT IGNORE INTO geo_queue (keyword_id,user_id,status,created_at) VALUES (?,?,'pending',NOW())", [$kw["id"], $uid]);
                    $count++;
                }
            }
        }
        if ($at["type"] === "geo_detect") {
            runBackgroundProcess("cron_worker.php");
        }
        dbExecute("UPDATE auto_tasks SET last_run_at=NOW() WHERE id=?", [$at["id"]]);
        setFlash("success", "任务已提交队列，共 {$count} 条，后台处理中");
    }
    redirect("index.php?route=admin/auto-task");
}
$tasks = dbFetchAll("SELECT * FROM auto_tasks ORDER BY id ASC");
$typeMap = ["scan" => "收录更新", "geo_detect" => "GEO检测"];
?>
<div class="page-header">
    <h1>自动任务</h1>
    <p>设置定期自动执行的品牌扫描和 GEO 检测任务</p>
</div>
<div class="dashboard-card" style="margin-bottom:20px;">
    <h3>添加任务</h3>
    <form method="post" action="index.php?route=admin/auto-task">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 80px 80px;gap:10px;align-items:end;">
            <div class="form-group"><label>任务名称</label><input type="text" name="name" required placeholder="如：每日品牌扫描"></div>
            <div class="form-group"><label>任务类型</label><select name="type"><option value="scan">收录更新</option><option value="geo_detect">GEO检测</option></select></div>
            <div class="form-group">
                <label>执行范围</label>
                <select name="scope" onchange="var tu=document.getElementById('targetUser');tu.style.display=this.value==='user'?'':'none'">
                    <option value="all">所有用户</option>
                    <option value="user">指定用户</option>
                </select>
                <select id="targetUser" name="target_user_id" style="display:none;margin-top:4px;">
                    <?php foreach ($users as $u): ?><option value="<?= $u["id"] ?>"><?= h($u["username"]) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>间隔(时)</label><input type="number" name="hours" min="0" value="0"></div>
                <div class="form-group"><label>间隔(分)</label><input type="number" name="minutes" min="0" value="30"></div>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:10px;">添加任务</button>
    </form>
</div>
<div class="dashboard-card">
    <h3>任务列表</h3>
    <?php if (empty($tasks)): ?>
    <div style="text-align:center;padding:30px;color:#9ca3af;">暂无自动任务，在上方添加</div>
    <?php else: ?>
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead><tr style="border-bottom:2px solid #e5e7eb;text-align:left;">
            <th style="padding:10px 8px;">ID</th><th style="padding:10px 8px;">任务名</th><th style="padding:10px 8px;">类型</th>
            <th style="padding:10px 8px;">范围</th><th style="padding:10px 8px;">间隔</th><th style="padding:10px 8px;">状态</th>
            <th style="padding:10px 8px;">上次执行</th><th style="padding:10px 8px;">操作</th>
        </tr></thead>
        <tbody>
        <?php foreach ($tasks as $t): ?>
        <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:10px 8px;color:#9ca3af;"><?= $t["id"] ?></td>
            <td style="padding:10px 8px;font-weight:600;"><?= h($t["name"]) ?></td>
            <td style="padding:10px 8px;"><?= $typeMap[$t["type"]] ?? $t["type"] ?></td>
            <td style="padding:10px 8px;"><?= $t["scope"] === "all" ? "所有用户" : h(dbFetchOne("SELECT username FROM users WHERE id=?", [$t["target_user_id"]])["username"] ?? "?") ?></td>
            <td style="padding:10px 8px;"><?= $t["interval_hours"] ?>时<?= $t["interval_minutes"] ?>分</td>
            <td style="padding:10px 8px;"><a href="index.php?route=admin/auto-task&action=toggle&id=<?= $t["id"] ?>" style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;background:<?= $t["enabled"] ? "#f0fdf4" : "#f3f4f6" ?>;color:<?= $t["enabled"] ? "#059669" : "#9ca3af" ?>;text-decoration:none;"><?= $t["enabled"] ? "启用" : "禁用" ?></a></td>
            <td style="padding:10px 8px;color:#9ca3af;font-size:13px;"><?= $t["last_run_at"] ? h($t["last_run_at"]) : "-" ?></td>
            <td style="padding:10px 8px;white-space:nowrap;">
                <a href="javascript:;" onclick="editTask(<?= $t["id"] ?>,'<?= addslashes($t["name"]) ?>','<?= $t["type"] ?>','<?= $t["scope"] ?>','<?= ($t["target_user_id"] ?? "") ?>','<?= $t["interval_hours"] ?>','<?= $t["interval_minutes"] ?>')" style="padding:3px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;color:#374151;background:#fff;text-decoration:none;">编辑</a>
                <a href="index.php?route=admin/auto-task&action=run&id=<?= $t["id"] ?>" style="display:inline-block;padding:3px 10px;border:1px solid #bbf7d0;border-radius:4px;font-size:12px;color:#059669;background:#f0fdf4;text-decoration:none;">更新</a>
                <a href="index.php?route=admin/auto-task&action=delete&id=<?= $t["id"] ?>" onclick="return confirm('确认删除?')" style="display:inline-block;padding:3px 10px;border:1px solid #fecaca;border-radius:4px;font-size:12px;color:#dc2626;background:#fef2f2;text-decoration:none;margin-left:4px;">删除</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<div id="editModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:28px;width:480px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
            <h2 style="margin:0;font-size:17px;">编辑任务</h2><button onclick="document.getElementById('editModal').style.display='none'" style="border:none;background:none;font-size:20px;cursor:pointer;color:#9ca3af;">x</button>
        </div>
        <form method="post" action="index.php?route=admin/auto-task">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="editId" name="id">
            <div class="form-group"><label>任务名称</label><input type="text" id="editName" name="name" required></div>
            <div class="form-group"><label>类型</label><select id="editType" name="type"><option value="scan">收录更新</option><option value="geo_detect">GEO检测</option></select></div>
            <div class="form-group">
                <label>范围</label>
                <select id="editScope" name="scope" onchange="var tu=document.getElementById('editTargetUser');tu.style.display=this.value==='user'?'':'none'"><option value="all">所有用户</option><option value="user">指定用户</option></select>
                <select id="editTargetUser" name="target_user_id" style="display:none;margin-top:4px;">
                    <?php foreach ($users as $u): ?><option value="<?= $u["id"] ?>"><?= h($u["username"]) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:10px;">
                <div class="form-group" style="flex:1;"><label>间隔(时)</label><input type="number" id="editHours" name="hours" min="0"></div>
                <div class="form-group" style="flex:1;"><label>间隔(分)</label><input type="number" id="editMinutes" name="minutes" min="0"></div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px;">保存</button>
        </form>
    </div>
</div>
<script>
function editTask(id,name,type,scope,uid,hours,minutes) {
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editType').value = type;
    document.getElementById('editScope').value = scope;
    var tu = document.getElementById('editTargetUser');
    if (tu) { tu.style.display = scope === 'user' ? '' : 'none'; tu.value = uid; }
    document.getElementById('editHours').value = hours;
    document.getElementById('editMinutes').value = minutes;
    document.getElementById('editModal').style.display = 'flex';
}
</script>
