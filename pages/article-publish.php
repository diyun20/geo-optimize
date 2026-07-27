<?php
// ===== AJAX/Download 处理器（在输出 HTML 之前） =====
requireLogin();
requireFeatureOrVip('article-publish');
$user = currentUser();

// AJAX: 获取文章详情
if (isset($_GET['action']) && $_GET['action'] === 'get_article') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) { echo json_encode(['error'=>'无效的文章ID']); exit; }
    $art = dbFetchOne("SELECT id, topic, brand_name, keywords, content, created_at FROM geo_articles WHERE id=? AND user_id=?", [$id, $user['id']]);
    if (!$art) { echo json_encode(['error'=>'文章不存在']); exit; }
    echo json_encode($art, JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'download_tool') {
    $zipFile = __DIR__ . '/../geo_publisher/geo_publisher.zip';
    if (!file_exists($zipFile)) {
        http_response_code(404);
        echo '发布工具包不存在，请联系管理员';
        exit;
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="geo_publisher.zip"');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);
    exit;
}
// ===== 正常页面 =====
$pageTitle = '文章多平台推送 - GEO优化';

$articles = dbFetchAll(
    "SELECT id, topic, brand_name, keywords, LEFT(content,200) as excerpt, created_at, completed_at 
     FROM geo_articles WHERE user_id=? AND status='completed' 
     ORDER BY id DESC LIMIT 50",
    [$user['id']]
);
?>
<div class="page-header">
    <h1>文章多平台推送</h1>
    <p>将 AI 生成的文章一键推送到各大自媒体平台</p>
</div>

<?php if (empty($articles)): ?>
<div style="text-align:center;padding:60px 20px;background:#fff;border-radius:8px;border:1px solid #e5e7eb;">
    <div style="font-size:48px;margin-bottom:16px;">📝</div>
    <h2 style="font-size:18px;color:#374151;margin:0 0 8px;">暂无已完成的文章</h2>
    <p style="font-size:14px;color:#9ca3af;margin:0;">
        请先在 <a href="index.php?route=article-generate" style="color:#4f46e5;">文章一键生成</a> 中生成文章
    </p>
</div>
<?php else: ?>

<div style="display:flex;gap:20px;align-items:flex-start;">

<div style="flex:1;min-width:0;">
    <div class="dash-chart-wrap">
        <div class="dash-chart-header">
            <h3>选择文章与平台</h3>
            <span style="font-size:12px;color:#6b7280;">共 <?= count($articles) ?> 篇</span>
        </div>
        <div class="dash-chart-body">
            <div style="margin-bottom:16px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                <span style="font-size:13px;font-weight:600;color:#374151;">目标平台：</span>
                <label style="font-size:13px;display:inline-flex;align-items:center;gap:3px;cursor:pointer;padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#f9fafb;">
                    <input type="checkbox" class="platform-all" checked onchange="toggleAllPlatforms(this)"> 全选
                </label>
<?php
$platforms = [
    ['key'=>'xiaohongshu','label'=>'小红书','icon'=>'📕'],
    ['key'=>'tencent','label'=>'视频号','icon'=>'📹'],
    ['key'=>'douyin','label'=>'抖音','icon'=>'🎵'],
    ['key'=>'kuaishou','label'=>'快手','icon'=>'📸'],
    ['key'=>'bilibili','label'=>'B站','icon'=>'📺'],
    ['key'=>'baijiahao','label'=>'百家号','icon'=>'📰'],
    ['key'=>'tiktok','label'=>'TikTok','icon'=>'🌍'],
    ['key'=>'instagram','label'=>'Instagram','icon'=>'📷'],
    ['key'=>'facebook','label'=>'Facebook','icon'=>'💙'],
];
foreach ($platforms as $p):
?>
                <label style="font-size:13px;display:inline-flex;align-items:center;gap:3px;cursor:pointer;padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:#fff;" class="platform-label">
                    <input type="checkbox" class="platform-cb" value="<?= $p['key'] ?>" checked> <?= $p['icon'] ?> <?= $p['label'] ?>
                </label>
<?php endforeach; ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" class="article-all" checked onchange="toggleAllArticles(this)"></th>
                        <th>文章主题</th>
                        <th style="width:100px;">品牌</th>
                        <th style="width:80px;">生成时间</th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($articles as $art): ?>
                    <tr class="article-row" style="cursor:pointer;">
                        <td><input type="checkbox" class="article-cb" value="<?= $art['id'] ?>" checked></td>
                        <td>
                            <div style="font-weight:500;color:#1f2937;font-size:13px;"><?= h($art['topic']) ?></div>
                            <div style="font-size:12px;color:#9ca3af;margin-top:2px;"><?= h(mb_substr($art['excerpt'],0,60)) ?>...</div>
                        </td>
                        <td style="font-size:12px;color:#6b7280;"><?= h($art['brand_name']) ?></td>
                        <td style="font-size:12px;color:#9ca3af;white-space:nowrap;"><?= h(substr($art['created_at'],0,10)) ?></td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb;display:flex;gap:10px;align-items:center;">
                <button class="btn btn-primary" onclick="exportConfig()" style="padding:10px 28px;font-size:14px;">
                    📥 导出配置
                </button>
                <span style="font-size:12px;color:#6b7280;">导出 JSON，在本地发布工具中导入使用</span>
            </div>
        </div>
    </div>
</div>

<div style="width:360px;flex-shrink:0;">
    <div class="dash-chart-wrap" style="margin-bottom:16px;">
        <div class="dash-chart-header"><h3>📋 使用步骤</h3></div>
        <div class="dash-chart-body" style="font-size:13px;line-height:2;color:#374151;">
            <ol style="padding-left:20px;margin:0;">
                <li>选择文章和目标平台，点击「导出配置」</li>
                <li>下载发布工具包，解压到电脑</li>
                <li>双击 <code style="background:#f3f4f6;padding:1px 6px;border-radius:4px;font-size:12px;color:#4f46e5;">install.bat</code> 一键安装</li>
                <li>双击 <code style="background:#f3f4f6;padding:1px 6px;border-radius:4px;font-size:12px;color:#4f46e5;">start.bat</code> 启动工具</li>
                <li>在工具中导入配置，点击发布</li>
            </ol>
        </div>
    </div>

        <div class="dash-chart-wrap">
        <div class="dash-chart-header"><h3>⬇️ 安装发布工具</h3></div>
        <div class="dash-chart-body">
            <div style="font-size:13px;color:#374151;margin-bottom:12px;">
                <strong>方法一：一键安装（推荐）</strong>
            </div>
            <div style="background:#1f2937;color:#d1d5db;font-size:13px;padding:14px 16px;border-radius:8px;font-family:Consolas,'Courier New',monospace;line-height:1.7;margin-bottom:12px;overflow-x:auto;white-space:nowrap;">
                <span style="color:#34d399;">PS&gt;</span> iex ((New-Object Net.WebClient).DownloadString("http://geo.diyunuu.cn/geo_publisher/install.ps1"))
            </div>
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px;">
                <button onclick="copyInstallCmd()" style="padding:6px 14px;font-size:12px;background:#4f46e5;color:#fff;border:none;border-radius:6px;cursor:pointer;">📋 复制命令</button>
                <span id="copyTip" style="font-size:11px;color:#9ca3af;"></span>
            </div>
            <div style="font-size:13px;color:#374151;margin-bottom:8px;">
                <strong>方法二：手动下载</strong>
            </div>
            <a href="index.php?route=article-publish&action=download_tool" class="btn btn-primary" style="display:inline-flex;align-items:center;padding:8px 20px;text-decoration:none;font-size:13px;">
                下载 geo_publisher.zip
            </a>
            <span style="font-size:11px;color:#9ca3af;margin-left:8px;">解压后双击 install.bat</span>
        </div>
    </div>
</div>

</div>

<script>
function toggleAllPlatforms(el) {
    document.querySelectorAll('.platform-cb').forEach(function(cb) { cb.checked = el.checked; });
}
function toggleAllArticles(el) {
    document.querySelectorAll('.article-cb').forEach(function(cb) { cb.checked = el.checked; });
}

function exportConfig() {
    var selectedArticles = [];
    document.querySelectorAll('.article-cb:checked').forEach(function(cb) {
        var row = cb.closest('tr');
        var cells = row.querySelectorAll('td');
        selectedArticles.push({
            id: parseInt(cb.value),
            title: cells[1].querySelector('div:first-child').textContent.trim()
        });
    });

    var selectedPlatforms = [];
    document.querySelectorAll('.platform-cb:checked').forEach(function(cb) {
        selectedPlatforms.push(cb.value);
    });

    if (selectedArticles.length === 0) { alert('请至少选择一篇文章'); return; }
    if (selectedPlatforms.length === 0) { alert('请至少选择一个目标平台'); return; }

    // 用第一篇已选文章获取完整内容
    var firstId = selectedArticles[0].id;
    fetch('index.php?route=article-publish&action=get_article&id=' + firstId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { alert(data.error); return; }
            var config = {
                version: '1.0',
                export_time: new Date().toISOString(),
                title: data.topic,
                content: data.content,
                brand: data.brand_name,
                tags: data.keywords || '',
                platforms: selectedPlatforms,
                article_ids: selectedArticles.map(function(a) { return a.id; })
            };
            var blob = new Blob([JSON.stringify(config, null, 2)], {type:'application/json'});
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'geo_publish_' + Date.now() + '.json';
            a.click();
            URL.revokeObjectURL(url);
        })
        .catch(function(err) { alert('获取文章详情失败: ' + err); });
}



function copyInstallCmd() {
    var cmd = 'iex ((New-Object Net.WebClient).DownloadString(\"http://' + 'geo.diyunuu.cn' + '/geo_publisher/install.ps1\"))';
    navigator.clipboard.writeText(cmd).then(function() {
        var tip = document.getElementById('copyTip');
        if (tip) { tip.textContent = '✅ 已复制'; setTimeout(function(){ tip.textContent = ''; }, 2000); }
    }).catch(function() { alert('复制失败'); });
}

</script>

<style>
.article-row:hover { background:#f9fafb; }
.article-row input[type="checkbox"] { cursor:pointer; }
.platform-label:hover { border-color:#a5b4fc; background:#eef2ff; }
</style>
<?php endif; ?>

