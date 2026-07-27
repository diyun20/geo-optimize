<?php
requireLogin();
require_once __DIR__ . '/../includes/auth.php';
$user = currentUser();
$pageTitle = '拉新返利 - GEO优化';

// 确保有邀请码
$myCode = ensureReferralCode($user['id']);
$refLink = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'geo.diyunuu.cn') . '/index.php?route=register&ref=' . $myCode;

// 统计
$totalInvited = dbFetchOne("SELECT COUNT(*) as c FROM referral_rewards WHERE referrer_id=?", [$user['id']])['c'] ?? 0;
$totalDays = dbFetchOne("SELECT COALESCE(SUM(reward_days),0) as d FROM referral_rewards WHERE referrer_id=?", [$user['id']])['d'] ?? 0;
$recentRewards = dbFetchAll("SELECT r.*, u.username FROM referral_rewards r JOIN users u ON u.id=r.new_user_id WHERE r.referrer_id=? ORDER BY r.id DESC LIMIT 10", [$user['id']]);

// VIP 到期日
$expireInfo = '';
if ($user['membership'] === 'vip' && $user['membership_expire']) {
    $daysLeft = max(0, ceil((strtotime($user['membership_expire']) - time()) / 86400));
    $expireInfo = "{$user['membership_expire']}<br><span style='font-size:11px;color:#6b7280;'>剩余 {$daysLeft} 天</span>";
} elseif ($user['membership'] === 'vip') {
    $expireInfo = '永久VIP';
} else {
    $expireInfo = '试用用户';
}
?>

<div style="max-width:860px;margin:0 auto;">

<!-- 头部 -->
<div style="text-align:center;padding:30px 0 20px;">
    <div style="font-size:48px;margin-bottom:8px;">🎁</div>
    <h1 style="font-size:26px;font-weight:800;color:#0F3460;margin:0 0 6px;">拉新返利</h1>
    <p style="font-size:14px;color:#6b7280;margin:0;"><?= isAgent() ? '邀请用户注册，自动归入名下并获VIP奖励' : '邀请好友注册，获VIP奖励' ?></p>
</div>

<!-- 规则卡片 -->
<?php if (isAgent()): ?>
<div style="background:linear-gradient(135deg,#fef3c7,#fef9c3);border-radius:12px;padding:24px;margin-bottom:24px;display:flex;gap:16px;align-items:center;">
    <div style="font-size:40px;">🏢</div>
    <div>
        <div style="font-size:16px;font-weight:700;color:#d97706;margin-bottom:6px;">代理商专属权益</div>
        <div style="font-size:14px;color:#6b7280;line-height:1.7;">
            通过你的链接注册的<span style="color:#d97706;font-weight:600;">每一位用户</span>将<span style="font-weight:600;">自动归入你名下</span>，同时你获得 <span style="font-size:18px;font-weight:800;color:#d97706;">+7天</span> VIP时长。
        </div>
    </div>
</div>
<?php else: ?>
<div style="background:linear-gradient(135deg,#ede9fe,#faf5ff);border-radius:12px;padding:24px;margin-bottom:24px;display:flex;gap:16px;align-items:center;">
    <div style="font-size:40px;">🎉</div>
    <div>
        <div style="font-size:16px;font-weight:700;color:#7c3aed;margin-bottom:6px;">邀请规则</div>
        <div style="font-size:14px;color:#6b7280;line-height:1.7;">
            通过你的专属链接注册的<span style="color:#7c3aed;font-weight:600;">每一位新用户</span>，你将获得 <span style="font-size:18px;font-weight:800;color:#7c3aed;">+7天</span> VIP会员时长。
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 专属链接 -->
<div style="background:#fff;border:2px dashed #7c3aed;border-radius:12px;padding:24px;margin-bottom:24px;text-align:center;">
    <div style="font-size:14px;color:#6b7280;margin-bottom:10px;">🔗 你的专属邀请链接</div>
    <div style="display:flex;gap:8px;align-items:center;max-width:600px;margin:0 auto 12px;">
        <input type="text" id="refLink" value="<?= h($refLink) ?>" readonly style="flex:1;padding:12px 16px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;background:#f9fafb;color:#374151;box-sizing:border-box;">
        <button onclick="copyRef()" style="padding:12px 20px;background:#7c3aed;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">📋 复制链接</button>
    </div>
    <div style="font-size:12px;color:#9ca3af;">邀请码：<strong style="color:#7c3aed;"><?= h($myCode) ?></strong></div>
</div>

<!-- 数据概览 -->
<div class="admin-stats" style="margin-bottom:24px;">
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:#ede9fe;color:#7c3aed;">👥</div>
        <div class="stat-body"><div class="stat-number"><?= $totalInvited ?></div><div class="stat-label">成功邀请人数</div></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:#f0fdf4;color:#059669;">📅</div>
        <div class="stat-body"><div class="stat-number">+<?= $totalDays ?></div><div class="stat-label">累计获得天数</div></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:#fff7ed;color:#c2410c;">👑</div>
        <div class="stat-body"><div class="stat-number" style="font-size:15px;"><?= $expireInfo ?></div><div class="stat-label">当前VIP状态</div></div>
    </div>
</div>

<!-- 邀请记录 -->
<div class="admin-card" style="margin-bottom:0;">
    <div class="admin-card-body" style="padding:20px;">
        <h3 style="font-size:15px;font-weight:600;color:#0F3460;margin:0 0 14px;">📋 邀请记录</h3>
        <?php if (empty($recentRewards)): ?>
            <div style="text-align:center;padding:40px 0;color:#9ca3af;">
                <div style="font-size:40px;margin-bottom:10px;">📭</div>
                <div>还没有邀请记录</div>
                <div style="font-size:13px;margin-top:4px;">分享你的专属链接，开始赚VIP时长吧</div>
            </div>
        <?php else: ?>
        <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <thead><tr style="color:#9ca3af;font-size:11px;text-align:left;">
                <th style="padding:8px 12px;border-bottom:1px solid #f3f4f6;">注册用户</th>
                <th style="padding:8px 12px;border-bottom:1px solid #f3f4f6;text-align:center;">奖励天数</th>
                <th style="padding:8px 12px;border-bottom:1px solid #f3f4f6;text-align:right;">时间</th>
            </tr></thead>
            <tbody>
            <?php foreach ($recentRewards as $r): ?>
            <tr>
                <td style="padding:10px 12px;font-weight:500;"><?= h($r['username']) ?></td>
                <td style="padding:10px 12px;text-align:center;">
                    <span style="display:inline-block;padding:2px 10px;background:#ede9fe;color:#7c3aed;border-radius:10px;font-size:12px;font-weight:600;">+<?= $r['reward_days'] ?>天</span>
                </td>
                <td style="padding:10px 12px;text-align:right;color:#6b7280;font-size:12px;"><?= date('Y-m-d H:i', strtotime($r['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

</div>

<script>
function copyRef() {
    var input = document.getElementById('refLink');
    input.select(); input.setSelectionRange(0, 99999);
    document.execCommand('copy');
    var btn = event.target;
    btn.textContent = '✅ 已复制';
    btn.style.background = '#059669';
    setTimeout(function(){ btn.textContent = '📋 复制链接'; btn.style.background = '#7c3aed'; }, 2000);
}
</script>
