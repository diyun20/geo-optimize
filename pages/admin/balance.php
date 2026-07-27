 <?php
 requireLogin();
 requireRole('admin');
 $userId = (int)($_GET['user_id'] ?? 0);
 $target = dbFetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
 if (!$target) { setFlash('error', '用户不存在'); redirect('index.php?route=admin/users'); }
 $pageTitle = '余额管理 - ' . h($target['username']);
 
 try { dbExecute("ALTER TABLE users ADD COLUMN `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `role`"); } catch (Exception $e) {}
 try {
     dbExecute("CREATE TABLE IF NOT EXISTS `transactions` (
         `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `user_id` INT UNSIGNED NOT NULL,
         `type` ENUM('recharge','consume','refund') NOT NULL, `amount` DECIMAL(10,2) NOT NULL,
         `description` VARCHAR(255) DEFAULT NULL, `created_at` DATETIME NOT NULL,
         FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 } catch (Exception $e) {}
 
 if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $amount = (float)($_POST['amount'] ?? 0);
     $op     = $_POST['operation'] ?? 'add';
     $desc   = trim($_POST['description'] ?? '');
     if ($amount > 0) {
         $sign = ($op === 'deduct') ? -$amount : $amount;
         $type = ($op === 'deduct') ? 'consume' : 'recharge';
         dbExecute('UPDATE users SET balance = balance + ? WHERE id = ?', [$sign, $userId]);
         dbExecute('INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?,?,?,?,?)',
             [$userId, $type, $amount, $desc, date('Y-m-d H:i:s')]);
         setFlash('success', '余额已调整');
     }
     redirect('index.php?route=admin/balance&user_id=' . $userId);
 }
 
 $history = dbFetchAll("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20", [$userId]);
 ?>
 
 <div class="page-header">
     <h1>💰 余额管理</h1>
     <p><?= h($target['username']) ?> 的账户余额</p>
 </div>
 
 <div class="dashboard-grid">
     <div class="dashboard-card">
         <div style="text-align:center;padding:30px 20px;background:#f0fdf4;border-radius:8px;">
             <div style="font-size:14px;color:#6b7280;">当前余额</div>
             <div style="font-size:36px;font-weight:700;color:#059669;">¥ <?= number_format($target['balance'] ?? 0, 2) ?></div>
         </div>
         <form method="post" style="margin-top:20px;">
             <div style="display:flex;gap:10px;margin-bottom:10px;">
                 <select name="operation" style="flex:0 0 100px;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                     <option value="add">+ 充值</option>
                     <option value="deduct">- 扣除</option>
                 </select>
                 <input type="number" name="amount" step="0.01" min="0.01" required placeholder="金额" style="flex:1;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
             </div>
             <div class="form-group">
                 <input type="text" name="description" placeholder="备注说明（选填）" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-sizing:border-box;">
             </div>
             <button type="submit" class="btn btn-primary btn-block">确认调整</button>
         </form>
         <a href="index.php?route=admin/users&user_id=<?= $userId ?>" style="display:block;text-align:center;margin-top:12px;font-size:13px;color:#9ca3af;">← 返回</a>
     </div>
 
     <div class="dashboard-card">
         <h3>交易记录</h3>
         <?php if ($history): ?>
             <?php foreach ($history as $h): ?>
             <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f3f4f6;">
                 <div>
                     <div style="font-size:13px;font-weight:500;"><?= $h['type'] === 'recharge' ? '充值' : '消耗' ?></div>
                     <div style="font-size:12px;color:#9ca3af;"><?= date('Y-m-d H:i', strtotime($h['created_at'])) ?></div>
                     <?php if ($h['description']): ?>
                     <div style="font-size:12px;color:#6b7280;"><?= h($h['description']) ?></div>
                     <?php endif; ?>
                 </div>
                 <div style="font-size:16px;font-weight:700;color:<?= $h['type'] === 'recharge' ? '#059669' : '#dc2626' ?>;">
                     <?= $h['type'] === 'recharge' ? '+' : '-' ?>¥ <?= number_format($h['amount'], 2) ?>
                 </div>
             </div>
             <?php endforeach; ?>
         <?php else: ?>
             <div style="text-align:center;padding:30px;color:#9ca3af;font-size:14px;">暂无交易记录</div>
         <?php endif; ?>
     </div>
 </div>
