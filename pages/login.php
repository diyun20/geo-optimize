<?php $pageTitle = "登录 - GEO优化"; ?>

<?php
$captchaOn = (dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='captcha_enabled'")['setting_value'] ?? '0') === '1';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    if (empty($username) || empty($password)) {
        setFlash("error", "请填写用户名和密码");
    } elseif ($captchaOn && (empty($_POST["captcha"]) || strtoupper($_POST["captcha"]) !== ($_SESSION["captcha_code"] ?? ""))) {
        setFlash("error", "验证码错误");
    } elseif (($result = login($username, $password)) === true) {
        appLog("用户 $username 登录成功");
        $redirect = $_SESSION["redirect_after"] ?? "index.php?route=dashboard";
        unset($_SESSION["redirect_after"]);
        redirect($redirect);
    } elseif ($result === 'banned') {
        setFlash("error", "您的账号已被封禁，请联系管理员");
    } else {
        setFlash("error", "用户名或密码错误");
    }
    redirect("index.php?route=login");
}
?>

<div style="max-width:420px;margin:40px auto;">
    <div style="background:linear-gradient(135deg,#f5f7ff 0%,#eef1f7 100%);border-radius:16px;box-shadow:0 4px 30px rgba(0,0,0,0.06);overflow:hidden;border:1px solid rgba(0,0,0,0.04);">
        <div style="height:4px;background:linear-gradient(90deg,#0F3460,#E94560);"></div>
        <div style="padding:36px 36px 32px;">
            <div style="text-align:center;margin-bottom:28px;">
                <div style="width:48px;height:48px;border-radius:12px;background:rgba(15,52,96,0.06);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#0F3460;font-size:22px;"><i class="fa fa-user"></i></div>
                <h2 style="font-size:22px;font-weight:700;color:#0F3460;margin:0;">登录</h2>
            </div>
            <form method="post" action="index.php?route=login">
                <div style="margin-bottom:20px;">
                    <label for="username" style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">用户名</label>
                    <input type="text" id="username" name="username" required style="width:100%;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;box-sizing:border-box;transition:border-color 0.2s,box-shadow 0.2s;" onfocus="this.style.borderColor='#0F3460';this.style.boxShadow='0 0 0 3px rgba(15,52,96,0.08)'" onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none'">
                </div>
                <div style="margin-bottom:24px;">
                    <label for="password" style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">密码</label>
                    <input type="password" id="password" name="password" required style="width:100%;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;box-sizing:border-box;transition:border-color 0.2s,box-shadow 0.2s;" onfocus="this.style.borderColor='#0F3460';this.style.boxShadow='0 0 0 3px rgba(15,52,96,0.08)'" onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none'">
                </div>
                <div style="margin-bottom:20px;">
                <?php if ($captchaOn): ?>
                    <label style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">验证码</label>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <input type="text" name="captcha" maxlength="4" required style="width:120px;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;text-transform:uppercase;box-sizing:border-box;" onfocus="this.style.borderColor='#0F3460'" onblur="this.style.borderColor='#d1d5db'" placeholder="验证码">
                        <img src="captcha.php" onclick="this.src='captcha.php?'+Date.now()" style="height:44px;border-radius:8px;cursor:pointer;border:1px solid #e5e7eb;" title="点击刷新">
                    </div>
                <?php endif; ?>
                </div>
                <button type="submit" style="width:100%;padding:14px 24px;background:#0F3460;color:#fff;font-weight:600;font-size:15px;border:none;border-radius:10px;cursor:pointer;transition:background 0.25s,transform 0.15s;" onmouseover="this.style.background='#16213E'" onmouseout="this.style.background='#0F3460'">登录</button>
            </form>
            <p style="text-align:center;margin:20px 0 0;font-size:14px;color:#6b7280;">
                还没有账号？<a href="index.php?route=register" style="color:#E94560;font-weight:500;text-decoration:none;">立即注册</a>
                &nbsp;|&nbsp;<a href="index.php?route=forgot-password" style="color:#0F3460;font-weight:500;text-decoration:none;">找回密码</a>
            </p>
        </div>
    </div>
</div>