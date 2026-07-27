<?php $pageTitle = "找回密码 - GEO优化"; ?>
<?php
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/../includes/mail.php';

$smsEnabled = smsIsConfigured();
$mailEnabled = mailIsConfigured();
// 如果都没启用，跳回登录页
if (!$smsEnabled && !$mailEnabled) {
    setFlash('error', '找回密码功能暂未开启，请联系管理员');
    redirect('index.php?route=login');
}
// 默认选中第一个可用的
$defaultType = $smsEnabled ? 'sms' : 'mail';

// AJAX: 发送验证码
if (isset($_GET['ajax']) && $_GET['ajax'] === 'send_code') {
    header('Content-Type: application/json; charset=utf-8');
    $type = $_POST['type'] ?? 'sms'; // sms | mail
    if ($type === 'mail') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) die(json_encode(['success'=>false,'error'=>'邮箱格式不正确']));
        $user = dbFetchOne("SELECT id FROM users WHERE email=?", [$email]);
        if (!$user) die(json_encode(['success'=>false,'error'=>'该邮箱未注册']));
        echo json_encode(mailSendCode($email), JSON_UNESCAPED_UNICODE);
    } else {
        $phone = trim($_POST['phone'] ?? '');
        if (!preg_match('/^1\d{10}$/', $phone)) die(json_encode(['success'=>false,'error'=>'手机号格式不正确']));
        $user = dbFetchOne("SELECT id FROM users WHERE phone=?", [$phone]);
        if (!$user) die(json_encode(['success'=>false,'error'=>'该手机号未注册']));
        echo json_encode(smsSendCode($phone), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// POST: 重置密码
$step = 1; $type = 'sms';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] === 'reset') {
        $type = $_POST['type'] ?? 'sms';
        $code = trim($_POST['code'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($type === 'mail') {
            $email = trim($_POST['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) setFlash('error', '邮箱格式不正确');
            elseif (!mailVerify($email, $code)) setFlash('error', '验证码错误或已过期');
            elseif (strlen($password) < 6) setFlash('error', '密码至少6位');
            else {
                dbExecute("UPDATE users SET password=? WHERE email=?", [password_hash($password, PASSWORD_DEFAULT), $email]);
                setFlash('success', '密码重置成功，请登录');
                redirect('index.php?route=login');
            }
        } else {
            $phone = trim($_POST['phone'] ?? '');
            if (!preg_match('/^1\d{10}$/', $phone)) setFlash('error', '手机号格式不正确');
            elseif (!smsVerify($phone, $code)) setFlash('error', '验证码错误或已过期');
            elseif (strlen($password) < 6) setFlash('error', '密码至少6位');
            else {
                dbExecute("UPDATE users SET password=? WHERE phone=?", [password_hash($password, PASSWORD_DEFAULT), $phone]);
                setFlash('success', '密码重置成功，请登录');
                redirect('index.php?route=login');
            }
        }
    }
    if (isset($_POST['phone']) || isset($_POST['email'])) {
        $step = 2;
        $type = $_POST['type'] ?? 'sms';
    }
}
?>
<div style="max-width:420px;margin:40px auto;">
    <div style="background:linear-gradient(135deg,#f5f7ff 0%,#eef1f7 100%);border-radius:16px;box-shadow:0 4px 30px rgba(0,0,0,0.06);overflow:hidden;border:1px solid rgba(0,0,0,0.04);">
        <div style="height:4px;background:linear-gradient(90deg,#0F3460,#E94560);"></div>
        <div style="padding:36px 36px 32px;">
            <div style="text-align:center;margin-bottom:28px;">
                <div style="width:48px;height:48px;border-radius:12px;background:rgba(15,52,96,0.06);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#0F3460;font-size:22px;"><i class="fa fa-key"></i></div>
                <h2 style="font-size:22px;font-weight:700;color:#0F3460;margin:0;">找回密码</h2>
            </div>

            <?php if ($step === 1): ?>
            <?php if ($smsEnabled && $mailEnabled): ?>
            <div style="margin-bottom:16px;display:flex;gap:0;">
                <button type="button" onclick="switchTab('sms')" id="tab-sms" style="flex:1;padding:10px;border:none;background:<?=$defaultType==='sms'?'#0F3460':'#fff'?>;color:<?=$defaultType==='sms'?'#fff':'#374151'?>;border-radius:8px 0 0 8px;cursor:pointer;font-size:14px;border:1px solid <?=$defaultType==='sms'?'#0F3460':'#d1d5db'?>;">📱 手机找回</button>
                <button type="button" onclick="switchTab('mail')" id="tab-mail" style="flex:1;padding:10px;border:none;background:<?=$defaultType==='mail'?'#0F3460':'#fff'?>;color:<?=$defaultType==='mail'?'#fff':'#374151'?>;border-radius:0 8px 8px 0;cursor:pointer;font-size:14px;border:1px solid <?=$defaultType==='mail'?'#0F3460':'#d1d5db'?>;">📧 邮箱找回</button>
            </div>
            <?php endif; ?>
            <form method="post" action="index.php?route=forgot-password" id="form-step1">
                <input type="hidden" name="type" id="resetType" value="<?=$defaultType?>">
                <?php if ($smsEnabled): ?>
                <div id="phoneField" style="margin-bottom:16px;<?=$defaultType==='mail'?'display:none;':''?>">
                    <label style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">手机号</label>
                    <input type="text" name="phone" maxlength="11" placeholder="请输入注册时绑定的手机号" style="width:100%;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;box-sizing:border-box;">
                </div>
                <?php endif; ?>
                <?php if ($mailEnabled): ?>
                <div id="emailField" style="margin-bottom:16px;<?=$defaultType==='sms'?'display:none;':''?>">
                    <label style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">邮箱</label>
                    <input type="email" name="email" placeholder="请输入注册时绑定的邮箱" style="width:100%;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;box-sizing:border-box;">
                </div>
                <?php endif; ?>
                <button type="submit" style="width:100%;padding:14px 24px;background:#0F3460;color:#fff;font-weight:600;font-size:15px;border:none;border-radius:10px;cursor:pointer;margin-top:16px;">下一步</button>
            </form>
            <?php else: ?>
            <form method="post" action="index.php?route=forgot-password">
                <input type="hidden" name="step" value="reset">
                <input type="hidden" name="type" value="<?= h($type) ?>">
                <input type="hidden" name="<?= $type==='mail'?'email':'phone' ?>" value="<?= h($_POST[$type==='mail'?'email':'phone'] ?? '') ?>">
                <p style="margin-bottom:16px;color:#6b7280;font-size:14px;">正在为 <strong><?= h($_POST[$type==='mail'?'email':'phone'] ?? '') ?></strong> 重置密码</p>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">验证码</label>
                    <div style="display:flex;gap:10px;">
                        <input type="text" name="code" required maxlength="6" placeholder="6位验证码" style="flex:1;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;box-sizing:border-box;">
                        <button type="button" id="btnSend" onclick="sendCode()" style="padding:12px 16px;background:#f3f4f6;color:#374151;border:1px solid #d1d5db;border-radius:10px;font-size:13px;cursor:pointer;white-space:nowrap;">发送验证码</button>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:500;font-size:14px;color:#374151;margin-bottom:6px;">新密码</label>
                    <input type="password" name="password" required minlength="6" placeholder="至少6位" style="width:100%;padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;box-sizing:border-box;">
                </div>
                <button type="submit" style="width:100%;padding:14px 24px;background:#E94560;color:#fff;font-weight:600;font-size:15px;border:none;border-radius:10px;cursor:pointer;">重置密码</button>
            </form>
            <?php endif; ?>

            <p style="text-align:center;margin:20px 0 0;font-size:14px;color:#6b7280;">
                <a href="index.php?route=login" style="color:#0F3460;font-weight:500;text-decoration:none;">← 返回登录</a>
            </p>
        </div>
    </div>
</div>

<script>
function switchTab(t){
    document.getElementById('tab-sms').style.background=t==='sms'?'#0F3460':'#fff';
    document.getElementById('tab-sms').style.color=t==='sms'?'#fff':'#374151';
    document.getElementById('tab-mail').style.background=t==='mail'?'#0F3460':'#fff';
    document.getElementById('tab-mail').style.color=t==='mail'?'#fff':'#374151';
    document.getElementById('phoneField').style.display=t==='sms'?'block':'none';
    document.getElementById('emailField').style.display=t==='mail'?'block':'none';
    document.getElementById('resetType').value=t;
}
function sendCode(){
    var btn=document.getElementById('btnSend'),fd=new FormData(),type='<?=h($type)?>';
    if(type==='mail'){fd.append('type','mail');fd.append('email',document.querySelector('input[name=email]').value);}
    else{fd.append('type','sms');fd.append('phone',document.querySelector('input[name=phone]').value);}
    btn.disabled=true;btn.textContent='发送中...';
    fetch('index.php?route=forgot-password&ajax=send_code',{method:'POST',body:fd})
        .then(r=>r.json()).then(d=>{
            if(d.success){btn.textContent='已发送';var s=60;var t=setInterval(function(){s--;btn.textContent=s+'s';if(s<=0){clearInterval(t);btn.textContent='重新发送';btn.disabled=false;}},1000);}
            else{alert(d.error||'发送失败');btn.disabled=false;btn.textContent='发送验证码';}
        }).catch(function(e){alert('请求失败');btn.disabled=false;btn.textContent='发送验证码';});
}
</script>
