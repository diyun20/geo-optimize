<?php
 
/**
  * PHP CLI CGI 包装器
  * 由 Node.js serve.js 调用，模拟 Web 服务器环境
  */
 
 // 从环境变量读取请求信息
 $_SERVER['REQUEST_METHOD'] = getenv('CM_REQ_METHOD') ?: 'GET';
 $_SERVER['REQUEST_URI']    = getenv('CM_REQ_URI') ?: '/';
 $_SERVER['QUERY_STRING']   = getenv('CM_REQ_QUERY') ?: '';
 $_SERVER['HTTP_HOST']      = 'localhost:8000';
 $_SERVER['SERVER_PROTOCOL']= 'HTTP/1.1';
 $_SERVER['SCRIPT_NAME']    = getenv('CM_REQ_SCRIPT') ?: '';
 $_SERVER['HTTP_COOKIE']    = getenv('CM_REQ_COOKIE') ?: '';
 
 // 解析 GET
 parse_str($_SERVER['QUERY_STRING'], $_GET);
 
 // 解析 POST
 if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $raw = getenv('CM_REQ_BODY') ?: '';
     parse_str($raw, $_POST);
     $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
 }
 
 // 解析 Cookie
 if ($_SERVER['HTTP_COOKIE']) {
     foreach (explode(';', $_SERVER['HTTP_COOKIE']) as $pair) {
         $parts = explode('=', $pair, 2);
         if (count($parts) === 2) {
             $_COOKIE[trim($parts[0])] = trim($parts[1]);
         }
     }
 }
 
 // 恢复 Session
 $sid = getenv('CM_REQ_SESSID') ?: ($_COOKIE['PHPSESSID'] ?? '');
 if ($sid) {
     @session_id($sid);
 }
 
 $target = getenv('CM_TARGET');
 if (!$target || !file_exists($target)) {
     http_response_code(404);
     echo '404 Not Found';
     exit;
 }
 
 chdir(dirname($target));
 
 // 捕获输出
 ob_start();
 
 register_shutdown_function(function () {
     $body = @ob_get_clean();
     $code = http_response_code() ?: 200;
     $hdrs = headers_list();
     $newSid = @session_id();
 
     // 将结果写入 stderr，Node.js 读取
     $result = json_encode([
         's'   => $code,
         'h'   => $hdrs,
         'b'   => $body,
         'sid' => $newSid ?: '',
     ]);
     fwrite(STDERR, "\n---RESULT---\n" . $result . "\n");
 });
 
 require $target;
