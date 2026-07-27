<?php
if (php_sapi_name() !== "cli") { http_response_code(403); exit; }
$docRoot = __DIR__;
require_once $docRoot . '/config/database.php';
require_once $docRoot . '/includes/functions.php';
require_once $docRoot . '/includes/db.php';
require_once $docRoot . '/includes/geo.php';
date_default_timezone_set("Asia/Hong_Kong");

if (!claimWorkerSlot()) { fwrite(STDOUT, "[" . date("H:i:s") . "] Worker pool full\n"); exit; }

$detectId = isset($argv[1]) ? (int)$argv[1] : 0;
if (!$detectId) { fwrite(STDERR, "[" . date("H:i:s") . "] No detect ID\n"); exit; }

fwrite(STDOUT, "[" . date("H:i:s") . "] Processing detect #{$detectId}...\n");
dbExecute("UPDATE geo_detect_results SET status='processing', started_at=NOW() WHERE id=?", [$detectId]);

$detect = dbFetchOne("SELECT * FROM geo_detect_results WHERE id=?", [$detectId]);
if (!$detect) { fwrite(STDERR, "[" . date("H:i:s") . "] Detect #{$detectId} not found\n"); exit; }

$settings = geoGetAdminSettings();
$platform = $detect["platform"] ?? "豆包";
if ($platform === "DeepSeek") {
    $provider = geoGetApiProvider("deepseek");
    if (empty($provider["api_key"])) {
        dbExecute("UPDATE geo_detect_results SET status='failed', error='DeepSeek API Key not configured', completed_at=NOW() WHERE id=?", [$detectId]);
        fwrite(STDERR, "[" . date("H:i:s") . "] No DeepSeek API key\n"); exit;
    }
    $apiKey = $provider["api_key"];
    $endpoint = $provider["api_endpoint"];
    $model = $provider["model"];
} else {
    if (empty($settings["api_key"])) {
        dbExecute("UPDATE geo_detect_results SET status='failed', error='API Key not configured', completed_at=NOW() WHERE id=?", [$detectId]);
        fwrite(STDERR, "[" . date("H:i:s") . "] No API key\n"); exit;
    }
    $apiKey = $settings["api_key"];
    $endpoint = $settings["api_endpoint"];
    $model = $settings["model"];
}

$prompt = "用户搜索了：" . $detect["question"] . "\n\n请先回答用户的搜索问题。\n\n回答完成后，请在最后输出两行：\n品牌提及：[是/否]\n品牌排名：[数字]（如果品牌被提到，指出是第几个被推荐的；未提及则写无）\n\n注意：请判断「" . $detect["brand"] . "」是否在你的回答中被提及或推荐。";

$payload = json_encode(["model" => $model, "messages" => [["role" => "system", "content" => "你是一个 AI 搜索助手，请如实回答用户问题。"], ["role" => "user", "content" => $prompt]], "max_tokens" => 4096]);

$ch = curl_init();
curl_setopt_array($ch, [CURLOPT_URL => $endpoint, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bearer " . $apiKey], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error || $httpCode !== 200) {
    $errMsg = $error ?: "HTTP " . $httpCode;
    dbExecute("UPDATE geo_detect_results SET status='failed', error=?, completed_at=NOW() WHERE id=?", [$errMsg, $detectId]);
    fwrite(STDERR, "[" . date("H:i:s") . "] API failed: {$errMsg}\n"); exit;
}

$data = json_decode($response, true);
$resultText = $data["choices"][0]["message"]["content"] ?? $data["output"][0]["content"][0]["text"] ?? $data["output"][0]["content"] ?? $data["content"] ?? "";
$bm = (int)(mb_stripos($resultText, $detect["brand"]) !== false || preg_match("/品牌提及：是/u", $resultText));

$bp = null; if (preg_match("/品牌排名：(\d+)/u", $resultText, $m)) $bp = (int)$m[1];
dbExecute("UPDATE geo_detect_results SET status='completed', result_text=?, brand_mentioned=?, brand_position=?, platform=?, completed_at=NOW() WHERE id=?", [$resultText, $bm, $bp, $platform, $detectId]);
fwrite(STDOUT, "[" . date("H:i:s") . "] Detect #{$detectId} done, brand " . ($bm ? "mentioned" : "not mentioned") . "\n");

