<?php
requireLogin();
$user = currentUser();
$cfg = membershipGetConfig();
$isVip = ($user['membership'] ?? 'trial') === 'vip';
$pageTitle = $isVip ? '续费VIP - GEO优化' : '开通VIP - GEO优化';

// 统计VIP期间数据
$firstVipScan = dbFetchOne("SELECT MIN(created_at) as dt FROM geo_brand_scan WHERE user_id=? AND doubao_count+deepseek_count>0", [$user['id']])['dt'] ?? '';
$totalKeywords = dbFetchOne("SELECT MAX(doubao_count+deepseek_count) as c FROM geo_brand_scan WHERE user_id=?", [$user['id']])['c'] ?? 0;
$totalArticles = dbFetchOne("SELECT COUNT(*) as c FROM geo_articles WHERE user_id=? AND status='completed'", [$user['id']])['c'] ?? 0;
$totalDetects = dbFetchOne("SELECT COUNT(*) as c FROM geo_detect_results WHERE user_id=?", [$user['id']])['c'] ?? 0;
$totalDistill = dbFetchOne("SELECT COUNT(*) as c FROM geo_keywords_distill WHERE user_id=?", [$user['id']])['c'] ?? 0;
$scanCount = dbFetchOne("SELECT COUNT(*) as c FROM geo_brand_scan WHERE user_id=?", [$user['id']])['c'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 啥也不做，走前端弹窗
}
// 读取联系方式：有代理显示代理，否则显示管理员
$contactSource = null;
if (!empty($user['agent_id'])) {
    $contactSource = dbFetchOne("SELECT qq, wechat, email, phone, show_qq, show_wechat, show_email, show_phone FROM users WHERE id=? AND role='agent'", [$user['agent_id']]);
}
if (!$contactSource) {
    $contactSource = dbFetchOne("SELECT qq, wechat, email, phone, show_qq, show_wechat, show_email, show_phone FROM users WHERE role='admin' ORDER BY id LIMIT 1");
}
$admin = $contactSource;
?>
<div style="max-width:860px;margin:0 auto;">

    <!-- 顶部 -->
    <div style="text-align:center;padding:30px 0 20px;">
        <div style="font-size:48px;margin-bottom:8px;"><?= $isVip ? '🔄' : '👑' ?></div>
        <h1 style="font-size:26px;font-weight:800;color:#0F3460;margin:0 0 6px;">
            <?= $isVip ? '续费VIP，继续享受AI营销红利' : '开通VIP，解锁AI营销全部能力' ?>
        </h1>
        <p style="font-size:14px;color:#6b7280;margin:0;">
            <?= h($user['username']) ?><?= $isVip ? '，你的VIP即将到期' : '，试用只是开始' ?>
        </p>
    </div>

    <?php if ($isVip): ?>
    <!-- 到期提醒 -->
    <div style="background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:12px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;">
        <div>
            <div style="font-size:13px;color:#92400e;font-weight:600;">⏰ VIP 到期时间</div>
            <div style="font-size:24px;font-weight:800;color:#92400e;">
                <?= $user['membership_expire'] ? date('Y年m月d日', strtotime($user['membership_expire'])) : '永久' ?>
                <?php if ($user['membership_expire']): 
                    $daysLeft = max(0, (strtotime($user['membership_expire']) - time()) / 86400);
                ?>
                <span style="font-size:14px;font-weight:400;">（还剩 <?= ceil($daysLeft) ?> 天）</span>
                <?php endif; ?>
            </div>
        </div>
        <div style="font-size:40px;">⏳</div>
    </div>

    <!-- VIP期间战绩 -->
    <div style="background:#fff;border-radius:16px;padding:28px;box-shadow:0 1px 3px rgba(0,0,0,0.04);margin-bottom:24px;">
        <h3 style="font-size:16px;font-weight:700;color:#374151;margin:0 0 20px;">📊 使用VIP期间，你获得了</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
            <div style="text-align:center;padding:16px;background:#faf5ff;border-radius:10px;">
                <div style="font-size:32px;font-weight:800;color:#7c3aed;"><?= number_format($totalKeywords) ?></div>
                <div style="font-size:12px;color:#7c3aed;margin-top:4px;">AI搜索收录关键词</div>
            </div>
            <div style="text-align:center;padding:16px;background:#f0fdf4;border-radius:10px;">
                <div style="font-size:32px;font-weight:800;color:#059669;"><?= number_format($totalArticles) ?></div>
                <div style="font-size:12px;color:#059669;margin-top:4px;">AI生成文章</div>
            </div>
            <div style="text-align:center;padding:16px;background:#eff6ff;border-radius:10px;">
                <div style="font-size:32px;font-weight:800;color:#2563eb;"><?= number_format($totalDistill + $totalDetects) ?></div>
                <div style="font-size:12px;color:#2563eb;margin-top:4px;">关键词检测+蒸馏</div>
            </div>
        </div>
        <div style="text-align:center;margin-top:16px;font-size:13px;color:#6b7280;">
            累计扫描 <strong><?= $scanCount ?></strong> 次，持续追踪品牌在AI搜索中的表现
        </div>
    </div>
    <?php endif; ?>

    <!-- 价格卡片 -->
    <div style="max-width:400px;margin:0 auto 30px;background:<?= $isVip?'linear-gradient(135deg,#064e3b,#065f46)':'linear-gradient(135deg,#1a0533,#2d1060)' ?>;border-radius:20px;padding:36px 32px;text-align:center;box-shadow:0 20px 60px <?= $isVip?'rgba(5,150,105,0.25)':'rgba(124,58,237,0.3)' ?>;">
        <div style="font-size:14px;color:<?= $isVip?'#6ee7b7':'#a78bfa' ?>;margin-bottom:4px;"><?= $isVip ? '续费 · VIP 会员' : 'VIP 会员' ?></div>
        <div style="font-size:48px;font-weight:800;color:#fff;line-height:1;">
            <?php if ((float)$cfg['upgrade_price'] > 0): ?>
            ¥<?= number_format((float)$cfg['upgrade_price'], 2) ?>
            <span style="font-size:16px;font-weight:400;opacity:0.7;">/月</span>
            <?php else: ?>
            限时免费
            <?php endif; ?>
        </div>
        <div style="font-size:13px;color:<?= $isVip?'#6ee7b7':'#a78bfa' ?>;margin-top:4px;">按月订阅 · 30天畅享全部功能</div>
        <button onclick="showContactModal()" style="width:100%;padding:14px;background:linear-gradient(135deg,#f59e0b,#ef4444);color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:700;cursor:pointer;box-shadow:0 4px 20px rgba(245,158,11,0.4);margin-top:24px;">
            <?= $isVip ? '🔄 立即续费' : '👑 立即开通 VIP' ?>
        </button>
    </div>

    <!-- 功能列表 -->
    <div style="background:#fff;border-radius:16px;padding:28px;box-shadow:0 1px 3px rgba(0,0,0,0.04);margin-bottom:24px;">
        <h3 style="font-size:16px;font-weight:700;color:#374151;margin:0 0 16px;"><?= $isVip ? '续费后继续享受' : 'VIP 专属权益' ?></h3>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
            <?php
            $perks = [
                ['🔍','无限关键词监测','实时追踪AI搜索排名'],
                ['🧪','无限关键词蒸馏','批量挖掘长尾流量词'],
                ['📝','无限文章生成','AI创作高质量内容'],
                ['📢','多平台文章推送','一键分发多渠道曝光'],
                ['🎬','视频脚本生成','爆款脚本自动生成'],
                ['📊','视频号数据分析','深度洞察内容表现'],
            ];
            foreach ($perks as $p):
            ?>
            <div style="text-align:center;padding:12px;border-radius:10px;background:#faf5ff;">
                <div style="font-size:24px;margin-bottom:6px;"><?= $p[0] ?></div>
                <div style="font-weight:600;font-size:13px;color:#374151;"><?= $p[1] ?></div>
                <div style="font-size:11px;color:#9ca3af;margin-top:2px;"><?= $p[2] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <p style="text-align:center;margin:20px 0;font-size:13px;color:#9ca3af;">支付问题？联系管理员获取帮助</p>
