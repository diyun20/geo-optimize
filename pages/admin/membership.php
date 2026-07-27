<?php
requireLogin();
requireRole('admin');
$pageTitle = '会员套餐 - GEO优化';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        foreach (['trial_features','vip_features'] as $fk) {
            if (isset($_POST[$fk])) {
                $val = array_values($_POST[$fk]);
                dbExecute("INSERT INTO site_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?", ["membership_{$fk}", json_encode($val, JSON_UNESCAPED_UNICODE), json_encode($val, JSON_UNESCAPED_UNICODE)]);
            }
        }
        if (isset($_POST['membership_default'])) {
            $d = in_array($_POST['membership_default'], ['trial','vip']) ? $_POST['membership_default'] : 'trial';
            dbExecute("INSERT INTO site_settings (setting_key,setting_value) VALUES ('membership_default',?) ON DUPLICATE KEY UPDATE setting_value=?", [$d, $d]);
        }
        if (isset($_POST['upgrade_price'])) {
            dbExecute("INSERT INTO site_settings (setting_key,setting_value) VALUES ('membership_upgrade_price',?) ON DUPLICATE KEY UPDATE setting_value=?", [max(0,(float)$_POST['upgrade_price']), max(0,(float)$_POST['upgrade_price'])]);
        }
        foreach (['trial_keyword_limit','trial_distill_limit','trial_article_limit'] as $lk) {
            if (isset($_POST[$lk])) {
                $v = max(0,(int)$_POST[$lk]);
                dbExecute("INSERT INTO site_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?", ["membership_{$lk}", $v, $v]);
            }
        }
        setFlash('success', '套餐配置已保存');
    }
    redirect('index.php?route=admin/membership');
}

$cfg = membershipGetConfig();

$features = [
    'dashboard'       => ['icon'=>'📮','label'=>'数据总览'],
    'keywords'        => ['icon'=>'🔳','label'=>'关键词监测','limit'=>'trial_keyword_limit'],
    'keywords-distill'=> ['icon'=>'🔈','label'=>'批量蒸馏','limit'=>'trial_distill_limit'],
    'article-generate'=> ['icon'=>'📑','label'=>'文章生成','limit'=>'trial_article_limit'],
    'article-publish' => ['icon'=>'📛','label'=>'多平台推送'],
    'video-script'    => ['icon'=>'🎀','label'=>'视频脚本'],
    'video-analyze'   => ['icon'=>'📳','label'=>'视频号分析'],
    'company'         => ['icon'=>'🏢','label'=>'企业信息'],
    'password'        => ['icon'=>'🔁','label'=>'个人信息'],
    'tickets'         => ['icon'=>'🎫','label'=>'工单系统'],
];

$trialCount = dbFetchOne("SELECT COUNT(*) as c FROM users WHERE membership='trial' AND role!='admin'")['c'] ?? 0;
$vipCount = dbFetchOne("SELECT COUNT(*) as c FROM users WHERE membership='vip' AND role!='admin'")['c'] ?? 0;
?>

<div class="page-header">
    <h1>会员套餐</h1>
    <p>配置试用和VIP会员的功能权限与价格</p>
</div>

<!-- 统计 -->
<div style="display:flex;gap:20px;margin-bottom:24px;">
    <div style="flex:1;background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.04);text-align:center;">
        <div style="font-size:28px;font-weight:800;color:#374151;"><?= $trialCount + $vipCount ?></div>
        <div style="font-size:12px;color:#9ca3af;">总会员</div>
    </div>
    <div style="flex:1;background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.04);text-align:center;">
        <div style="font-size:28px;font-weight:800;color:#f59e0b;"><?= $trialCount ?></div>
        <div style="font-size:12px;color:#9ca3af;">试用中</div>
    </div>
    <div style="flex:1;background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.04);text-align:center;">
        <div style="font-size:28px;font-weight:800;color:#7c3aed;"><?= $vipCount ?></div>
        <div style="font-size:12px;color:#9ca3af;">VIP</div>
    </div>
</div>

