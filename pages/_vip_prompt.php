<?php
$cfg = membershipGetConfig();
?>
<div style="max-width:500px;margin:80px auto;text-align:center;">
    <div style="font-size:64px;margin-bottom:20px;">👑</div>
    <h2 style="font-size:22px;color:#374151;margin:0 0 8px;">此功能为VIP专属</h2>
    <p style="font-size:14px;color:#9ca3af;margin:0 0 24px;">升级VIP即可解锁全部功能</p>
    <?php if ((float)$cfg['upgrade_price'] > 0): ?>
    <div style="font-size:32px;font-weight:700;color:#7c3aed;margin-bottom:20px;">¥<?= number_format((float)$cfg['upgrade_price'], 2) ?></div>
    <?php endif; ?>
    <a href="index.php?route=upgrade" style="display:inline-block;padding:12px 32px;background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;border-radius:10px;text-decoration:none;font-weight:600;font-size:15px;">👑 立即开通VIP</a>
</div>