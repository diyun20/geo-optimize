<?php
if (!isAdmin()) { setFlash('error', '无权访问'); redirect('index.php?route=dashboard'); }

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$msg = '';
$msgType = 'info';

// ---- 保存 ----
if ($action === 'save' && $_POST) {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $content = $_POST['content'] ?? '';
    $thumbnail = trim($_POST['thumbnail'] ?? '');
    if ($name && $content) {
        if ($id > 0) {
            dbExecute("UPDATE home_templates SET name=?, description=?, content=?, thumbnail=?, updated_at=NOW() WHERE id=?", [$name, $desc, $content, $thumbnail, $id]);
            $msg = '✅ 模板已更新'; $msgType = 'success';
        } else {
            dbExecute("INSERT INTO home_templates (name, description, content, thumbnail, is_active, created_at, updated_at) VALUES (?,?,?,?,0,NOW(),NOW())", [$name, $desc, $content, $thumbnail]);
            $msg = '✅ 模板已创建'; $msgType = 'success';
        }
        redirect('index.php?route=admin/templates');
    } else {
        $msg = '名称和内容不能为空'; $msgType = 'error';
    }
}

// ---- 激活（写入 home.php）----
if ($action === 'activate' && ($id = (int)($_GET['id'] ?? 0))) {
    $tpl = dbFetchOne("SELECT * FROM home_templates WHERE id=?", [$id]);
    if ($tpl && !empty($tpl['content'])) {
        // 剥离模板中已有的预览守卫，然后统一追加
        $content = preg_replace('/^<\?php\s*\/\/\s*预览模式.*?\?>\s*/s', '', $tpl['content']);
        $guard = "<?php\n// 预览模式：仅当 URL 带 preview_tpl 参数时从数据库加载模板预览\n"
               . "\$previewId = isset(\$_GET['preview_tpl']) ? (int)\$_GET['preview_tpl'] : 0;\n"
               . "if (\$previewId > 0) {\n"
               . "    \$tpl = dbFetchOne(\"SELECT * FROM home_templates WHERE id=?\", [\$previewId]);\n"
               . "    if (\$tpl && !empty(\$tpl['content'])) {\n"
               . "        \$tmpDir = __DIR__ . '/../storage/tmp';\n"
               . "        if (!is_dir(\$tmpDir)) mkdir(\$tmpDir, 0755, true);\n"
               . "        \$tmpFile = \$tmpDir . '/preview_tpl_' . \$previewId . '.php';\n"
               . "        file_put_contents(\$tmpFile, \$tpl['content']);\n"
               . "        include \$tmpFile;\n"
               . "        return;\n"
               . "    }\n"
               . "}\n"
               . "?>";

        // 写入模板内容到 home.php（带预览守卫）
        file_put_contents(__DIR__ . '/../home.php', $guard . $content);

        // 更新状态
        dbExecute("UPDATE home_templates SET is_active=0");
        dbExecute("UPDATE home_templates SET is_active=1, updated_at=NOW() WHERE id=?", [$id]);

        $msg = '✅ 已切换为「' . h($tpl['name']) . '」';
        $msgType = 'success';

        // 清理旧的模板缓存
        $tmpDir = __DIR__ . '/../storage/tmp';
        if (is_dir($tmpDir)) {
            foreach (glob($tmpDir . '/home_tpl_*.php') as $f) { @unlink($f); }
            foreach (glob($tmpDir . '/preview_tpl_*.php') as $f) { @unlink($f); }
        }
        redirect('index.php?route=admin/templates');
    } else {
        $msg = '模板不存在';
        $msgType = 'error';
    }
}

// ---- 删除 ----
if ($action === 'delete' && ($id = (int)($_GET['id'] ?? 0))) {
    $active = dbFetchOne("SELECT is_active FROM home_templates WHERE id=?", [$id]);
    if ($active && $active['is_active']) {
        $msg = '不能删除正在使用的模板，请先切换到其他模板';
        $msgType = 'error';
    } else {
        dbExecute("DELETE FROM home_templates WHERE id=?", [$id]);
        $msg = '模板已删除';
        $msgType = 'info';
    }
    redirect('index.php?route=admin/templates');
}

