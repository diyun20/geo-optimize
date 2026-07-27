<?php
requireLogin();
require_once __DIR__ . '/../includes/geo.php';
geoInitTables();

$user = currentUser();
$isAdmin = isAdmin();
$isAgent = isAgent();

// 处理 AJAX 请求
if (!empty($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $priority = (int)($_POST['priority'] ?? 0);
        if (!$title || !$content) {
            echo json_encode(['ok' => false, 'msg' => '标题和内容不能为空']);
            exit;
        }
        dbInsert("INSERT INTO tickets (user_id,title,content,status,priority,created_at) VALUES (?,?,?,'open',?,NOW())",
            [$user['id'], $title, $content, $priority]);
        echo json_encode(['ok' => true, 'msg' => '工单创建成功']);
        exit;
    }

    if ($action === 'reply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $content  = trim($_POST['content'] ?? '');
        if (!$ticketId || !$content) {
            echo json_encode(['ok' => false, 'msg' => '缺少参数']);
            exit;
        }
        // 权限检查
        $ticket = dbFetchOne("SELECT * FROM tickets WHERE id=?", [$ticketId]);
        if (!$ticket) { echo json_encode(['ok' => false, 'msg' => '工单不存在']); exit; }
        if (!canViewTicket($ticket)) { echo json_encode(['ok' => false, 'msg' => '无权操作']); exit; }
        if ($ticket['status'] === 'closed') { echo json_encode(['ok' => false, 'msg' => '工单已关闭']); exit; }

        dbInsert("INSERT INTO ticket_replies (ticket_id,user_id,content,created_at) VALUES (?,?,?,NOW())",
            [$ticketId, $user['id'], $content]);
        // 非创建者回复时自动设为处理中
        if ($ticket['user_id'] != $user['id']) {
            dbExecute("UPDATE tickets SET status='in_progress', updated_at=NOW() WHERE id=?", [$ticketId]);
        }
        echo json_encode(['ok' => true, 'msg' => '回复成功']);
        exit;
    }

    if ($action === 'close' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdminOrAgent()) { echo json_encode(['ok' => false, 'msg' => '无权操作']); exit; }
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $ticket = dbFetchOne("SELECT * FROM tickets WHERE id=?", [$ticketId]);
        if (!$ticket) { echo json_encode(['ok' => false, 'msg' => '工单不存在']); exit; }
        if (!canViewTicket($ticket)) { echo json_encode(['ok' => false, 'msg' => '无权操作']); exit; }
        dbExecute("UPDATE tickets SET status='closed', updated_at=NOW() WHERE id=?", [$ticketId]);
        echo json_encode(['ok' => true, 'msg' => '工单已关闭']);
        exit;
    }

    if ($action === 'reopen' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdminOrAgent()) { echo json_encode(['ok' => false, 'msg' => '无权操作']); exit; }
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $ticket = dbFetchOne("SELECT * FROM tickets WHERE id=?", [$ticketId]);
        if (!$ticket) { echo json_encode(['ok' => false, 'msg' => '工单不存在']); exit; }
        if (!canViewTicket($ticket)) { echo json_encode(['ok' => false, 'msg' => '无权操作']); exit; }
        dbExecute("UPDATE tickets SET status='open', updated_at=NOW() WHERE id=?", [$ticketId]);
        echo json_encode(['ok' => true, 'msg' => '工单已重新打开']);
        exit;
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$isAdmin) { echo json_encode(['ok' => false, 'msg' => '仅管理员可删除']); exit; }
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        dbExecute("DELETE FROM tickets WHERE id=?", [$ticketId]);
        echo json_encode(['ok' => true, 'msg' => '工单已删除']);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => '未知操作']);
    exit;
}

