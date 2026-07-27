<?php
requireLogin();
requireRole('admin');

$action = $_GET['action'] ?? '';

if ($action === 'login' && isset($_GET['user_id'])) {
    $targetId = (int)$_GET['user_id'];
    $target = dbFetchOne("SELECT * FROM users WHERE id=?", [$targetId]);
    if (!$target) { setFlash('error', '用户不存在'); redirect('index.php?route=admin/users'); }

    $_SESSION['impersonated_by'] = $_SESSION['user_id'];
    $_SESSION['user_id'] = (int)$target['id'];
    $_SESSION['username'] = $target['username'];

    appLog("Admin #{$_SESSION['impersonated_by']} entered user #{$targetId}");
    setFlash('info', '已登录用户: ' . $target['username']);
    redirect('index.php?route=dashboard');
}

if ($action === 'back') {
    $adminId = (int)($_SESSION['impersonated_by'] ?? 0);
    if ($adminId) {
        $admin = dbFetchOne("SELECT id,username FROM users WHERE id=?", [$adminId]);
        if ($admin) {
            $_SESSION['user_id'] = (int)$admin['id'];
            $_SESSION['username'] = $admin['username'];
            unset($_SESSION['impersonated_by']);
            setFlash('info', '已返回管理后台');
            redirect('index.php?route=admin/users');
        }
    }
    setFlash('error', '无法返回管理后台');
    redirect('index.php?route=admin/users');
}

setFlash('error', '非法操作');
redirect('index.php?route=admin/users');