// ---- 导入当前首页为新模板 ----
if ($action === 'import_current') {
    $current = file_get_contents(__DIR__ . '/../home.php');
    // 剥离预览守卫
    $current = preg_replace('/^<\?php\s*\/\/\s*预览模式.*?\?>\s*/s', '', $current);
    preg_match('/\$pageTitle\s*=\s*\'([^\']+)\'/', $current, $m);
    $tplName = $m[1] ?? '手动导入';
    dbExecute("INSERT INTO home_templates (name, description, content, thumbnail, is_active, created_at, updated_at) VALUES (?,?,?,?,0,NOW(),NOW())",
        [$tplName, '从当前首页导入', $current, '']);
    $msg = '✅ 当前首页已导入为模板'; $msgType = 'success';
}

// ---- 获取数据 ----
$templates = dbFetchAll("SELECT * FROM home_templates ORDER BY is_active DESC, updated_at DESC");
$activeTpl = null;
foreach ($templates as $t) { if ($t['is_active']) { $activeTpl = $t; break; } }

// 如果没有模板，自动导入当前首页
if (empty($templates)) {
    $current = file_get_contents(__DIR__ . '/../home.php');
    dbExecute("INSERT INTO home_templates (name, description, content, thumbnail, is_active, created_at, updated_at) VALUES (?,?,?,?,1,NOW(),NOW())",
        ['默认模板', '系统初始首页模板', $current, '']);
    $templates = dbFetchAll("SELECT * FROM home_templates ORDER BY is_active DESC, updated_at DESC");
    foreach ($templates as $t) { if ($t['is_active']) { $activeTpl = $t; break; } }
}

$editParam = $_GET['edit'] ?? '';
$isNew = ($editParam === 'new');
$editId = $isNew ? 0 : (int)$editParam;
$editTpl = null;
if ($editId > 0) $editTpl = dbFetchOne("SELECT * FROM home_templates WHERE id=?", [$editId]);

// 缩略图色板
$palette = ['#6366f1','#8b5cf6','#ec4899','#f43f5e','#f97316','#eab308','#22c55e','#14b8a6','#06b6d4','#3b82f6'];
function tplThumb($tpl) {
    global $palette;
    if (!empty($tpl['thumbnail'])) return '<img src="'.h($tpl['thumbnail']).'" alt="" style="width:100%;height:100%;object-fit:cover;">';
    $bg = $palette[$tpl['id'] % count($palette)];
    $text = strip_tags($tpl['content']);
    $text = preg_replace('/\s+/', ' ', trim($text));
    $preview = mb_substr($text, 0, 60) ?: '空白模板';
    return '<div style="width:100%;height:100%;background:'.$bg.';display:flex;flex-direction:column;justify-content:center;align-items:center;color:#fff;padding:16px;overflow:hidden;"><div style="font-size:22px;font-weight:700;margin-bottom:6px;text-shadow:0 2px 8px rgba(0,0,0,.2);">'.h($tpl['name']).'</div><div style="font-size:10px;opacity:.75;line-height:1.4;text-align:center;max-height:40px;overflow:hidden;">'.h($preview).'</div></div>';
}
?>