<!-- 双套餐卡片 -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">

    <!-- 试用套餐 -->
    <form method="post" action="index.php?route=admin/membership">
        <input type="hidden" name="action" value="save">
        <div style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.04);border:1px solid #f3f4f6;">
            <div style="background:#fef3c7;padding:24px;text-align:center;">
                <div style="font-size:36px;margin-bottom:8px;">🆓</div>
                <div style="font-size:20px;font-weight:700;color:#92400e;">免费试用</div>
                <div style="font-size:13px;color:#a16207;margin-top:4px;">功能受限 · 永久免费</div>
                <div style="margin-top:16px;display:inline-block;background:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;color:#92400e;">
                    <?= $cfg['default']==='trial' ? '✅ 新用户默认' : '新用户可选' ?>
                </div>
            </div>
            <div style="padding:20px 24px;">
                <?php foreach ($features as $key => $f): ?>
                <div style="display:flex;align-items:center;gap:6px;padding:7px 0;border-bottom:1px solid #fafafa;">
                    <input type="checkbox" name="trial_features[]" value="<?= $key ?>" <?= in_array($key, $cfg['trial_features'])?'checked':'' ?> style="accent-color:#f59e0b;width:16px;height:16px;flex-shrink:0;">
                    <span style="font-size:13px;flex:1;"><?= $f['icon'] ?> <?= $f['label'] ?></span>
                    <?php if (!empty($f['limit'])): ?>
                    <input type="number" name="<?= $f['limit'] ?>" min="0" value="<?= (int)$cfg[$f['limit']] ?>" style="width:55px;padding:3px 5px;border:1px solid #e5e7eb;border-radius:4px;font-size:12px;text-align:center;" title="数量上限">
                    <span style="font-size:10px;color:#d1d5db;">个</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <button type="submit" style="margin-top:16px;width:100%;padding:10px;background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">保存试用配置</button>
            </div>
        </div>
    </form>

    <!-- VIP套餐 -->
    <form method="post" action="index.php?route=admin/membership">
        <input type="hidden" name="action" value="save">
        <div style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.04);border:1px solid #f3f4f6;position:relative;">
            <div style="position:absolute;top:12px;right:-28px;background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:3px 30px;transform:rotate(45deg);">HOT</div>
            <div style="background:linear-gradient(135deg,#5b21b6,#7c3aed);padding:24px;text-align:center;">
                <div style="font-size:36px;margin-bottom:8px;">👑</div>
                <div style="font-size:20px;font-weight:700;color:#fff;">VIP 会员</div>
                <div style="font-size:28px;font-weight:800;color:#fbbf24;margin-top:8px;">
                    ¥<?= number_format((float)$cfg['upgrade_price'], 2) ?><span style="font-size:13px;font-weight:400;color:#c4b5fd;">/月</span>
                </div>
                <div style="font-size:12px;color:#c4b5fd;margin-top:2px;">30天订阅 · 自动续费提醒</div>
                <div style="margin-top:12px;display:inline-block;background:rgba(255,255,255,0.15);padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;color:#e9d5ff;">
                    <?= $cfg['default']==='vip' ? '✅ 新用户默认' : '需升级获取' ?>
                </div>
            </div>
            <div style="padding:20px 24px;">
                <?php foreach ($features as $key => $f): ?>
                <div style="display:flex;align-items:center;gap:6px;padding:7px 0;border-bottom:1px solid #fafafa;">
                    <input type="checkbox" name="vip_features[]" value="<?= $key ?>" <?= in_array($key, $cfg['vip_features'])?'checked':'' ?> style="accent-color:#7c3aed;width:16px;height:16px;flex-shrink:0;">
                    <span style="font-size:13px;flex:1;"><?= $f['icon'] ?> <?= $f['label'] ?></span>
                    <span style="font-size:11px;color:#a78bfa;">无限</span>
                </div>
                <?php endforeach; ?>
                <button type="submit" style="margin-top:16px;width:100%;padding:10px;background:#7c3aed;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">保存VIP配置</button>
            </div>
        </div>
    </form>
</div>

<!-- 全局设置 -->
<form method="post" action="index.php?route=admin/membership">
    <input type="hidden" name="action" value="save">
    <div style="background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <h3 style="font-size:15px;font-weight:600;color:#374151;margin:0 0 16px;">⚙️ 全局设置</h3>
        <div style="display:flex;gap:24px;align-items:end;">
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#6b7280;">新注册默认套餐</label>
                <select name="membership_default" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;margin-top:4px;">
                    <option value="trial" <?= $cfg['default']==='trial'?'selected':'' ?>>🆓 免费试用</option>
                    <option value="vip" <?= $cfg['default']==='vip'?'selected':'' ?>>👑 VIP（需付费）</option>
                </select>
            </div>
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#6b7280;">VIP月费（元）</label>
                <input type="number" name="upgrade_price" step="0.01" min="0" value="<?= h($cfg['upgrade_price']) ?>" placeholder="0.00" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;margin-top:4px;">
            </div>
            <button type="submit" style="padding:10px 28px;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap;">保存设置</button>
        </div>
    </div>
</form>