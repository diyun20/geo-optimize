<?php $isHomepageFooter = ($_GET["route"] ?? "home") === "home"; ?>
<?php if (!$isHomepageFooter): ?>
</main>
<?php endif; ?>

<?php if ($isHomepageFooter): ?>
<section class="geo7-footer">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8" style="max-width:1100px;margin:0 auto;padding:0 20px;">
        <div class="footer-grid">
            <div>
                <div class="footer-brand">
                    <i class="fa fa-globe"></i>
                    <span>GEO<span class="text-accent">优化</span></span>
                </div>
                <p class="footer-desc">
                    专业的 GEO 优化解决方案提供商，助力企业实现精准定位与全球触达。面向大模型时代的品牌可见度优化平台。
                </p>
                <div class="footer-socials">
                    <a href="javascript:;"><i class="fa fa-weixin"></i></a>
                    <a href="javascript:;"><i class="fa fa-weibo"></i></a>
                    <a href="javascript:;"><i class="fa fa-linkedin"></i></a>
                    <a href="javascript:;"><i class="fa fa-twitter"></i></a>
                </div>
            </div>
            <div>
                <h4>快速链接</h4>
                <ul>
                    <li><a href="index.php?route=home">首页</a></li>
                    <li><a href="index.php?route=about">关于我们</a></li>
                    <li><a href="index.php?route=login">登录</a></li>
                    <li><a href="index.php?route=register">注册</a></li>
                </ul>
            </div>
            <div>
                <h4>服务项目</h4>
                <ul>
                    <li><a href="index.php?route=home#services">品牌监测</a></li>
                    <li><a href="index.php?route=home#services">排名分析</a></li>
                    <li><a href="index.php?route=home#services">多维监测</a></li>
                    <li><a href="index.php?route=home#services">数据可视化</a></li>
                </ul>
            </div>
            <div>
                <h4>联系信息</h4>
        <?php
        // 已登录用户有代理显示代理联系方式，否则显示管理员
        $footerUser = currentUser();
        $contactSource = null;
        if ($footerUser && !empty($footerUser['agent_id'])) {
            $contactSource = dbFetchOne("SELECT email, qq, wechat, phone, show_email, show_phone, show_qq, show_wechat FROM users WHERE id=? AND role='agent'", [$footerUser['agent_id']]);
        }
        if (!$contactSource) {
            $contactSource = dbFetchOne("SELECT email, qq, wechat, phone, show_email, show_phone, show_qq, show_wechat FROM users WHERE role='admin' ORDER BY id LIMIT 1");
        }
        $footerQQ = $contactSource['qq'] ?? '';
        $footerWX = $contactSource['wechat'] ?? '';
        $footerEM = $contactSource['email'] ?? '';
        $footerPH = $contactSource['phone'] ?? '';
        if ($footerEM && ($contactSource['show_email'] ?? 1)): ?>
        <div class="footer-contact-item"><i class="fa fa-envelope"></i><span><?= h($footerEM) ?></span></div>
        <?php endif; ?>
        <?php if ($footerPH && ($contactSource['show_phone'] ?? 1)): ?>
        <div class="footer-contact-item"><i class="fa fa-phone"></i><span><?= h($footerPH) ?></span></div>
        <?php endif; ?>
        <?php if ($footerQQ && ($contactSource['show_qq'] ?? 1)): ?>
        <div class="footer-contact-item"><i class="fa fa-qq"></i><span><?= h($footerQQ) ?></span></div>
        <?php endif; ?>
        <?php if ($footerWX && ($contactSource['show_wechat'] ?? 1)): ?>
        <div class="footer-contact-item"><i class="fa fa-weixin"></i><span><?= h($footerWX) ?></span></div>
        <?php endif; ?>
            </div>
        </div>
        <div class="footer-bottom">
