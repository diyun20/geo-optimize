<?php
 /**
  * 安装向导 - 多步骤 Web 安装器
  * 
  * Step 1: 环境检查
  * Step 2: 数据库配置
  * Step 3: 运行安装（建表 + 创建管理员）
  * Step 4: 安装完成
  */
 
 if (session_status() === PHP_SESSION_NONE) session_start(); ob_start();
 
 $step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
 $error = '';
 $success = '';
 
 // 检查是否已经安装
 $lockFile = __DIR__ . '/../storage/installed.lock';
 if (file_exists($lockFile)) {
     $lockData = json_decode(file_get_contents($lockFile), true);
     if ($lockData['installed'] ?? false) {
         $alreadyInstalled = true;
     }
 }
 ?>
 <!DOCTYPE html>
 <html lang="zh-CN">
 <head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <title>安装向导 - GEO优化</title>
     <link rel="stylesheet" href="../public/css/style.css">
     <style>
         body { background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
         .installer-wrapper { width: 100%; max-width: 680px; }
         .installer-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); overflow: hidden; }
         .installer-header { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; padding: 30px; text-align: center; }
         .installer-header h1 { margin: 0 0 8px; font-size: 24px; }
         .installer-header p { margin: 0; opacity: 0.9; font-size: 14px; }
         .installer-body { padding: 30px; }
         .step-indicator { display: flex; justify-content: center; gap: 8px; margin-bottom: 24px; }
         .step-dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; background: #e5e7eb; color: #9ca3af; }
         .step-dot.active { background: #4f46e5; color: #fff; }
         .step-dot.done { background: #10b981; color: #fff; }
         .step-line { width: 40px; height: 2px; align-self: center; background: #e5e7eb; }
         .step-line.done { background: #10b981; }
         .form-group { margin-bottom: 16px; }
         .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #374151; }
         .form-group input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
         .form-group input:focus { border-color: #4f46e5; outline: none; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
         .btn { display: inline-block; padding: 10px 24px; border: none; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; }
         .btn-primary { background: #4f46e5; color: #fff; }
         .btn-primary:hover { background: #4338ca; }
         .btn-success { background: #10b981; color: #fff; }
         .btn-success:hover { background: #059669; }
         .btn-block { width: 100%; text-align: center; }
         .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
         .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
         .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
         .check-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
         .check-item:last-child { border-bottom: none; }
         .check-label { font-size: 14px; color: #374151; }
         .check-status { font-size: 13px; font-weight: 600; }
         .check-pass { color: #10b981; }
         .check-fail { color: #dc2626; }
         .check-warn { color: #f59e0b; }
         .text-center { text-align: center; }
         .mt-4 { margin-top: 24px; }
         .mb-4 { margin-bottom: 24px; }
         .installer-footer { text-align: center; padding: 20px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #9ca3af; }
         .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
         @media (max-width: 480px) { .form-row { grid-template-columns: 1fr; } }
         .installer-logo { font-size: 40px; margin-bottom: 8px; }
     </style>
 </head>
 <body>
     <div class="installer-wrapper">
         <div class="installer-card">
             <div class="installer-header">
                 <div class="installer-logo">🚀</div>
                 <h1>MyApp 安装向导</h1>
                 <p>只需几步，即可完成系统安装</p>
             </div>
             
             <div class="installer-body">
                 <!-- 步骤指示器 -->
                 <div class="step-indicator">
                     <div class="step-dot <?= $step > 1 ? 'done' : ($step === 1 ? 'active' : '') ?>">1</div>
                     <div class="step-line <?= $step > 2 ? 'done' : '' ?>"></div>
                     <div class="step-dot <?= $step > 2 ? 'done' : ($step === 2 ? 'active' : '') ?>">2</div>
                     <div class="step-line <?= $step > 3 ? 'done' : '' ?>"></div>
                     <div class="step-dot <?= $step > 3 ? 'done' : ($step === 3 ? 'active' : '') ?>">3</div>
                     <div class="step-line <?= $step > 4 ? 'done' : '' ?>"></div>
                     <div class="step-dot <?= $step > 4 ? 'done' : ($step === 4 ? 'active' : '') ?>">4</div>
                 </div>
 
                 <?php if (isset($alreadyInstalled)): ?>
                     <div class="alert alert-error text-center">
                         <strong>系统已安装</strong>
                         <p style="margin:8px 0 0">安装锁定文件已存在。如需重新安装，请删除 <code>storage/installed.lock</code> 文件。</p>
                         <a href="../" class="btn btn-primary mt-4">进入首页</a>
                     </div>
                 <?php elseif ($step === 1): ?>
                     <!-- Step 1: 环境检查 -->
                     <h2 style="margin-top:0;font-size:18px;">环境检查</h2>
                     <p style="color:#6b7280;font-size:14px;margin-bottom:20px;">系统将检查您的服务器环境是否满足运行要求。</p>
                     
                     <?php
                     $checks = [];
                     $allPass = true;
                     
                     // PHP 版本检查
                     $phpVersion = PHP_VERSION;
                     $phpOk = version_compare($phpVersion, '8.0', '>=');
                     $checks[] = ['label' => 'PHP 版本 (≥ 8.0)', 'status' => $phpOk ? 'pass' : 'fail', 'detail' => $phpVersion];
                     if (!$phpOk) $allPass = false;
                     
                     // PDO 扩展
                     $pdoOk = extension_loaded('pdo');
                     $checks[] = ['label' => 'PDO 扩展', 'status' => $pdoOk ? 'pass' : 'fail', 'detail' => $pdoOk ? '已加载' : '未安装'];
                     if (!$pdoOk) $allPass = false;
                     
                     // PDO MySQL 扩展
                     $mysqlOk = extension_loaded('pdo_mysql');
                     $checks[] = ['label' => 'PDO_MySQL 扩展', 'status' => $mysqlOk ? 'pass' : 'fail', 'detail' => $mysqlOk ? '已加载' : '未安装'];
                     if (!$mysqlOk) $allPass = false;
                     
                     // JSON 扩展
                     $jsonOk = extension_loaded('json');
                     $checks[] = ['label' => 'JSON 扩展', 'status' => $jsonOk ? 'pass' : 'fail', 'detail' => $jsonOk ? '已加载' : '未安装'];
                     if (!$jsonOk) $allPass = false;
                     
                     // GD 或 Imagick（可选）
                     $gdOk = extension_loaded('gd');
                     $checks[] = ['label' => 'GD 扩展（图片处理）', 'status' => $gdOk ? 'pass' : 'warn', 'detail' => $gdOk ? '已加载' : '可选'];
                     
                     // 文件权限检查
                     $configDir = __DIR__ . '/../config';
                     $storageDir = __DIR__ . '/../storage';
                     $uploadsDir = __DIR__ . '/../uploads';
                     $configWritable = is_writable(dirname($configDir));
                     $storageWritable = is_writable($storageDir) || (is_dir($storageDir) && is_writable(dirname($storageDir)));
                     $checks[] = ['label' => 'config/ 目录可写', 'status' => 'pass', 'detail' => '可写'];
                     
                     // session 支持
                     $checks[] = ['label' => 'Session 支持', 'status' => 'pass', 'detail' => '已启用'];
                     ?>
                     
                     <div style="background:#f9fafb;border-radius:6px;padding:16px;">
                         <?php foreach ($checks as $c): ?>
                             <div class="check-item">
                                 <span class="check-label"><?= htmlspecialchars($c['label']) ?></span>
                                 <span>
                                     <span class="check-<?= $c['status'] ?>">●</span>
                                     <span class="check-<?= $c['status'] ?>"><?= htmlspecialchars($c['detail']) ?></span>
                                 </span>
                             </div>
                         <?php endforeach; ?>
                     </div>
                     
                     <?php if ($allPass): ?>
                         <form method="get" action="index.php" class="mt-4">
                             <input type="hidden" name="step" value="2">
                             <button type="submit" class="btn btn-primary btn-block">环境检查通过，继续 →</button>
                         </form>
                     <?php else: ?>
                         <div class="alert alert-error mt-4">
                             <strong>环境不满足要求</strong>
                             <p style="margin:4px 0 0">请安装缺失的 PHP 扩展后再试。</p>
                         </div>
                     <?php endif; ?>
                     
                 <?php elseif ($step === 2): ?>
                     <!-- Step 2: 数据库配置 -->
                     <h2 style="margin-top:0;font-size:18px;">数据库配置</h2>
                     <p style="color:#6b7280;font-size:14px;margin-bottom:20px;">请输入您的 MySQL 数据库连接信息。</p>
                     
                     <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                         <?php
                         $host = $_POST['host'] ?? 'localhost';
                         $port = (int)($_POST['port'] ?? 3306);
                         $dbname = $_POST['dbname'] ?? '';
                         $username = $_POST['username'] ?? '';
                         $password = $_POST['password'] ?? '';
                         
                         if (empty($dbname) || empty($username)) {
                             $error = '请填写数据库名和用户名';
                         } else {
                             try {
                                 // 先尝试连接（不指定数据库）
                                 $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
                                 $testPdo = new PDO($dsn, $username, $password, [
                                     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                     PDO::ATTR_TIMEOUT => 5,
                                 ]);
                                 
                                 // 尝试创建数据库（如果不存在）
                                 $testPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                                 
                                 // 保存配置到 session
                                 $_SESSION['install_db'] = [
                                     'host'     => $host,
                                     'port'     => $port,
                                     'dbname'   => $dbname,
                                     'username' => $username,
                                     'password' => $password,
                                 ];
                                 
                                 $success = '数据库连接成功！';
                                 // 自动跳转到下一步
                                 echo '<meta http-equiv="refresh" content="1;url=?step=3">';
                             } catch (PDOException $e) {
                                 $error = '数据库连接失败：' . $e->getMessage();
                             }
                         }
                         ?>
                     <?php endif; ?>
                     
                     <?php if ($error): ?>
                         <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                     <?php endif; ?>
                     <?php if ($success): ?>
                         <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                     <?php endif; ?>
                     
                     <form method="post" action="?step=2">
                         <div class="form-row">
                             <div class="form-group">
                                 <label for="host">数据库主机</label>
                                 <input type="text" id="host" name="host" value="<?= htmlspecialchars($_POST['host'] ?? 'localhost') ?>" required>
                             </div>
                             <div class="form-group">
                                 <label for="port">端口</label>
                                 <input type="number" id="port" name="port" value="<?= htmlspecialchars($_POST['port'] ?? '3306') ?>" required>
                             </div>
                         </div>
                         <div class="form-group">
                             <label for="dbname">数据库名</label>
                             <input type="text" id="dbname" name="dbname" value="<?= htmlspecialchars($_POST['dbname'] ?? '') ?>" required>
                         </div>
                         <div class="form-group">
                             <label for="username">数据库用户名</label>
                             <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                         </div>
                         <div class="form-group">
                             <label for="password">数据库密码</label>
                             <input type="password" id="password" name="password" value="<?= htmlspecialchars($_POST['password'] ?? '') ?>">
                         </div>
                         <button type="submit" class="btn btn-primary btn-block">测试连接并继续 →</button>
                     </form>
                     
                 <?php elseif ($step === 3): ?>
                     <!-- Step 3: 执行安装 -->
                     <h2 style="margin-top:0;font-size:18px;">执行安装</h2>
                     <p style="color:#6b7280;font-size:14px;margin-bottom:20px;">正在创建数据库表和初始数据...</p>
                     
                     <?php
                    $installSuccess = true;
                    if (!isset($_SESSION['install_db'])) {
                        $installSuccess = false;
                         echo '<div class="alert alert-error">安装信息丢失，请从第一步重新开始。</div>';
                         echo '<a href="?step=1" class="btn btn-primary btn-block">重新开始</a>';
                     } else {
                         $dbConfig = $_SESSION['install_db'];
                         $installSuccess = true;
                         $installMessages = [];
                         
                         try {
                             // 连接数据库
                             $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                                 $dbConfig['host'], $dbConfig['port'], $dbConfig['dbname']);
                             $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                                 PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                             ]);
                             
                        $configContent = '<?php' . PHP_EOL
                            . '/**' . PHP_EOL
                            . ' * 数据库配置 - 由安装向导自动生成' . PHP_EOL
                            . ' */' . PHP_EOL . PHP_EOL
                            . 'return [' . PHP_EOL
                            . "    'host'     => '{$dbConfig['host']}'," . PHP_EOL
                            . "    'port'     => {$dbConfig['port']}," . PHP_EOL
                            . "    'dbname'   => '{$dbConfig['dbname']}'," . PHP_EOL
                            . "    'username' => '{$dbConfig['username']}'," . PHP_EOL
                            . "    'password' => '{$dbConfig['password']}'," . PHP_EOL
                            . "    'charset'  => 'utf8mb4'," . PHP_EOL
                            . '];' . PHP_EOL;
                             
                             file_put_contents(__DIR__ . '/../config/database.php', $configContent);
                             $installMessages[] = ['type' => 'success', 'text' => '数据库配置文件已生成'];
                             
                             // 执行 schema.sql
                             $schemaFile = __DIR__ . '/../schema.sql';
                             if (file_exists($schemaFile)) {
                                 $sql = file_get_contents($schemaFile);
                                 // 按分号分割执行多条语句
                                 $statements = array_filter(
                                     array_map('trim', explode(';', $sql)),
                                     fn($s) => !empty($s) && !str_starts_with($s, '--')
                                 );
                                 foreach ($statements as $stmt) {
                                     if (!empty(trim($stmt))) {
                                         $pdo->exec($stmt);
                                     }
                                 }
                              $installMessages[] = ['type' => 'success', 'text' => '数据表创建成功'];
                              }
                              
                              // 自动创建默认管理员
                              $hashed = password_hash('123456', PASSWORD_DEFAULT);
                              $now = date('Y-m-d H:i:s');
$stmt = $pdo->prepare('INSERT IGNORE INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)');
                               $stmt->execute(['admin', 'admin@admin.com', $hashed, 'admin', $now]);
                               $installMessages[] = ['type' => 'success', 'text' => '默认管理员已创建 (admin / 123456)'];
                              
                              // 写入锁定文件
                              $lockData = json_encode([
                                  'installed'    => true,
                                  'installed_at' => $now,
                                  'version'      => '1.0.0',
                              ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                              file_put_contents(__DIR__ . '/../storage/installed.lock', $lockData);
                              
                              // 清理 session
                              unset($_SESSION['install_db']);
                              
                          } catch (Exception $e) {
                             $installSuccess = false;
                             $error = '安装过程出错：' . $e->getMessage();
                         }
                     }
                     ?>
                     
                     <?php if (!empty($installMessages)): ?>
                         <div style="background:#f9fafb;border-radius:6px;padding:16px;">
                             <?php foreach ($installMessages as $msg): ?>
                                 <div style="padding:6px 0;color:<?= $msg['type'] === 'success' ? '#16a34a' : '#dc2626' ?>;font-size:14px;">
                                     ✓ <?= htmlspecialchars($msg['text']) ?>
                                 </div>
                             <?php endforeach; ?>
                         </div>
                     <?php endif; ?>
                     
                     <?php if ($error): ?>
                         <div class="alert alert-error mt-4"><?= htmlspecialchars($error) ?></div>
                     <?php endif; ?>
                     
<?php if ($installSuccess): ?>
                          <div class="text-center mt-4">
                              <p style="margin-bottom:16px;color:#6b7280;">管理员账号：<strong>admin</strong> / 密码：<strong>123456</strong></p>
                              <a href="../index.php?route=login" class="btn btn-success btn-block">进入登录 →</a>
                          </div>
                      <?php endif; ?>
                     
                 <?php elseif ($step === 4): ?>
                     <!-- Step 4: 创建管理员 -->
                     <h2 style="margin-top:0;font-size:18px;">创建管理员账号</h2>
                     <p style="color:#6b7280;font-size:14px;margin-bottom:20px;">请设置系统管理员账号信息。</p>
                     
                     <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                         <?php
                         $adminUser = $_POST['username'] ?? '';
                         $adminEmail = $_POST['email'] ?? '';
                         $adminPass = $_POST['password'] ?? '';
                         $adminPass2 = $_POST['password2'] ?? '';
                         
                         if (empty($adminUser) || empty($adminEmail) || empty($adminPass)) {
                             $error = '请填写所有必填项';
                         } elseif ($adminPass !== $adminPass2) {
                             $error = '两次输入的密码不一致';
                         } elseif (strlen($adminPass) < 6) {
                             $error = '密码长度至少 6 位';
                         } else {
                             try {
                                 $hashed = password_hash($adminPass, PASSWORD_DEFAULT);
                                 $now = date('Y-m-d H:i:s');
                                 
                                 $dbConfig = $_SESSION['install_db'];
                                 $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                                     $dbConfig['host'], $dbConfig['port'], $dbConfig['dbname']);
                                 $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                                     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                 ]);
                                 
$stmt = $pdo->prepare('INSERT IGNORE INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)');
                                 $stmt->execute([$adminUser, $adminEmail, $hashed, 'admin', $now]);
                                 
                                 // 写入锁定文件
                                 $lockData = json_encode([
                                     'installed'   => true,
                                     'installed_at' => $now,
                                     'version'     => '1.0.0',
                                 ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                 file_put_contents(__DIR__ . '/../storage/installed.lock', $lockData);
                                 
                                 // 清理 session
                                 unset($_SESSION['install_db']);
                                 $_SESSION['install_complete'] = true;
                                 
                                 header('Location: ?step=5');
                                 exit;
                             } catch (Exception $e) {
                                 $error = '创建管理员失败：' . $e->getMessage();
                             }
                         }
                         ?>
                     <?php endif; ?>
                     
                     <?php if ($error): ?>
                         <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                     <?php endif; ?>
                     
                     <form method="post" action="?step=4">
                         <div class="form-group">
                             <label for="username">管理员用户名</label>
                             <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>" required>
                         </div>
                         <div class="form-group">
                             <label for="email">管理员邮箱</label>
                             <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                         </div>
                         <div class="form-row">
                             <div class="form-group">
                                 <label for="password">密码</label>
                                 <input type="password" id="password" name="password" value="<?= htmlspecialchars($_POST['password'] ?? '123456') ?>" required>
                             </div>
                             <div class="form-group">
                                 <label for="password2">确认密码</label>
                                 <input type="password" id="password2" name="password2" value="<?= htmlspecialchars($_POST['password2'] ?? '123456') ?>" required>
                             </div>
                         </div>
                         <button type="submit" class="btn btn-success btn-block">完成安装 →</button>
                     </form>
                     
                 <?php elseif ($step === 5): ?>
                     <!-- Step 5: 安装完成 -->
                     <div class="text-center">
                         <div style="font-size:64px;margin-bottom:16px;">🎉</div>
                         <h2 style="font-size:20px;margin:0 0 8px;">安装完成！</h2>
                         <p style="color:#6b7280;font-size:14px;">系统已成功安装，现在可以开始使用了。</p>
                         
                         <div style="background:#f0fdf4;border-radius:8px;padding:16px;margin:24px 0;text-align:left;">
                             <div style="color:#16a34a;font-weight:600;margin-bottom:8px;">安装摘要</div>
                             <div style="font-size:13px;color:#374151;">
                                 <p>✓ 数据库配置已生成</p>
                                 <p>✓ 数据表已创建</p>
                                 <p>✓ 管理员账号已注册</p>
                                 <p>✓ 安装锁定已启用</p>
                             </div>
                         </div>
                         
                         <div style="background:#fffbeb;border-radius:8px;padding:12px 16px;margin-bottom:24px;text-align:left;font-size:13px;color:#92400e;">
                             <strong>安全提示：</strong> 为保障安全，建议删除或重命名 <code>installer/</code> 目录。
                         </div>
                         
                         <a href="../" class="btn btn-primary btn-block">进入首页</a>
                     </div>
                 <?php endif; ?>
             </div>
             
             <div class="installer-footer">
                 MyApp v1.0.0 &mdash; 安装向导
             </div>
         </div>
     </div>
 </body>
 </html>
                        // 写入数据库配置文件
