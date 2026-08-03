<?php $pageTitle = "注册 - GEO优化";
if (isLoggedIn()) redirect("index.php?route=dashboard");
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/../includes/mail.php';

// AJAX 发验证码
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if ($_GET['action'] === 'send_sms' && smsIsConfigured()) {
        $phone = trim($_POST['phone'] ?? '');
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) { echo json_encode(['ok'=>false,'msg'=>'手机号格式不正确']); exit; }
        $result = smsSendCode($phone);
        echo json_encode(['ok'=>$result['success'], 'msg'=>$result['error']??'']);
        exit;
    }
    if ($_GET['action'] === 'send_mail' && mailIsConfigured()) {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok'=>false,'msg'=>'邮箱格式不正确']); exit; }
        $result = mailSendCode($email);
        echo json_encode(['ok'=>$result['success'], 'msg'=>$result['error']??'']);
        exit;
    }
    echo json_encode(['ok'=>false,'msg'=>'未知操作']); exit;
}

$smsOn = smsIsConfigured();
$mailOn = mailIsConfigured();
$captchaOn = (dbFetchOne("SELECT setting_value FROM site_settings WHERE setting_key='captcha_enabled'")['setting_value'] ?? '0') === '1';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $password2 = $_POST["password2"] ?? "";
    $phone = trim($_POST["phone"] ?? "");

    if (empty($username) || empty($email) || empty($password)) {
        setFlash("error", "请填写所有必填项");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash("error", "邮箱格式不正确");
    } elseif ($password !== $password2) {
        setFlash("error", "两次输入的密码不一致");
    } elseif (strlen($password) < 6) {
        setFlash("error", "密码至少 6 位");
    } elseif ($smsOn && !smsVerify($phone, trim($_POST['sms_code'] ?? ''))) {
        setFlash("error", "短信验证码错误");
    } elseif ($mailOn && !mailVerify($email, trim($_POST['mail_code'] ?? ''))) {
        setFlash("error", "邮箱验证码错误");
    } elseif ($captchaOn && (empty($_POST["captcha"]) || strtoupper($_POST["captcha"]) !== ($_SESSION["captcha_code"] ?? ""))) {
        setFlash("error", "验证码错误");
    } else {
        $refCode = trim($_POST['ref'] ?? $_GET['ref'] ?? '');
        $result = register($username, $email, $password, 'user', null, $refCode ?: null);
        if ($result === true) {
            if ($phone) { try { dbExecute("UPDATE users SET phone=? WHERE username=?", [$phone, $username]); } catch (Exception $e) {} }
            setFlash("success", "注册成功，请登录");
            redirect("index.php?route=login");
        } else {
            setFlash("error", $result);
        }
    }
    redirect("index.php?route=register");
}
?>

