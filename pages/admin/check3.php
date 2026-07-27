<?php
$lines = file("C:\Users\LQW\Desktop\项目\GEO\pages\dashboard.php");
$ifc = 0; $end = 0;
foreach ($lines as $i => $l) {
    $t = rtrim($l);
    if (preg_match('/^<\?php\s+if\s*\(/', $t)) { $ifc++; }
    if (preg_match('/^<\?php\s+endif;\s*\?>$/', $t)) { $end++; }
}
echo "if: $ifc, endif: $end\n";
