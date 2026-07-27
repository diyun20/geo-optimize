<?php
$content = file_get_contents("php://stdin");
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    $hasNonAscii = false;
    for ($j = 0; $j < strlen($line); $j++) {
        if (ord($line[$j]) > 127) { $hasNonAscii = true; break; }
    }
    if ($hasNonAscii) {
        echo "Line " . ($i + 1) . ": " . trim(substr($line, 0, 70)) . "\n";
    }
}
