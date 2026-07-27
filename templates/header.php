<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'GEO优化') ?></title>
    <?php if (!empty($metaKeywords)): ?>
    <meta name="keywords" content="<?= h($metaKeywords) ?>">
    <?php endif; ?>
    <?php if (!empty($metaDescription)): ?>
    <meta name="description" content="<?= h($metaDescription) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "<?= addslashes('#0F3460') ?>",
                        secondary: "<?= addslashes('#1A73E8') ?>",
                        accent: "<?= addslashes('#E94560') ?>",
                        dark: "<?= addslashes('#16213E') ?>",
                        light: "<?= addslashes('#F5F5F7') ?>"
                    },
                    fontFamily: {
                        inter: ["Inter", "sans-serif"],
                    },
                },
            }
        }
    </script>
</head>
<body class="font-inter text-gray-800<?php $r = $_GET["route"] ?? "home"; if ($r === "home") echo " bg-white homepage"; if (isLoggedIn() && !in_array($r, ["home","about"])) echo " sidebar-visible"; if ($r !== "home") echo " bg-gray-50"; ?>">
<?php
$currentRoute = $_GET["route"] ?? "home";
$isHomepage = $currentRoute === "home";
?>
<header id="navbar" class="navbar">
    <div class="container nav-inner" style="display:flex;justify-content:space-between;align-items:center;">
        <a href="index.php?route=home" class="nav-brand" style="display:flex;align-items:center;gap:8px;text-decoration:none;">
            <i class="fa fa-globe" style="color:#E94560;font-size:24px;"></i>
            <span>GEO<span style="color:#E94560;">优化</span></span>
        </a>
        <ul class="nav-links" style="list-style:none;margin:0;padding:0;display:flex;gap:24px;align-items:center;">
            <li><a href="index.php?route=home">首页</a></li>
<?php if ($isHomepage): ?>
            <li><a href="#about">关于我们</a></li>
            <li><a href="#services">服务内容</a></li>
            <li><a href="#benefits">核心优势</a></li>
            <li><a href="#testimonials">案例研究</a></li>
            <li><a href="#contact">联系我们</a></li>
<?php else: ?>
            <li><a href="index.php?route=about">关于</a></li>
<?php endif; ?>
<?php if (isLoggedIn()): ?>
            <li><a href="index.php?route=dashboard">数据总览</a></li>
            <li><a href="index.php?route=logout">退出</a></li>
<?php else: ?>
<?php if (!$isHomepage): ?>
            <li><a href="index.php?route=login">登录</a></li>
            <li><a href="index.php?route=register" style="display:inline-block;padding:8px 20px;background:#E94560;color:#fff;border-radius:9999px;font-weight:600;">注册</a></li>
<?php endif; ?>
<?php endif; ?>
        </ul>
    </div>
</header>

<?php if (isLoggedIn() && !in_array($currentRoute, ["home","about"])): require __DIR__ . "/" . (isAdmin() ? "sidebar" : "sidebar_user") . ".php"; endif; ?>
<?php if (!$isHomepage): ?>
<main class="container main-content">
<?php endif; ?>
<?php
$flash = flashMessage();
if ($flash): ?>
    <div class="alert alert-<?= h($flash["type"]) ?>">
        <?= h($flash["message"]) ?>
    </div>
<?php endif; ?>
