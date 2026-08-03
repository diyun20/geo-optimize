<?php
requireLogin();
requireRole('admin');
require_once __DIR__ . '/../../includes/geo.php';
geoInitTables();
$pageTitle = '网站设置 - GEO优化';

try {
    dbExecute("CREATE TABLE IF NOT EXISTS `site_settings` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`setting_key` VARCHAR(100) NOT NULL UNIQUE,`setting_value` TEXT DEFAULT NULL,`updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

$gs = geoGetAdminSettings();

// Read sidebar settings
$defaultGroups = [
    ['name'=>'数据总览','collapsed'=>false,'items'=>[['route'=>'dashboard','visible'=>true]]],
    ['name'=>'品牌监测','collapsed'=>false,'items'=>[['route'=>'keywords','visible'=>true],['route'=>'keywords-distill','visible'=>true]]],
    ['name'=>'内容生产','collapsed'=>true,'items'=>[['route'=>'article-generate','visible'=>true],['route'=>'article-publish','visible'=>true],['route'=>'video-script','visible'=>true],['route'=>'video-analyze','visible'=>true]]],
    ['name'=>'账号','collapsed'=>false,'items'=>[['route'=>'password','visible'=>true]]],
    ['name'=>'企业设置','collapsed'=>false,'items'=>[['route'=>'company','visible'=>true]]],
    ['name'=>'支持','collapsed'=>false,'items'=>[['route'=>'tickets','visible'=>true],['route'=>'referral','visible'=>true]]],
];
$savedGroups = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='sidebar_menu_groups'")['setting_value'] ?? '';
$sidebarGroups = $savedGroups ? json_decode($savedGroups, true) : $defaultGroups;
$itemLabels = [
    'dashboard'=>['icon'=>'📮','label'=>'数据总览'],'keywords'=>['icon'=>'🔳','label'=>'关键词监测'],
    'keywords-distill'=>['icon'=>'🔈','label'=>'关键词批量蒸馏'],'article-generate'=>['icon'=>'📑','label'=>'文章一键生成'],
    'article-publish'=>['icon'=>'📛','label'=>'文章多平台推送'],'video-script'=>['icon'=>'🎀','label'=>'视频脚本一键生成'],
    'video-analyze'=>['icon'=>'📳','label'=>'视频号分析'],'password'=>['icon'=>'🔁','label'=>'个人信息'],
    'company'=>['icon'=>'🏢','label'=>'企业信息'],'tickets'=>['icon'=>'🎫','label'=>'工单系统'],
    'referral'=>['icon'=>'🎁','label'=>'拉新返利'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_site') {
        $siteName = trim($_POST['site_name'] ?? 'GEO优化');
        $titleSuffix = trim($_POST['title_suffix'] ?? '');
        $metaKeywords = trim($_POST['meta_keywords'] ?? '');
        $metaDescription = trim($_POST['meta_description'] ?? '');
        dbExecute("INSERT INTO site_settings (setting_key,setting_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=?,updated_at=NOW()", ['site_name', $siteName, $siteName]);
        dbExecute("INSERT INTO site_settings (setting_key,setting_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=?,updated_at=NOW()", ['title_suffix', $titleSuffix, $titleSuffix]);
        dbExecute("INSERT INTO site_settings (setting_key,setting_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=?,updated_at=NOW()", ['meta_keywords', $metaKeywords, $metaKeywords]);
        dbExecute("INSERT INTO site_settings (setting_key,setting_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=?,updated_at=NOW()", ['meta_description', $metaDescription, $metaDescription]);
        dbExecute("INSERT INTO site_settings (setting_key,setting_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=?,updated_at=NOW()", ['contact_qq', trim($_POST['contact_qq']??''), trim($_POST['contact_qq']??'')]);
        dbExecute("INSERT INTO site_settings (setting_key,setting_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=?,updated_at=NOW()", ['contact_wechat', trim($_POST['contact_wechat']??''), trim($_POST['contact_wechat']??'')]);
        dbExecute("INSERT INTO site_settings (setting_key,setting_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=?,updated_at=NOW()", ['contact_email', trim($_POST['contact_email']??''), trim($_POST['contact_email']??'')]);
        dbExecute("INSERT INTO site_settings (setting_key,setting_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=?,updated_at=NOW()", ['captcha_enabled', ($_POST['captcha_enabled']??'0')==='1'?'1':'0', ($_POST['captcha_enabled']??'0')==='1'?'1':'0']);
        setFlash('success', '网站设置已保存');
    }

    if ($action === 'save_sidebar') {
        $raw = $_POST['sidebar_data'] ?? '';
        $groups = json_decode($raw, true);
        if (!is_array($groups)) $groups = [];
        dbExecute("DELETE FROM site_settings WHERE setting_key IN ('sidebar_menu_config','sidebar_visible','sidebar_order')");
        dbExecute("INSERT INTO site_settings (setting_key,setting_value,updated_at) VALUES ('sidebar_menu_groups',?,NOW()) ON DUPLICATE KEY UPDATE setting_value=?,updated_at=NOW()", [json_encode($groups, JSON_UNESCAPED_UNICODE), json_encode($groups, JSON_UNESCAPED_UNICODE)]);
        setFlash('success', '侧边栏设置已保存');
        redirect('index.php?route=admin/settings');
    }

    // 同步最新菜单到已保存的分组
    if ($action === 'sync_sidebar') {
        $navItems = getNavItems();
        $allRoutes = array_column($navItems, 'route');
        $existingRoutes = [];
        foreach ($sidebarGroups as $g) {
            foreach (($g['items'] ?? []) as $it) {
                $existingRoutes[] = $it['route'];
            }
        }
        $missing = array_diff($allRoutes, $existingRoutes);
        if (!empty($missing)) {
            // 添加到"支持"分组末尾，没有则创建
            $found = false;
            foreach ($sidebarGroups as &$g) {
                if ($g['name'] === '支持') {
                    foreach ($missing as $r) $g['items'][] = ['route'=>$r, 'visible'=>true];
                    $found = true; break;
                }
            }
            if (!$found) {
                $newItems = [];
                foreach ($missing as $r) $newItems[] = ['route'=>$r, 'visible'=>true];
                $sidebarGroups[] = ['name'=>'其他','collapsed'=>false,'items'=>$newItems];
            }
            dbExecute("INSERT INTO site_settings (setting_key,setting_value,updated_at) VALUES ('sidebar_menu_groups',?,NOW()) ON DUPLICATE KEY UPDATE setting_value=?,updated_at=NOW()", [json_encode($sidebarGroups, JSON_UNESCAPED_UNICODE), json_encode($sidebarGroups, JSON_UNESCAPED_UNICODE)]);
            setFlash('success', '已同步 '.count($missing).' 个新菜单项');
        } else {
            setFlash('info', '菜单已是最新，无需同步');
        }
        redirect('index.php?route=admin/settings');
    }
    if ($_POST['action'] === 'save_sms') {
        dbExecute("INSERT INTO site_settings (setting_key,setting_value) VALUES ('smsbao_user',?) ON DUPLICATE KEY UPDATE setting_value=?", [trim($_POST['smsbao_user']??''), trim($_POST['smsbao_user']??'')]);
        dbExecute("INSERT INTO site_settings (setting_key,setting_value) VALUES ('smsbao_pass',?) ON DUPLICATE KEY UPDATE setting_value=?", [trim($_POST['smsbao_pass']??''), trim($_POST['smsbao_pass']??'')]);
        dbExecute("INSERT INTO site_settings (setting_key,setting_value) VALUES ('smsbao_enabled',?) ON DUPLICATE KEY UPDATE setting_value=?", [isset($_POST['smsbao_enabled'])?'1':'0', isset($_POST['smsbao_enabled'])?'1':'0']);
        setFlash('success', '短信配置已保存');
    }
    if ($_POST['action'] === 'save_mail') {
        foreach (['smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from_name'] as $k) {
            dbExecute("INSERT INTO site_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?", [$k, trim($_POST[$k]??''), trim($_POST[$k]??'')]);
        }
        dbExecute("INSERT INTO site_settings (setting_key,setting_value) VALUES ('smtp_enabled',?) ON DUPLICATE KEY UPDATE setting_value=?", [isset($_POST['smtp_enabled'])?'1':'0', isset($_POST['smtp_enabled'])?'1':'0']);
        setFlash('success', '邮箱配置已保存');
    }

    redirect('index.php?route=admin/settings');
}

$siteName = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='site_name'")['setting_value'] ?? 'GEO优化';
$titleSuffix = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='title_suffix'")['setting_value'] ?? '';
$metaKeywords = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='meta_keywords'")['setting_value'] ?? '';
$metaDescription = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='meta_description'")['setting_value'] ?? '';
$captchaEnabled = (dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='captcha_enabled'")['setting_value'] ?? '0') === '1';
?>

<div class="page-header">
    <h1>网站设置</h1>
    <p>基本配置与侧边栏管理</p>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <h3>基本设置</h3>
        <form method="post" action="index.php?route=admin/settings">
            <input type="hidden" name="action" value="save_site">
            <div class="form-group">
                <label>网站名称</label>
                <input type="text" name="site_name" value="<?= h($siteName) ?>" placeholder="GEO优化" style="max-width:400px;">
            </div>
            <div class="form-group">
                <label>标题后缀 <small style="color:#9ca3af;">（显示在页面标题末尾）</small></label>
                <input type="text" name="title_suffix" value="<?= h($titleSuffix) ?>" placeholder="例如：- AI品牌优化平台" style="max-width:400px;">
            </div>
            <div class="form-group">
                <label>网站关键词 <small style="color:#9ca3af;">（SEO meta keywords，逗号分隔）</small></label>
                <input type="text" name="meta_keywords" value="<?= h($metaKeywords) ?>" placeholder="例如：GEO优化,AI搜索,品牌监测,内容创作" style="max-width:600px;">
            </div>
            <div class="form-group">
                <label>网站描述 <small style="color:#9ca3af;">（SEO meta description）</small></label>
                <textarea name="meta_description" rows="3" placeholder="例如：GEO优化平台，帮助品牌在豆包、DeepSeek等AI搜索中获得更好曝光" style="max-width:600px;"><?= h($metaDescription) ?></textarea>
            </div>
            <h4 style="margin:20px 0 10px;font-size:14px;color:#374151;">📞 联系信息</h4>
            <p style="font-size:12px;color:#9ca3af;margin-bottom:10px;">首页底部联系信息自动读取管理员账户资料，请前往 <a href="index.php?route=password" style="color:#4f46e5;">个人信息</a> 页面修改。</p>
            <div class="form-group" style="display:flex;align-items:center;gap:10px;">
                <label style="margin:0;cursor:pointer;">🔐 登录注册验证码</label>
                <input type="hidden" name="captcha_enabled" value="0">
                <input type="checkbox" name="captcha_enabled" value="1" <?= ($captchaEnabled?'checked':'') ?> style="width:18px;height:18px;cursor:pointer;">
                <small style="color:#9ca3af;">开启后登录和注册页面需要输入图形验证码</small>
            </div>
            <button type="submit" class="btn btn-primary">保存设置</button>
        </form>
    </div>

    <div class="dashboard-card">
        <h3>📱 短信配置（短信宝）</h3>
        <form method="post" action="index.php?route=admin/settings">
            <input type="hidden" name="action" value="save_sms">
            <div class="form-group">
                <label>短信宝用户名</label>
                <input type="text" name="smsbao_user" value="<?= h(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smsbao_user'")['setting_value'] ?? '') ?>" placeholder="短信宝平台注册的用户名" style="max-width:300px;">
            </div>
            <div class="form-group">
                <label>短信宝密码 <small style="color:#9ca3af;">（明文存储，MD5后发送）</small></label>
                <input type="text" name="smsbao_pass" value="<?= h(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smsbao_pass'")['setting_value'] ?? '') ?>" placeholder="短信宝平台登录密码" style="max-width:300px;">
            </div>
            <button type="submit" class="btn btn-primary">保存配置</button>
            <label style="display:flex;align-items:center;gap:6px;margin-top:12px;cursor:pointer;font-size:13px;">
                <input type="checkbox" name="smsbao_enabled" <?= (dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smsbao_enabled'")['setting_value']??'0')==='1'?'checked':'' ?>>
                启用短信找回密码
            </label>
        </form>
    </div>

    <div class="dashboard-card">
        <h3>📧 邮箱配置（SMTP）</h3>
        <form method="post" action="index.php?route=admin/settings">
            <input type="hidden" name="action" value="save_mail">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group">
                    <label>SMTP服务器</label>
                    <input type="text" name="smtp_host" value="<?= h(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smtp_host'")['setting_value'] ?? '') ?>" placeholder="smtp.qq.com">
                </div>
                <div class="form-group">
                    <label>端口</label>
                    <input type="text" name="smtp_port" value="<?= h(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smtp_port'")['setting_value'] ?? '465') ?>" placeholder="465">
                </div>
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="smtp_user" value="<?= h(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smtp_user'")['setting_value'] ?? '') ?>" placeholder="xxx@qq.com">
                </div>
                <div class="form-group">
                    <label>密码（授权码）</label>
                    <input type="text" name="smtp_pass" value="<?= h(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smtp_pass'")['setting_value'] ?? '') ?>" placeholder="QQ邮箱需使用授权码">
                </div>
                <div class="form-group">
                    <label>发件人名称</label>
                    <input type="text" name="smtp_from_name" value="<?= h(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smtp_from_name'")['setting_value'] ?? 'GEO优化') ?>" placeholder="GEO优化">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:12px;">保存配置</button>
            <label style="display:flex;align-items:center;gap:6px;margin-top:8px;cursor:pointer;font-size:13px;">
                <input type="checkbox" name="smtp_enabled" <?= (dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smtp_enabled'")['setting_value']??'0')==='1'?'checked':'' ?>>
                启用邮箱找回密码
            </label>
        </form>
    </div>
</div>

<div class="dashboard-card" style="margin-top:20px;">
    <h3>用户端侧边栏设置</h3>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <p style="font-size:13px;color:#6b7280;margin:0;">点击菜单项选中，再点击分组放入。↑↓ 调整分组顺序，开关控制可见性。</p>
        <form method="post" action="index.php?route=admin/settings" style="margin:0;" onsubmit="return confirm('同步将把新增菜单自动加入「支持」分组，确定？')">
            <input type="hidden" name="action" value="sync_sidebar">
            <button type="submit" class="btn" style="padding:6px 14px;font-size:12px;background:#fff;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;color:#6b7280;">🔄 同步最新菜单</button>
        </form>
    </div>
    <form method="post" action="index.php?route=admin/settings" id="sidebarForm" onsubmit="prepareSubmit()">
        <input type="hidden" name="action" value="save_sidebar">
        <input type="hidden" name="sidebar_data" id="sidebarData" value="">

        <!-- 未分组菜单池 -->
        <div style="margin-bottom:20px;">
            <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:8px;">📦 未分组菜单 <span style="font-weight:400;color:#9ca3af;">（点击选中，再点分组放入）</span></div>
            <div id="itemPool" class="item-pool"
                 style="min-height:48px;border:2px dashed #e5e7eb;border-radius:10px;padding:8px 12px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;background:#fafafa;transition:all 0.2s;">
                <span id="poolEmpty" style="font-size:12px;color:#9ca3af;display:none;">所有菜单已分组</span>
            </div>
        </div>

        <!-- 分组列表 -->
        <div id="groupList" style="display:flex;flex-direction:column;gap:12px;">
            <?php
            $groupedRoutes = [];
            foreach ($sidebarGroups as $group) {
                foreach ($group['items'] as $item) {
                    $groupedRoutes[] = $item['route'];
                }
            }
            foreach ($sidebarGroups as $gi => $group):
                $gid = 'group-' . $gi;
            ?>
            <div class="sidebar-group-card" data-group-id="<?= $gid ?>"
                 style="border:1px solid #e5e7eb;border-radius:10px;background:#fff;overflow:hidden;transition:box-shadow 0.2s;">
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f9fafb;border-bottom:1px solid #f0f0f0;">
                    <span class="drag-handle" style="color:#c4c4c4;cursor:grab;font-size:18px;user-select:none;" title="拖拽排序分组">⋮⋮</span>
                    <span style="font-size:13px;font-weight:600;color:#6b7280;flex-shrink:0;">分组</span>
                    <input type="text" class="group-name-input" value="<?= h($group['name']) ?>" placeholder="分组名称"
                           style="flex:1;min-width:80px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;">
                    <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#6b7280;cursor:pointer;white-space:nowrap;">
                        <input type="checkbox" class="group-collapsed-check" <?= ($group['collapsed']??false)?'checked':'' ?>> 折叠
                    </label>
                    <div style="display:flex;gap:3px;flex-shrink:0;">
                        <button type="button" onclick="moveGroupCard(this,-1)" title="上移" style="border:1px solid #d1d5db;background:#fff;border-radius:5px;width:28px;height:28px;cursor:pointer;font-size:13px;color:#6b7280;">↑</button>
                        <button type="button" onclick="moveGroupCard(this,1)" title="下移" style="border:1px solid #d1d5db;background:#fff;border-radius:5px;width:28px;height:28px;cursor:pointer;font-size:13px;color:#6b7280;">↓</button>
                        <button type="button" onclick="deleteGroup(this)" title="删除分组" style="border:1px solid #fca5a5;background:#fef2f2;border-radius:5px;width:28px;height:28px;cursor:pointer;font-size:13px;color:#dc2626;">✕</button>
                    </div>
                </div>
                <div class="group-items-dropzone" data-group-id="<?= $gid ?>"
                     style="min-height:44px;padding:8px 12px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;transition:background 0.2s;">
                    <?php foreach ($group['items'] as $ii => $item):
                        $r = $item['route'];
                        $info = $itemLabels[$r] ?? ['icon'=>'?','label'=>$r];
                        $visible = $item['visible'] ?? true;
                    ?>
                    <div class="menu-chip" data-route="<?= h($r) ?>" data-visible="<?= $visible?'1':'0' ?>"
                         style="display:flex;align-items:center;gap:6px;padding:5px 8px 5px 6px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;user-select:none;transition:all 0.15s;box-shadow:0 1px 2px rgba(0,0,0,0.04);">
                        <span style="color:#c4c4c4;font-size:12px;">⋮</span>
                        <span style="font-size:14px;"><?= $info['icon'] ?></span>
                        <span style="font-weight:500;color:#374151;"><?= $info['label'] ?></span>
                        <label class="chip-toggle" style="position:relative;display:inline-block;width:30px;height:16px;cursor:pointer;flex-shrink:0;margin-left:2px;" onclick="event.stopPropagation();">
                            <input type="checkbox" <?= $visible?'checked':'' ?> onchange="updateChipToggle(this)" style="opacity:0;width:0;height:0;position:absolute;">
                            <span style="position:absolute;top:0;left:0;right:0;bottom:0;background:<?= $visible?'#22c55e':'#d1d5db' ?>;border-radius:8px;transition:0.25s;"></span>
                            <span style="position:absolute;top:1.5px;left:<?= $visible?'15px':'1.5px' ?>;width:13px;height:13px;background:#fff;border-radius:50%;transition:0.25s;box-shadow:0 1px 2px rgba(0,0,0,0.2);"></span>
                        </label>
                        <button type="button" onclick="removeChip(this)" title="移出分组" style="border:none;background:none;color:#9ca3af;cursor:pointer;font-size:14px;padding:0 2px;line-height:1;">✕</button>
                    </div>
                    <?php endforeach; ?>
                    <span class="zone-placeholder" style="font-size:11px;color:#c4c4c4;<?= !empty($group['items'])?'display:none;':'' ?>">点击上方菜单添加到此</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- 池中未分组项（隐藏数据源） -->
        <div id="poolItems" style="display:none;">
            <?php
            foreach ($itemLabels as $route => $info) {
                if (!in_array($route, $groupedRoutes)) {
                    echo '<span data-route="' . h($route) . '" data-icon="' . $info['icon'] . '" data-label="' . $info['label'] . '"></span>';
                }
            }
            ?>
        </div>

        <button type="button" onclick="addGroup()" style="display:block;width:100%;padding:10px 0;border:1px dashed #d1d5db;background:#fff;border-radius:8px;cursor:pointer;font-size:14px;color:#6b7280;margin:16px 0 12px;text-align:center;transition:all 0.2s;">+ 添加分组</button>
        <button type="submit" class="btn btn-primary">保存侧边栏设置</button>
    </form>
</div>

<style>
.menu-chip.selected { border-color: #6366f1 !important; box-shadow: 0 0 0 2px rgba(99,102,241,0.25), 0 2px 8px rgba(99,102,241,0.12); background: #eef2ff !important; }
.group-items-dropzone.target { background: #eef2ff; border-radius: 6px; }
.menu-chip:hover { border-color: #a5b4fc; box-shadow: 0 2px 6px rgba(0,0,0,0.08); cursor: pointer; }
.item-pool .menu-chip { border-color: #e5e7eb; background: #fff; }
</style>

<script>
// === 点击选择/移动菜单项 ===
let selectedChip = null;

document.addEventListener('click', function(e) {
    const chip = e.target.closest('.menu-chip');
    const zone = e.target.closest('.group-items-dropzone');
    const pool = document.getElementById('itemPool');

    // 点击了 toggle 或 ✕ 按钮，不处理
    if (e.target.closest('.chip-toggle') || e.target.closest('button')) return;

    if (chip && zone) {
        // 点击了分组内的 chip → 取消选中或移回池
        if (chip === selectedChip) {
            deselectChip();
            return;
        }
        if (selectedChip) {
            // 有选中的 chip，把它移到这里
            zone.insertBefore(selectedChip, chip);
            deselectChip();
            refreshAll();
            return;
        }
        // 选中这个 chip（分组内的可以移回池或移到其他组）
        selectChip(chip);
        return;
    }

    if (chip && chip.parentElement === pool) {
        // 点击池中的 chip
        if (chip === selectedChip) { deselectChip(); return; }
        selectChip(chip);
        return;
    }

    if (zone && selectedChip) {
        // 点击了空白区域 → 把选中的放这里
        zone.appendChild(selectedChip);
        deselectChip();
        refreshAll();
        return;
    }

    if (pool && e.target === pool && selectedChip) {
        // 点击池空白 → 如果选中的在分组中，移回池
        pool.appendChild(selectedChip);
        deselectChip();
        refreshAll();
        return;
    }

    // 点击其他地方取消选中
    if (selectedChip && !chip && !zone) {
        deselectChip();
    }
});

function selectChip(chip) {
    deselectChip();
    selectedChip = chip;
    chip.classList.add('selected');
    // 高亮所有可放置区域
    document.querySelectorAll('.group-items-dropzone').forEach(z => z.classList.add('target'));
    document.getElementById('itemPool').classList.add('target');
}

function deselectChip() {
    if (selectedChip) selectedChip.classList.remove('selected');
    selectedChip = null;
    document.querySelectorAll('.group-items-dropzone.target').forEach(z => z.classList.remove('target'));
    var p = document.getElementById('itemPool');
    if (p) p.classList.remove('target');
}

// === 移除单个菜单项 ===
function removeChip(btn) {
    const chip = btn.closest('.menu-chip');
    if (chip === selectedChip) deselectChip();
    document.getElementById('itemPool').appendChild(chip);
    refreshAll();
}

// === 切换可见性 ===
function updateChipToggle(cb) {
    const chip = cb.closest('.menu-chip');
    chip.dataset.visible = cb.checked ? '1' : '0';
    const track = cb.nextElementSibling;
    const knob = track.nextElementSibling;
    track.style.background = cb.checked ? '#22c55e' : '#d1d5db';
    knob.style.left = cb.checked ? '15px' : '1.5px';
}

// === 刷新所有状态 ===
function refreshAll() {
    refreshZonePlaceholders();
    refreshPool();
}

function refreshZonePlaceholders() {
    document.querySelectorAll('.group-items-dropzone').forEach(zone => {
        const ph = zone.querySelector('.zone-placeholder');
        const chips = zone.querySelectorAll('.menu-chip');
        if (ph) ph.style.display = chips.length === 0 ? '' : 'none';
    });
}

function refreshPool() {
    const pool = document.getElementById('itemPool');
    const chips = pool.querySelectorAll('.menu-chip');
    const empty = document.getElementById('poolEmpty');
    if (empty) empty.style.display = chips.length === 0 ? '' : 'none';
}

// === 分组排序 ===
function moveGroupCard(btn, dir) {
    const card = btn.closest('.sidebar-group-card');
    const list = document.getElementById('groupList');
    const cards = [...list.querySelectorAll('.sidebar-group-card')];
    const idx = cards.indexOf(card);
    const target = cards[idx + dir];
    if (!target) return;
    if (dir < 0) list.insertBefore(card, target);
    else list.insertBefore(card, target.nextElementSibling);
}

function deleteGroup(btn) {
    if (!confirm('确认删除此分组？分组内的菜单项将回到未分组池。')) return;
    const card = btn.closest('.sidebar-group-card');
    if (card.contains(selectedChip)) deselectChip();
    const chips = card.querySelectorAll('.menu-chip');
    const pool = document.getElementById('itemPool');
    chips.forEach(c => pool.appendChild(c));
    card.remove();
    refreshAll();
}

// === 添加分组 ===
function addGroup() {
    const list = document.getElementById('groupList');
    const idx = list.querySelectorAll('.sidebar-group-card').length;
    const gid = 'group-' + idx;
    const div = document.createElement('div');
    div.className = 'sidebar-group-card';
    div.setAttribute('data-group-id', gid);
    div.style.cssText = 'border:1px solid #e5e7eb;border-radius:10px;background:#fff;overflow:hidden;';
    div.innerHTML = '<div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f9fafb;border-bottom:1px solid #f0f0f0;">'
        + '<span style="color:#c4c4c4;font-size:18px;user-select:none;">⋮⋮</span>'
        + '<span style="font-size:13px;font-weight:600;color:#6b7280;flex-shrink:0;">分组</span>'
        + '<input type="text" class="group-name-input" value="" placeholder="分组名称" style="flex:1;min-width:80px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;">'
        + '<label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#6b7280;cursor:pointer;white-space:nowrap;">'
        + '<input type="checkbox" class="group-collapsed-check"> 折叠</label>'
        + '<div style="display:flex;gap:3px;flex-shrink:0;">'
        + '<button type="button" onclick="moveGroupCard(this,-1)" title="上移" style="border:1px solid #d1d5db;background:#fff;border-radius:5px;width:28px;height:28px;cursor:pointer;font-size:13px;color:#6b7280;">↑</button>'
        + '<button type="button" onclick="moveGroupCard(this,1)" title="下移" style="border:1px solid #d1d5db;background:#fff;border-radius:5px;width:28px;height:28px;cursor:pointer;font-size:13px;color:#6b7280;">↓</button>'
        + '<button type="button" onclick="deleteGroup(this)" title="删除分组" style="border:1px solid #fca5a5;background:#fef2f2;border-radius:5px;width:28px;height:28px;cursor:pointer;font-size:13px;color:#dc2626;">✕</button>'
        + '</div></div>'
        + '<div class="group-items-dropzone" data-group-id="' + gid + '"'
        + ' style="min-height:44px;padding:8px 12px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;">'
        + '<span class="zone-placeholder" style="font-size:11px;color:#c4c4c4;">点击上方池中菜单添加到此</span>'
        + '</div>';
    list.appendChild(div);
}

// === 提交流程 ===
function prepareSubmit() {
    const data = [];
    const cards = document.querySelectorAll('#groupList .sidebar-group-card');
    cards.forEach(card => {
        const name = card.querySelector('.group-name-input').value.trim();
        if (!name) return;
        const collapsed = card.querySelector('.group-collapsed-check').checked;
        const items = [];
        card.querySelectorAll('.menu-chip').forEach(chip => {
            items.push({
                route: chip.dataset.route,
                visible: chip.dataset.visible === '1'
            });
        });
        data.push({ name, collapsed, items });
    });
    document.getElementById('sidebarData').value = JSON.stringify(data);
}

// === 初始化池 ===
(function initPool() {
    const pool = document.getElementById('itemPool');
    const src = document.getElementById('poolItems');
    if (!src) return;
    const spans = src.querySelectorAll('span[data-route]');
    spans.forEach(sp => {
        const chip = document.createElement('div');
        chip.className = 'menu-chip';
        chip.dataset.route = sp.dataset.route;
        chip.dataset.visible = '1';
        chip.style.cssText = 'display:flex;align-items:center;gap:6px;padding:5px 8px 5px 6px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;user-select:none;transition:all 0.15s;box-shadow:0 1px 2px rgba(0,0,0,0.04);';
        chip.innerHTML = '<span style="color:#c4c4c4;font-size:12px;">⋮</span>'
            + '<span style="font-size:14px;">' + sp.dataset.icon + '</span>'
            + '<span style="font-weight:500;color:#374151;">' + sp.dataset.label + '</span>'
            + '<label class="chip-toggle" style="position:relative;display:inline-block;width:30px;height:16px;cursor:pointer;flex-shrink:0;margin-left:2px;" onclick="event.stopPropagation();">'
            + '<input type="checkbox" checked onchange="updateChipToggle(this)" style="opacity:0;width:0;height:0;position:absolute;">'
            + '<span style="position:absolute;top:0;left:0;right:0;bottom:0;background:#22c55e;border-radius:8px;transition:0.25s;"></span>'
            + '<span style="position:absolute;top:1.5px;left:15px;width:13px;height:13px;background:#fff;border-radius:50%;transition:0.25s;box-shadow:0 1px 2px rgba(0,0,0,0.2);"></span>'
            + '</label>'
            + '<button type="button" onclick="removeChip(this)" title="移出分组" style="border:none;background:none;color:#9ca3af;cursor:pointer;font-size:14px;padding:0 2px;line-height:1;">✕</button>';
        pool.appendChild(chip);
    });
    refreshPool();
})();
</script>