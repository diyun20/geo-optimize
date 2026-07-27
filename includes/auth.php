<?php


function isLoggedIn(): bool {
    return isset($_SESSION["user_id"]);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return dbFetchOne("SELECT id, username, email, phone, qq, wechat, show_email, show_phone, show_qq, show_wechat, role, agent_id, balance, banned, membership, membership_expire, referral_code, created_at FROM users WHERE id = ?", [$_SESSION["user_id"]]);
}

function login(string $username, string $password): bool|string {
    $user = dbFetchOne("SELECT * FROM users WHERE username = ? OR email = ? OR phone = ?", [$username, $username, $username]);
    if ($user && password_verify($password, $user["password"])) {
        if (!empty($user['banned'])) { appLog("login BLOCKED (banned): " . $username, "WARN"); return 'banned'; }
        $_SESSION["user_id"] = (int)$user["id"];
        $_SESSION["username"] = $user["username"];
        dbExecute("UPDATE users SET last_login_at=NOW() WHERE id=?", [$user["id"]]);
        appLog("login OK: " . $username);
        return true;
    }
    appLog("login FAIL: " . $username, "WARN");
    return false;
}

function register(string $username, string $email, string $password, string $role = 'user', ?int $agentId = null, ?string $referralCode = null): bool|string {
    if (dbFetchOne("SELECT id FROM users WHERE username = ?", [$username])) return "Username exists";
    if (dbFetchOne("SELECT id FROM users WHERE email = ?", [$email])) return "Email exists";
    $hp = password_hash($password, PASSWORD_DEFAULT);
    $membership = dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='membership_default'")['setting_value'] ?? 'vip';
    // 生成唯一邀请码
    $myCode = substr(md5($username . uniqid()), 0, 8);
    dbInsert("INSERT INTO users (username,email,password,role,agent_id,membership,referral_code,created_at) VALUES (?,?,?,?,?,?,?,?)", [$username,$email,$hp,$role,$agentId,$membership,$myCode,date("Y-m-d H:i:s")]);
    $newUserId = dbFetchOne("SELECT id FROM users WHERE username=?", [$username])['id'];
    
    // 处理邀请返利
    if ($referralCode && $newUserId) {
        $referrer = dbFetchOne("SELECT id, role, membership, membership_expire FROM users WHERE referral_code=? AND id!=?", [$referralCode, $newUserId]);
        if ($referrer) {
            // 代理商邀请：归入名下
            if ($referrer['role'] === 'agent') {
                dbExecute("UPDATE users SET agent_id=? WHERE id=?", [$referrer['id'], $newUserId]);
            }
            // 给邀请人加7天VIP（代理商也加）
            $now = time();
            $currentExpire = $referrer['membership_expire'] ? strtotime($referrer['membership_expire']) : 0;
            $base = max($now, $currentExpire);
            $newExpire = date('Y-m-d', strtotime('+7 days', $base));
            dbExecute("UPDATE users SET membership='vip', membership_expire=? WHERE id=?", [$newExpire, $referrer['id']]);
            // 记录奖励
            try {
                dbExecute("INSERT INTO referral_rewards (referrer_id, new_user_id, reward_days, created_at) VALUES (?,?,7,NOW())", [$referrer['id'], $newUserId]);
            } catch (Exception $e) {}
        }
    }
    
    appLog("new user: " . $username . " role=" . $role . " membership=" . $membership);
    return true;
}

/** 给老用户补生成邀请码 */
function ensureReferralCode(int $userId): string {
    $user = dbFetchOne("SELECT referral_code FROM users WHERE id=?", [$userId]);
    if (!empty($user['referral_code'])) return $user['referral_code'];
    $code = substr(md5($userId . uniqid()), 0, 8);
    dbExecute("UPDATE users SET referral_code=? WHERE id=?", [$code, $userId]);
    return $code;
}

function logout(): void { session_destroy(); }

function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION["redirect_after"] = ($_SERVER["REQUEST_URI"] ?? "index.php?route=home");
        setFlash("warning", "Please login first");
        redirect("index.php?route=login");
    }
}

function hasRole(string $role): bool {
    $u = currentUser();
    return $u && $u["role"] === $role;
}

function isAdmin(): bool { return hasRole("admin"); }
function isAgent(): bool { return hasRole("agent"); }
function isAdminOrAgent(): bool { return isAdmin() || isAgent(); }

/** 检查当前用户会员等级 */
function userMembership(): string { $u = currentUser(); return $u['membership'] ?? 'vip'; }
function isVip(): bool { return userMembership() === 'vip' || isAdminOrAgent(); }

