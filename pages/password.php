 <?php
 requireLogin();
 $user = currentUser();
 $pageTitle = '个人信息 - GEO优化';

 // AJAX: 切换公开开关
 if (isset($_GET['action']) && $_GET['action'] === 'toggle_show') {
     $field = $_GET['field'] ?? '';
     $val = (int)($_GET['val'] ?? 1);
     if (in_array($field, ['show_email','show_phone','show_qq','show_wechat'])) {
         dbExecute("UPDATE users SET {$field}=? WHERE id=?", [$val, $user['id']]);
         echo 'ok'; exit;
     }
     echo 'err'; exit;
 }

 // 修改邮箱
 if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
     $newEmail = trim($_POST['email'] ?? '');
     $newQQ = trim($_POST['qq'] ?? '');
     $newWechat = trim($_POST['wechat'] ?? '');
     $newPhone = trim($_POST['phone'] ?? '');
     $showEmail = isset($_POST['show_email']) ? 1 : 0;
     $showPhone = isset($_POST['show_phone']) ? 1 : 0;
     $showQQ = isset($_POST['show_qq']) ? 1 : 0;
     $showWechat = isset($_POST['show_wechat']) ? 1 : 0;
     if (!empty($newEmail) && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
         setFlash('error', '邮箱格式不正确');
     } elseif (!empty($newPhone) && !preg_match('/^1\d{10}$/', $newPhone)) {
         setFlash('error', '手机号格式不正确');
     } else {
         dbExecute('UPDATE users SET email=?, phone=?, qq=?, wechat=?, show_email=?, show_phone=?, show_qq=?, show_wechat=? WHERE id=?', [$newEmail, $newPhone, $newQQ, $newWechat, $showEmail, $showPhone, $showQQ, $showWechat, $user['id']]);
         setFlash('success', '个人信息已更新');
     }
     redirect('index.php?route=password');
 }
 
 // 修改密码
 if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
     $oldPass  = $_POST['old_password'] ?? '';
     $newPass  = $_POST['new_password'] ?? '';
     $newPass2 = $_POST['new_password2'] ?? '';
 
     if (empty($oldPass) || empty($newPass)) {
         setFlash('error', '请填写所有必填项');
     } elseif ($newPass !== $newPass2) {
         setFlash('error', '两次新密码不一致');
     } elseif (strlen($newPass) < 6) {
         setFlash('error', '新密码至少 6 位');
     } else {
         $stored = dbFetchOne('SELECT password FROM users WHERE id = ?', [$user['id']]);
         if ($stored && password_verify($oldPass, $stored['password'])) {
             $hashed = password_hash($newPass, PASSWORD_DEFAULT);
             dbExecute('UPDATE users SET password = ? WHERE id = ?', [$hashed, $user['id']]);
             setFlash('success', '密码已修改');
         } else {
             setFlash('error', '原密码不正确');
         }
     }
     redirect('index.php?route=password');
 }
 ?>
 
 <div class="page-header">
     <h1>👤 个人信息</h1>
     <p>管理你的账户资料与安全设置</p>
 </div>
 
 <div class="dashboard-grid">
     <!-- 账户信息 -->
     <div class="dashboard-card">
         <div class="card-icon">📋</div>
         <h3>账户资料</h3>
            <table class="info-table">
                <tr><td>用户名</td><td><?= h($user['username'] ?? '') ?></td></tr>
                <tr><td>邮箱</td><td><?= h($user['email'] ?? '') ?: '<span style="color:#9ca3af;">未设置</span>' ?> <label style="font-size:11px;cursor:pointer;margin-left:8px;"><input type="checkbox" onchange="toggleShow('show_email',this)" <?= ($user['show_email']??1)?'checked':'' ?>> 公开</label></td></tr>
                <tr><td>手机号</td><td><?= h($user['phone'] ?? '') ?: '<span style="color:#9ca3af;">未设置</span>' ?> <label style="font-size:11px;cursor:pointer;margin-left:8px;"><input type="checkbox" onchange="toggleShow('show_phone',this)" <?= ($user['show_phone']??1)?'checked':'' ?>> 公开</label></td></tr>
                <tr><td>QQ</td><td><?= h($user['qq'] ?? '') ?: '<span style="color:#9ca3af;">未设置</span>' ?> <label style="font-size:11px;cursor:pointer;margin-left:8px;"><input type="checkbox" onchange="toggleShow('show_qq',this)" <?= ($user['show_qq']??1)?'checked':'' ?>> 公开</label></td></tr>
                <tr><td>微信</td><td><?= h($user['wechat'] ?? '') ?: '<span style="color:#9ca3af;">未设置</span>' ?> <label style="font-size:11px;cursor:pointer;margin-left:8px;"><input type="checkbox" onchange="toggleShow('show_wechat',this)" <?= ($user['show_wechat']??1)?'checked':'' ?>> 公开</label></td></tr>
                <tr><td>角色</td><td><?= $user['role'] === 'admin' ? '管理员' : '用户' ?></td></tr>
                <tr><td>注册时间</td><td><?= h($user['created_at'] ?? '') ?></td></tr>
            </table>

            <hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0;">
            <h4 style="margin:0 0 12px;font-size:14px;">编辑资料</h4>
            <form method="post" action="index.php?route=password">
                <input type="hidden" name="action" value="update_profile">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div class="form-group" style="margin-bottom:8px;">
                        <label style="font-size:12px;color:#6b7280;">邮箱</label>
                        <input type="email" name="email" value="<?= h($user['email'] ?? '') ?>" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div class="form-group" style="margin-bottom:8px;">
                        <label style="font-size:12px;color:#6b7280;">手机号</label>
                        <input type="text" name="phone" value="<?= h($user['phone'] ?? '') ?>" maxlength="11" placeholder="11位手机号" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div class="form-group" style="margin-bottom:8px;">
                        <label style="font-size:12px;color:#6b7280;">QQ</label>
                        <input type="text" name="qq" value="<?= h($user['qq'] ?? '') ?>" placeholder="QQ号码" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div class="form-group" style="margin-bottom:8px;">
                        <label style="font-size:12px;color:#6b7280;">微信</label>
                        <input type="text" name="wechat" value="<?= h($user['wechat'] ?? '') ?>" placeholder="微信号" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="padding:8px 16px;font-size:13px;margin-top:4px;">保存资料</button>
            </form>
     </div>
 
     <!-- 修改密码 -->
     <div class="dashboard-card">
         <div class="card-icon">🔒</div>
         <h3>安全设置</h3>
         <form method="post" action="index.php?route=password">
             <input type="hidden" name="action" value="change_password">
             <div class="form-group">
                 <label for="old_password">原密码</label>
                 <input type="password" id="old_password" name="old_password" required>
             </div>
             <hr style="border:none;border-top:1px solid #e5e7eb;margin:12px 0;">
             <div class="form-group">
                 <label for="new_password">新密码</label>
                 <input type="password" id="new_password" name="new_password" required placeholder="至少 6 位">
             </div>
             <div class="form-group">
                 <label for="new_password2">确认新密码</label>
                 <input type="password" id="new_password2" name="new_password2" required>
             </div>
             <button type="submit" class="btn btn-primary btn-block">更新密码</button>
         </form>
     </div>
 </div>
<script>
function toggleShow(field, cb) {
    fetch('index.php?route=password&action=toggle_show&field='+field+'&val='+(cb.checked?1:0))
        .then(r => r.text()).then(t => { if(t!=='ok') cb.checked = !cb.checked; });
}
</script>
