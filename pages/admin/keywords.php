 <?php
 requireLogin();
 requireRole('admin');
 require_once __DIR__ . '/../../includes/geo.php';
 geoInitTables();
 $userId = (int)($_GET['user_id'] ?? 0);
 $target = dbFetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
 if (!$target) { setFlash('error', '用户不存在'); redirect('index.php?route=admin/users'); }
 $pageTitle = '关键词排名 - ' . h($target['username']);
 
 $settings = geoGetAdminSettings();
 
if (isset($_GET['detect'])) {
    $kid = (int)$_GET['detect'];
    geoEnqueue($kid, $userId);
    setFlash('info', '检测已加入队列');
    redirect('index.php?route=admin/keywords&user_id=' . $userId);
}
 
 $keywords = dbFetchAll("SELECT * FROM geo_keywords WHERE user_id=? ORDER BY created_at DESC", [$userId]);
 $latestResults = [];
 foreach ($keywords as $kw) {
     $r = dbFetchOne("SELECT * FROM geo_results WHERE keyword_id=? ORDER BY checked_at DESC LIMIT 1", [$kw['id']]);
     if ($r) $latestResults[$kw['id']] = $r;
 }
 ?>
 
 <div class="page-header">
     <h1>关键词排名监测</h1>
     <p><?= h($target['username']) ?> 的关键词数据</p>
 </div>
 
 <div class="dashboard-card" style="margin-bottom:20px;">
     <table style="width:100%;border-collapse:collapse;font-size:14px;">
         <thead>
             <tr style="border-bottom:2px solid #e5e7eb;text-align:left;">
                 <th style="padding:10px 8px;">关键词</th>
                 <th style="padding:10px 8px;">品牌</th>
                 <th style="padding:10px 8px;">最近检测</th>
                <th style="padding:10px 8px;">状态</th>
                <th style="padding:10px 8px;">位置</th>
                 <th style="padding:10px 8px;">操作</th>
             </tr>
         </thead>
         <tbody>
             <?php if ($keywords): ?>
                 <?php foreach ($keywords as $kw): ?>
                 <?php $lr = $latestResults[$kw['id']] ?? null; ?>
                 <tr style="border-bottom:1px solid #f3f4f6;">
                     <td style="padding:10px 8px;font-weight:600;"><?= h($kw['keyword']) ?></td>
                     <td style="padding:10px 8px;"><?= h($kw['brand_name']) ?></td>
                     <td style="padding:10px 8px;font-size:13px;color:#9ca3af;"><?= $lr ? h($lr['checked_at']) : '未检测' ?></td>
                <td style="padding:10px 8px;">
                    <?php $qs = geoGetQueueStatus($kw['id']); ?>
                    <?php if ($qs['status'] === 'pending'): ?>
                        <span style="color:#f59e0b;">排队中...</span>
                    <?php elseif ($qs['status'] === 'processing'): ?>
                        <span style="color:#3b82f6;">检测中...</span>
                    <?php elseif ($lr): ?>
                        <span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;background:<?= $lr['brand_mentioned'] ? '#f0fdf4' : '#fef2f2' ?>;color:<?= $lr['brand_mentioned'] ? '#16a34a' : '#dc2626' ?>;">
                            <?= $lr['brand_mentioned'] ? '已提及' : '未提及' ?>
                        </span>
                    <?php elseif ($qs['status'] === 'failed'): ?>
                        <span style="color:#dc2626;">失败</span>
                    <?php else: ?><span style="color:#9ca3af;">-</span><?php endif; ?>
                </td>
                     <td style="padding:10px 8px;font-weight:600;">
                         <?php if ($lr && $lr['brand_mentioned']): ?>#<?= $lr['rank_position'] ?? '?' ?><?php else: ?><span style="color:#9ca3af;">-</span><?php endif; ?>
                     </td>
                     <td style="padding:10px 8px;">
                         <a href="index.php?route=admin/keywords&user_id=<?= $userId ?>&detect=<?= $kw['id'] ?>" class="btn" style="padding:4px 12px;font-size:12px;background:#4f46e5;color:#fff;border-radius:4px;text-decoration:none;">检测</a>
                     </td>
                 </tr>
                 <?php if ($lr && $lr['response_snippet']): ?>
                 <tr style="background:#f9fafb;">
                     <td colspan="6" style="padding:8px 8px 12px;font-size:13px;color:#6b7280;"><?= h(mb_substr($lr['response_snippet'], 0, 300)) ?></td>
                 </tr>
                 <?php endif; ?>
                 <?php endforeach; ?>
             <?php else: ?>
                 <tr><td colspan="6" style="text-align:center;padding:20px;color:#9ca3af;">暂无关键词</td></tr>
             <?php endif; ?>
         </tbody>
     </table>
 </div>
 
 <a href="index.php?route=admin/users&user_id=<?= $userId ?>" style="font-size:13px;color:#9ca3af;">返回</a>
<script><?php $active = dbFetchOne("SELECT id FROM geo_queue WHERE status IN ('pending','processing')"); if ($active): ?>setTimeout(function(){ location.reload(); }, 5000);<?php endif; ?></script>
