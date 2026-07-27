<?php
$currentRoute = $_GET['route'] ?? 'home';
$user = currentUser();
$navItems = getNavItems();
// Build route lookup from nav items
$routeLookup = [];
foreach ($navItems as $item) { $routeLookup[$item['route']] = $item; }
// Read group config
$groupsSetting = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='sidebar_menu_groups'");
$groupsConfig = $groupsSetting ? json_decode($groupsSetting['setting_value'], true) : null;
?>
<aside class="sidebar">
     <div class="sidebar-header">
         <div class="sidebar-avatar"><?= h(strtoupper(substr($user['username'] ?? 'U', 0, 1))) ?></div>
         <div class="sidebar-user">
             <div class="sidebar-username"><?= h($user['username'] ?? '') ?></div>
             <div class="sidebar-role"><?= h($user['role'] ?? 'user') ?></div>
         </div>
     </div>
    <nav class="sidebar-nav">
<?php if (($user['role'] ?? '') === 'user'): ?>
    <?php if (($user['membership'] ?? 'vip') === 'trial'): ?>
    <a href="index.php?route=upgrade" class="sidebar-link" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;border-radius:8px;margin-bottom:12px;justify-content:center;font-weight:600;gap:6px;">
        <span style="font-size:16px;">👑</span>
        <span>开通VIP</span>
    </a>
    <?php else: ?>
    <a href="index.php?route=upgrade" class="sidebar-link" style="background:linear-gradient(135deg,#059669,#10b981);color:#fff;border-radius:8px;margin-bottom:12px;justify-content:center;font-weight:600;gap:6px;">
        <span style="font-size:16px;">🔄</span>
        <span>续费VIP</span>
    </a>
    <?php endif; ?>
<?php endif; ?>
<?php if ($groupsConfig && is_array($groupsConfig) && count($groupsConfig) > 0): ?>
    <?php foreach ($groupsConfig as $group): ?>
        <?php
        $groupItems = [];
        foreach ($group['items'] as $gi) {
            if (!($gi['visible'] ?? true)) continue;
            $route = $gi['route'];
            if (isset($routeLookup[$route])) {
                $groupItems[] = $routeLookup[$route];
                unset($routeLookup[$route]);
            }
        }
        if (empty($groupItems)) continue;
        $isCollapsed = $group['collapsed'] ?? false;
        ?>
        <div class="sidebar-group" style="margin-bottom:10px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;overflow:hidden;">
            <div class="sidebar-group-header" onclick="var c=this.nextElementSibling;c.style.display=c.style.display==='none'?'':'none';this.querySelector('.sidebar-group-arrow').textContent=c.style.display==='none'?'\u25B6':'\u25BC'" style="display:flex;align-items:center;padding:6px 16px;cursor:pointer;">
                <span class="sidebar-group-arrow" style="font-size:10px;margin-right:6px;color:#9ca3af;"><?= $isCollapsed ? '▶' : '▼' ?></span>
                <span style="font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;"><?= h($group['name']) ?></span>
            </div>
            <div class="sidebar-group-items" style="<?= $isCollapsed ? 'display:none;' : '' ?>padding:0 4px 6px 4px;">
                <?php foreach ($groupItems as $item): ?>
                <a href="index.php?route=<?= $item['route'] ?>" class="sidebar-link <?= $currentRoute === $item['route'] ? 'active' : '' ?>">
                    <span class="sidebar-icon"><?= $item['icon'] ?></span>
                    <span><?= $item['label'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (count($routeLookup) > 0): ?>
        <?php foreach ($routeLookup as $item): ?>
        <a href="index.php?route=<?= $item['route'] ?>" class="sidebar-link <?= $currentRoute === $item['route'] ? 'active' : '' ?>">
            <span class="sidebar-icon"><?= $item['icon'] ?></span>
            <span><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
<?php else: ?>
    <?php foreach ($navItems as $item): ?>
        <a href="index.php?route=<?= $item['route'] ?>" class="sidebar-link <?= $currentRoute === $item['route'] ? 'active' : '' ?>">
            <span class="sidebar-icon"><?= $item['icon'] ?></span>
            <span><?= $item['label'] ?></span>
        </a>
    <?php endforeach; ?>
<?php endif; ?>
    </nav>
     <div class="sidebar-footer">
         <a href="index.php?route=logout" class="sidebar-link">
             <span class="sidebar-icon">🚪</span>
             <span>退出登录</span>
         </a>
     </div>
 </aside>