/** 检查某功能在当前会员等级下是否可用 */
function membershipCan(string $featureKey): bool {
    if (isAdminOrAgent()) return true; // 管理员和代理商不受限
    $membership = userMembership();
    $key = $membership === 'vip' ? 'membership_vip_features' : 'membership_trial_features';
    $features = json_decode(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key=?", [$key])['setting_value'] ?? '[]', true);
    return in_array($featureKey, $features ?? []);
}

/** 获取会员配置 */
function membershipGetConfig(): array {
    return [
        'default' => dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='membership_default'")['setting_value'] ?? 'vip',
        'upgrade_price' => dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='membership_upgrade_price'")['setting_value'] ?? '0.00',
        'trial_features' => json_decode(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='membership_trial_features'")['setting_value'] ?? '[]', true) ?: [],
        'vip_features' => json_decode(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='membership_vip_features'")['setting_value'] ?? '[]', true) ?: [],
        'trial_keyword_limit' => (int)(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='membership_trial_keyword_limit'")['setting_value'] ?? 5),
        'trial_distill_limit' => (int)(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='membership_trial_distill_limit'")['setting_value'] ?? 50),
        'trial_article_limit' => (int)(dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='membership_trial_article_limit'")['setting_value'] ?? 3),
    ];
}

/** 页面级VIP权限拦截 */
function requireFeatureOrVip(string $featureKey): void {
    if (!membershipCan($featureKey)) {
        if (!isset($GLOBALS['pageTitle'])) $GLOBALS['pageTitle'] = 'VIP功能 - GEO优化';
        require __DIR__ . '/../pages/_vip_prompt.php';
        exit;
    }
}

/** 检查试用限额，返回可用数量，-1表示不限 */
function trialLimitRemaining(string $type): int {
    if (isAdminOrAgent() || isVip()) return -1;
    $cfg = membershipGetConfig();
    $user = currentUser();
    switch ($type) {
        case 'keyword':
            $used = (int)(dbFetchOne("SELECT COUNT(*) as c FROM geo_keywords WHERE user_id=?", [$user['id']])['c'] ?? 0);
            return max(0, $cfg['trial_keyword_limit'] - $used);
        case 'distill':
            $used = (int)(dbFetchOne("SELECT COUNT(*) as c FROM geo_keywords_distill WHERE user_id=?", [$user['id']])['c'] ?? 0);
            return max(0, $cfg['trial_distill_limit'] - $used);
        case 'article':
            $used = (int)(dbFetchOne("SELECT COUNT(*) as c FROM geo_articles WHERE user_id=?", [$user['id']])['c'] ?? 0);
            return max(0, $cfg['trial_article_limit'] - $used);
    }
    return -1;
}

function requireRole(string $role): void {
    if (!hasRole($role)) {
        setFlash("error", "Need role: " . $role);
        redirect("index.php?route=dashboard");
    }
}

function requireAdminOrAgent(): void {
    if (!isAdminOrAgent()) {
        setFlash("error", "无权限访问");
        redirect("index.php?route=dashboard");
    }
}

/** 检查当前用户是否可以管理目标用户 */
function canManageUser(int $targetUserId): bool {
    if (isAdmin()) return true;
    $u = currentUser();
    if (!$u || !isAgent()) return false;
    $target = dbFetchOne("SELECT agent_id FROM users WHERE id=?", [$targetUserId]);
    return $target && (int)$target['agent_id'] === (int)$u['id'];
}

function getNavItems(): array {
    $items = [
        ["route" => "dashboard",   "icon" => "\xF0\x9F\x94\xAE", "label" => isAdmin() ? "管理控制台" : (isAgent() ? "代理控制台" : "数据总览"), "role" => null],
        ["route" => "admin/users", "icon" => "\xF0\x9F\x91\x83", "label" => "用户管理",        "role" => ["admin","agent"]],
        ["route" => "admin/queue", "icon" => "\xF0\x9F\x93\xB5", "label" => "任务队列", "role" => "admin"],
        ["route" => "admin/auto-task", "icon" => "⚙️", "label" => "自动任务", "role" => "admin"],
        ["route" => "admin/virtual-collection", "icon" => "🔖", "label" => "虚拟收录", "role" => ["admin","agent"]],
        ["route" => "admin/finance", "icon" => "\xF0\x9F\x92\xB0", "label" => "金额明细", "role" => ["admin","agent"]],
        ["route" => "admin/api-config", "icon" => "🔑", "label" => "API配置", "role" => ["admin","agent"]],
        ["route" => "admin/settings", "icon" => "\xE2\x9A\x99\xEF\xB8\x8F", "label" => "网站管理", "role" => "admin"],
        ["route" => "admin/membership", "icon" => "👑", "label" => "会员套餐", "role" => "admin"],
        ["route" => "admin/templates", "icon" => "🎨", "label" => "模板管理", "role" => "admin"],
        ["route" => "admin/update", "icon" => "🔄", "label" => "系统更新", "role" => "admin"],
        ["route" => "keywords-distill", "icon" => "\xF0\x9F\x94\x88", "label" => "关键词批量蒸馏", "role" => "user", "feature" => "keywords-distill"],
        ["route" => "keywords",    "icon" => "\xF0\x9F\x94\xB3", "label" => "GEO检测",  "role" => "user", "feature" => "keywords"],
        ["route" => "article-generate", "icon" => "\xF0\x9F\x93\x91", "label" => "文章一键生成",  "role" => "user", "feature" => "article-generate"],
        ["route" => "article-publish", "icon" => "\xF0\x9F\x93\x9B", "label" => "文章多平台推送", "role" => "user", "feature" => "article-publish"],
        ["route" => "video-script",    "icon" => "\xF0\x9F\x8E\x80", "label" => "视频脚本一键生成", "role" => "user", "feature" => "video-script"],
        ["route" => "video-analyze",   "icon" => "\xF0\x9F\x93\xB3", "label" => "视频号分析", "role" => "user", "feature" => "video-analyze"],
        ["route" => "password",    "icon" => "\xF0\x9F\x94\x81", "label" => "个人信息", "role" => null],
        ["route" => "company",     "icon" => "\xF0\x9F\x8F\x9A", "label" => "企业信息", "role" => "user"],
        ["route" => "tickets",    "icon" => "\xF0\x9F\x8E\xAB", "label" => "工单系统", "role" => null],
        ["route" => "referral",   "icon" => "\xF0\x9F\x8E\x81", "label" => "拉新返利", "role" => ["user","agent"]],
    ];
    return array_filter($items, function ($item) {
        if ($item["role"] === null) return true;
        if (is_array($item["role"])) {
            $ok = false;
            foreach ($item["role"] as $r) { if (hasRole($r)) { $ok = true; break; } }
            if (!$ok) return false;
        } else {
            if (!hasRole($item["role"])) return false;
        }
        return true;
    });
}