<?php
$content = file_get_contents("C:\Users\LQW\Desktop\项目\GEO\pages\dashboard.php");
// Strip non-ASCII from HTML-only lines (no <?php tag)
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (strpos($line, "<?php") === false && preg_match('/[^\x20-\x7E]/', $line)) {
        $lines[$i] = preg_replace('/[^\x20-\x7E\r]/', ' ', $line);
    }
}
file_put_contents("C:\Users\LQW\Desktop\项目\GEO\pages\dashboard.php", implode("\n", $lines));
echo "Done\n";
