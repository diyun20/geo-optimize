<?php
$content = file_get_contents("C:\Users\LQW\Desktop\项目\GEO\pages\dashboard.php");
$phpOpen = preg_match_all('/\<\?php|\<\?=/', $content);
$phpClose = preg_match_all('/\?\>/', $content);
echo "<?php|<?= count: $phpOpen\n";
echo "?> count: $phpClose\n";
