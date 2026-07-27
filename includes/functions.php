<?php
/**
 * 工具函数库
 */

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }
    echo '<script>window.location.href="' . h($url) . '";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . h($url) . '"></noscript>';
    exit;
}

function currentUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flashMessage() {
    if (isset($_SESSION['flash'])) {
        $msg = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $msg;
    }
    return null;
}

function appLog($message, $level = 'INFO') {
    $logFile = __DIR__ . '/../storage/app.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$time][$level] $message" . PHP_EOL, FILE_APPEND);
}
/**
 * 跨平台启动后台 PHP CLI 进程（不阻塞当前请求）
 * @param string $script 脚本路径（相对项目根）
 * @param array  $args   命令行参数
 */
function runBackgroundProcess($script, $args = []) {
    $docRoot = __DIR__ . '/..';
    $scriptPath = realpath($docRoot . '/' . ltrim($script, '/')) ?: $docRoot . '/' . ltrim($script, '/');

    $phpBin = '/www/server/php/82/bin/php'; // 固定用 PHP 8.2
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cmd = $phpBin . ' ' . $scriptPath;
        foreach ($args as $a) { $cmd .= ' ' . escapeshellarg((string)$a); }
        pclose(popen('start /B ' . $cmd . ' > NUL 2>&1', 'r'));
    } else {
        $cmd = $phpBin . ' ' . escapeshellarg($scriptPath);
        foreach ($args as $a) { $cmd .= ' ' . escapeshellarg((string)$a); }
        exec($cmd . ' > /dev/null 2>&1 &');
    }
}
/** 最大并发扫描进程数 */
define('MAX_WORKERS', 5);

function getWorkerDir(): string {
    $dir = __DIR__ . '/../storage/worker_pids';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    return realpath($dir);
}

function isProcessRunning(int $pid): bool {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $out = [];
        exec('tasklist /FI "PID eq ' . $pid . '" /NH 2>NUL', $out);
        return !empty($out[0]) && strpos($out[0], 'php.exe') !== false;
    }
    return @file_exists("/proc/$pid");
}

/** 注册当前进程为工作进程，已达上限返回 false */
function claimWorkerSlot(): bool {
    $dir = getWorkerDir();
    if (getWorkerCount() >= MAX_WORKERS) return false;
    $pidFile = $dir . '/s_' . getmypid() . '.pid';
    file_put_contents($pidFile, getmypid());
    register_shutdown_function(function() use ($pidFile) { @unlink($pidFile); });
    return true;
}

/** 获取当前运行的工作进程数 */
function getWorkerCount(): int {
    $dir = getWorkerDir();
    $count = 0;
    foreach (glob($dir . '/s_*.pid') as $f) {
        $pid = (int)substr(basename($f), 2, -4);
        if ($pid > 0 && isProcessRunning($pid)) {
            $count++;
        } else {
            @unlink($f);
        }
    }
    return $count;
}
