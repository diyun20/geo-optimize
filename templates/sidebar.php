<?php
$currentRoute = $_GET['route'] ?? 'home';
$user = currentUser();
$navItems = getNavItems();
?>
<aside class="sidebar">
     <div class="sidebar-header">
         <div class="sidebar-avatar"><?= h(strtoupper(substr($user['username'] ?? 'U', 0, 1))) ?></div>
         <div class="sidebar-user">
             <div class="sidebar-username"><?= h($user['username'] ?? '') ?></div>
              <div class="sidebar-role"><?= $user['role']==='admin'?'管理员':($user['role']==='agent'?'代理商':'用户') ?></div>
         </div>
     </div>
    <nav class="sidebar-nav">
    <?php foreach ($navItems as $item): ?>
        <a href="index.php?route=<?= $item['route'] ?>" class="sidebar-link <?= $currentRoute === $item['route'] ? 'active' : '' ?>">
            <span class="sidebar-icon"><?= $item['icon'] ?></span>
            <span><?= $item['label'] ?></span>
        </a>
    <?php endforeach; ?>
    </nav>
     <div class="sidebar-footer">
         <a href="index.php?route=logout" class="sidebar-link">
             <span class="sidebar-icon">🚪</span>
             <span>退出登录</span>
         </a>
     </div>
 </aside>
