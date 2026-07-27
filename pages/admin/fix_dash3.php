<?php
$content = file_get_contents("C:\Users\LQW\Desktop\项目\GEO\pages\dashboard.php");
$lines = explode("\n", $content);
$fixed = 0;
// Strip non-ASCII from ALL lines that have non-ASCII content
foreach ($lines as $i => $line) {
    if (preg_match('/[^\x20-\x7E\r]/', $line)) {
        // Replace non-printable/non-ASCII with spaces, preserving \r
        $lines[$i] = preg_replace('/[^\x20-\x7E\r\t]/', ' ', $line);
        $fixed++;
    }
}
file_put_contents("C:\Users\LQW\Desktop\项目\GEO\pages\dashboard.php", implode("\n", $lines));
echo "Fixed $fixed lines\n";
