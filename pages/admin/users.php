<?php
requireLogin();
requireAdminOrAgent();
require_once __DIR__ . '/../../includes/geo.php';
$me = currentUser();
$pageTitle = '用户管理 - GEO优化';

try { dbExecute("ALTER TABLE users ADD COLUMN `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `role`"); } catch (Exception $e) {}
try { dbExecute("ALTER TABLE users ADD COLUMN agent_id INT UNSIGNED DEFAULT NULL AFTER role"); } catch (Exception $e) {}
try {
    dbExecute("CREATE TABLE IF NOT EXISTS `transactions` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNSIGNED NOT NULL,
        `type` ENUM('recharge','consume','refund') NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        `created_at` DATETIME NOT NULL,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// ---- 操作处理 ----
if ($selectedUserId > 0 && !canManageUser($selectedUserId)) {
    setFlash('error', '无权管理此用户');
    redirect('index.php?route=admin/users');
}

// 更新收录
if (isset($_GET['action']) && $_GET['action'] === 'update_index' && $selectedUserId > 0) {
    geoInitTables();
    dbExecute("INSERT INTO geo_scan_queue (user_id,status,created_at) VALUES (?,'pending',NOW())", [$selectedUserId]);
    setFlash('info', '更新收录已加入队列');
    redirect('index.php?route=admin/users&user_id=' . $selectedUserId);
}

// AJAX: 保存排除天数设置
if (isset($_GET['action']) && $_GET['action'] === 'save_exclude_days') {
    header('Content-Type: application/json; charset=utf-8');
    $days = (int)($_GET['days'] ?? 0);
    dbExecute("INSERT INTO site_settings (setting_key,setting_value) VALUES ('scan_exclude_days',?) ON DUPLICATE KEY UPDATE setting_value=?", [$days, $days]);
    echo json_encode(['success'=>true]);
    exit;
}

// AJAX: 更新全体收录
if (isset($_GET['action']) && $_GET['action'] === 'refresh_all') {
    header('Content-Type: application/json; charset=utf-8');
    while (ob_get_level()) ob_end_clean(); ob_start();
    error_reporting(0); ini_set('display_errors', 0);
    try {
        $excludeDays = (int)($_GET['exclude_days'] ?? 0);
        $excludeDate = $excludeDays > 0 ? date('Y-m-d H:i:s', strtotime("-{$excludeDays} days")) : null;
        if (isAgent()) {
            $users = dbFetchAll("SELECT id FROM users WHERE agent_id=?", [$me['id']]);
        } else {
            $sql = "SELECT DISTINCT u.id FROM users u INNER JOIN company_info c ON c.user_id=u.id WHERE u.role='user'";
            $params = [];
            if ($excludeDate) {
                $sql .= " AND u.created_at < ?";
                $params[] = $excludeDate;
            }
            $users = dbFetchAll($sql, $params);
        }
        $added = $skipped = 0;
        foreach ($users as $u) {
            $p = dbFetchOne("SELECT id FROM geo_scan_queue WHERE user_id=? AND status IN ('pending','processing')", [$u['id']]);
            if ($p) { $skipped++; continue; }
            dbExecute("INSERT INTO geo_scan_queue (user_id,status,created_at) VALUES (?,'pending',NOW())", [$u['id']]);
            $added++;
        }
        $msg = "已添加 {$added} 个任务" . ($skipped>0 ? "，{$skipped} 个跳过" : "");
        if ($excludeDays > 0) $msg .= "（排除 {$excludeDays} 天内上线用户）";
        echo json_encode(['success'=>true,'added'=>$added,'skipped'=>$skipped,'message'=>$msg], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// POST 操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = isAdmin() ? ($_POST['role'] ?? 'user') : 'user';
        $agentId  = isAdmin() ? ($_POST['agent_id'] ? (int)$_POST['agent_id'] : null) : $me['id'];
        if (empty($username) || empty($email) || empty($password)) {
            setFlash('error', '请填写所有必填项');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', '邮箱格式不正确');
        } elseif (strlen($password) < 6) {
            setFlash('error', '密码至少 6 位');
        } else {
            $result = register($username, $email, $password, $role, $agentId);
            if ($result === true) {
                if ($role !== 'user' || $agentId) {
                    dbExecute('UPDATE users SET role=?, agent_id=? WHERE username=?', [$role, $agentId, $username]);
                }
                setFlash('success', "用户 {$username} 已创建");
            } else {
                setFlash('error', $result);
            }
        }
        redirect('index.php?route=admin/users');
    }

    if ($action === 'change_role') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $newRole  = $_POST['role'] ?? 'user';
                if ($targetId > 0 && canManageUser($targetId) && in_array($newRole, ['user','agent'])) {
            dbExecute('UPDATE users SET role=? WHERE id=?', [$newRole, $targetId]);
            setFlash('success', '角色已更新');
        }
    }

    if ($action === 'toggle_ban' && (isAdmin() || isAgent())) {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $ban = (int)($_POST['banned'] ?? 0);
        if ($targetId > 0 && canManageUser($targetId)) {
            dbExecute('UPDATE users SET banned=? WHERE id=?', [$ban, $targetId]);
            setFlash('success', $ban ? '账号已封禁' : '账号已解封');
        }
        redirect('index.php?route=admin/users&user_id=' . $targetId);
    }

if ($action === 'set_membership' && (isAdmin() || isAgent())) {
    $targetId = (int)($_POST['user_id'] ?? 0);
    $m = in_array($_POST['membership'] ?? '', ['trial','vip']) ? $_POST['membership'] : 'trial';
    if ($targetId > 0 && canManageUser($targetId)) {
        $expire = $m === 'vip' ? date('Y-m-d', strtotime('+30 days')) : null;
        // 代理商名下用户VIP时长不能超过代理商本身
        if (isAgent() && $expire) {
            $meExpire = $me['agent_expire'] ?? '';
            if ($meExpire && $expire > $meExpire) {
                setFlash('error', "名下用户VIP时长不能超过您的代理授权到期日（{$meExpire}）");
                redirect('index.php?route=admin/users&user_id=' . $targetId);
            }
        }
        dbExecute('UPDATE users SET membership=?, membership_expire=? WHERE id=?', [$m, $expire, $targetId]);
        setFlash('success', $m === 'vip' ? '已升级VIP，到期日：' . $expire : '已降为试用');
    }
    redirect('index.php?route=admin/users&user_id=' . $targetId);
}

    if ($action === 'set_membership_expire' && (isAdmin() || isAgent())) {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $expire = trim($_POST['membership_expire'] ?? '');
        if ($targetId > 0 && canManageUser($targetId)) {
            // 代理商手动设日期也不能超过自己的到期日
            if (isAgent() && $expire) {
                $meExpire = $me['agent_expire'] ?? '';
                if ($meExpire && $expire > $meExpire) {
                    setFlash('error', "到期日不能超过您的代理授权日期（{$meExpire}）");
                    redirect('index.php?route=admin/users&user_id=' . $targetId);
                }
            }
            dbExecute('UPDATE users SET membership_expire=? WHERE id=?', [$expire ?: null, $targetId]);
            setFlash('success', $expire ? "VIP到期日已设为 {$expire}" : 'VIP到期日已清除');
        }
        redirect('index.php?route=admin/users&user_id=' . $targetId);
    }

    if ($action === 'set_agent_expire' && isAdmin()) {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $expire = trim($_POST['agent_expire'] ?? '');
        if ($targetId > 0) {
            dbExecute('UPDATE users SET agent_expire=? WHERE id=? AND role=?', [$expire ?: null, $targetId, 'agent']);
            setFlash('success', $expire ? "代理到期日已设为 {$expire}" : '代理到期日已清除');
        }
        redirect('index.php?route=admin/users&user_id=' . $targetId);
    }

    if ($action === 'reassign_agent') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $newAgent = $_POST['agent_id'] ? (int)$_POST['agent_id'] : null;
        if ($targetId > 0 && isAdmin()) {
            dbExecute('UPDATE users SET agent_id=? WHERE id=?', [$newAgent, $targetId]);
            setFlash('success', '代理商已重新分配');
        }
        redirect('index.php?route=admin/users');
    }

    if ($action === 'save_prices') {
        $detect = (float)($_POST['detect_price'] ?? 0);
        $upgrade = (float)($_POST['upgrade_price'] ?? 0);
        dbExecute("INSERT INTO site_settings (setting_key,setting_value) VALUES ('detect_price',?) ON DUPLICATE KEY UPDATE setting_value=?", [$detect, $detect]);
        dbExecute("INSERT INTO site_settings (setting_key,setting_value) VALUES ('upgrade_price',?) ON DUPLICATE KEY UPDATE setting_value=?", [$upgrade, $upgrade]);
        setFlash('success', '价格设置已保存');
        redirect('index.php?route=admin/users');
    }
}

// ---- 查询用户列表 ----
if (isAgent()) {
    // 代理商只看自己名下的
    $users = dbFetchAll("SELECT u.*, COALESCE(k.keywords,0) AS keyword_count FROM users u LEFT JOIN (SELECT user_id, MAX(doubao_count+deepseek_count) AS keywords FROM geo_brand_scan GROUP BY user_id) k ON k.user_id=u.id WHERE (u.agent_id=? OR u.id=?) AND u.role!='admin' ORDER BY u.id", [$me['id'], $me['id']]);
} else {
    $users = dbFetchAll("SELECT u.*, COALESCE(k.keywords,0) AS keyword_count FROM users u LEFT JOIN (SELECT user_id, MAX(doubao_count+deepseek_count) AS keywords FROM geo_brand_scan GROUP BY user_id) k ON k.user_id=u.id WHERE u.role!='admin' ORDER BY u.id");
}

// 代理商列表（管理员用）
$agents = isAdmin() ? dbFetchAll("SELECT id, username FROM users WHERE role='agent' ORDER BY id") : [];

$selectedUser = $selectedUserId > 0 ? dbFetchOne('SELECT * FROM users WHERE id = ?', [$selectedUserId]) : null;
?>

<div class="admin-page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <div>
        <h1>用户管理</h1>
        <p><?= $selectedUser ? "管理: " . h($selectedUser["username"]) : (isAgent() ? "我名下的用户" : "系统所有用户") ?></p>
    </div>
    <?php if (!$selectedUser): ?>
    <div style="display:flex;gap:8px;">
    <?php if (isAdminOrAgent()): ?><button onclick="openPriceModal()" style="padding:10px 14px;font-size:13px;border:none;border-radius:6px;background:#f59e0b;color:#fff;cursor:pointer;white-space:nowrap;">💰 价格设置</button><?php endif; ?>
    <button onclick="openRefreshModal()" id="btnRefreshAll" style="padding:10px 14px;font-size:13px;border:none;border-radius:6px;background:#4f46e5;color:#fff;cursor:pointer;white-space:nowrap;"><?= isAgent() ? '🔄 更新名下收录' : '🔄 更新全体收录' ?></button>
    </div>
    <?php endif; ?>
</div>
<span id="refreshAllStatus" style="display:block;margin:-8px 0 16px 0;font-size:13px;color:#6b7280;"></span>

<!-- 更新收录弹窗 -->
<?php $savedExcludeDays = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='scan_exclude_days'")['setting_value'] ?? '0'; ?>
<input type="hidden" id="savedExcludeDays" value="<?= $savedExcludeDays ?>">
<div id="refreshModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:28px;width:420px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="margin:0;font-size:17px;">🔄 <?= isAgent() ? '更新名下收录' : '更新全体收录' ?></h2>
            <button onclick="closeRefreshModal()" style="border:none;background:none;font-size:20px;cursor:pointer;color:#9ca3af;padding:0;line-height:1;">×</button>
        </div>
        <div style="margin-bottom:20px;">
            <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">排除最近上线用户（天）</label>
            <input type="number" id="excludeDays" value="0" min="0" placeholder="例如：7 表示排除7天内上线的用户" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;box-sizing:border-box;">
            <p style="font-size:12px;color:#9ca3af;margin-top:6px;">填 0 或不填表示不排除，扫描全部用户。填入数字则跳过该天数内注册的用户。</p>
        </div>
        <div style="display:flex;gap:10px;">
            <button onclick="saveExcludeDays()" style="flex:1;padding:10px;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#374151;font-size:14px;font-weight:500;cursor:pointer;">💾 保存</button>
            <button onclick="doRefreshAll()" id="btnDoRefresh" style="flex:2;padding:10px;border:none;border-radius:8px;background:#4f46e5;color:#fff;font-size:14px;font-weight:600;cursor:pointer;">⚡ 立即更新</button>
        </div>
    </div>
</div>

<script>
function openRefreshModal(){
    document.getElementById('refreshModal').style.display='flex';
    // 加载已保存的天数
    var sd=document.getElementById('savedExcludeDays');
    if(sd) document.getElementById('excludeDays').value=sd.value||0;
}
function closeRefreshModal(){document.getElementById('refreshModal').style.display='none'}
async function saveExcludeDays(){
    var days=document.getElementById('excludeDays').value||0;
    try{
        await fetch('index.php?route=admin/users&action=save_exclude_days&days='+days);
        // 同步更新隐藏值和状态提示
        var sd=document.getElementById('savedExcludeDays');
        if(sd) sd.value=days;
        var st=document.getElementById('refreshAllStatus');
        st.textContent='✅ 已保存：排除 '+days+' 天内上线用户';
        st.style.color='#059669';
    }catch(e){}
    closeRefreshModal();
}
async function doRefreshAll(){
    const days=document.getElementById('excludeDays').value||0;
    const btn=document.getElementById('btnDoRefresh'),st=document.getElementById('refreshAllStatus');
    btn.disabled=true;btn.textContent='⏳ 处理中...';closeRefreshModal();
    st.textContent='';
    try{
        const r=await fetch('index.php?route=admin/users&action=refresh_all&exclude_days='+days),d=await r.json();
        st.textContent=d.message;st.style.color=d.success?'#059669':'#dc2626';
    }catch(e){st.textContent='请求失败: '+e.message;st.style.color='#dc2626';}
    btn.disabled=false;btn.textContent='⚡ 立即更新';
}
</script>

<?php if (!$selectedUser): ?>
<!-- 创建用户卡片 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-accent"></div>
    <div class="admin-card-body" style="padding:20px;">
        <h3 style="font-size:15px;font-weight:600;margin:0 0 16px 0;">➕ 创建用户</h3>
        <form method="post" action="index.php?route=admin/users">
            <input type="hidden" name="action" value="add">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;align-items:end;">
                <div><label style="font-size:12px;color:#6b7280;">用户名</label><input type="text" name="username" required style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;"></div>
                <div><label style="font-size:12px;color:#6b7280;">邮箱</label><input type="email" name="email" required style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;"></div>
                <div><label style="font-size:12px;color:#6b7280;">密码</label><input type="text" name="password" required style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;"></div>
                <?php if (isAdmin()): ?>
                <div><label style="font-size:12px;color:#6b7280;">角色</label>
                    <select name="role" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;">
        <option value="user">普通用户</option>
        <option value="agent">代理商</option>
                    </select>
                </div>
                <div><label style="font-size:12px;color:#6b7280;">归属代理商</label>
                    <select name="agent_id" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;">
                        <option value="">无</option>
                        <?php foreach($agents as $a): ?>
                        <option value="<?=$a['id']?>"><?=h($a['username'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div><button type="submit" style="padding:9px 16px;background:#4f46e5;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;">创建</button></div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($selectedUser): ?>
<!-- 用户详情 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-accent"></div>
    <div class="admin-card-body" style="padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-size:16px;font-weight:600;margin:0;"><?= h($selectedUser['username']) ?> 详情</h3>
            <a href="index.php?route=admin/users" style="font-size:13px;color:#6b7280;text-decoration:none;">← 返回列表</a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
            <div><span style="color:#9ca3af;font-size:12px;">ID</span><div style="font-weight:600;"><?= $selectedUser['id'] ?></div></div>
            <div><span style="color:#9ca3af;font-size:12px;">用户名</span><div style="font-weight:600;"><?= h($selectedUser['username']) ?></div></div>
            <div><span style="color:#9ca3af;font-size:12px;">邮箱</span><div><?= h($selectedUser['email']) ?></div></div>
            <div><span style="color:#9ca3af;font-size:12px;">角色</span><div><?= $selectedUser['role']==='agent'?'代理商':'用户' ?></div></div>
            <div><span style="color:#9ca3af;font-size:12px;">余额</span><div style="font-weight:600;">¥ <?= number_format($selectedUser['balance']??0,2) ?></div></div>
            <div><span style="color:#9ca3af;font-size:12px;">注册时间</span><div><?= $selectedUser['created_at'] ?></div></div>
            <?php if (isAdmin() || isAgent()): ?>
            <div><span style="color:#9ca3af;font-size:12px;">会员</span>
                <form method="post" action="index.php?route=admin/users&user_id=<?=$selectedUserId?>" style="display:inline;">
                    <input type="hidden" name="action" value="set_membership">
                    <input type="hidden" name="user_id" value="<?=$selectedUserId?>">
                    <select name="membership" onchange="this.form.submit()" style="padding:2px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;">
                        <option value="trial" <?= ($selectedUser['membership']??'vip')==='trial'?'selected':'' ?>>🆓 试用</option>
                        <option value="vip" <?= ($selectedUser['membership']??'vip')==='vip'?'selected':'' ?>>👑 VIP</option>
                    </select>
                </form>
            </div>
            <div><span style="color:#9ca3af;font-size:12px;">VIP到期</span>
                <form method="post" action="index.php?route=admin/users&user_id=<?=$selectedUserId?>" style="display:inline;">
                    <input type="hidden" name="action" value="set_membership_expire">
                    <input type="hidden" name="user_id" value="<?=$selectedUserId?>">
                    <input type="date" name="membership_expire" value="<?= $selectedUser['membership_expire'] ?? '' ?>" onchange="this.form.submit()" style="padding:2px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;width:130px;">
                </form>
            </div>
            <?php if (isAdmin() && $selectedUser['role']==='agent'): ?>
            <div><span style="color:#9ca3af;font-size:12px;">🏢 代理到期</span>
                <form method="post" action="index.php?route=admin/users&user_id=<?=$selectedUserId?>" style="display:inline;">
                    <input type="hidden" name="action" value="set_agent_expire">
                    <input type="hidden" name="user_id" value="<?=$selectedUserId?>">
                    <input type="date" name="agent_expire" value="<?= $selectedUser['agent_expire'] ?? '' ?>" onchange="this.form.submit()" style="padding:2px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;width:130px;">
                </form>
            </div>
            <?php endif; ?>
            <div><span style="color:#9ca3af;font-size:12px;">状态</span>
                <form method="post" action="index.php?route=admin/users&user_id=<?=$selectedUserId?>" style="display:inline;">
                    <input type="hidden" name="action" value="toggle_ban">
                    <input type="hidden" name="user_id" value="<?=$selectedUserId?>">
                    <input type="hidden" name="banned" value="<?= empty($selectedUser['banned']) ? '1' : '0' ?>">
                    <button type="submit" style="border:none;background:none;cursor:pointer;padding:0;font-size:13px;font-weight:600;<?= empty($selectedUser['banned']) ? 'color:#059669' : 'color:#dc2626' ?>">
                        <?= empty($selectedUser['banned']) ? '✅ 正常' : '🚫 已封禁' ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <div style="margin-top:20px;display:flex;gap:10px;">
            <a href="index.php?route=admin/users&user_id=<?=$selectedUserId?>&action=update_index" class="btn" style="padding:8px 16px;background:#4f46e5;color:#fff;border-radius:6px;text-decoration:none;font-size:13px;">🔄 更新收录</a>
            <a href="index.php?route=admin/balance&user_id=<?=$selectedUserId?>" class="btn" style="padding:8px 16px;background:#10b981;color:#fff;border-radius:6px;text-decoration:none;font-size:13px;">💰 余额管理</a>
            <?php if (isAdmin()): ?>
            <a href="index.php?route=admin/impersonate&action=login&user_id=<?=$selectedUserId?>" class="btn" style="padding:8px 16px;background:#f59e0b;color:#fff;border-radius:6px;text-decoration:none;font-size:13px;">👤 模拟登录</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 用户列表 -->
<div class="admin-card">
    <div class="admin-card-accent"></div>
    <div class="admin-card-body" style="padding:0;">
        <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:50px;text-align:center;">ID</th>
                    <th>用户名</th>
                    <th style="text-align:center;">会员</th>
                    <th style="text-align:center;">收录词</th>
                    <th style="text-align:center;">角色</th>
                    <?php if (isAdmin()): ?><th style="text-align:center;">归属代理商</th><?php endif; ?>
                    <th style="text-align:center;">余额</th>
                    <th style="text-align:center;">创建时间</th>
                    <th style="text-align:center;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="8" style="text-align:center;padding:40px 0;color:#9ca3af;">暂无用户</td></tr>
                <?php else: foreach ($users as $u):
                    $agentName = '';
                    if ($u['agent_id']) {
                        $ag = dbFetchOne("SELECT username FROM users WHERE id=?", [$u['agent_id']]);
                        $agentName = $ag ? h($ag['username']) : '#' . $u['agent_id'];
                    }
                ?>
                <tr>
                    <td style="text-align:center;"><?= $u['id'] ?></td>
                    <td>
                        <a href="index.php?route=admin/users&user_id=<?= $u['id'] ?>" style="color:#0F3460;font-weight:500;text-decoration:none;"><?= h($u['username']) ?></a>
                        <?php if ($u['role']==='agent'): ?><span style="font-size:11px;background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:4px;margin-left:6px;">代理</span><?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <span style="font-size:11px;font-weight:600;padding:1px 8px;border-radius:10px;<?= ($u['membership']??'vip')==='vip'?'background:#ede9fe;color:#7c3aed':'background:#fef3c7;color:#d97706'?>"><?= ($u['membership']??'vip')==='vip'?'👑 VIP':'🆓 试用' ?></span>
                        <?php if ($u['role']==='agent' && !empty($u['agent_expire'])): ?>
                        <div style="font-size:10px;color:<?= strtotime($u['agent_expire'])<time()?'#dc2626':'#d97706'?>;margin-top:2px;">🏢 <?=$u['agent_expire']?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <span style="font-weight:600;color:<?= $u['keyword_count']>0?'#059669':'#9ca3af'?>;font-size:13px;"><?= number_format((int)($u['keyword_count']??0)) ?></span>
                    </td>
                    <td style="text-align:center;">
                        <?php if (isAdmin() && $u['id'] != $me['id']): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="change_role">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <select name="role" onchange="this.form.submit()" style="padding:4px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;">
                                <option value="user" <?= $u['role']==='user'?'selected':''?>>用户</option>
                                <option value="agent" <?= $u['role']==='agent'?'selected':''?>>代理商</option>
                            </select>
                        </form>
                        <?php else: ?>
                        <span style="font-size:12px;<?= $u['role']==='agent'?'color:#d97706;':($u['role']==='admin'?'color:#4f46e5;':'')?>"><?= $u['role']==='admin'?'管理员':($u['role']==='agent'?'代理商':'用户') ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if (isAdmin()): ?>
                    <td style="text-align:center;font-size:12px;">
                        <?php if ($u['role']!=='agent'): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="reassign_agent">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <select name="agent_id" onchange="this.form.submit()" style="padding:4px;border:1px solid #d1d5db;border-radius:4px;font-size:11px;max-width:100px;">
                                <option value="">无</option>
                                <?php foreach($agents as $a): ?>
                                <option value="<?=$a['id']?>" <?= $u['agent_id']==$a['id']?'selected':''?>><?=h($a['username'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php else: ?>
                        <span style="color:#9ca3af;">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td style="text-align:center;">¥ <?= number_format($u['balance'] ?? 0, 2) ?></td>
                    <td style="text-align:center;font-size:12px;color:#9ca3af;"><?= date('m-d H:i', strtotime($u['created_at'])) ?></td>
                    <td style="text-align:center;">
                        <a href="index.php?route=admin/users&user_id=<?= $u['id'] ?>" style="font-size:12px;color:#4f46e5;">详情</a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php if (isAdminOrAgent() && !$selectedUser):
$detectPrice = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='detect_price'")['setting_value'] ?? '0.00';
$upgradePrice = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='upgrade_price'")['setting_value'] ?? '0.00';
$savedExcludeDays = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='scan_exclude_days'")['setting_value'] ?? '0';
?>
<div id="priceModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:24px;width:380px;max-width:90vw;">
        <h3 style="margin:0 0 16px 0;font-size:16px;">💰 价格设置</h3>
        <form method="post" action="index.php?route=admin/users">
            <input type="hidden" name="action" value="save_prices">
            <div style="margin-bottom:12px;">
                <label style="font-size:13px;color:#6b7280;">GEO检测单次定价（元）</label>
                <input type="number" name="detect_price" step="0.01" min="0" value="<?=h($detectPrice)?>" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;margin-top:4px;">
                <p style="font-size:11px;color:#9ca3af;margin:4px 0 0;">每次品牌检测消耗的金额，设为0则不扣费</p>
            </div>
            <div style="margin-bottom:16px;">
                <label style="font-size:13px;color:#6b7280;">试用升级正式用户定价（元）</label>
                <input type="number" name="upgrade_price" step="0.01" min="0" value="<?=h($upgradePrice)?>" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;margin-top:4px;">
                <p style="font-size:11px;color:#9ca3af;margin:4px 0 0;">试用账户升级为正式用户的费用</p>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="closePriceModal()" style="padding:8px 16px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;">取消</button>
                <button type="submit" style="padding:8px 16px;background:#f59e0b;color:#fff;border:none;border-radius:6px;cursor:pointer;">保存</button>
            </div>
        </form>
    </div>
</div>
<script>
function openPriceModal() { document.getElementById('priceModal').style.display='flex'; }
function closePriceModal() { document.getElementById('priceModal').style.display='none'; }
document.getElementById('priceModal').addEventListener('click', function(e) { if(e.target===this) closePriceModal(); });
</script>
<?php endif; ?>