<style>
:root{--tpl-brand:#6366f1;--tpl-brand2:#8b5cf6}
.tpl-wrap{padding:0}
.tpl-topbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:24px}
.tpl-topbar h1{font-size:22px;font-weight:700;margin:0}
.tpl-topbar p{color:#888;margin:4px 0 0;font-size:13px}
.tpl-banner{background:linear-gradient(135deg,#10b981,#059669);border-radius:12px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;gap:14px;color:#fff;box-shadow:0 4px 20px rgba(16,185,129,.25)}
.tpl-banner .dot{width:10px;height:10px;background:#fff;border-radius:50%;animation:tpldot 2s infinite}
@keyframes tpldot{0%,100%{opacity:1}50%{opacity:.4}}
.tpl-banner .info{flex:1}
.tpl-banner .info strong{font-size:16px}
.tpl-banner span.o{opacity:.85;font-size:13px;margin-left:8px}
.tpl-banner .t{font-size:12px;opacity:.7;white-space:nowrap}
.tpl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px}
.tpl-card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);border:1px solid #e5e7eb;transition:all .2s}
.tpl-card:hover{box-shadow:0 8px 30px rgba(0,0,0,.1);transform:translateY(-2px)}
.tpl-card.active{border-color:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.1)}
.tpl-card-thumb{height:160px;overflow:hidden;position:relative;background:#f3f4f6}
.tpl-card-badge{position:absolute;top:10px;left:10px;background:#10b981;color:#fff;font-size:11px;font-weight:600;padding:4px 10px;border-radius:20px;z-index:2}
.tpl-card-body{padding:16px}
.tpl-card-body h3{font-size:15px;font-weight:600;margin:0 0 4px}
.tpl-card-body .desc{font-size:12px;color:#888;margin:0 0 12px;line-height:1.4}
.tpl-card-body .meta{font-size:11px;color:#bbb;margin-bottom:12px}
.tpl-card-actions{display:flex;gap:6px;flex-wrap:wrap}
.tpl-btn{display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:500;border:1px solid #e5e7eb;background:#fff;color:#374151;cursor:pointer;text-decoration:none;transition:all .15s}
.tpl-btn:hover{background:#f9fafb;border-color:#d1d5db}
.tpl-btn.primary{background:var(--tpl-brand);color:#fff;border-color:var(--tpl-brand)}
.tpl-btn.primary:hover{background:#4f46e5}
.tpl-btn.success{background:#10b981;color:#fff;border-color:#10b981}
.tpl-btn.success:hover{background:#059669}
.tpl-btn.danger{color:#ef4444;border-color:#fecaca}
.tpl-btn.danger:hover{background:#fef2f2;border-color:#ef4444}
.tpl-new-card{border:2px dashed #d1d5db;background:#fafafa;display:flex;align-items:center;justify-content:center;flex-direction:column;min-height:280px;cursor:pointer;transition:all .2s;border-radius:12px;text-decoration:none;color:#9ca3af}
.tpl-new-card:hover{border-color:var(--tpl-brand);color:var(--tpl-brand);background:#eef2ff}
.tpl-new-card .icon{font-size:40px;margin-bottom:8px}
.tpl-new-card .label{font-size:14px;font-weight:500}
.tpl-editor{background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px;margin-bottom:24px;box-shadow:0 4px 20px rgba(0,0,0,.06)}
.tpl-editor h3{font-size:18px;margin:0 0 20px}
.tpl-editor .row{margin-bottom:16px}
.tpl-editor label{display:block;font-weight:600;font-size:13px;margin-bottom:6px;color:#374151}
.tpl-editor input[type=text],.tpl-editor textarea{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:10px 14px;font-size:14px;box-sizing:border-box}
.tpl-editor input:focus,.tpl-editor textarea:focus{outline:none;border-color:var(--tpl-brand);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.tpl-editor textarea{resize:vertical;min-height:300px;font-family:'SF Mono','Fira Code',monospace;font-size:13px;line-height:1.6}
.tpl-hint{font-size:12px;color:#9ca3af;margin-top:4px}
.tpl-editor-actions{display:flex;gap:10px;margin-top:8px}
.tpl-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:none;align-items:center;justify-content:center}
.tpl-modal-overlay.show{display:flex}
.tpl-modal{background:#fff;border-radius:12px;padding:24px;max-width:460px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.tpl-modal h2{margin:0 0 12px;font-size:18px}
.tpl-modal p{color:#666;font-size:14px;margin:0 0 20px;line-height:1.6}
.tpl-modal .btns{display:flex;gap:10px;justify-content:center}
.tpl-modal code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:12px}
@media(max-width:640px){.tpl-grid{grid-template-columns:1fr}}
</style>

<div class="tpl-wrap">
<?php if ($msg): ?>
<div style="margin-bottom:16px;padding:10px 16px;border-radius:8px;font-size:14px;<?=$msgType==='success'?'background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0':''?><?=$msgType==='error'?'background:#fef2f2;color:#991b1b;border:1px solid #fecaca':''?><?=$msgType==='info'?'background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe':''?>"><?=$msg?></div>
<?php endif; ?>

<div class="tpl-topbar">
    <div>
        <h1>🎨 首页模板管理</h1>
        <p>设计多套首页风格，一键切换直接写入首页文件，实时生效</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="?route=admin/templates&action=import_current" class="tpl-btn" title="把当前首页导入为模板备份">📥 导入当前首页</a>
        <a href="?route=admin/templates&edit=new" class="tpl-btn primary" style="padding:8px 16px;font-size:13px;">＋ 新建模板</a>
    </div>
</div>

<?php if ($activeTpl): ?>
<div class="tpl-banner">
    <div class="dot"></div>
    <div class="info"><strong>当前：<?=h($activeTpl['name'])?></strong><span class="o"><?=h($activeTpl['description'])?></span></div>
    <span class="t">更新于 <?=h($activeTpl['updated_at'])?></span>
    <a href="?route=home" target="_blank" class="tpl-btn" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3);">预览首页 →</a>
</div>
<?php endif; ?>

<!-- 编辑面板 -->
<?php if ($isNew || $editTpl): ?>
<?php $formTpl = $isNew ? ['id'=>0,'name'=>'','description'=>'','content'=>'','thumbnail'=>''] : $editTpl; ?>
<div class="tpl-editor">
    <h3><?=$isNew?'✨ 新建首页模板':'✏️ 编辑：'.h($formTpl['name'])?></h3>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?=$formTpl['id']?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="row"><label>模板名称 <span style="color:#ef4444">*</span></label><input type="text" name="name" value="<?=h($formTpl['name'])?>" placeholder="例如：极简商务风" required></div>
            <div class="row"><label>缩略图URL</label><input type="text" name="thumbnail" value="<?=h($formTpl['thumbnail'])?>" placeholder="留空自动生成"><div class="tpl-hint">填写图片URL或留空自动生成彩色预览</div></div>
        </div>
        <div class="row"><label>简短描述</label><input type="text" name="description" value="<?=h($formTpl['description'])?>" placeholder="一句话描述模板风格特点"></div>
        <div class="row"><label>模板代码 (HTML+PHP) <span style="color:#ef4444">*</span></label><div class="tpl-hint" style="margin-bottom:6px">完整首页代码，支持 HTML/CSS/JS/PHP。变量：<code>{{site_name}}</code></div><textarea name="content" required><?=h($formTpl['content'])?></textarea></div>
        <div class="tpl-editor-actions"><button type="submit" class="tpl-btn primary" style="padding:8px 20px;font-size:13px;">💾 保存模板</button><a href="?route=admin/templates" class="tpl-btn">取消</a></div>
    </form>
</div>
<?php endif; ?>

<!-- 卡片网格 -->
<div class="tpl-grid">
    <?php foreach ($templates as $i => $t): ?>
    <div class="tpl-card <?=$t['is_active']?'active':''?>">
        <div class="tpl-card-thumb">
            <?php if ($t['is_active']): ?><span class="tpl-card-badge">● 使用中</span><?php endif; ?>
            <?=tplThumb($t)?>
        </div>
        <div class="tpl-card-body">
            <h3><?=h($t['name'])?></h3>
            <p class="desc"><?=h($t['description']?:'暂无描述')?></p>
            <p class="meta">🕒 <?=h($t['updated_at'])?></p>
            <div class="tpl-card-actions">
                <a href="?route=home&preview_tpl=<?=$t['id']?>" target="_blank" class="tpl-btn">👁 预览</a>
                <a href="?route=admin/templates&edit=<?=$t['id']?>" class="tpl-btn">✏️ 编辑</a>
                <?php if (!$t['is_active']): ?>
                <a href="#" class="tpl-btn success" onclick="confirmActivate(<?=$t['id']?>,'<?=h(addslashes($t['name']))?>');return false">🚀 启用</a>
                <a href="#" class="tpl-btn danger" onclick="confirmDelete(<?=$t['id']?>,'<?=h(addslashes($t['name']))?>');return false">🗑</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <a href="?route=admin/templates&edit=new" class="tpl-new-card"><div class="icon">＋</div><div class="label">新建模板</div></a>
</div>
</div>

<!-- 激活确认弹窗 -->
<div class="tpl-modal-overlay" id="activateModal">
    <div class="tpl-modal">
        <h2>🚀 确认切换</h2>
        <p>将首页切换为 <strong id="actName">-</strong></p>
        <div class="btns"><button class="tpl-btn" onclick="closeModal('activateModal')">取消</button><button class="tpl-btn success" id="actBtn" onclick="doActivate()">确认切换</button></div>
    </div>
</div>

<!-- 删除确认弹窗 -->
<div class="tpl-modal-overlay" id="deleteModal">
    <div class="tpl-modal">
        <h2>⚠️ 确认删除</h2>
        <p>删除模板 <strong id="delName">-</strong>？不可恢复。</p>
        <div class="btns"><button class="tpl-btn" onclick="closeModal('deleteModal')">取消</button><a id="delLink" href="#" class="tpl-btn danger">确认删除</a></div>
    </div>
</div>

<script>
var _activateId=0;
function confirmActivate(id,name){
    _activateId=id;
    document.getElementById('actName').textContent=name;
    document.getElementById('activateModal').classList.add('show');
}
function doActivate(){if(_activateId)location.href='?route=admin/templates&action=activate&id='+_activateId}
function confirmDelete(id,name){
    document.getElementById('delName').textContent=name;
    document.getElementById('delLink').href='?route=admin/templates&action=delete&id='+id;
    document.getElementById('deleteModal').classList.add('show');
}
function closeModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.tpl-modal-overlay').forEach(function(m){m.addEventListener('click',function(e){if(e.target===this)closeModal(this.id);});});
</script>
