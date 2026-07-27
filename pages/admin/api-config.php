<?php
// ── AJAX: 测试 API 连接 ──
if (isset($_GET['action']) && $_GET['action'] === 'test_api') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    if (!isLoggedIn() || !(isAdmin() || isAgent())) {
        echo json_encode(['ok'=>false, 'error'=>'未授权'], JSON_UNESCAPED_UNICODE); exit;
    }

    $provider = trim($_POST['provider'] ?? '');
    $key      = trim($_POST['api_key'] ?? '');
    $endpoint = trim($_POST['endpoint'] ?? '');
    $model    = trim($_POST['model'] ?? '');

    if (empty($key) || empty($endpoint)) {
        echo json_encode(['ok'=>false, 'error'=>'请填写 API Key 和 Endpoint'], JSON_UNESCAPED_UNICODE); exit;
    }

    require_once __DIR__ . '/../../includes/geo.php';
    $payload = json_encode([
        'model'    => $model ?: 'gpt-3.5-turbo',
        'messages' => [['role'=>'user','content'=>'hi']],
        'max_tokens' => 5
    ]);

    $start  = microtime(true);
    $result = geoApiCall($endpoint, $key, $payload, 15);
    $elapsed = round((microtime(true) - $start) * 1000);

    if ($result['error']) {
        echo json_encode(['ok'=>false, 'error'=>$result['error'], 'ms'=>$elapsed], JSON_UNESCAPED_UNICODE); exit;
    }
    if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
        $body = substr($result['response'] ?? '', 0, 200);
        echo json_encode(['ok'=>false, 'error'=>"HTTP {$result['http_code']}: {$body}", 'ms'=>$elapsed], JSON_UNESCAPED_UNICODE); exit;
    }

    echo json_encode(['ok'=>true, 'ms'=>$elapsed, 'model'=>$model], JSON_UNESCAPED_UNICODE);
    exit;
}
// ── AJAX 处理结束 ──

requireLogin();
requireAdminOrAgent();
require_once __DIR__ . '/../../includes/geo.php';
$me = currentUser();
$pageTitle = 'API配置 - GEO优化';

$isGlobal = isAdmin() && empty($_GET['agent_id']);
$editAgentId = isAdmin() && !empty($_GET['agent_id']) ? (int)$_GET['agent_id'] : $me['id'];

if (isAgent() && $editAgentId !== (int)$me['id']) {
    setFlash('error', '无权操作');
    redirect('index.php?route=admin/api-config');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dKey  = trim($_POST['doubao_key'] ?? '');
    $dEp   = trim($_POST['doubao_endpoint'] ?? '');
    $dMod  = trim($_POST['doubao_model'] ?? '');
    $dsKey = trim($_POST['deepseek_key'] ?? '');
    $dsEp  = trim($_POST['deepseek_endpoint'] ?? '');
    $dsMod = trim($_POST['deepseek_model'] ?? '');

    if ($isGlobal) {
        geoSaveApiProvider('doubao', $dKey, $dEp, $dMod);
        geoSaveApiProvider('deepseek', $dsKey, $dsEp, $dsMod);
    } else {
        $exists = dbFetchOne("SELECT id FROM agent_api_config WHERE agent_id=?", [$editAgentId]);
        if ($exists) {
            dbExecute("UPDATE agent_api_config SET doubao_key=?,doubao_endpoint=?,doubao_model=?,deepseek_key=?,deepseek_endpoint=?,deepseek_model=?,updated_at=NOW() WHERE agent_id=?", [$dKey,$dEp,$dMod,$dsKey,$dsEp,$dsMod,$editAgentId]);
        } else {
            dbExecute("INSERT INTO agent_api_config (agent_id,doubao_key,doubao_endpoint,doubao_model,deepseek_key,deepseek_endpoint,deepseek_model,updated_at) VALUES (?,?,?,?,?,?,?,NOW())", [$editAgentId,$dKey,$dEp,$dMod,$dsKey,$dsEp,$dsMod]);
        }
    }
    setFlash('success', 'API配置已保存');
    redirect('index.php?route=admin/api-config' . (!$isGlobal && isAdmin() ? "&agent_id={$editAgentId}" : ''));
}

// 加载配置
if ($isGlobal) {
    $dbProvider = geoGetApiProvider('doubao');
    $dsProvider = geoGetApiProvider('deepseek');
    $cfg = [
        'doubao_key'      => $dbProvider['api_key'] ?? '',
        'doubao_endpoint' => $dbProvider['api_endpoint'] ?? 'https://ark.cn-beijing.volces.com/api/compatible',
        'doubao_model'    => $dbProvider['model'] ?? 'doubao-seed-evolving',
        'deepseek_key'      => $dsProvider['api_key'] ?? '',
        'deepseek_endpoint' => $dsProvider['api_endpoint'] ?? 'https://api.deepseek.com/chat/completions',
        'deepseek_model'    => $dsProvider['model'] ?? 'deepseek-v4-flash',
    ];
    $pageDesc = '全局 API 配置，直管用户使用此密钥';
} else {
    $cfg = dbFetchOne("SELECT * FROM agent_api_config WHERE agent_id=?", [$editAgentId]) ?: [];
    $agentName = dbFetchOne("SELECT username FROM users WHERE id=?", [$editAgentId])['username'] ?? '';
    $pageDesc = "代理商 {$agentName} 的 API 密钥，名下用户使用此密钥";
}

