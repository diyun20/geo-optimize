<?php
$content = file_get_contents("C:\Users\LQW\Desktop\项目\GEO\pages\dashboard.php");
$lines = explode("\n", $content);
echo "Total lines: " . count($lines) . "\n";
echo "Line 227: " . ($lines[226] ?? "N/A") . "\n";
// Count if: and endif
$ifcolon = preg_match_all('/if\s*\([^)]+\)\s*:\s*$/', $content);
$endif = preg_match_all('/endif;\s*$/', $content);
echo "if: count: $ifcolon\n";
echo "endif count: $endif\n";
