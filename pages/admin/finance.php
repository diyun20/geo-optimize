<?php
requireLogin();
requireAdminOrAgent();
$pageTitle = "金额明细 - GEO优化";
$me = currentUser();

// 处理充值/扣款
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adjust_balance') {
    $targetId = (int)($_POST['user_id'] ?? 0);
    $type     = $_POST['type'] ?? 'recharge';
    $amount   = (float)($_POST['amount'] ?? 0);
    $desc     = trim($_POST['description'] ?? '');
    if ($targetId <= 0 || $amount <= 0) {
        setFlash('error', '用户和金额不能为空');
    } elseif (isAgent() && !canManageUser($targetId)) {
        setFlash('error', '无权操作此用户');
    } else {
        if ($type === 'recharge') {
            dbExecute("UPDATE users SET balance = balance + ? WHERE id=?", [$amount, $targetId]);
        } else {
            dbExecute("UPDATE users SET balance = balance - ? WHERE id=?", [$amount, $targetId]);
        }
        dbExecute("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?,?,?,?,NOW())", [$targetId, $type, $amount, $desc ?: ($type==='recharge'?'手动充值':'手动扣款')]);
        setFlash('success', ($type==='recharge'?'充值':'扣款') . " ¥{$amount} 成功");
    }
    redirect('index.php?route=admin/finance' . ($selectedUserId ? "&user_id={$selectedUserId}" : ''));
}

// 代理商过滤
$agentJoin  = "";
$agentWhere = "";
if (isAgent()) {
    $agentJoin  = " INNER JOIN users u2 ON t.user_id=u2.id";
    $agentWhere = " AND u2.agent_id=" . (int)$me['id'];
}

// 总流水统计
$totalRecharge = dbFetchOne("SELECT COALESCE(SUM(t.amount),0) FROM transactions t{$agentJoin} WHERE t.type='recharge'{$agentWhere}")["COALESCE(SUM(t.amount),0)"];
$totalConsume  = dbFetchOne("SELECT COALESCE(SUM(t.amount),0) FROM transactions t{$agentJoin} WHERE t.type='consume'{$agentWhere}")["COALESCE(SUM(t.amount),0)"];
$totalNet      = $totalRecharge - $totalConsume;

// 今日统计
if (isAgent()) {
    $todayRecharge = dbFetchOne("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='recharge' AND DATE(created_at)=CURDATE() AND user_id IN (SELECT id FROM users WHERE agent_id=?)", [$me['id']])["COALESCE(SUM(amount),0)"];
    $todayConsume  = dbFetchOne("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='consume' AND DATE(created_at)=CURDATE() AND user_id IN (SELECT id FROM users WHERE agent_id=?)", [$me['id']])["COALESCE(SUM(amount),0)"];
} else {
    $todayRecharge = dbFetchOne("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='recharge' AND DATE(created_at)=CURDATE()")["COALESCE(SUM(amount),0)"];
    $todayConsume  = dbFetchOne("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='consume' AND DATE(created_at)=CURDATE()")["COALESCE(SUM(amount),0)"];
}

// 用户列表（用于筛选）- 代理商只看名下
$users = isAgent()
    ? dbFetchAll("SELECT id, username FROM users WHERE agent_id=? ORDER BY username", [$me['id']])
    : dbFetchAll("SELECT id, username FROM users ORDER BY username");

// 当前选中的用户
$selectedUserId = isset($_GET["user_id"]) ? (int)$_GET["user_id"] : 0;
$selectedUser = null;
if ($selectedUserId > 0) {
    $selectedUser = dbFetchOne("SELECT username FROM users WHERE id=?", [$selectedUserId]);
}

// 查询条件
$where = "";
$params = [];
if ($selectedUserId > 0) {
    $where = "WHERE user_id=?";
    $params[] = $selectedUserId;
}

// 交易记录
$txns = dbFetchAll("SELECT t.*, u.username, (SELECT COALESCE(SUM(CASE WHEN t2.type='recharge' THEN t2.amount WHEN t2.type='consume' THEN -t2.amount ELSE 0 END),0) FROM transactions t2 WHERE t2.user_id = t.user_id AND t2.id <= t.id) as balance_after FROM transactions t LEFT JOIN users u ON t.user_id=u.id {$where} ORDER BY t.id DESC LIMIT 100", $params);
?>

<div class="page-header">
    <h1>💰 金额明细</h1>
    <p>查看网站流水和用户消费记录</p>
</div>

<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-icon green">📈</div>
        <div class="stat-body">
            <div class="stat-number">¥ <?= number_format($totalRecharge, 2) ?></div>
            <div class="stat-label">总充值</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">📉</div>
        <div class="stat-body">
            <div class="stat-number">¥ <?= number_format($totalConsume, 2) ?></div>
            <div class="stat-label">总消耗</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">💰</div>
        <div class="stat-body">
            <div class="stat-number">¥ <?= number_format($totalNet, 2) ?></div>
            <div class="stat-label">净流水</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">🔥</div>
        <div class="stat-body">
            <div class="stat-number">¥ <?= number_format($todayConsume, 2) ?></div>
            <div class="stat-label">今日消耗</div>
        </div>
    </div>
