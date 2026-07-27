<?php
$content = file_get_contents("C:\Users\LQW\Desktop\项目\GEO\pages\dashboard.php");
$lines = explode("\n", $content);
// Fix line 91 (0-indexed 90) - admin page header
$lines[90] = "    <h1><?= isAdmin() ? " . "'管理控制台'" . " : " . "'数据总览'" . " ?></h1>";
// Fix line 92 (0-indexed 91) - welcome message
$lines[91] = "    <p>欢迎回来，<?= (!isAdmin() && \$company) ? h(\$company['company_abbr'] ?? \$user['username']) : h(\$user['username']) ?></p>";
file_put_contents("C:\Users\LQW\Desktop\项目\GEO\pages\dashboard.php", implode("\n", $lines));
echo "Fixed\n";
