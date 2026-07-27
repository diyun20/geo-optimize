<?php
/**
 * 验证码图片生成器
 * 用法：<img src="captcha.php" onclick="this.src='captcha.php?'+Date.now()">
 */
session_start();

$width = 120;
$height = 42;
$length = 4;

// 生成随机验证码
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$code = '';
for ($i = 0; $i < $length; $i++) {
    $code .= $chars[random_int(0, strlen($chars) - 1)];
}
$_SESSION['captcha_code'] = $code;
$_SESSION['captcha_time'] = time();

// 创建图片
$image = imagecreatetruecolor($width, $height);

// 背景色
$bg = imagecolorallocate($image, 245, 248, 255);
imagefill($image, 0, 0, $bg);

// 干扰线
for ($i = 0; $i < 5; $i++) {
    $lineColor = imagecolorallocate($image, rand(180, 220), rand(180, 220), rand(200, 230));
    imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $lineColor);
}

// 干扰点
for ($i = 0; $i < 60; $i++) {
    $dotColor = imagecolorallocate($image, rand(160, 210), rand(160, 210), rand(180, 220));
    imagesetpixel($image, rand(0, $width), rand(0, $height), $dotColor);
}

// 绘制文字
$fontSize = 20;
$textColor = imagecolorallocate($image, 15, 52, 96);
$x = 15;
for ($i = 0; $i < $length; $i++) {
    $y = rand(26, 34);
    $angle = rand(-15, 15);
    imagettftext($image, $fontSize, $angle, $x, $y, $textColor, __DIR__ . '/fonts/arial.ttf', $code[$i]);
    $x += 23;
}

// 备用：如果没有字体文件，使用内置字体
if (!function_exists('imagettftext')) {
    $textColor = imagecolorallocate($image, 15, 52, 96);
    imagestring($image, 5, 20, 12, $code, $textColor);
}

header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
imagepng($image);
imagedestroy($image);