<div style="max-width:420px;margin:40px auto;">
    <div style="background:linear-gradient(135deg,#f5f7ff 0%,#eef1f7 100%);border-radius:16px;box-shadow:0 4px 30px rgba(0,0,0,0.06);overflow:hidden;border:1px solid rgba(0,0,0,0.04);">
        <div style="height:4px;background:linear-gradient(90deg,#0F3460,#E94560);"></div>
        <div style="padding:36px 36px 32px;">
            <div style="text-align:center;margin-bottom:28px;">
                <div style="width:48px;height:48px;border-radius:12px;background:rgba(233,69,96,0.06);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#E94560;font-size:22px;"><i class="fa fa-user-plus"></i></div>
                <h2 style="font-size:22px;font-weight:700;color:#0F3460;margin:0;">注册</h2>
            </div>
            <form method="post" action="index.php?route=register<?= !empty($_GET['ref']) ? '&ref='.urlencode($_GET['ref']) : '' ?>" id="regForm">
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">用户名</label>
                    <input type="text" name="username" required style="width:100%;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;box-sizing:border-box;">
                </div>

                <?php if ($smsOn): ?>
                <!-- 短信验证模式 -->
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">手机号</label>
                    <input type="text" name="phone" id="phone" required placeholder="用于接收验证码" style="width:100%;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">短信验证码</label>
                    <div style="display:flex;gap:10px;">
                        <input type="text" name="sms_code" maxlength="6" required style="flex:1;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;box-sizing:border-box;">
                        <button type="button" id="btnSms" onclick="sendSms()" style="padding:12px 16px;background:#4f46e5;color:#fff;border:none;border-radius:10px;font-size:13px;cursor:pointer;white-space:nowrap;">发送验证码</button>
                    </div>
                </div>
                <?php endif; ?>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">邮箱</label>
                    <input type="email" name="email" id="email" required style="width:100%;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;box-sizing:border-box;">
                </div>

                <?php if ($mailOn): ?>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">邮箱验证码</label>
                    <div style="display:flex;gap:10px;">
                        <input type="text" name="mail_code" maxlength="6" required style="flex:1;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;box-sizing:border-box;">
                        <button type="button" id="btnMail" onclick="sendMail()" style="padding:12px 16px;background:#059669;color:#fff;border:none;border-radius:10px;font-size:13px;cursor:pointer;white-space:nowrap;">发送验证码</button>
                    </div>
                </div>
                <?php endif; ?>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">密码</label>
                    <input type="password" name="password" required style="width:100%;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;box-sizing:border-box;">
                </div>
                <div style="margin-bottom:24px;">
                    <label style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">确认密码</label>
                    <input type="password" name="password2" required style="width:100%;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;box-sizing:border-box;">
                </div>
                <div style="margin-bottom:24px;">
                    <label style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">邀请码 <span style="color:#9ca3af;font-weight:400;">（选填）</span></label>
                    <input type="text" name="ref" value="<?= h($_GET['ref'] ?? '') ?>" placeholder="输入邀请码（选填）" style="width:100%;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;box-sizing:border-box;">
                </div>

                <!-- 图形验证码 -->
                <?php if ($captchaOn): ?>
                <div style="margin-bottom:20px;">
                    <label style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">验证码</label>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <input type="text" name="captcha" maxlength="4" required style="width:120px;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;text-transform:uppercase;box-sizing:border-box;" placeholder="验证码">
                        <img src="captcha.php" onclick="this.src='captcha.php?'+Date.now()" style="height:44px;border-radius:8px;cursor:pointer;border:1px solid #e5e7eb;" title="点击刷新">
                    </div>
                </div>
                <?php endif; ?>

                <button type="submit" style="width:100%;padding:14px 24px;background:#E94560;color:#fff;font-weight:600;font-size:15px;border:none;border-radius:10px;cursor:pointer;transition:background 0.25s,transform 0.15s;">注册</button>
            </form>
            <p style="text-align:center;margin:20px 0 0;font-size:14px;color:#6b7280;">
                已有账号？<a href="index.php?route=login" style="color:#0F3460;font-weight:500;text-decoration:none;">去登录</a>
            </p>
        </div>
    </div>
</div>

<script>
function sendSms(){
    const phone = document.getElementById('phone').value.trim();
    if (!/^1[3-9]\d{9}$/.test(phone)) { alert('请输入正确的手机号'); return; }
    const btn = document.getElementById('btnSms');
    btn.disabled = true; btn.textContent = '发送中...';
    fetch('index.php?route=register&action=send_sms', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({phone})
    }).then(r=>r.json()).then(d=>{
        if(d.ok||d.success){ alert('验证码已发送'); countdown(btn,60); }
        else { alert(d.msg||d.error||'发送失败'); btn.disabled=false; btn.textContent='发送验证码'; }
    }).catch(()=>{ alert('网络错误'); btn.disabled=false; btn.textContent='发送验证码'; });
}
function sendMail(){
    const email = document.getElementById('email').value.trim();
    if (!email) { alert('请先输入邮箱'); return; }
    const btn = document.getElementById('btnMail');
    btn.disabled = true; btn.textContent = '发送中...';
    fetch('index.php?route=register&action=send_mail', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({email})
    }).then(r=>r.json()).then(d=>{
        if(d.ok){ alert('验证码已发送'); countdown(btn,60); }
        else { alert(d.msg||'发送失败'); btn.disabled=false; btn.textContent='发送验证码'; }
    }).catch(()=>{ alert('网络错误'); btn.disabled=false; btn.textContent='发送验证码'; });
}
function countdown(btn, s){
    btn.disabled = true;
    const t = setInterval(()=>{ s--; btn.textContent = s+'s后重发'; if(s<=0){ clearInterval(t); btn.disabled=false; btn.textContent='发送验证码'; } },1000);
}
</script>