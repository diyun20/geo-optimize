<?php
/**
 * 短信宝 SMS 工具类
 * 文档: https://www.smsbao.com/openapi/213.html
 */

function smsGetConfig(): array {
    return [
        'user' => dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smsbao_user'")['setting_value'] ?? '',
        'pass' => dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smsbao_pass'")['setting_value'] ?? '',
    ];
}

function smsIsConfigured(): bool {
    $enabled = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='smsbao_enabled'")['setting_value'] ?? '0';
    if ($enabled !== '1') return false;
    $cfg = smsGetConfig();
    return !empty($cfg['user']) && !empty($cfg['pass']);
}

/**
 * 发送短信
 * @return array{success:bool, error?:string}
 */
function smsSend(string $phone, string $content): array {
    $cfg = smsGetConfig();
    if (empty($cfg['user']) || empty($cfg['pass'])) {
        return ['success' => false, 'error' => '短信服务未配置'];
    }
    $url = 'http://api.smsbao.com/sms?' . http_build_query([
        'u' => $cfg['user'],
        'p' => md5($cfg['pass']),
        'm' => $phone,
        'c' => $content,
    ]);
    $ctx = stream_context_create(['http' => ['timeout' => 10]]);
    $result = @file_get_contents($url, false, $ctx);
    if ($result === false) {
        return ['success' => false, 'error' => '短信接口请求失败'];
    }
    if ($result === '0') {
        return ['success' => true];
    }
    $errors = [
        '30' => '密码错误', '40' => '账号不存在', '41' => '余额不足',
        '43' => 'IP地址限制', '50' => '内容敏感词', '51' => '手机号不正确',
    ];
    return ['success' => false, 'error' => $errors[$result] ?? "发送失败(错误码:{$result})"];
}

/**
 * 发送验证码并存入 session
 */
function smsSendCode(string $phone): array {
    // 60秒内不能重复发送
    if (isset($_SESSION['sms_code_time']) && time() - $_SESSION['sms_code_time'] < 60) {
        return ['success' => false, 'error' => '请60秒后再试'];
    }
    $code = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $result = smsSend($phone, "【GEO优化】您的验证码是{$code}，5分钟内有效，请勿泄露。");
    if ($result['success']) {
        $_SESSION['sms_code'] = $code;
        $_SESSION['sms_code_phone'] = $phone;
        $_SESSION['sms_code_time'] = time();
    }
    return $result;
}

/**
 * 验证短信验证码
 */
function smsVerify(string $phone, string $code): bool {
    if (empty($_SESSION['sms_code']) || empty($_SESSION['sms_code_phone'])) return false;
    if (time() - ($_SESSION['sms_code_time'] ?? 0) > 300) {
        unset($_SESSION['sms_code'], $_SESSION['sms_code_phone'], $_SESSION['sms_code_time']);
        return false;
    }
    if ($_SESSION['sms_code_phone'] === $phone && $_SESSION['sms_code'] === $code) {
        unset($_SESSION['sms_code'], $_SESSION['sms_code_phone'], $_SESSION['sms_code_time']);
        return true;
    }
    return false;
}
