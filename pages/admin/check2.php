<?php
$content = file_get_contents("C:\Users\LQW\Desktop\项目\GEO\pages\dashboard.php");
// Count PHP blocks above line 90
$lines = explode("\n", $content);
$ifColon = 0;
$endifCount = 0;
for ($i = 0; $i < count($lines); $i++) {
    $line = trim($lines[$i]);
    if (preg_match('/^<\?php\s+if\s*\(.*\)\s*:\s*$/', $line)) {
        echo "if: at line " . ($i + 1) . ": $line\n";
        $ifColon++;
    }
    if ($line === '<?php endif; ?>') {
        echo "endif at line " . ($i + 1) . "\n";
        $endifCount++;
    }
}
echo "Total if: $ifColon, endif: $endifCount\n";