$agents = isAdmin() ? dbFetchAll("SELECT id,username FROM users WHERE role='agent' ORDER BY id") : [];
?>

<div class="admin-page-header">
    <h1>🔑 API 配置</h1>
    <p><?=h($pageDesc)?></p>
</div>

<?php if (isAdmin()): ?>
<div style="margin-bottom:16px;display:flex;gap:8px;">
    <a href="index.php?route=admin/api-config" style="padding:6px 14px;border-radius:6px;text-decoration:none;font-size:13px;<?=$isGlobal?'background:#4f46e5;color:#fff;':'background:#f3f4f6;color:#374151;'?>">全局配置</a>
    <?php foreach($agents as $a): ?>
    <a href="index.php?route=admin/api-config&agent_id=<?=$a['id']?>" style="padding:6px 14px;border-radius:6px;text-decoration:none;font-size:13px;<?=$editAgentId==$a['id']?'background:#4f46e5;color:#fff;':'background:#f3f4f6;color:#374151;'?>"><?=h($a['username'])?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-body" style="padding:20px;">
        <form method="post">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                        <h4 style="margin:0;color:#4f46e5;">🟣 豆包 (Doubao)</h4>
                        <button type="button" id="btn_test_doubao" style="padding:4px 12px;border:1px solid #4f46e5;border-radius:4px;background:#fff;color:#4f46e5;cursor:pointer;font-size:12px;" onclick="var p='doubao';var b=document.getElementById('btn_test_'+p);var r=document.getElementById('result_'+p);var k=document.getElementById(p+'_key').value.trim();var e=document.getElementById(p+'_endpoint').value.trim();var m=document.getElementById(p+'_model').value.trim();if(!k||!e){alert('请先填写 API Key 和 Endpoint');return;}b.disabled=true;b.textContent='⏳ 测试中...';r.style.display='block';r.style.background='#fefce8';r.style.color='#92400e';r.textContent='⏳ 正在测试连接...';var f=new FormData();f.append('provider',p);f.append('api_key',k);f.append('endpoint',e);f.append('model',m);fetch('index.php?route=admin/api-config&action=test_api',{method:'POST',credentials:'include',body:f}).then(function(x){return x.json()}).then(function(d){if(d.ok){r.style.background='#f0fdf4';r.style.color='#166534';r.textContent='✅ 连接成功！延迟 '+d.ms+'ms，模型: '+d.model}else{r.style.background='#fef2f2';r.style.color='#991b1b';r.textContent='❌ '+(d.error||'未知错误')+' ('+d.ms+'ms)'}}).catch(function(err){r.style.background='#fef2f2';r.style.color='#991b1b';r.textContent='❌ 请求失败: '+err}).finally(function(){b.disabled=false;b.textContent='🔗 测试连接'})">🔗 测试连接</button>
                    </div>
                    <div id="result_doubao" style="display:none;margin-bottom:8px;padding:6px 10px;border-radius:4px;font-size:12px;"></div>
                    <div style="margin-bottom:10px;"><label style="font-size:12px;color:#6b7280;">API Key</label>
                    <div style="position:relative;">
                    <input type="password" name="doubao_key" id="doubao_key" value="<?=h($cfg['doubao_key']??'')?>" placeholder="sk-xxx" style="width:100%;padding:8px 36px 8px 8px;border:1px solid #d1d5db;border-radius:6px;margin-top:4px;">
                    <span onclick="toggleEye('doubao_key',this)" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);cursor:pointer;user-select:none;font-size:16px;">👁</span>
                    </div></div>
                    <div style="margin-bottom:10px;"><label style="font-size:12px;color:#6b7280;">Endpoint</label><input type="text" name="doubao_endpoint" id="doubao_endpoint" value="<?=h($cfg['doubao_endpoint']??'https://ark.cn-beijing.volces.com/api/compatible')?>" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;margin-top:4px;"></div>
                    <div><label style="font-size:12px;color:#6b7280;">Model</label><input type="text" name="doubao_model" id="doubao_model" value="<?=h($cfg['doubao_model']??'doubao-seed-evolving')?>" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;margin-top:4px;"></div>
                </div>
                <div style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                        <h4 style="margin:0;color:#10b981;">🟢 DeepSeek</h4>
                        <button type="button" id="btn_test_deepseek" style="padding:4px 12px;border:1px solid #10b981;border-radius:4px;background:#fff;color:#10b981;cursor:pointer;font-size:12px;" onclick="var p='deepseek';var b=document.getElementById('btn_test_'+p);var r=document.getElementById('result_'+p);var k=document.getElementById(p+'_key').value.trim();var e=document.getElementById(p+'_endpoint').value.trim();var m=document.getElementById(p+'_model').value.trim();if(!k||!e){alert('请先填写 API Key 和 Endpoint');return;}b.disabled=true;b.textContent='⏳ 测试中...';r.style.display='block';r.style.background='#fefce8';r.style.color='#92400e';r.textContent='⏳ 正在测试连接...';var f=new FormData();f.append('provider',p);f.append('api_key',k);f.append('endpoint',e);f.append('model',m);fetch('index.php?route=admin/api-config&action=test_api',{method:'POST',credentials:'include',body:f}).then(function(x){return x.json()}).then(function(d){if(d.ok){r.style.background='#f0fdf4';r.style.color='#166534';r.textContent='✅ 连接成功！延迟 '+d.ms+'ms，模型: '+d.model}else{r.style.background='#fef2f2';r.style.color='#991b1b';r.textContent='❌ '+(d.error||'未知错误')+' ('+d.ms+'ms)'}}).catch(function(err){r.style.background='#fef2f2';r.style.color='#991b1b';r.textContent='❌ 请求失败: '+err}).finally(function(){b.disabled=false;b.textContent='🔗 测试连接'})">🔗 测试连接</button>
                    </div>
                    <div id="result_deepseek" style="display:none;margin-bottom:8px;padding:6px 10px;border-radius:4px;font-size:12px;"></div>
                    <div style="margin-bottom:10px;"><label style="font-size:12px;color:#6b7280;">API Key</label>
                    <div style="position:relative;">
                    <input type="password" name="deepseek_key" id="deepseek_key" value="<?=h($cfg['deepseek_key']??'')?>" placeholder="sk-xxx（留空则不启用）" style="width:100%;padding:8px 36px 8px 8px;border:1px solid #d1d5db;border-radius:6px;margin-top:4px;">
                    <span onclick="toggleEye('deepseek_key',this)" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);cursor:pointer;user-select:none;font-size:16px;">👁</span>
                    </div></div>
                    <div style="margin-bottom:10px;"><label style="font-size:12px;color:#6b7280;">Endpoint</label><input type="text" name="deepseek_endpoint" id="deepseek_endpoint" value="<?=h($cfg['deepseek_endpoint']??'https://api.deepseek.com/chat/completions')?>" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;margin-top:4px;"></div>
                    <div><label style="font-size:12px;color:#6b7280;">Model</label><input type="text" name="deepseek_model" id="deepseek_model" value="<?=h($cfg['deepseek_model']??'deepseek-v4-flash')?>" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;margin-top:4px;"></div>
                </div>
            </div>
            <div style="margin-top:12px;text-align:right;">
                <button type="submit" style="padding:10px 24px;background:#4f46e5;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">💾 保存配置</button>
            </div>
        </form>
    </div>
