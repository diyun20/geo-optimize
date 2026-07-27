<?php
/**
 * 邮件发送工具类
 * 支持 SMTP 发送邮件（用于找回密码等场景）
 */

function mailGetConfig(): array {
    return [
        'host'     => dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smtp_host'")['setting_value'] ?? '',
        'port'     => dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smtp_port'")['setting_value'] ?? '465',
        'user'     => dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smtp_user'")['setting_value'] ?? '',
        'pass'     => dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smtp_pass'")['setting_value'] ?? '',
        'from'     => dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smtp_from'")['setting_value'] ?? '',
        'fromName' => dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smtp_from_name'")['setting_value'] ?? 'GEO优化',
    ];
}

function mailIsConfigured(): bool {
    $enabled = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smtp_enabled'")['setting_value'] ?? '0';
    if ($enabled !== '1') return false;
    $cfg = mailGetConfig();
    return !empty($cfg['host']) && !empty($cfg['user']) && !empty($cfg['pass']);
}

/**
 * 发送邮件（SMTP）
 */
function mailSend(string $to, string $subject, string $body): array {
    $cfg = mailGetConfig();
    if (!mailIsConfigured()) {
        return ['success' => false, 'error' => '邮件服务未配置'];
    }

    $host = $cfg['host'];
    $port = (int)$cfg['port'];
    $user = $cfg['user'];
    $pass = $cfg['pass'];
    $from = $cfg['from'] ?: $user;
    $fromName = $cfg['fromName'];

    $errno = 0; $errstr = '';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $socket = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
    if (!$socket) {
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10);
        if (!$socket) {
            return ['success' => false, 'error' => "连接邮件服务器失败: {$errstr}"];
        }
    }

    $talk = function($cmd, $expect = null) use ($socket) {
        if ($cmd !== '') {
            fputs($socket, $cmd . "\r\n");
        }
        $resp = '';
        while ($line = fgets($socket, 512)) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        if ($expect !== null && substr($resp, 0, 1) !== (string)$expect) {
            throw new \RuntimeException("SMTP error: " . trim($resp));
        }
        return $resp;
    };

    try {
        $talk(''); // read greeting only, no command sent
        $talk('EHLO ' . gethostname(), '2');
        $talk('AUTH LOGIN');
        $talk(base64_encode($user), '3');
        $talk(base64_encode($pass), '2');
        $talk('MAIL FROM:<' . $from . '>', '2');
        $talk('RCPT TO:<' . $to . '>', '2');
        $talk('DATA', '3');
        $header = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
        $header .= "To: <{$to}>\r\n";
        $header .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $header .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        fputs($socket, $header . $body . "\r\n.\r\n");
        $talk('', '2');
        $talk('QUIT');
        fclose($socket);
        return ['success' => true];
    } catch (\RuntimeException $e) {
        @fclose($socket);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * 发送邮箱验证码
 */
function mailSendCode(string $email): array {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => '邮箱格式不正确'];
    }
    if (isset($_SESSION['mail_code_time']) && time() - $_SESSION['mail_code_time'] < 60) {
        return ['success' => false, 'error' => '请60秒后再试'];
    }
    $code = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $result = mailSend($email, 'GEO优化 - 验证码', "您的验证码是：{$code}\n\n有效期5分钟，请勿泄露。");
    if ($result['success']) {
        $_SESSION['mail_code'] = $code;
        $_SESSION['mail_code_email'] = $email;
        $_SESSION['mail_code_time'] = time();
    }
    return $result;
}

/**
 * 验证邮箱验证码
 */
function mailVerify(string $email, string $code): bool {
    if (empty($_SESSION['mail_code']) || empty($_SESSION['mail_code_email'])) return false;
    if (time() - ($_SESSION['mail_code_time'] ?? 0) > 300) {
        unset($_SESSION['mail_code'], $_SESSION['mail_code_email'], $_SESSION['mail_code_time']);
        return false;
    }
    if ($_SESSION['mail_code_email'] === $email && $_SESSION['mail_code'] === $code) {
        unset($_SESSION['mail_code'], $_SESSION['mail_code_email'], $_SESSION['mail_code_time']);
        return true;
    }
    return false;
}