</div>

<!-- 联系管理员弹窗 -->
<div id="contactModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:#fff;border-radius:20px;padding:32px;width:380px;max-width:90vw;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="font-size:48px;margin-bottom:12px;">💬</div>
        <h3 style="font-size:18px;font-weight:700;color:#374151;margin:0 0 4px;">联系管理员</h3>
        <p style="font-size:13px;color:#9ca3af;margin:0 0 24px;">添加以下联系方式，完成支付后即可开通</p>
        <?php if (!empty($admin['qq']) && ($admin['show_qq'] ?? 1)): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:#f0fdf4;border-radius:10px;margin-bottom:10px;cursor:pointer;" onclick="copyText('<?= h($admin['qq']) ?>')">
            <span style="font-size:20px;">🐧</span>
            <div style="text-align:left;flex:1;">
                <div style="font-size:12px;color:#6b7280;">QQ</div>
                <div style="font-weight:600;color:#059669;"><?= h($admin['qq']) ?></div>
            </div>
            <span style="font-size:12px;color:#9ca3af;">点击复制</span>
        </div>
        <?php endif; ?>
        <?php if (!empty($admin['wechat']) && ($admin['show_wechat'] ?? 1)): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:#f0fdf4;border-radius:10px;margin-bottom:10px;cursor:pointer;" onclick="copyText('<?= h($admin['wechat']) ?>')">
            <span style="font-size:20px;">💚</span>
            <div style="text-align:left;flex:1;">
                <div style="font-size:12px;color:#6b7280;">微信</div>
                <div style="font-weight:600;color:#059669;"><?= h($admin['wechat']) ?></div>
            </div>
            <span style="font-size:12px;color:#9ca3af;">点击复制</span>
        </div>
        <?php endif; ?>
        <?php if (!empty($admin['email']) && ($admin['show_email'] ?? 1)): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:#f0fdf4;border-radius:10px;margin-bottom:10px;">
            <span style="font-size:20px;">📧</span>
            <div style="text-align:left;flex:1;">
                <div style="font-size:12px;color:#6b7280;">邮箱</div>
                <div style="font-weight:600;color:#059669;"><?= h($admin['email']) ?></div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($admin['phone']) && ($admin['show_phone'] ?? 1)): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:#f0fdf4;border-radius:10px;margin-bottom:10px;">
            <span style="font-size:20px;">📱</span>
            <div style="text-align:left;flex:1;">
                <div style="font-size:12px;color:#6b7280;">手机</div>
                <div style="font-weight:600;color:#059669;"><?= h($admin['phone']) ?></div>
            </div>
        </div>
        <?php endif; ?>
        <button onclick="document.getElementById('contactModal').style.display='none'" style="margin-top:16px;padding:10px 24px;background:#e5e7eb;color:#374151;border:none;border-radius:8px;cursor:pointer;font-size:14px;">关闭</button>
    </div>
</div>
<script>
function showContactModal() { document.getElementById('contactModal').style.display='flex'; }
function copyText(t) { navigator.clipboard.writeText(t).then(()=>alert('已复制：'+t)); }
</script>