</div>

<div class="dashboard-card" style="margin-bottom:20px;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
        <label style="font-size:14px;font-weight:600;">筛选用户：</label>
        <select onchange="location.href='index.php?route=admin/finance&user_id='+this.value" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;min-width:200px;">
            <option value="0">全部用户</option>
            <?php foreach ($users as $u): ?>
            <option value="<?= $u["id"] ?>" <?= $selectedUserId === (int)$u["id"] ? "selected" : "" ?>><?= h($u["username"]) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($selectedUser): ?>
        <span style="font-size:13px;color:#6b7280;">当前查看：<strong><?= h($selectedUser["username"]) ?></strong></span>
        <?php endif; ?>
        <button onclick="openAdjustModal()" style="margin-left:auto;padding:8px 16px;background:#4f46e5;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">💳 余额调整</button>
    </div>

    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead>
            <tr style="border-bottom:2px solid #e5e7eb;text-align:left;">
                <th style="padding:10px 8px;">ID</th>
                <th style="padding:10px 8px;">用户</th>
                <th style="padding:10px 8px;">类型</th>
                <th style="padding:10px 8px;">金额</th>
                <th style="padding:10px 8px;">变动后余额</th>
                <th style="padding:10px 8px;">说明</th>
                <th style="padding:10px 8px;">时间</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($txns)): ?>
            <tr><td colspan="7" style="text-align:center;padding:30px;color:#9ca3af;">暂无交易记录</td></tr>
            <?php endif; ?>
            <?php foreach ($txns as $t): ?>
            <tr style="border-bottom:1px solid #f3f4f6;">
                <td style="padding:10px 8px;color:#9ca3af;font-size:13px;"><?= $t["id"] ?></td>
                <td style="padding:10px 8px;"><?= h($t["username"] ?? "未知") ?></td>
                <td style="padding:10px 8px;">
                    <span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;background:<?= $t["type"] === "recharge" ? "#f0fdf4" : ($t["type"] === "consume" ? "#fef2f2" : "#f3f4f6") ?>;color:<?= $t["type"] === "recharge" ? "#059669" : ($t["type"] === "consume" ? "#dc2626" : "#6b7280") ?>;">
                        <?= $t["type"] === "recharge" ? "充值" : ($t["type"] === "consume" ? "消耗" : "退款") ?>
                    </span>
                </td>
                <td style="padding:10px 8px;font-weight:600;color:<?= $t["type"] === "recharge" ? "#059669" : "#dc2626" ?>;">
                    <?= $t["type"] === "recharge" ? "+" : "-" ?>¥ <?= number_format((float)$t["amount"], 2) ?>
                </td>
                <td style="padding:10px 8px;font-weight:600;color:#374151;">¥ <?= number_format((float)$t["balance_after"], 2) ?></td>
                <td style="padding:10px 8px;color:#6b7280;font-size:13px;"><?= h($t["description"] ?? "") ?></td>
                <td style="padding:10px 8px;color:#9ca3af;font-size:13px;"><?= h($t["created_at"]) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- 余额调整弹窗 -->
<div id="adjustModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:24px;width:400px;max-width:90vw;">
        <h3 style="margin:0 0 16px 0;font-size:16px;">💳 余额调整</h3>
        <form method="post" action="index.php?route=admin/finance<?= $selectedUserId ? "&user_id={$selectedUserId}" : '' ?>">
            <input type="hidden" name="action" value="adjust_balance">
            <div style="margin-bottom:12px;">
                <label style="font-size:13px;color:#6b7280;">用户</label>
                <select name="user_id" required style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;margin-top:4px;">
                    <?php foreach ($users as $u): ?>
                    <option value="<?=$u['id']?>" <?=$selectedUserId===$u['id']?'selected':''?>><?=h($u['username'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:13px;color:#6b7280;">类型</label>
                <select name="type" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;margin-top:4px;">
                    <option value="recharge">充值 (+)</option>
                    <option value="consume">扣款 (-)</option>
                </select>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:13px;color:#6b7280;">金额</label>
                <input type="number" name="amount" step="0.01" min="0.01" required placeholder="输入金额" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;margin-top:4px;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="font-size:13px;color:#6b7280;">说明</label>
                <input type="text" name="description" placeholder="如：手动充值" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;margin-top:4px;">
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="closeAdjustModal()" style="padding:8px 16px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;">取消</button>
                <button type="submit" style="padding:8px 16px;background:#4f46e5;color:#fff;border:none;border-radius:6px;cursor:pointer;">确认</button>
            </div>
        </form>
    </div>
</div>
<script>
function openAdjustModal() { document.getElementById('adjustModal').style.display='flex'; }
function closeAdjustModal() { document.getElementById('adjustModal').style.display='none'; }
// 点背景关闭
document.getElementById('adjustModal').addEventListener('click', function(e) { if(e.target===this) closeAdjustModal(); });
</script>