/** 检查当前用户是否可以查看某工单 */
function canViewTicket(array $ticket): bool {
    $u = currentUser();
    if ($u['role'] === 'admin') return true;
    if ($u['role'] === 'agent') {
        // 检查工单创建者是否属于该代理商
        $owner = dbFetchOne("SELECT agent_id FROM users WHERE id=?", [$ticket['user_id']]);
        return $owner && (int)$owner['agent_id'] === (int)$u['id'];
    }
    // 普通用户只能看自己的
    return (int)$ticket['user_id'] === (int)$u['id'];
}

// 列表页逻辑
$statusFilter = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 构建查询
$where = '';
$params = [];
if ($isAdmin) {
    // 管理员看全部
    $where = '1=1';
} elseif ($isAgent) {
    // 代理商看自己名下用户的工单
    $where = 't.user_id IN (SELECT id FROM users WHERE agent_id=?)';
    $params[] = $user['id'];
} else {
    // 普通用户看自己的
    $where = 't.user_id=?';
    $params[] = $user['id'];
}

if ($statusFilter && in_array($statusFilter, ['open','in_progress','closed'])) {
    $where .= ' AND t.status=?';
    $params[] = $statusFilter;
}

// 总数
$countParams = $params;
$total = dbFetchOne("SELECT COUNT(*) AS cnt FROM tickets t WHERE {$where}", $countParams)['cnt'];
$totalPages = max(1, ceil($total / $perPage));

// 查询列表
$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;
$tickets = dbFetchAll(
    "SELECT t.*, u.username AS creator_name, u.role AS creator_role
     FROM tickets t LEFT JOIN users u ON t.user_id=u.id
     WHERE {$where}
     ORDER BY FIELD(t.status,'open','in_progress','closed'), t.updated_at DESC
     LIMIT ? OFFSET ?",
    $listParams
);

// 详情页
$detailId = (int)($_GET['id'] ?? 0);
$detail = null;
$replies = [];
if ($detailId > 0) {
    $detail = dbFetchOne(
        "SELECT t.*, u.username AS creator_name, u.role AS creator_role, u.email AS creator_email, u.phone AS creator_phone
         FROM tickets t LEFT JOIN users u ON t.user_id=u.id WHERE t.id=?",
        [$detailId]
    );
    if ($detail && canViewTicket($detail)) {
        $replies = dbFetchAll(
            "SELECT r.*, u.username, u.role
             FROM ticket_replies r LEFT JOIN users u ON r.user_id=u.id
             WHERE r.ticket_id=? ORDER BY r.created_at ASC",
            [$detailId]
        );
    } else {
        $detail = null;
    }
}

$pageTitle = "工单系统 - GEO优化";

$statusLabels = ['open' => '待处理', 'in_progress' => '处理中', 'closed' => '已关闭'];
$statusColors = ['open' => 'bg-yellow-100 text-yellow-800', 'in_progress' => 'bg-blue-100 text-blue-800', 'closed' => 'bg-gray-100 text-gray-500'];

// 如果是详情页
if ($detail):
?>
<div class="mb-4">
  <a href="index.php?route=tickets" class="text-blue-600 hover:underline">&larr; 返回工单列表</a>
</div>

<div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
  <div class="flex justify-between items-start mb-4 flex-wrap gap-3">
    <div>
      <div class="flex items-center gap-3 mb-2 flex-wrap">
        <h1 class="text-xl font-bold"><?= h($detail['title']) ?></h1>
        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusColors[$detail['status']] ?>">
          <?= $statusLabels[$detail['status']] ?>
        </span>
        <?php if ($detail['priority'] == 1): ?>
          <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">紧急</span>
        <?php endif; ?>
      </div>
      <p class="text-sm text-gray-500">
        创建者：<?= h($detail['creator_name']) ?>
        <?php if ($detail['creator_email']): ?> · <?= h($detail['creator_email']) ?><?php endif; ?>
        <?php if (!empty($detail['creator_phone'])): ?> · <?= h($detail['creator_phone']) ?><?php endif; ?>
        · <?= date('Y-m-d H:i', strtotime($detail['created_at'])) ?>
      </p>
    </div>
    <?php if (isAdminOrAgent() && $detail['status'] !== 'closed'): ?>
      <button onclick="closeTicket(<?= $detail['id'] ?>)" class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm hover:bg-gray-700">关闭工单</button>
    <?php elseif (isAdminOrAgent() && $detail['status'] === 'closed'): ?>
      <button onclick="reopenTicket(<?= $detail['id'] ?>)" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">重新打开</button>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
      <button onclick="deleteTicket(<?= $detail['id'] ?>)" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600">删除</button>
    <?php endif; ?>
  </div>
  <div class="p-4 bg-gray-50 rounded-lg whitespace-pre-wrap text-sm"><?= h($detail['content']) ?></div>