</div>
<script>
function toggleEye(id, el) {
    var inp = document.getElementById(id);
    if (inp.type === 'password') { inp.type = 'text'; el.textContent = '🙈'; }
    else { inp.type = 'password'; el.textContent = '👁'; }
}

function testApi(provider) {
    var btn = document.getElementById('btn_test_' + provider);
    var res = document.getElementById('result_' + provider);
    var keyId = provider + '_key';
    var epId  = provider + '_endpoint';
    var mdlId = provider + '_model';

    var key  = document.getElementById(keyId).value.trim();
    var ep   = document.getElementById(epId).value.trim();
    var mdl  = document.getElementById(mdlId).value.trim();

    if (!key || !ep) { alert('请先填写 API Key 和 Endpoint'); return; }

    btn.disabled = true;
    btn.textContent = '⏳ 测试中...';
    res.style.display = 'block';
    res.style.background = '#fefce8';
    res.style.color = '#92400e';
    res.textContent = '⏳ 正在测试连接...';

    var fd = new FormData();
    fd.append('provider', provider);
    fd.append('api_key', key);
    fd.append('endpoint', ep);
    fd.append('model', mdl);

    fetch('index.php?route=admin/api-config&action=test_api', {
        method: 'POST',
        credentials: 'include',
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) {
            res.style.background = '#f0fdf4';
            res.style.color = '#166534';
            res.textContent = '✅ 连接成功！延迟 ' + d.ms + 'ms，模型: ' + d.model;
        } else {
            res.style.background = '#fef2f2';
            res.style.color = '#991b1b';
            res.textContent = '❌ ' + (d.error || '未知错误') + ' (' + d.ms + 'ms)';
        }
    })
    .catch(function(err) {
        res.style.background = '#fef2f2';
        res.style.color = '#991b1b';
        res.textContent = '❌ 请求失败: ' + err;
    })
    .finally(function() {
        btn.disabled = false;
        btn.textContent = '🔗 测试连接';
    });
}
</script>