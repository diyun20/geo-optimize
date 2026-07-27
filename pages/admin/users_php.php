 <?php
 requireLogin();
 requireRole('admin');
 require_once __DIR__ . '/../../includes/geo.php';
 $admin = currentUser();
 $pageTitle = '用户管理 - GEO优化';
 
 try { dbExecute("ALTER TABLE users ADD COLUMN `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `role`"); } catch (Exception $e) {}
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
 

// Index update
if (isset($_GET['action']) && $_GET['action'] === 'update_index' && $selectedUserId > 0) {
    geoInitTables();
    dbExecute("INSERT INTO geo_scan_queue (user_id,status,created_at) VALUES (?,'pending',NOW())", [$selectedUserId]);
    setFlash('info', '指数更新已加入队列，后台处理中...');
    redirect('index.php?route=admin/users&user_id=' . $selectedUserId);
}

 if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $action = $_POST['action'] ?? '';
 
     if ($action === 'add') {
         $username = trim($_POST['username'] ?? '');
         $email    = trim($_POST['email'] ?? '');
         $password = $_POST['password'] ?? '';
         $role     = $_POST['role'] ?? 'user';
         if (empty($username) || empty($email) || empty($password)) {
             setFlash('error', '请填写所有必填项');
         } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
             setFlash('error', '邮箱格式不正确');
         } elseif (strlen($password) < 6) {
             setFlash('error', '密码至少 6 位');
         } else {
             $result = register($username, $email, $password);
             if ($result === true) {
                 dbExecute('UPDATE users SET role = ? WHERE username = ?', [$role, $username]);
                 setFlash('success', "用户 {$username} 已创建");
             } else {
                 setFlash('error', $result);
             }
         }
         redirect('index.php?route=admin/users');
     }
 
     if ($action === 'change_role') {
         $targetId = (int)($_POST['user_id'] ?? 0);
         $newRole  = $_POST['new_role'] ?? 'user';
         if ($targetId > 0 && $targetId !== $admin['id']) {
             dbExecute('UPDATE users SET role = ? WHERE id = ?', [$newRole, $targetId]);
             setFlash('success', '用户角色已更新');
         }
         redirect('index.php?route=admin/users');
     }
 
     if ($action === 'delete') {
         $targetId = (int)($_POST['user_id'] ?? 0);
         if ($targetId > 0 && $targetId !== $admin['id']) {
             dbExecute('DELETE FROM users WHERE id = ?', [$targetId]);
             setFlash('success', '用户已删除');
         }
         redirect('index.php?route=admin/users');
     }
 }
 
 $users = dbFetchAll('SELECT id, username, email, role, balance, created_at FROM users ORDER BY created_at DESC');
 
 $selectedUser = null;
 if ($selectedUserId > 0) {
     $selectedUser = dbFetchOne('SELECT * FROM users WHERE id = ?', [$selectedUserId]);
 }
 ?>
-join


