<?php
requireLogin();
require_once __DIR__ . '/../includes/geo.php';
$user = currentUser();
 $pageTitle = '控制台 - GEO优化';
 
 try { dbExecute("ALTER TABLE users ADD COLUMN `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `role`"); } catch (Exception $e) {}
 
if (isAdminOrAgent()) {
    try { dbExecute("CREATE TABLE IF NOT EXISTS `transactions` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`type` ENUM('recharge','consume','refund') NOT NULL,`amount` DECIMAL(10,2) NOT NULL,`description` VARCHAR(255) DEFAULT NULL,`created_at` DATETIME NOT NULL,FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}
    if (isAgent()) {
        $agentFilter = "WHERE agent_id=" . (int)$user['id'];
        $totalUsers=dbFetchOne("SELECT COUNT(*) as c FROM users {$agentFilter}")['c'];
        $newToday=dbFetchOne("SELECT COUNT(*) as c FROM users WHERE DATE(created_at)=CURDATE() AND agent_id=?", [$user['id']])['c'];
        try { $totalAmount=dbFetchOne("SELECT COALESCE(SUM(balance),0) as total FROM users WHERE agent_id=?", [$user['id']])['total']; } catch (Exception $e) { $totalAmount = 0; }
        $consumeToday = 0;
        try { $consumeToday=dbFetchOne("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='consume' AND DATE(created_at)=CURDATE() AND user_id IN (SELECT id FROM users WHERE agent_id=?)", [$user['id']])['total']; } catch (Exception $e) { $consumeToday = 0; }
    } else {
        $totalUsers=dbFetchOne("SELECT COUNT(*) as c FROM users WHERE role!='admin'")['c'];
        $newToday=dbFetchOne("SELECT COUNT(*) as c FROM users WHERE DATE(created_at)=CURDATE() AND role!='admin'")['c'];
        try { $totalAmount=dbFetchOne("SELECT COALESCE(SUM(CASE WHEN type='recharge' THEN amount WHEN type='consume' THEN -amount ELSE 0 END),0) as total FROM transactions")['total']; } catch (Exception $e) { $totalAmount = 0; }
        try { $consumeToday=dbFetchOne("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='consume' AND DATE(created_at)=CURDATE()")['total']; } catch (Exception $e) { $consumeToday = 0; }
    }
    // ── 代理商专属数据 ──
    if (isAgent()) {
        $aid = (int)$user['id'];
        $agentVipCount = dbFetchOne("SELECT COUNT(*) as c FROM users WHERE agent_id=? AND membership='vip' AND membership_expire>=CURDATE()", [$aid])['c'] ?? 0;
        $agentTrialCount = $totalUsers - $agentVipCount;
        $agentKeywordTotal = dbFetchOne("SELECT COALESCE(SUM(doubao_count+deepseek_count),0) as total FROM geo_brand_scan WHERE user_id IN (SELECT id FROM users WHERE agent_id=?)", [$aid])['total'] ?? 0;
        $agentRecentUsers = dbFetchAll("SELECT id,username,membership,membership_expire,balance,created_at FROM users WHERE agent_id=? ORDER BY id DESC LIMIT 6", [$aid]);
        $agentRechargeToday = 0;
        try { $agentRechargeToday = dbFetchOne("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='recharge' AND DATE(created_at)=CURDATE() AND user_id IN (SELECT id FROM users WHERE agent_id=?)", [$aid])['total']; } catch (Exception $e) {}
        $agentConsumeWeek = 0;
        try { $agentConsumeWeek = dbFetchOne("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='consume' AND created_at>=DATE_SUB(CURDATE(),INTERVAL 7 DAY) AND user_id IN (SELECT id FROM users WHERE agent_id=?)", [$aid])['total']; } catch (Exception $e) {}
        $agentTrend = dbFetchAll("SELECT DATE(t.created_at) as dt, COALESCE(SUM(t.amount),0) as amt FROM transactions t JOIN users u ON u.id=t.user_id WHERE u.agent_id=? AND t.type='consume' AND t.created_at>=DATE_SUB(CURDATE(),INTERVAL 7 DAY) GROUP BY DATE(t.created_at) ORDER BY dt", [$aid]);
    }
}

// ── 管理员专属：核心业务数据 ──
if (isAdmin()) {
    // 收入数据
    $todayRecharge  = dbFetchOne("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='recharge' AND DATE(created_at)=CURDATE()")['total'] ?? 0;
    $weekRecharge   = dbFetchOne("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='recharge' AND created_at>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)")['total'] ?? 0;
    $todayConsume   = dbFetchOne("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='consume' AND DATE(created_at)=CURDATE()")['total'] ?? 0;
    
    // 用户数据
    $adminVipCount    = dbFetchOne("SELECT COUNT(*) as c FROM users WHERE membership='vip' AND membership_expire>=CURDATE()")['c'] ?? 0;
    $adminTrialCount  = dbFetchOne("SELECT COUNT(*) as c FROM users WHERE membership='trial' AND role='user'")['c'] ?? 0;
    $todayScans       = dbFetchOne("SELECT COUNT(*) as c FROM geo_brand_scan WHERE DATE(created_at)=CURDATE()")['c'] ?? 0;
    $recentUsers      = dbFetchAll("SELECT id,username,membership,agent_id,created_at FROM users WHERE role!='admin' ORDER BY id DESC LIMIT 6");
    
    // 内容数据
    $totalDistillWords = dbFetchOne("SELECT COUNT(*) as c FROM geo_distill_queue")['c'] ?? 0;
    $totalArticles     = dbFetchOne("SELECT COUNT(*) as c FROM geo_articles")['c'] ?? 0;
    
    // 代理数据
    $agentCount     = dbFetchOne("SELECT COUNT(*) as c FROM users WHERE role='agent'")['c'] ?? 0;
    $agentUserTotal = dbFetchOne("SELECT COUNT(*) as c FROM users WHERE agent_id IS NOT NULL")['c'] ?? 0;

    // 队列积压
    $queueScan    = dbFetchOne("SELECT COUNT(*) as c FROM geo_scan_queue WHERE status IN ('pending','processing')")['c'] ?? 0;
    $queueKw      = dbFetchOne("SELECT COUNT(*) as c FROM geo_queue WHERE status IN ('pending','processing')")['c'] ?? 0;
    $queueDistill = dbFetchOne("SELECT COUNT(*) as c FROM geo_distill_queue WHERE status IN ('pending','processing')")['c'] ?? 0;
    $queueArticle = dbFetchOne("SELECT COUNT(*) as c FROM geo_articles WHERE status IN ('pending','processing')")['c'] ?? 0;
    $queueDetect  = dbFetchOne("SELECT COUNT(*) as c FROM geo_detect_results WHERE status IN ('pending','processing')")['c'] ?? 0;
    $queueAutoTask = dbFetchOne("SELECT COUNT(*) as c FROM auto_tasks WHERE enabled=1")['c'] ?? 0;
    $queueTotal   = $queueScan + $queueKw + $queueDistill + $queueArticle + $queueDetect;
    $workerCount  = function_exists('getWorkerCount') ? getWorkerCount() : 0;

    // 工单
    try {
        $openTickets     = dbFetchOne("SELECT COUNT(*) as c FROM tickets WHERE status='open'")['c'] ?? 0;
        $progressTickets = dbFetchOne("SELECT COUNT(*) as c FROM tickets WHERE status='in_progress'")['c'] ?? 0;
    } catch (Exception $e) { $openTickets = 0; $progressTickets = 0; }
    $totalTickets    = $openTickets + $progressTickets;

    // API
    $apiDoubao = dbFetchOne("SELECT COUNT(*) as c FROM geo_api_providers WHERE provider='doubao' AND api_key!=''")['c'] ?? 0;
    $apiDoubao += dbFetchOne("SELECT COUNT(*) as c FROM agent_api_config WHERE doubao_key!=''")['c'] ?? 0;
    $apiDeepseek = dbFetchOne("SELECT COUNT(*) as c FROM geo_api_providers WHERE provider='deepseek' AND api_key!=''")['c'] ?? 0;
    $apiDeepseek += dbFetchOne("SELECT COUNT(*) as c FROM agent_api_config WHERE deepseek_key!=''")['c'] ?? 0;
    $apiCount = $apiDoubao + $apiDeepseek;
}

 
 // User data section
 $company = null;
 $keywordCount = 0;
 $mentionCount = 0;
 $dailyStats = [];
 $chartLabels = '[]';
 $chartScan = '[]';
 $chartDetect = '[]';
 
 if (!isAdminOrAgent()) {
     $company = dbFetchOne("SELECT * FROM company_info WHERE user_id=?", [$user['id']]);
     $keywordCount = dbFetchOne("SELECT COUNT(*) as c FROM geo_keywords WHERE user_id=?", [$user['id']])['c'] ?? 0;
    $articleCount = dbFetchOne("SELECT COUNT(*) as c FROM geo_articles WHERE user_id=?", [$user['id']])['c'] ?? 0;
     try { dbExecute("CREATE TABLE IF NOT EXISTS `geo_brand_scan` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`brand_visible` TINYINT(1) NOT NULL DEFAULT 0,`brand_position` INT DEFAULT NULL,`keyword_count` INT NOT NULL DEFAULT 0,`doubao_count` INT NOT NULL DEFAULT 0,`deepseek_count` INT NOT NULL DEFAULT 0,`total_keywords` INT NOT NULL DEFAULT 0,`scan_percent` DECIMAL(5,1) NOT NULL DEFAULT 0.0,`raw_response` MEDIUMTEXT DEFAULT NULL,`created_at` DATETIME NOT NULL,INDEX `idx_user_date` (`user_id`,`created_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}
    
    try { dbExecute("CREATE TABLE IF NOT EXISTS \`geo_brand_scan_details\` (\`id\` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\`scan_id\` INT UNSIGNED NOT NULL,\`user_id\` INT UNSIGNED NOT NULL,\`keyword\` VARCHAR(200) NOT NULL,\`keyword_index\` INT NOT NULL DEFAULT 0,\`platform\` VARCHAR(50) NOT NULL DEFAULT '',\`mentioned\` TINYINT(1) NOT NULL DEFAULT 0,\`rank_position\` INT DEFAULT NULL,\`created_at\` DATETIME NOT NULL,INDEX \`idx_user_mention\` (\`user_id\`,\`mentioned\`),INDEX \`idx_scan\` (\`scan_id\`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}
    try { dbExecute("CREATE TABLE IF NOT EXISTS \`geo_daily_keyword_stats\` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`total_keywords` INT NOT NULL DEFAULT 0,`record_date` DATE NOT NULL,`created_at` DATETIME NOT NULL,UNIQUE KEY `idx_user_date` (`user_id`,`record_date`),FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}
    $scanResult = dbFetchOne("SELECT * FROM geo_brand_scan WHERE user_id=? ORDER BY id DESC LIMIT 1", [$user['id']]);
    $keywords = [];
    if ($scanResult && !empty($scanResult['raw_response'])) {
        preg_match_all('/^\d+[.\s]+(.+)$/m', $scanResult['raw_response'], $matches);
        foreach ($matches[1] as $kw) { $kw = trim($kw); if (strlen($kw) > 2) $keywords[] = ['keyword' => $kw]; if (count($keywords) >= 15) break; }
    }
    
    $detectRows = dbFetchAll("SELECT DATE(r.checked_at) as dt,COALESCE(SUM(r.brand_mentioned),0) as val FROM geo_results r JOIN geo_keywords k ON r.keyword_id=k.id WHERE k.user_id=? AND r.checked_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY DATE(r.checked_at) ORDER BY dt", [$user['id']]);
    $dailyRows = dbFetchAll("SELECT DATE(created_at) as record_date, MAX(doubao_count + deepseek_count) as total_keywords FROM geo_brand_scan WHERE user_id=? AND created_at>=DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY record_date", [$user['id']]);
    $vaChart = 0; try { $vrChart = dbFetchOne("SELECT amount FROM virtual_collections WHERE user_id=?", [$user["id"]]); if ($vrChart) $vaChart = (int)$vrChart["amount"]; } catch (Exception $e) {}
    $realCount = $scanResult ? ((int)($scanResult["doubao_count"]??0) + (int)($scanResult["deepseek_count"]??0)) : 0;
    $currentTotal = $realCount + $vaChart;
    $allDates = []; for ($d = 29; $d >= 0; $d--) { $allDates[date('Y-m-d', strtotime("-{$d} days"))] = 1; }
    $dMap=[]; foreach($detectRows as $r) $dMap[$r['dt']]=(int)$r['val'];
    $dailyMap=[]; foreach($dailyRows as $r) $dailyMap[$r['record_date']]=(int)$r['total_keywords'];
    $dl=[]; $dv=[]; $sv=[];
    $today = date("Y-m-d");
    foreach(array_keys($allDates) as $dt) { $dl[]=substr($dt,5); $dv[]=$dMap[$dt]??0; $sv[]=($dt === $today) ? $currentTotal : ($dailyMap[$dt] ?? 0); }
    $chartLabels=json_encode($dl);
    $chartDetect=json_encode($dv);
    $chartScan=json_encode($sv);
 }

// Refresh data button action
if (isset($_GET["action"]) && $_GET["action"] === "refresh_data") {
    geoInitTables();
    $latest = dbFetchOne("SELECT created_at FROM geo_brand_scan WHERE user_id=? ORDER BY id DESC LIMIT 1", [$user["id"]]);
    $pending = dbFetchOne("SELECT id FROM geo_scan_queue WHERE user_id=? AND status IN (\"pending\",\"processing\")", [$user["id"]]);
    $canRefresh = true;
    if ($pending) $canRefresh = false;
    if ($latest && strtotime($latest["created_at"]) > time() - 3600) $canRefresh = false;
    if ($canRefresh) {
        dbExecute("INSERT INTO geo_scan_queue (user_id,status,created_at) VALUES (?,\"pending\",NOW())", [$user["id"]]);
        setFlash("success", "更新收录已加入队列");
    } else {
        setFlash('warning', '1小时内已更新或已有任务排队，请稍后再试');
    }
    redirect("index.php?route=dashboard");
}

// Check hourly cooldown for refresh button
$latestScan = dbFetchOne("SELECT created_at FROM geo_brand_scan WHERE user_id=? ORDER BY id DESC LIMIT 1", [$user["id"]]);
$pendingScan = dbFetchOne("SELECT id FROM geo_scan_queue WHERE user_id=? AND status IN (\"pending\",\"processing\")", [$user["id"]]);
$canRefresh = true;
if ($pendingScan) { $canRefresh = false; }
if ($latestScan && strtotime($latestScan["created_at"]) > time() - 3600) { $canRefresh = false; }
?>

<div class="admin-page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <div>
        <h1><?= isAdmin() ? "管理控制台" : "数据总览" ?></h1>
        <p><?= isAdmin() ? "欢迎回来，管理员" : (isAgent() ? "欢迎回来，代理商" : "欢迎回来") ?></p>
        <?php if (isAgent() && !empty($user['agent_expire'])): 
            $agDays = max(0, ceil((strtotime($user['agent_expire']) - time()) / 86400));
        ?>
        <p style="font-size:12px;color:<?=$agDays<=7?'#dc2626':'#9ca3af'?>;margin-top:2px;">🏢 代理授权到期：<?=$user['agent_expire']?>（剩余 <?=$agDays?> 天）</p>
        <?php endif; ?>
    </div>
    <?php
    // 工单提示
    try {
        if (isAdminOrAgent()) {
            $myOpenTickets = dbFetchOne("SELECT COUNT(*) as c FROM tickets WHERE status='open'")['c'] ?? 0;
        } else {
            $myOpenTickets = dbFetchOne("SELECT COUNT(*) as c FROM tickets WHERE user_id=? AND status IN ('open','in_progress')", [$user['id']])['c'] ?? 0;
        }
    } catch (Exception $e) { $myOpenTickets = 0; }
    if ($myOpenTickets > 0):
    ?>
    <a href="index.php?route=tickets" style="text-decoration:none;color:#dc2626;font-weight:700;font-size:14px;animation:pulse 2s infinite;">
        🔔 有 <?= $myOpenTickets ?> 条工单待处理 →
    </a>
    <?php endif; ?>
</div>
<style>@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.6}}</style>

<?php if (isAdminOrAgent()): ?>
<div class="admin-stats">
    <div class="admin-stat-card"><div class="admin-stat-icon" style="background:#eef2ff;color:#4f46e5;">💰</div><div class="stat-body"><div class="stat-number">¥ <?= number_format($totalAmount,2) ?></div><div class="stat-label">总余额</div></div></div>
    <div class="admin-stat-card"><div class="admin-stat-icon" style="background:#fff7ed;color:#c2410c;">📉</div><div class="stat-body"><div class="stat-number">¥ <?= number_format($consumeToday,2) ?></div><div class="stat-label">今日消耗</div></div></div>
    <div class="admin-stat-card"><div class="admin-stat-icon" style="background:#f0fdf4;color:#059669;">👥</div><div class="stat-body"><div class="stat-number"><?= $totalUsers ?></div><div class="stat-label">总用户数</div></div></div>
    <div class="admin-stat-card"><div class="admin-stat-icon" style="background:#faf5ff;color:#7c3aed;">✨</div><div class="stat-body"><div class="stat-number">+<?= $newToday ?></div><div class="stat-label">今日新增</div></div></div>
</div>
<?php endif; ?>

<?php if (isAgent()): ?>
<!-- ═══════ 代理商专属 ═══════ -->
<div class="admin-stats" style="margin-top:0;">
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:#ede9fe;color:#7c3aed;">👑</div>
        <div class="stat-body"><div class="stat-number"><?= $agentVipCount ?></div><div class="stat-label">VIP 用户</div></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:#fef3c7;color:#d97706;">🆓</div>
        <div class="stat-body"><div class="stat-number"><?= $agentTrialCount ?></div><div class="stat-label">试用用户</div></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:#dbeafe;color:#2563eb;">🔑</div>
        <div class="stat-body"><div class="stat-number"><?= number_format($agentKeywordTotal) ?></div><div class="stat-label">旗下关键词</div></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:#fee2e2;color:#dc2626;">📉</div>
        <div class="stat-body"><div class="stat-number">¥ <?= number_format($agentConsumeWeek,2) ?></div><div class="stat-label">近7日消耗</div></div>
    </div>
</div>

<!-- 近期用户 & 趋势 -->
<div style="display:flex;gap:20px;margin-top:20px;align-items:stretch;">
    <!-- 名下用户 -->
    <div class="admin-card" style="flex:1;margin-bottom:0;">
        <div class="admin-card-body" style="padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h3 style="font-size:15px;font-weight:600;color:#0F3460;margin:0;">👥 名下用户</h3>
                <a href="index.php?route=admin/users" style="font-size:12px;color:#4f46e5;text-decoration:none;">查看全部 →</a>
            </div>
            <?php if (empty($agentRecentUsers)): ?>
                <div style="text-align:center;padding:30px 0;color:#9ca3af;font-size:13px;">暂无用户</div>
            <?php else: ?>
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <thead><tr style="color:#9ca3af;font-size:11px;text-align:left;">
                    <th style="padding:6px 8px;border-bottom:1px solid #f3f4f6;">用户</th>
                    <th style="padding:6px 8px;border-bottom:1px solid #f3f4f6;text-align:center;">会员</th>
                    <th style="padding:6px 8px;border-bottom:1px solid #f3f4f6;text-align:right;">余额</th>
                </tr></thead>
                <tbody>
                <?php foreach ($agentRecentUsers as $au): ?>
                <tr>
                    <td style="padding:8px;">
                        <a href="index.php?route=admin/users&user_id=<?=$au['id']?>" style="color:#0F3460;font-weight:500;text-decoration:none;"><?= h($au['username']) ?></a>
                    </td>
                    <td style="padding:8px;text-align:center;">
                        <span style="font-size:11px;font-weight:600;padding:1px 8px;border-radius:10px;<?= ($au['membership']??'vip')==='vip'?'background:#ede9fe;color:#7c3aed':'background:#fef3c7;color:#d97706'?>"><?= ($au['membership']??'vip')==='vip'?'VIP':'试用' ?></span>
                    </td>
                    <td style="padding:8px;text-align:right;font-weight:600;">¥<?= number_format($au['balance']??0,2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- 7日消耗趋势 -->
    <div class="admin-card" style="flex:1;margin-bottom:0;">
        <div class="admin-card-body" style="padding:20px;">
            <h3 style="font-size:15px;font-weight:600;color:#0F3460;margin:0 0 14px;">📊 近7日消耗趋势</h3>
            <?php
            $trendMap = [];
            if (!empty($agentTrend)) { foreach ($agentTrend as $t) { $trendMap[$t['dt']] = (float)$t['amt']; } }
            $maxVal = !empty($trendMap) ? max(max($trendMap), 0.01) : 1;
            ?>
            <div style="display:flex;align-items:flex-end;gap:8px;height:140px;padding:0 4px;">
                <?php for ($i=6; $i>=0; $i--):
                    $d = date('Y-m-d', strtotime("-{$i} days"));
                    $val = $trendMap[$d] ?? 0;
                    $h = $maxVal > 0 ? max(4, round($val / $maxVal * 100)) : 0;
                    $dayLabel = date('m/d', strtotime($d));
                ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
                    <span style="font-size:10px;color:#9ca3af;">¥<?= $val>0 ? number_format($val,0) : 0 ?></span>
                    <div style="width:100%;max-width:40px;height:<?=$h?>%;background:<?=$val>0?'linear-gradient(180deg,#7c3aed,#a855f7)':'#f3f4f6'?>;border-radius:4px 4px 0 0;min-height:4px;transition:height 0.5s;" title="<?=$d?>: ¥<?=number_format($val,2)?>"></div>
                    <span style="font-size:10px;color:#6b7280;"><?=$dayLabel?></span>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (isAdmin()): ?>
<!-- ═══════ 管理端核心指标 ═══════ -->
<div class="admin-stats" style="margin-top:0;">
    <!-- 总蒸馏词量 -->
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:#eef2ff;color:#4f46e5;">🔑</div>
        <div class="stat-body">
            <div class="stat-number"><?= number_format($totalDistillWords) ?></div>
            <div class="stat-label">总蒸馏词量</div>
        </div>
    </div>
    <!-- 总文章 -->
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:#f0fdf4;color:#059669;">📝</div>
        <div class="stat-body">
            <div class="stat-number"><?= number_format($totalArticles) ?></div>
            <div class="stat-label">总文章数</div>
        </div>
    </div>
    <!-- 今日扫描 -->
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:#fff7ed;color:#c2410c;">🔍</div>
        <div class="stat-body">
            <div class="stat-number"><?= number_format($todayScans) ?></div>
            <div class="stat-label">今日扫描次数</div>
        </div>
    </div>
    <!-- VIP/试用 -->
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:#ede9fe;color:#7c3aed;">👑</div>
        <div class="stat-body">
            <div class="stat-number"><?= $adminVipCount ?> <span style="font-size:12px;color:#9ca3af;">/ <?= $adminTrialCount ?></span></div>
            <div class="stat-label">VIP / 试用用户</div>
            <div style="width:100%;height:4px;background:#f3f4f6;border-radius:2px;margin-top:6px;overflow:hidden;">
                <?php $vipPct = ($adminVipCount+$adminTrialCount)>0 ? round($adminVipCount/($adminVipCount+$adminTrialCount)*100) : 0; ?>
                <div style="height:100%;width:<?=$vipPct?>%;background:linear-gradient(90deg,#7c3aed,#a855f7);border-radius:2px;"></div>
            </div>
            <div style="font-size:10px;color:#9ca3af;margin-top:2px;">VIP 占比 <?=$vipPct?>%</div>
        </div>
    </div>
</div>

<!-- ═══════ 系统状态 ═══════ -->
<div style="display:flex;gap:20px;margin-top:20px;align-items:stretch;">
    <!-- 队列积压 -->
    <div class="admin-card" style="flex:1;margin-bottom:0;">
        <div class="admin-card-body" style="padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h3 style="font-size:15px;font-weight:600;color:#0F3460;margin:0;">📋 队列积压</h3>
                <span style="font-size:12px;padding:2px 10px;border-radius:12px;font-weight:600;<?= $queueTotal > 0 ? 'background:#fef2f2;color:#dc2626;' : 'background:#f0fdf4;color:#059669;' ?>"><?= $queueTotal > 0 ? "{$queueTotal} 积压" : '空闲' ?></span>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <?php
                $queueItems = [
                    ['品牌扫描', $queueScan, '#4f46e5'],
                    ['关键词检测', $queueKw, '#c2410c'],
                    ['关键词蒸馏', $queueDistill, '#7c3aed'],
                    ['文章生成', $queueArticle, '#059669'],
                    ['GEO检测', $queueDetect, '#d97706'],
                    ['定时任务', $queueAutoTask, '#0891b2'],
                ];
                foreach ($queueItems as $qi):
                    $pct = $queueTotal > 0 ? round($qi[1] / $queueTotal * 100) : 0;
                ?>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="width:80px;font-size:12px;color:#6b7280;flex-shrink:0;"><?= $qi[0] ?></span>
                    <div style="flex:1;height:6px;background:#f3f4f6;border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:<?= $pct ?>%;background:<?= $qi[2] ?>;border-radius:3px;transition:width 0.3s;"></div>
                    </div>
                    <span style="font-size:12px;font-weight:600;color:<?= $qi[1] > 0 ? $qi[2] : '#9ca3af' ?>;min-width:24px;text-align:right;"><?= $qi[1] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:12px;padding-top:10px;border-top:1px solid #f3f4f6;font-size:12px;color:#9ca3af;">
                Worker: <strong style="color:#374151;"><?= $workerCount ?></strong> / <?= defined('MAX_WORKERS') ? MAX_WORKERS : 5 ?>
            </div>
        </div>
    </div>

    <!-- 最近注册 -->
    <div class="admin-card" style="flex:1;margin-bottom:0;">
        <div class="admin-card-body" style="padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h3 style="font-size:15px;font-weight:600;color:#0F3460;margin:0;">🆕 最近注册</h3>
                <a href="index.php?route=admin/users" style="font-size:12px;color:#4f46e5;text-decoration:none;">全部用户 →</a>
            </div>
            <?php if (empty($recentUsers)): ?>
                <div style="text-align:center;padding:30px 0;color:#9ca3af;font-size:13px;">暂无用户</div>
            <?php else: ?>
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <thead><tr style="color:#9ca3af;font-size:11px;text-align:left;">
                    <th style="padding:6px 8px;border-bottom:1px solid #f3f4f6;">用户</th>
                    <th style="padding:6px 8px;border-bottom:1px solid #f3f4f6;text-align:center;">会员</th>
                    <th style="padding:6px 8px;border-bottom:1px solid #f3f4f6;text-align:right;">来源</th>
                </tr></thead>
                <tbody>
                <?php foreach ($recentUsers as $ru): 
                    $ruSource = '';
                    if ($ru['agent_id']) {
                        $ra = dbFetchOne("SELECT username FROM users WHERE id=?", [$ru['agent_id']]);
                        $ruSource = $ra ? '代理:'.h($ra['username']) : '代理';
                    } else {
                        $ruSource = '自主注册';
                    }
                ?>
                <tr>
                    <td style="padding:8px;">
                        <a href="index.php?route=admin/users&user_id=<?=$ru['id']?>" style="color:#0F3460;font-weight:500;text-decoration:none;"><?= h($ru['username']) ?></a>
                    </td>
                    <td style="padding:8px;text-align:center;">
                        <span style="font-size:11px;font-weight:600;padding:1px 8px;border-radius:10px;<?= ($ru['membership']??'vip')==='vip'?'background:#ede9fe;color:#7c3aed':'background:#fef3c7;color:#d97706'?>"><?= ($ru['membership']??'vip')==='vip'?'VIP':'试用' ?></span>
                    </td>
                    <td style="padding:8px;text-align:right;font-size:11px;color:#6b7280;"><?= $ruSource ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!isAdminOrAgent()): ?>
<div class="admin-stats">
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:#eef2ff;color:#4f46e5;">📈</div>
        <div class="stat-body">
            <div class="admin-stat-num"><?php
                $va = 0;
                try { $vr = dbFetchOne("SELECT amount FROM virtual_collections WHERE user_id=?", [$user["id"]]); if ($vr) $va = (int)$vr["amount"]; } catch (Exception $e) {}
                $rc = $scanResult ? ((int)($scanResult["doubao_count"]??0) + (int)($scanResult["deepseek_count"]??0)) : 0;
                $total = $rc + $va;
                echo $total > 0 ? $total : "-";
            ?></div>
            <div class="admin-stat-label">关键词收录量</div>
        </div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:#fef3c7;color:#d97706;">📝</div>
        <div class="stat-body">
            <div class="admin-stat-num"><?= $articleCount ?></div>
            <div class="admin-stat-label">创作文章数量</div>
        </div>
    </div>
<?php
// 收录占比数据
$dbCount  = $scanResult ? (int)($scanResult['doubao_count'] ?? 0) : 0;
$dsCount  = $scanResult ? (int)($scanResult['deepseek_count'] ?? 0) : 0;
// 虚拟量
$vDb = 0; $vDs = 0;
try { $v = dbFetchOne("SELECT doubao_amount, deepseek_amount FROM virtual_collections WHERE user_id=?", [$user['id']]); if($v){ $vDb=(int)$v['doubao_amount']; $vDs=(int)$v['deepseek_amount']; } } catch (Exception $e) {}
$dbTotal = $dbCount + $vDb;
$dsTotal = $dsCount + $vDs;
$platformTotal = $dbTotal + $dsTotal;
$allKeywords   = $scanResult ? (int)($scanResult['total_keywords'] ?? 0) : 0;
$dbPct = $allKeywords > 0 ? round($dbTotal / $allKeywords * 100) : 0;
$dsPct = $allKeywords > 0 ? round($dsTotal / $allKeywords * 100) : 0;
$dbColor = '#4f46e5';   // 豆包 - 紫色
$dsColor = '#10b981';   // DeepSeek - 绿色
$noColor = '#e5e7eb';   // 灰色底
$ringPct = min($dbPct + $dsPct, 100);
?>

<div class="admin-stat-card" style="flex:2;min-width:280px;">
    <div style="display:flex;align-items:center;gap:20px;height:100%;">
        <!-- 左侧圆环 -->
        <div style="position:relative;width:70px;height:70px;flex-shrink:0;">
            <svg viewBox="0 0 36 36" style="width:100%;height:100%;transform:rotate(-90deg);">
                <circle cx="18" cy="18" r="15.9" fill="none" stroke="<?=$noColor?>" stroke-width="3"/>
                <?php if ($platformTotal > 0): ?>
                <circle cx="18" cy="18" r="15.9" fill="none" stroke="<?=$dbColor?>" stroke-width="3"
                    stroke-dasharray="<?=$dbPct?> <?=100-$dbPct?>" stroke-dashoffset="0"/>
    <circle cx="18" cy="18" r="15.9" fill="none" stroke="<?=$dsColor?>" stroke-width="3"
        stroke-dasharray="<?=$dsPct?> <?=100-$dsPct?>" stroke-dashoffset="<?=-$dbPct?>"/>
    <?php endif; ?>
            </svg>
            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:14px;font-weight:700;color:#374151;">
                <?= $ringPct ?>%
            </div>
        </div>
        <!-- 右侧图例 -->
        <div style="min-width:0;">
            <div style="font-size:12px;color:#6b7280;margin-bottom:6px;">收录占比</div>
            <div style="display:flex;flex-direction:column;gap:3px;">
                <div style="display:flex;align-items:center;gap:6px;font-size:12px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:<?=$dbColor?>;flex-shrink:0;"></span>
                    <span style="color:#374151;">豆包</span>
                    <span style="color:#6b7280;margin-left:auto;"><?=$dbTotal?> (<?=$dbPct?>%)</span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;font-size:12px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:<?=$dsColor?>;flex-shrink:0;"></span>
                    <span style="color:#374151;">DeepSeek</span>
        <span style="color:#6b7280;margin-left:auto;"><?=$dsTotal?> (<?=$dsPct?>%)</span>
    </div>
        </div>
        </div>
    </div>
</div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:#fef3c7;color:#d97706;">💰</div>
        <div class="stat-body">
            <div class="admin-stat-num">¥ <?= number_format($user['balance'] ?? 0, 2) ?></div>
            <div class="admin-stat-label">余额</div>
        </div>
    </div>
</div>

<div class="admin-card admin-chart-card">
    <div class="admin-card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-size:16px;font-weight:600;color:#0F3460;margin:0;">每日趋势</h3>
            <span class="dash-chart-badge" style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:#eef2ff;color:#4f46e5;">30天</span>
        </div>
        <div class="admin-chart">
            <canvas id="trendChart" height="250"></canvas>
            <?php if (empty($chartScan) || $chartScan === "[]"): ?>
            <div class="dash-chart-empty" style="text-align:center;padding:40px 0;">
                <div style="font-size:40px;margin-bottom:12px;">📊</div>
                <div style="font-size:15px;color:#6b7280;">暂无数据</div>
                <div style="font-size:13px;color:#9ca3af;margin-top:4px;">添加关键词并运行检测以查看趋势</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// 长尾词排名
$rankedKeywords = [];
if ($scanResult) {
    $details = dbFetchAll("SELECT keyword, platform, MIN(rank_position) as pos FROM geo_brand_scan_details WHERE scan_id=? AND mentioned=1 GROUP BY keyword, platform ORDER BY pos ASC", [$scanResult['id']]);
    // 合并同关键词多平台
    $kwMap = [];
    foreach ($details as $d) {
        $k = $d['keyword'];
        if (!isset($kwMap[$k])) $kwMap[$k] = ['keyword'=>$k, 'pos'=>$d['pos'], 'db'=>false, 'ds'=>false];
        if ($d['platform']==='doubao') $kwMap[$k]['db']=true;
        if ($d['platform']==='deepseek') $kwMap[$k]['ds']=true;
    }
    $rankedKeywords = array_values($kwMap);
}
if (!empty($rankedKeywords)):
    $dbColor = '#4f46e5'; $dsColor = '#10b981';
?>
<div class="admin-card" style="margin-top:20px;">
    <div class="admin-card-body">
        <h3 style="font-size:16px;font-weight:600;color:#0F3460;margin:0 0 16px 0;">📋 长尾词排名</h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px 20px;">
            <?php foreach (array_slice($rankedKeywords, 0, 15) as $i => $kw):
                $db = $kw['db']; $ds = $kw['ds'];
                // 颜色：双平台用渐变，单平台用对应颜色
                if ($db && $ds) $style = "background:linear-gradient(90deg,{$dbColor},{$dsColor});-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-weight:600;";
                elseif ($db) $style = "color:{$dbColor};font-weight:600;";
                else $style = "color:{$dsColor};font-weight:600;";
                // 平台小标
                $badges = '';
                if ($db) $badges .= "<span style='display:inline-block;width:6px;height:6px;border-radius:50%;background:{$dbColor};margin-right:2px;vertical-align:middle;'></span>";
                if ($ds) $badges .= "<span style='display:inline-block;width:6px;height:6px;border-radius:50%;background:{$dsColor};margin-right:2px;vertical-align:middle;'></span>";
            ?>
            <div style="display:flex;align-items:center;gap:6px;padding:4px 0;font-size:13px;">
                <span style="color:#9ca3af;font-size:11px;min-width:18px;"><?=$kw['pos']?></span>
                <?=$badges?>
                <span style="<?=$style?>"><?=h($kw['keyword'])?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<script src="/public/js/chart.min.js"></script>

<?php if (!isAdminOrAgent()): ?>
<script>
<?php if (!empty($chartScan) && $chartScan !== "[]"): ?>
new Chart(document.getElementById("trendChart"), {
    type: "line",
    data: {
        labels: <?= $chartLabels ?>,
        datasets: [
            { label: "关键词", data: <?= $chartScan ?: "[]" ?>, borderColor: "#10b981", backgroundColor: "rgba(16,185,129,0.08)", borderWidth: 2, fill: true, tension: 0.4, pointRadius: 3 }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true, position: "top", labels: { usePointStyle: true, padding: 16, font: { size: 12 } } } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 11 }, color: "#9ca3af" } },
            y: { beginAtZero: true, grid: { color: "#f3f4f6" }, ticks: { stepSize: 1, font: { size: 11 }, color: "#9ca3af" } }
        },
        interaction: { intersect: false, mode: "index" }
    }
});
<?php endif; ?>
</script>
<?php endif; ?>