</div>

<!-- 回复列表 -->
<div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
  <h2 class="font-semibold text-lg mb-4">回复记录 (<?= count($replies) ?>)</h2>
  <?php if (empty($replies)): ?>
    <p class="text-gray-400 text-sm">暂无回复</p>
  <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($replies as $r): ?>
        <div class="border rounded-lg p-4 <?= $r['user_id'] === $detail['user_id'] ? 'border-blue-200 bg-blue-50/30' : 'border-gray-200' ?>">
          <div class="flex items-center gap-2 mb-2">
            <span class="font-medium text-sm"><?= h($r['username']) ?></span>
            <span class="px-1.5 py-0.5 rounded text-xs <?= $r['role']==='admin'?'bg-red-100 text-red-700':($r['role']==='agent'?'bg-purple-100 text-purple-700':'bg-gray-100 text-gray-600') ?>">
              <?= $r['role']==='admin'?'管理员':($r['role']==='agent'?'代理商':'用户') ?>
            </span>
            <span class="text-xs text-gray-400 ml-auto"><?= date('Y-m-d H:i', strtotime($r['created_at'])) ?></span>
          </div>
          <div class="text-sm whitespace-pre-wrap"><?= h($r['content']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- 回复表单 -->
<?php if ($detail['status'] !== 'closed'): ?>
<div class="bg-white rounded-xl shadow-sm border p-6">
  <h2 class="font-semibold text-lg mb-4">回复工单</h2>
  <form id="replyForm">
    <input type="hidden" name="ticket_id" value="<?= $detail['id'] ?>">
    <textarea name="content" rows="4" required placeholder="输入回复内容..." 
      class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
    <button type="submit" class="mt-3 px-6 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">发送回复</button>
  </form>
</div>
<?php endif; ?>

<script>
document.getElementById('replyForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('button');
  btn.disabled = true; btn.textContent = '发送中...';
  const fd = new FormData(this);
  fd.append('ticket_id', '<?= $detail['id'] ?>');
  try {
    const resp = await fetch('index.php?route=tickets&action=reply', { method: 'POST', body: new URLSearchParams(fd) });
    const data = await resp.json();
    if (data.ok) { location.reload(); }
    else { alert(data.msg); }
  } catch (err) { alert('网络错误'); }
  btn.disabled = false; btn.textContent = '发送回复';
});

async function closeTicket(id) {
  if (!confirm('确认关闭此工单？')) return;
  const resp = await fetch('index.php?route=tickets&action=close', { method: 'POST', body: new URLSearchParams({ticket_id: id}) });
  const data = await resp.json();
  if (data.ok) location.reload(); else alert(data.msg);
}
async function reopenTicket(id) {
  if (!confirm('确认重新打开此工单？')) return;
  const resp = await fetch('index.php?route=tickets&action=reopen', { method: 'POST', body: new URLSearchParams({ticket_id: id}) });
  const data = await resp.json();
  if (data.ok) location.reload(); else alert(data.msg);
}
async function deleteTicket(id) {
  if (!confirm('确认删除此工单？此操作不可撤销。')) return;
  const resp = await fetch('index.php?route=tickets&action=delete', { method: 'POST', body: new URLSearchParams({ticket_id: id}) });
  const data = await resp.json();
  if (data.ok) { location.href = 'index.php?route=tickets'; } else { alert(data.msg); }
}
</script>

<?php else: // 列表页 ?>