<?php $ver = trim(@file_get_contents(__DIR__.'/../storage/version.txt') ?: ''); ?>
            <p>&copy; <?= date("Y") ?> GEO优化. 保留所有权利.<?= $ver ? ' v'.$ver : '' ?></p>
        </div>
    </div>
</section>
<?php else: ?>
<footer style="background:#f8f9fb;padding:16px 0;text-align:center;border-top:1px solid #eef1f5;">
    <div class="container" style="max-width:1100px;margin:0 auto;padding:0 20px;">
        <p style="margin:0;font-size:13px;color:#9ca3af;">&copy; <?= date("Y") ?> GEO优化. 保留所有权利.<?= $ver ? ' v'.$ver : '' ?></p>
    </div>
</footer>
<?php endif; ?>

<!-- 回到顶部按钮 -->
<button id="geo7-back-top" class="geo7-back-top" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i class="fa fa-arrow-up"></i>
</button>

<script>
(function() {
    var navbar = document.getElementById('navbar');
    var backTop = document.getElementById('geo7-back-top');
    if (!navbar) return;
    var isHomepage = document.body.classList.contains('homepage');

    window.addEventListener('scroll', function() {
        if (window.scrollY > 80) {
            if (isHomepage) navbar.classList.add('scrolled');
            if (backTop) { backTop.classList.add('visible'); }
        } else {
            if (isHomepage) navbar.classList.remove('scrolled');
            if (backTop) { backTop.classList.remove('visible'); }
        }
    });

    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.querySelector(this.getAttribute('href'));
            if (target) {
                window.scrollTo({ top: target.offsetTop - 80, behavior: 'smooth' });
            }
        });
    });

    var counters = document.querySelectorAll('.counter');
    if (counters.length > 0) {
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var counter = entry.target;
                    var target = +counter.getAttribute('data-target');
                    var count = 0;
                    var speed = 200;
                    function updateCount() {
                        var increment = target / speed;
                        if (count < target) {
                            count += increment;
                            counter.innerText = Math.ceil(count);
                            setTimeout(updateCount, 1);
                        } else {
                            counter.innerText = target;
                        }
                    }
                    updateCount();
                    observer.unobserve(counter);
                }
            });
        }, { threshold: 0.5 });
        counters.forEach(function(c) { observer.observe(c); });
    }

    var track = document.querySelector('.geo7-testimonial-track');
    var dots = document.querySelectorAll('.geo7-testimonial-dot');
    var slides = document.querySelectorAll('.geo7-testimonial-card');
    if (track && dots.length > 0) {
        var currentIndex = 0;
        var autoTimer = null;
        var isDesktop = function() { return window.innerWidth >= 1025; };
        function updateSlide() {
            var firstCard = track.querySelector('.geo7-testimonial-card');
            if (!firstCard) return;
            if (isDesktop()) {
                track.style.transform = 'translateX(0)';
            } else {
                track.style.transform = 'translateX(-' + (currentIndex * firstCard.offsetWidth) + 'px)';
            }
            dots.forEach(function(dot, i) {
                if (i === currentIndex) { dot.classList.add('active'); }
                else { dot.classList.remove('active'); }
            });
        }
        function resetAuto() {
            if (autoTimer) clearInterval(autoTimer);
            autoTimer = setInterval(function() {
                if (!isDesktop()) {
                    currentIndex = (currentIndex + 1) % slides.length;
                    updateSlide();
                }
            }, 5000);
        }
        dots.forEach(function(dot, i) {
            dot.addEventListener('click', function() { currentIndex = i; updateSlide(); });
        });
        resetAuto();
        window.addEventListener('resize', updateSlide);
    }

    var contactForm = document.getElementById('contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = this.querySelector('button[type="submit"]');
            var orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> 提交中...';
            setTimeout(function() {
                alert('您的咨询已提交成功，我们将尽快与您联系！');
                contactForm.reset();
                btn.disabled = false;
                btn.innerHTML = orig;
            }, 1500);
        });
    }
})();
</script>
</body>
</html>