<?php $pageTitle = 'GEO优化 - AI时代的获客新基建'; ?>
<style>
:root{--bg:#060d1a;--card:#0b1832;--text:#e0e6f0;--muted:#c5cddb;--border:rgba(255,255,255,.08);--accent:#6d8cff;--accent2:#8b9fff;--accent3:#a78bfa;--green:#34d399}
*{margin:0;padding:0;box-sizing:border-box}
html{background:var(--bg)}
body{font-family:-apple-system,'PingFang SC','Microsoft YaHei',sans-serif;background:linear-gradient(180deg,#060d1a 0%,#0a1a3a 30%,#0d1f45 60%,#0b1832 100%);color:var(--text);-webkit-font-smoothing:antialiased;overflow-x:hidden;line-height:1.6;min-height:100vh}

/* ===== HERO 全屏 ===== */
.hero{position:relative;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:80px 32px;overflow:hidden;
    background:radial-gradient(ellipse 70% 50% at 50% 30%,rgba(109,140,255,.1) 0%,transparent 60%),
               radial-gradient(ellipse 40% 40% at 80% 70%,rgba(167,139,250,.08) 0%,transparent 60%),
               radial-gradient(ellipse 50% 40% at 20% 20%,rgba(59,130,246,.06) 0%,transparent 60%)}
.hero-inner{max-width:1000px;width:100%;margin:0 auto;position:relative;z-index:1;text-align:center}

.platform-tags{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin-bottom:28px}
.platform-tag{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:500;background:rgba(255,255,255,.05);color:var(--muted);border:1px solid var(--border);backdrop-filter:blur(8px)}
.platform-tag .dot{width:7px;height:7px;border-radius:50%}
.dot-doubao{background:#4dabf7}.dot-deepseek{background:#10b981}.dot-qianwen{background:#8b5cf6}.dot-yuanbao{background:#f59e0b}.dot-kimi{background:#06b6d4}.dot-wenxin{background:#3b82f6}

.hero h1{font-size:clamp(2rem,5vw,3.2rem);font-weight:800;line-height:1.2;letter-spacing:-.03em;margin-bottom:16px;color:#f0f4ff}
.hero h1 .hl{background:linear-gradient(135deg,#8b9fff,#a78bfa,#c084fc);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.hero-sub{font-size:17px;color:var(--muted);max-width:560px;margin:0 auto 32px;line-height:1.7}

/* 分析卡片 */
.analysis-card{background:var(--card);border-radius:24px;padding:36px 40px;border:1px solid rgba(255,255,255,.08);max-width:800px;width:100%;margin:0 auto;position:relative;z-index:1;
    box-shadow:0 4px 60px rgba(0,0,0,.25),0 1px 0 rgba(255,255,255,.04) inset}
.analysis-card .card-title{font-size:17px;font-weight:700;margin-bottom:24px;display:flex;align-items:center;gap:8px;color:#fff}
.analysis-row{display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap}
.analysis-input{flex:1;min-width:200px}
.analysis-input label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px}
.analysis-input input,.analysis-input select{width:100%;padding:12px 16px;border:1px solid rgba(255,255,255,.1);border-radius:10px;font-size:14px;background:rgba(255,255,255,.04);color:#e0e6f0;transition:all .2s;outline:none;font-family:inherit}
.analysis-input input:focus,.analysis-input select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(109,140,255,.15);background:rgba(255,255,255,.06)}
.analysis-input input::placeholder{color:rgba(255,255,255,.2)}
.analysis-input select option{background:var(--card);color:#fff}

.platform-checks{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px}
.platform-check{position:relative}
.platform-check input{position:absolute;opacity:0}
.platform-check label{display:flex;align-items:center;gap:6px;padding:8px 16px;border:1px solid rgba(255,255,255,.1);border-radius:10px;font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;background:rgba(255,255,255,.03);user-select:none;color:var(--muted)}
.platform-check input:checked+label{border-color:var(--accent);background:rgba(109,140,255,.12);color:var(--accent)}
.analysis-submit{text-align:center}
.btn-start{display:inline-flex;align-items:center;gap:8px;padding:14px 48px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:700;cursor:pointer;text-decoration:none;transition:all .3s;box-shadow:0 4px 20px rgba(109,140,255,.3)}
.btn-start:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(109,140,255,.45)}
.analysis-hint{text-align:center;margin-top:16px;font-size:13px;color:var(--muted)}

/* ===== SECTION 通用 ===== */
.section{padding:100px 32px}
.section-inner{max-width:1100px;margin:0 auto}
.section-tag{font-size:11px;font-weight:700;letter-spacing:.25em;text-transform:uppercase;color:var(--accent);margin-bottom:16px}
.section-title{font-size:clamp(1.5rem,3vw,2rem);font-weight:700;line-height:1.3;margin-bottom:16px;color:#f0f4ff}
.section-title .hl{background:linear-gradient(135deg,var(--accent),var(--accent2));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.section-desc{color:var(--muted);max-width:560px;font-size:15px;line-height:1.7;margin-bottom:48px}

/* ===== 4列数据 ===== */
.insight-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
.insight-card{background:var(--card);border-radius:16px;padding:32px 24px;border:1px solid var(--border);transition:all .3s}
.insight-card:hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,0,0,.25);border-color:rgba(255,255,255,.12)}
.insight-card .icon{font-size:36px;margin-bottom:16px}
.insight-card h4{font-size:16px;font-weight:700;margin-bottom:8px;color:#fff}
.insight-card p{font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:16px}
.insight-num{font-size:2rem;font-weight:800;background:linear-gradient(135deg,var(--accent),var(--accent2));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.insight-num-label{font-size:12px;color:var(--muted);margin-top:4px}

/* ===== 交替功能行 ===== */
.feature-row{display:flex;align-items:center;gap:80px;margin-bottom:80px}
.feature-row:last-child{margin-bottom:0}
.feature-row.reverse{flex-direction:row-reverse}
.feature-visual{flex:1;background:rgba(255,255,255,.03);border-radius:20px;padding:48px;text-align:center;border:1px solid var(--border);min-height:300px;display:flex;flex-direction:column;align-items:center;justify-content:center}
.feature-visual .icon{font-size:52px;margin-bottom:16px}
.feature-visual .title{font-size:17px;font-weight:700;margin-bottom:6px}
.feature-visual .sub{font-size:13px;color:var(--muted)}
.feature-copy{flex:1}
.feature-copy .tag{font-size:11px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--accent);margin-bottom:12px}
.feature-copy h3{font-size:1.5rem;font-weight:700;margin-bottom:12px;line-height:1.3;color:#fff}
.feature-copy p{color:var(--muted);line-height:1.8;margin-bottom:16px}
.feature-pills{display:flex;flex-wrap:wrap;gap:8px}
.feature-pill{padding:6px 14px;border-radius:8px;font-size:12px;font-weight:500;background:rgba(109,140,255,.1);color:var(--accent)}

/* ===== 平台网格 ===== */
.platform-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px}
.platform-card{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:16px;padding:28px 16px;text-align:center;transition:all .3s}
.platform-card:hover{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.15);transform:translateY(-3px)}
.platform-card .icon{font-size:36px;margin-bottom:10px}
.platform-card .name{font-size:15px;font-weight:600;color:#f0f4ff}
.platform-card .dot{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:6px}

/* ===== 服务卡片 ===== */
.service-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}
.service-card{background:var(--card);border-radius:16px;padding:32px;border:1px solid var(--border);transition:all .3s}
.service-card:hover{transform:translateY(-3px);box-shadow:0 12px 40px rgba(0,0,0,.25);border-color:rgba(255,255,255,.12)}
.service-card h4{font-size:17px;font-weight:700;margin-bottom:8px;color:#fff}
.service-card p{color:var(--muted);font-size:14px;line-height:1.7}

/* ===== CTA ===== */
.cta-section{background:linear-gradient(135deg,#080e24,#101840);padding:100px 32px;text-align:center;position:relative;overflow:hidden}
.cta-section::before{content:'';position:absolute;width:600px;height:600px;border-radius:50%;filter:blur(200px);opacity:.1;background:var(--accent);top:50%;left:50%;transform:translate(-50%,-50%);pointer-events:none}
.cta-inner{position:relative;z-index:1;max-width:640px;margin:0 auto}
.cta-section h2{font-size:clamp(1.6rem,3.5vw,2.2rem);color:#f0f4ff;font-weight:700;margin-bottom:16px;line-height:1.3}
.cta-section h2 .hl{background:linear-gradient(135deg,var(--accent),var(--accent3));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.cta-section p{color:var(--muted);font-size:16px;margin-bottom:36px}
.btn-cta{display:inline-flex;align-items:center;gap:8px;padding:16px 40px;background:#fff;color:#0a1a3a;border-radius:50px;font-size:16px;font-weight:700;text-decoration:none;transition:all .3s;box-shadow:0 4px 30px rgba(0,0,0,.3)}
.btn-cta:hover{transform:scale(1.03);box-shadow:0 8px 40px rgba(0,0,0,.4)}

/* ===== 滚动触发 ===== */
.reveal{opacity:0;transform:translateY(24px);transition:all .7s cubic-bezier(.16,1,.3,1)}
.reveal.visible{opacity:1;transform:translateY(0)}

/* ===== 响应式 ===== */
@media(max-width:768px){.insight-grid{grid-template-columns:repeat(2,1fr)}.feature-row,.feature-row.reverse{flex-direction:column;gap:32px}.service-grid{grid-template-columns:1fr}.analysis-card{padding:24px}.hero{padding:60px 20px}}
</style>

<!-- ==================== HERO 全屏 ==================== -->
<section class="hero">
    <div class="hero-inner">
        <div class="platform-tags">
            <span class="platform-tag"><span class="dot dot-doubao"></span>豆包</span>
            <span class="platform-tag"><span class="dot dot-deepseek"></span>DeepSeek</span>
            <span class="platform-tag"><span class="dot dot-qianwen"></span>通义千问</span>
            <span class="platform-tag"><span class="dot dot-yuanbao"></span>腾讯元宝</span>
            <span class="platform-tag"><span class="dot dot-kimi"></span>Kimi</span>
            <span class="platform-tag"><span class="dot dot-wenxin"></span>文心一言</span>
        </div>
        <h1>让AI<span class="hl">优先推荐</span>你的品牌</h1>
        <p class="hero-sub">GEO生成式引擎优化——AI时代的获客新基建。覆盖豆包、DeepSeek、通义千问等主流AI平台，帮助品牌在AI搜索结果中抢占前排。</p>
        <div class="analysis-card">
            <div class="card-title">🔍 品牌AI可见度分析</div>
            <div class="analysis-row">
                <div class="analysis-input"><label>品牌名称</label><input type="text" placeholder="输入你的品牌名称，例如：OpenClaw"></div>
                <div class="analysis-input"><label>所属行业</label><select><option value="">请选择行业</option><option>AI / 人工智能</option><option>SaaS / 企业服务</option><option>电商 / 零售</option><option>金融 / 保险</option><option>教育 / 培训</option><option>医疗 / 健康</option><option>其他</option></select></div>
            </div>
            <div style="font-size:13px;font-weight:600;margin-bottom:10px;color:#fff">选择检测平台</div>
            <div class="platform-checks">
                <div class="platform-check"><input type="checkbox" id="p1" checked><label for="p1"><span class="dot dot-doubao"></span>豆包</label></div>
                <div class="platform-check"><input type="checkbox" id="p2" checked><label for="p2"><span class="dot dot-deepseek"></span>DeepSeek</label></div>
                <div class="platform-check"><input type="checkbox" id="p3" checked><label for="p3"><span class="dot dot-qianwen"></span>通义千问</label></div>
                <div class="platform-check"><input type="checkbox" id="p4"><label for="p4"><span class="dot dot-yuanbao"></span>腾讯元宝</label></div>
                <div class="platform-check"><input type="checkbox" id="p5"><label for="p5"><span class="dot dot-kimi"></span>Kimi</label></div>
                <div class="platform-check"><input type="checkbox" id="p6"><label for="p6"><span class="dot dot-wenxin"></span>文心一言</label></div>
            </div>
            <div class="analysis-submit">
                <?php if (!isLoggedIn()): ?>
                <a href="index.php?route=login" class="btn-start">🚀 开始分析，查看品牌AI表现</a>
                <?php else: ?>
                <a href="index.php?route=keywords" class="btn-start">🚀 开始分析，查看品牌AI表现</a>
                <?php endif; ?>
            </div>
            <p class="analysis-hint">分析指标：<strong>搜索指数</strong> · <strong>正面占比</strong> · <strong>平均可信度</strong> · <strong>行业排名</strong></p>
        </div>
    </div>
</section>

<!-- ==================== AI成为信息第一站 ==================== -->
<section class="section">
    <div class="section-inner">
        <div class="reveal" style="text-align:center;margin-bottom:48px">
            <div class="section-tag">WHY GEO</div>
            <h2 class="section-title">AI成为用户<span class="hl">获取信息</span>的第一站</h2>
            <p class="section-desc" style="margin:0 auto">当用户向AI提问时，你的品牌是否出现在答案里？</p>
        </div>
        <div class="insight-grid">
            <div class="insight-card reveal"><div class="icon">🎯</div><h4>获取高意向客户</h4><p>潜在客户提问后，直接在AI答案中看到你的品牌推荐</p><div class="insight-num">60%</div><div class="insight-num-label">推荐的客户精准度比传统渠道高</div></div>
            <div class="insight-card reveal"><div class="icon">💰</div><h4>降低获客成本</h4><p>AI基于语义理解匹配客户潜在需求，替代部分SEM投放费用</p><div class="insight-num">50%</div><div class="insight-num-label">获客成本降低</div></div>
            <div class="insight-card reveal"><div class="icon">📈</div><h4>提高销售转化率</h4><p>AI推荐自带权威属性，客户对AI推荐的信任度远高于传统广告</p><div class="insight-num">5-10x</div><div class="insight-num-label">客户咨询的销售转化率</div></div>
            <div class="insight-card reveal"><div class="icon">🧠</div><h4>抢占客户心智</h4><p>品牌反复被AI推荐、提及甚至首推时，客户信任度潜移默化形成</p><div class="insight-num">饱和营销</div><div class="insight-num-label">客户选择品牌的最后一站</div></div>
        </div>
    </div>
</section>

<!-- ==================== 重塑品牌表达 ==================== -->
<section class="section" style="background:rgba(255,255,255,.015)">
    <div class="section-inner">
        <div class="reveal" style="text-align:center;margin-bottom:60px">
            <div class="section-tag">CORE FEATURES</div>
            <h2 class="section-title">面向AI<span class="hl">重塑品牌</span>表达</h2>
            <p class="section-desc" style="margin:0 auto">围绕品牌在AI平台的推荐度进行系统化优化</p>
        </div>
        <div class="feature-row reveal"><div class="feature-visual"><div class="icon">🗺️</div><div class="title">GEO画布</div><div class="sub">构建「目标用户-优化单元-用户意图-生成文章」一体化策略</div></div><div class="feature-copy"><div class="tag">策略规划</div><h3>GEO画布 —— 可视化优化策略</h3><p>围绕品牌面向AI营销目标，构建从目标用户到优化单元、用户意图再到生成文章的完整GEO优化策略地图，让每一步都有据可依。</p></div></div>
        <div class="feature-row reverse reveal"><div class="feature-visual"><div class="icon">🎯</div><div class="title">用户意图识别</div><div class="sub">实时检测每一个用户意图与品牌关联度</div></div><div class="feature-copy"><div class="tag">数据驱动</div><h3>用户意图 —— 识别AI平台真实提问</h3><p>基于品牌用户真实需求，识别用户在各AI平台上的真实提问，实时检测推荐度、AI平台评价、平均排名、内容引用率等核心指标。</p><div class="feature-pills"><span class="feature-pill">推荐度</span><span class="feature-pill">AI平台评价</span><span class="feature-pill">平均排名</span><span class="feature-pill">内容引用率</span></div></div></div>
        <div class="feature-row reveal"><div class="feature-visual"><div class="icon">✍️</div><div class="title">AI内容生成</div><div class="sub">自动生成品牌易于被AI引用的高质量内容</div></div><div class="feature-copy"><div class="tag">内容创作</div><h3>内容生成 —— 让AI帮你写AI爱看的内容</h3><p>自动生成品牌易于被AI引用的内容，无需人工手写。丰富的策略模板可选：技术教程、观点评论、产品评测、故事叙述，适配不同行业表达方式。</p></div></div>
        <div class="feature-row reverse reveal"><div class="feature-visual"><div class="icon">🚀</div><div class="title">自动化发布</div><div class="sub">一键发布到公众号、头条号、百家号等主流平台</div></div><div class="feature-copy"><div class="tag">高效分发</div><h3>自动化发布 —— 内容生成后一键同步</h3><p>品牌GEO内容生成后，授权主流内容平台即可实现一键自动发布，告别手动搬运。</p><div class="feature-pills"><span class="feature-pill">公众号</span><span class="feature-pill">头条号</span><span class="feature-pill">搜狐号</span><span class="feature-pill">百家号</span><span class="feature-pill">媒体平台</span></div></div></div>
        <div class="feature-row reveal"><div class="feature-visual"><div class="icon">📚</div><div class="title">品牌知识库</div><div class="sub">自动读取品牌信息、竞品、内容规则等</div></div><div class="feature-copy"><div class="tag">知识管理</div><h3>品牌知识库 —— 让AI准确理解你的品牌</h3><p>自动读取品牌基础信息、品牌业务、竞争对手、内容规则、目标客户、知识库等，支持随时修改，确保AI引用的每一条信息都准确无误。</p></div></div>
    </div>
</section>

<!-- ==================== 平台覆盖 ==================== -->
<section class="section" style="background:rgba(255,255,255,.02)">
    <div class="section-inner">
        <div class="reveal" style="text-align:center;margin-bottom:48px"><div class="section-tag" style="color:var(--accent2)">PLATFORMS</div><h2 class="section-title">覆盖主流<span class="hl">AI搜索平台</span></h2><p class="section-desc" style="margin:0 auto">一个平台管理所有AI渠道的品牌表现</p></div>
        <div class="platform-grid reveal">
            <?php foreach([['🤖','豆包','#4dabf7'],['🐋','DeepSeek','#10b981'],['🧠','通义千问','#8b5cf6'],['💎','腾讯元宝','#f59e0b'],['🌙','Kimi','#06b6d4'],['📝','文心一言','#3b82f6'],['🔮','360智脑','#a855f7'],['⚡','讯飞星火','#ec4899']] as $p): ?>
            <div class="platform-card"><div class="icon"><?=$p[0]?></div><div class="name"><span class="dot" style="background:<?=$p[2]?>"></span><?=$p[1]?></div></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==================== 陪伴式服务 ==================== -->
<section class="section">
    <div class="section-inner">
        <div class="reveal" style="text-align:center;margin-bottom:48px"><div class="section-tag">SERVICES</div><h2 class="section-title">不仅提供工具，更有<span class="hl">陪伴式增长</span>服务</h2></div>
        <div class="service-grid">
            <div class="service-card reveal"><h4>🧑‍💼 GEO营销顾问</h4><p>品牌AI推荐诊断，制定企业专属增长方案，一对一策略指导</p></div>
            <div class="service-card reveal"><h4>💬 AI+人工服务</h4><p>7×12h在线客服解答，五对一专属服务群，问题快速响应</p></div>
            <div class="service-card reveal"><h4>📖 产品功能培训</h4><p>GEO产品功能详细讲解，实操配置手把手教学，快速上手</p></div>
            <div class="service-card reveal"><h4>📡 行业资讯同步</h4><p>GEO优化最新规则调整同步，行业最新趋势解读，保持领先</p></div>
        </div>
    </div>
</section>

<!-- ==================== CTA ==================== -->
<section class="cta-section">
    <div class="cta-inner">
        <h2>GEO不是可选的营销工具<br>而是AI时代企业的<span class="hl">获客标配</span></h2>
        <p>品牌在AI中的存在感，就是未来的市场份额</p>
        <?php if (!isLoggedIn()): ?>
        <a href="index.php?route=login" class="btn-cta">✨ 立即免费试用</a>
        <?php else: ?>
        <a href="index.php?route=dashboard" class="btn-cta">📊 进入控制台</a>
        <?php endif; ?>
    </div>
</section>

<script>
(function(){var els=document.querySelectorAll('.reveal');function check(){els.forEach(function(el){var r=el.getBoundingClientRect();if(r.top<window.innerHeight*.88)el.classList.add('visible')})}check();window.addEventListener('scroll',check)})();
</script>
