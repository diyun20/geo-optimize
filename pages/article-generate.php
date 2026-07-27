<?php
// ── AJAX 请求：最先处理，返回纯 JSON ──
if (isset($_GET['action'])) {
    $ajaxAction = $_GET['action'];

    // AI 美化：提交队列
    if ($ajaxAction === 'ai_beautify') {
        while (ob_get_level()) ob_end_clean();
        error_reporting(0); ini_set('display_errors', 0);
        header('Content-Type: application/json; charset=utf-8');

        if (!isLoggedIn()) {
            echo json_encode(['error' => '请先登录'], JSON_UNESCAPED_UNICODE); exit;
        }

        try {
            // 确保表存在
            dbExecute("CREATE TABLE IF NOT EXISTS `geo_articles` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `topic` VARCHAR(500) DEFAULT NULL,
                `brand_name` VARCHAR(200) DEFAULT NULL,
                `keywords` TEXT DEFAULT NULL,
                `content` LONGTEXT DEFAULT NULL,
                `status` ENUM('pending','processing','completed','failed') DEFAULT 'completed',
                `created_at` DATETIME DEFAULT NULL,
                `started_at` DATETIME DEFAULT NULL,
                `completed_at` DATETIME DEFAULT NULL,
                INDEX `idx_user` (`user_id`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {}

        $user = currentUser();
        // 试用限额检查
        $remaining = trialLimitRemaining('article');
        if ($remaining === 0) {
            echo json_encode(['error' => '试用账户文章生成数量已达上限，请升级VIP'], JSON_UNESCAPED_UNICODE); exit;
        }
        $title    = trim($_POST['title'] ?? '');
        $brand    = trim($_POST['brand'] ?? '');
        $content  = trim($_POST['content'] ?? '');
        $provider = trim($_POST['provider'] ?? 'doubao');
        if (empty($title) || empty($content)) {
            echo json_encode(['error'=>'请先填写文章标题和内容'], JSON_UNESCAPED_UNICODE); exit;
        }

        try {
            // 存入队列
            dbExecute("INSERT INTO geo_articles (user_id,topic,brand_name,keywords,content,status,created_at) VALUES (?,?,?,?,?,'pending',NOW())",
                [$user['id'], $title, $brand,
                 json_encode(['type'=>'beautify','provider'=>$provider,'original_title'=>$title,'original_content'=>$content], JSON_UNESCAPED_UNICODE),
                 '(排队中...)']);
            $newId = dbFetchOne("SELECT LAST_INSERT_ID() as id")['id'];

            // 触发后台 worker
            runBackgroundProcess("background_article.php", [(int)$newId]);

            echo json_encode(['ok'=>true, 'queued'=>true, 'id'=>(int)$newId], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['error'=>'服务器错误：'.$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // 轮询美化状态
    if ($ajaxAction === 'poll_beautify') {
        while (ob_get_level()) ob_end_clean();
        error_reporting(0); ini_set('display_errors', 0);
        header('Content-Type: application/json; charset=utf-8');
        if (!isLoggedIn()) {
            echo json_encode(['error'=>'请先登录'], JSON_UNESCAPED_UNICODE); exit;
        }
        try {
            $user = currentUser();
            $articleId = (int)($_GET['id'] ?? 0);
            $row = dbFetchOne("SELECT * FROM geo_articles WHERE id=? AND user_id=?", [$articleId, $user['id']]);
            if (!$row) { echo json_encode(['error'=>'文章不存在'], JSON_UNESCAPED_UNICODE); exit; }
            echo json_encode([
                'status'  => $row['status'],
                'title'   => $row['topic'],
                'content' => $row['content'],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['error'=>'服务器错误：'.$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if ($ajaxAction === 'batch_delete') {
        while (ob_get_level()) ob_end_clean();
        error_reporting(0); ini_set('display_errors', 0);
        header('Content-Type: application/json; charset=utf-8');
        if (!isLoggedIn()) { echo json_encode(['error'=>'请先登录'], JSON_UNESCAPED_UNICODE); exit; }
        try {
            $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
            if (empty($ids)) { echo json_encode(['ok'=>false, 'error'=>'请选择要删除的文章'], JSON_UNESCAPED_UNICODE); exit; }
            $user = currentUser();
            $idList = array_map('intval', (array)$ids);
            $placeholders = implode(',', array_fill(0, count($idList), '?'));
            $params = array_merge($idList, [$user['id']]);
            dbExecute("DELETE FROM geo_articles WHERE id IN ($placeholders) AND user_id=?", $params);
            echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['error'=>'服务器错误：'.$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}
// ── AJAX 处理结束 ──

set_time_limit(180);
requireLogin();
requireFeatureOrVip('article-generate');
require_once __DIR__ . '/../includes/geo.php';
$user = currentUser();
$pageTitle = '文章创作 - GEO优化';

// 确保表存在
try {
    dbExecute("CREATE TABLE IF NOT EXISTS `geo_articles` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`user_id` INT UNSIGNED NOT NULL,`topic` VARCHAR(500) NOT NULL DEFAULT '',`brand_name` VARCHAR(200) NOT NULL DEFAULT '',`keywords` TEXT DEFAULT NULL,`content` LONGTEXT NOT NULL,`status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'completed',`created_at` DATETIME NOT NULL,`started_at` DATETIME DEFAULT NULL,`completed_at` DATETIME DEFAULT NULL,INDEX `idx_user` (`user_id`),FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}
try { dbExecute("ALTER TABLE geo_articles ADD COLUMN `keywords` TEXT DEFAULT NULL AFTER `brand_name`"); } catch (Exception $e) {}

$company = dbFetchOne("SELECT * FROM company_info WHERE user_id=?", [$user['id']]);
$defaultBrand = $company['company_name'] ?? '';

// 获取所有可用 API 提供商
geoInitApiProviders();
$providers = dbFetchAll("SELECT * FROM geo_api_providers");
if (empty($providers)) {
    // 兜底：用默认配置
    $dp = geoGetApiProvider('doubao');
    $ds = geoGetApiProvider('deepseek');
    $providers = [];
    if ($dp['api_key']) $providers[] = $dp;
    if ($ds['api_key']) $providers[] = $ds;
}
$defaultProvider = !empty($providers) ? $providers[0]['provider'] : 'doubao';

// 处理保存
$savedId = 0;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $topic = trim($_POST['topic'] ?? '');
    $brand = trim($_POST['brand_name'] ?? '');
    $content = trim($_POST['article_content'] ?? '');
    if (empty($topic)) { $error = '请输入文章标题'; }
    elseif (empty($content)) { $error = '请输入文章内容'; }
    else {
        dbExecute("INSERT INTO geo_articles (user_id,topic,brand_name,keywords,content,status,created_at) VALUES (?,?,?,'',?,'completed',NOW())", [$user['id'], $topic, $brand, $content]);
        $savedId = dbFetchOne("SELECT LAST_INSERT_ID() as id")['id'];
        setFlash('success', '文章已保存');
        redirect('index.php?route=article-generate&id=' . $savedId);
    }
}

// 读取已有文章
$viewArticle = null;
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($viewId > 0) {
    $viewArticle = dbFetchOne("SELECT * FROM geo_articles WHERE id=? AND user_id=?", [$viewId, $user['id']]);
}
$recentArticles = dbFetchAll("SELECT id, topic, brand_name, created_at FROM geo_articles WHERE user_id=? ORDER BY id DESC LIMIT 30", [$user['id']]);
?>
<div class="page-header">
    <h1>文章创作</h1>
    <p>手动撰写文章，使用 AI 优化润色后保存</p>
</div>

<?php if ($error): ?>
<div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<div style="display:flex;gap:20px;align-items:flex-start;">

<div style="flex:1;min-width:0;">
    <div class="dash-chart-wrap">
        <div class="dash-chart-header">
            <h3>文章编辑器</h3>
            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                <select id="providerSelect" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;">
                    <?php foreach ($providers as $p): ?>
                    <option value="<?= h($p['provider']) ?>" <?= $p['provider'] === $defaultProvider ? 'selected' : '' ?>><?= h($p['provider'] === 'doubao' ? '豆包' : ($p['provider'] === 'deepseek' ? 'DeepSeek' : h($p['provider']))) ?> (<?= h($p['model']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-primary" onclick="aiBeautify()" id="beautifyBtn" style="padding:6px 16px;font-size:13px;">✨ AI 美化</button>
            </div>
        </div>
        <div class="dash-chart-body">
            <form method="post" action="index.php?route=article-generate">
            <div id="beautifyStatus" style="margin-bottom:10px;display:none;padding:10px 14px;border-radius:6px;font-size:13px;"></div>
                <div class="form-group">
                    <label for="topic" style="font-weight:600;font-size:14px;color:#374151;display:block;margin-bottom:6px;">文章标题</label>
                    <input type="text" id="topic" name="topic" value="<?= h($viewArticle ? $viewArticle['topic'] : '') ?>" placeholder="输入文章标题" style="width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-sizing:border-box;">
                </div>
                <div class="form-group" style="margin-top:12px;">
                    <label for="brand_name" style="font-weight:600;font-size:14px;color:#374151;display:block;margin-bottom:6px;">品牌名称</label>
                    <input type="text" id="brand_name" name="brand_name" value="<?= h($viewArticle ? $viewArticle['brand_name'] : $defaultBrand) ?>" placeholder="品牌名称（可选）" style="width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-sizing:border-box;">
                </div>
                <div class="form-group" style="margin-top:12px;">
                    <label for="article_content" style="font-weight:600;font-size:14px;color:#374151;display:block;margin-bottom:6px;">文章内容 <span style="font-weight:400;font-size:12px;color:#9ca3af;">可直接输入，也可粘贴草稿后点击 AI 美化</span></label>
                    <textarea id="article_content" name="article_content" rows="18" placeholder="在此撰写或粘贴文章内容..." style="width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-sizing:border-box;line-height:1.7;resize:vertical;font-family:inherit;"><?= h($viewArticle ? $viewArticle['content'] : '') ?></textarea>
                </div>
                <div style="margin-top:16px;display:flex;gap:10px;align-items:center;">
                    <button type="submit" name="save" class="btn btn-primary" style="padding:10px 28px;font-size:14px;background:#059669;color:#fff;border:none;border-radius:6px;cursor:pointer;">💾 保存文章</button>
                    <?php if ($viewArticle): ?>
                    <span style="font-size:12px;color:#6b7280;">上次保存：<?= h($viewArticle['created_at']) ?></span>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

</div>

<div style="width:320px;flex-shrink:0;background:#fff;border-radius:12px;border:1px solid #f3f4f6;box-shadow:0 1px 3px rgba(0,0,0,0.04);overflow:hidden;">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:20px 24px 0;">
        <h3>历史文章</h3>
        <span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:#eef2ff;color:#4f46e5;"><?= count($recentArticles) ?></span>
    </div>
    <?php if (!empty($recentArticles)): ?>
    <div style="padding:6px 16px;display:flex;gap:6px;align-items:center;border-bottom:1px solid #f3f4f6;">
        <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;"><input type="checkbox" id="selectAllHistory" onchange="toggleAllHistory(this)" style="cursor:pointer;"> 全选</label>
        <button type="button" class="btn btn-sm" onclick="batchDelete()" style="padding:3px 10px;font-size:11px;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:4px;cursor:pointer;">🗑 删除选中</button>
        <span style="font-size:11px;color:#9ca3af;" id="selectedCount">已选 0 篇</span>
    </div>
    <?php endif; ?>
    <div style="padding:16px 0 0;">
        <?php if (empty($recentArticles)): ?>
        <div style="text-align:center;padding:40px 16px;color:#9ca3af;font-size:13px;">暂无文章</div>
        <?php else: ?>
            <?php foreach ($recentArticles as $art): ?>
            <div style="display:flex;align-items:center;padding:0 4px;border-bottom:1px solid #f3f4f6;">
                <input type="checkbox" class="history-cb" value="<?= $art['id'] ?>" onchange="updateSelectedCount()" style="margin:0 4px;cursor:pointer;flex-shrink:0;">
                <a href="index.php?route=article-generate&id=<?= $art['id'] ?>" style="display:block;flex:1;padding:14px 8px;text-decoration:none;color:inherit;transition:background 0.15s;<?= $viewId == $art['id'] ? 'background:#eef2ff;' : '' ?>">
                    <div style="font-size:13px;font-weight:600;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:4px;"><?= h($art['topic'] ?: '(无标题)') ?></div>
                    <div style="font-size:12px;color:#9ca3af;"><?= h($art['brand_name']) ?><span style="margin:0 6px;color:#d1d5db;">|</span><?= h(substr($art['created_at'],0,10)) ?></div>
                </a>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</div>

<script>
var _beautifyTimer = null;

function aiBeautify() {
    var title = document.getElementById('topic').value;
    var brand = document.getElementById('brand_name').value;
    var content = document.getElementById('article_content').value;
    var provider = document.getElementById('providerSelect').value;
    if (!title.trim() || !content.trim()) { alert('请先填写文章标题和内容'); return; }

    var btn = document.getElementById('beautifyBtn');
    var status = document.getElementById('beautifyStatus');
    btn.disabled = true;
    btn.textContent = '⏳ 提交中...';
    status.style.display = 'block';
    status.style.background = '#fefce8';
    status.style.color = '#92400e';
    status.textContent = '⏳ 正在提交到后台队列...';

    var formData = new FormData();
    formData.append('provider', provider);
    formData.append('title', title);
    formData.append('brand', brand);
    formData.append('content', content);

    fetch('index.php?route=article-generate&action=ai_beautify', {
        method: 'POST',
        credentials: 'include',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.error) {
            status.style.background = '#fef2f2';
            status.style.color = '#991b1b';
            status.textContent = '❌ ' + d.error;
            btn.disabled = false;
            btn.textContent = '✨ AI 美化';
        } else {
            status.textContent = '⏳ 后台处理中，请稍候...';
            pollBeautify(d.id, btn, status);
        }
    })
    .catch(function(err) {
        status.style.background = '#fef2f2';
        status.style.color = '#991b1b';
        status.textContent = '❌ 请求失败: ' + err;
        btn.disabled = false;
        btn.textContent = '✨ AI 美化';
    });
}

function pollBeautify(articleId, btn, status) {
    if (_beautifyTimer) clearInterval(_beautifyTimer);
    var count = 0;
    _beautifyTimer = setInterval(function() {
        count++;
        fetch('index.php?route=article-generate&action=poll_beautify&id=' + articleId, { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.status === 'completed') {
                clearInterval(_beautifyTimer);
                document.getElementById('topic').value = d.title;
                document.getElementById('article_content').value = d.content;
                status.style.background = '#f0fdf4';
                status.style.color = '#166534';
                status.textContent = '✅ AI 美化完成！标题和内容已更新';
                btn.disabled = false;
                btn.textContent = '✨ AI 美化';
            } else if (d.status === 'failed') {
                clearInterval(_beautifyTimer);
                status.style.background = '#fef2f2';
                status.style.color = '#991b1b';
                status.textContent = '❌ 后台处理失败，请重试';
                btn.disabled = false;
                btn.textContent = '✨ AI 美化';
            } else {
                var dots = ''.padStart((count % 4) + 1, '.');
                status.textContent = '⏳ 后台处理中' + dots + ' (' + (d.status === 'processing' ? 'AI正在写作' : '排队等待') + ')';
            }
        });
    }, 3000);
}

function editArticle(id) {
    window.location.href = 'index.php?route=article-generate&id=' + id;
}

function copyContent() {
    var el = document.getElementById('preview-content');
    if (!el) return;
    navigator.clipboard.writeText(el.textContent).then(function() {
        alert('已复制');
    }).catch(function() {
        alert('复制失败');
    });
}

function toggleAllHistory(el) {
    document.querySelectorAll('.history-cb').forEach(function(cb) { cb.checked = el.checked; });
    updateSelectedCount();
}

function updateSelectedCount() {
    var count = document.querySelectorAll('.history-cb:checked').length;
    var el = document.getElementById('selectedCount');
    if (el) el.textContent = '\u5df2\u9009 ' + count + ' \u7bc7';
}

function batchDelete() {
    var ids = [];
    document.querySelectorAll('.history-cb:checked').forEach(function(cb) { ids.push(cb.value); });
    if (ids.length === 0) { alert('\u8bf7\u5148\u9009\u62e9\u8981\u5220\u9664\u7684\u6587\u7ae0'); return; }
    if (!confirm('\u786e\u5b9a\u5220\u9664\u9009\u4e2d\u7684 ' + ids.length + ' \u7bc7\u6587\u7ae0\u5417\uff1f\u6b64\u64cd\u4f5c\u4e0d\u53ef\u64a4\u9500\u3002')) return;

    var formData = new FormData();
    ids.forEach(function(id) { formData.append('ids[]', id); });

    fetch('index.php?route=article-generate&action=batch_delete', {
        method: 'POST',
        credentials: 'include',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            alert('\u5220\u9664\u6210\u529f');
            location.reload();
        } else {
            alert('\u5220\u9664\u5931\u8d25: ' + (data.error || '\u672a\u77e5\u9519\u8bef'));
        }
    })
    .catch(function(err) {
        alert('\u8bf7\u6c42\u5931\u8d25: ' + err);
    });
}
</script>

