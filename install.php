<?php
 /**
  * 安装检测入口
  * 检查系统是否已安装，未安装则重定向到安装向导
  */
 
 if (session_status() === PHP_SESSION_NONE) session_start(); ob_start();
 
 // 检查是否已安装（通过锁定文件判断）
 $installed = false;
 $lockFile = __DIR__ . '/storage/installed.lock';
 
 if (file_exists($lockFile)) {
     $lockData = json_decode(file_get_contents($lockFile), true);
     $installed = isset($lockData['installed']) && $lockData['installed'] === true;
 }
 
if (!$installed) {
     // 重定向到安装向导
     header('Location: installer/');
     exit;
 }
 
 // 已安装，加载主应用
 require __DIR__ . '/index.php';