<div class="flex justify-between items-center mb-6 flex-wrap gap-3">
  <h1 class="text-2xl font-bold">工单系统</h1>
  <button onclick="showCreateForm()" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
    + 新建工单
  </button>
</div>

<!-- 筛选 -->
<div class="bg-white rounded-xl shadow-sm border p-4 mb-6">
  <div class="flex gap-3 flex-wrap">
    <a href="?route=tickets" class="px-4 py-2 rounded-full text-sm <?= !$statusFilter ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">全部</a>
    <?php foreach (['open'=>'待处理','in_progress'=>'处理中','closed'=>'已关闭'] as $k => $v): ?>
      <a href="?route=tickets&status=<?= $k ?>" class="px-4 py-2 rounded-full text-sm <?= $statusFilter === $k ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= $v ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- 工单列表 -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
  <?php if (empty($tickets)): ?>
    <div class="p-12 text-center text-gray-400">
      <div class="text-4xl mb-3">📭</div>
      <p>暂无工单</p>
    </div>
  <?php else: ?>
    <div class="divide-y">
      <?php foreach ($tickets as $t): ?>
        <a href="?route=tickets&id=<?= $t['id'] ?><?= $statusFilter ? '&status='.$statusFilter : '' ?>" 
           class="block p-5 hover:bg-gray-50 transition">
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1 flex-wrap">
                <span class="font-semibold text-gray-800 truncate"><?= h($t['title']) ?></span>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $statusColors[$t['status']] ?>"><?= $statusLabels[$t['status']] ?></span>
                <?php if ($t['priority'] == 1): ?>
                  <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">紧急</span>
                <?php endif; ?>
              </div>
              <p class="text-sm text-gray-400">
                创建者：<?= h($t['creator_name']) ?> · <?= date('Y-m-d H:i', strtotime($t['updated_at'] ?? $t['created_at'])) ?>
              </p>
            </div>
            <span class="text-gray-300">&rarr;</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- 分页 -->
<?php if ($totalPages > 1): ?>
<div class="flex justify-center gap-2 mt-6">
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?route=tickets&p=<?= $i ?><?= $statusFilter ? '&status='.$statusFilter : '' ?>" 
       class="px-3 py-1.5 rounded text-sm <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">
      <?= $i ?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<!-- 新建工单弹窗 -->
<div id="createModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 p-6">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-lg font-bold">新建工单</h2>
      <button onclick="hideCreateForm()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
    </div>
    <form id="createForm">
      <div class="mb-3">
        <label class="block text-sm font-medium text-gray-700 mb-1">标题 <span class="text-red-500">*</span></label>
        <input name="title" required placeholder="请输入工单标题" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div class="mb-3">
        <label class="block text-sm font-medium text-gray-700 mb-1">优先级</label>
        <select name="priority" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm">
          <option value="0">普通</option>
          <option value="1">紧急</option>
        </select>
      </div>
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">内容 <span class="text-red-500">*</span></label>
        <textarea name="content" required rows="5" placeholder="请详细描述您的问题..." class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-blue-500"></textarea>
      </div>
      <div class="flex justify-end gap-3">
        <button type="button" onclick="hideCreateForm()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">取消</button>
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">提交工单</button>
      </div>
    </form>
  </div>
</div>

<script>
function showCreateForm() { document.getElementById('createModal').style.display = 'flex'; }
function hideCreateForm() { document.getElementById('createModal').style.display = 'none'; }

document.getElementById('createForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('button[type=submit]');
  btn.disabled = true; btn.textContent = '提交中...';
  const fd = new FormData(this);
  try {
    const resp = await fetch('index.php?route=tickets&action=create', { method: 'POST', body: new URLSearchParams(fd) });
    const data = await resp.json();
    if (data.ok) { location.reload(); }
    else { alert(data.msg); }
  } catch (err) { alert('网络错误'); }
  btn.disabled = false; btn.textContent = '提交工单';
});
</script>

<?php endif; ?>
