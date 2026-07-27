 <?php
 requireLogin();
 $user = currentUser();
 $pageTitle = '个人信息 - GEO优化';
 ?>
 
 <div class="page-header">
     <h1>👤 个人信息</h1>
     <p>管理你的账户信息</p>
 </div>
 
 <div class="dashboard-grid">
     <div class="dashboard-card">
         <div class="card-icon">📋</div>
         <h3>账户资料</h3>
         <table class="info-table">
             <tr><td>用户名</td><td><?= h($user['username'] ?? '') ?></td></tr>
             <tr><td>邮箱</td><td><?= h($user['email'] ?? '') ?></td></tr>
             <tr><td>角色</td><td><?= h($user['role'] ?? '') ?></td></tr>
             <tr><td>注册时间</td><td><?= h($user['created_at'] ?? '') ?></td></tr>
         </table>
     </div>
 
     <div class="dashboard-card">
         <div class="card-icon">🔒</div>
         <h3>安全设置</h3>
         <form method="post" action="index.php?route=profile" style="margin-top:12px;">
             <div class="form-group">
                 <label for="email">修改邮箱</label>
                 <input type="email" id="email" name="email" value="<?= h($user['email'] ?? '') ?>">
             </div>
             <div class="form-group">
                 <label for="password">新密码</label>
                 <input type="password" id="password" name="password" placeholder="留空不修改">
             </div>
             <div class="form-group">
                 <label for="password2">确认新密码</label>
                 <input type="password" id="password2" name="password2" placeholder="再次输入新密码">
             </div>
             <button type="submit" class="btn btn-primary btn-block">保存修改</button>
         </form>
     </div>
 </div>
 
 <div style="margin-top:16px;">
     <a href="index.php?route=dashboard" class="btn btn-outline">← 返回控制台</a>
 </div>
 <?php
 // 处理表单提交
 if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $newEmail = trim($_POST['email'] ?? '');
     $newPass = $_POST['password'] ?? '';
     $newPass2 = $_POST['password2'] ?? '';
     
     if ($newEmail && $newEmail !== $user['email']) {
         if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
             setFlash('error', '邮箱格式不正确');
         } else {
             dbExecute('UPDATE users SET email = ? WHERE id = ?', [$newEmail, $user['id']]);
             setFlash('success', '邮箱已更新');
         }
     }
     
     if ($newPass) {
         if ($newPass !== $newPass2) {
             setFlash('error', '两次输入的密码不一致');
         } elseif (strlen($newPass) < 6) {
             setFlash('error', '密码至少 6 位');
         } else {
             $hashed = password_hash($newPass, PASSWORD_DEFAULT);
             dbExecute('UPDATE users SET password = ? WHERE id = ?', [$hashed, $user['id']]);
             setFlash('success', '密码已更新');
         }
     }
     
     redirect('index.php?route=profile');
 }
 ?>
