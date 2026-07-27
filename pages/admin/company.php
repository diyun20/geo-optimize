 <?php
 requireLogin();
 requireRole('admin');
 $admin = currentUser();
 $userId = (int)($_GET['user_id'] ?? 0);
 $target = dbFetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
 if (!$target) { setFlash('error', '用户不存在'); redirect('index.php?route=admin/users'); }
 $pageTitle = '企业信息 - ' . h($target['username']);
 
 // 迁移
 try { dbExecute("ALTER TABLE users ADD COLUMN `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `role`"); } catch (Exception $e) {}
 
 $info = dbFetchOne('SELECT * FROM company_info WHERE user_id = ?', [$userId]);
 
 if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $data = [
         'company_name'       => trim($_POST['company_name'] ?? ''),
         'company_abbr'       => trim($_POST['company_abbr'] ?? ''),
        'region'             => trim($_POST['region'] ?? ''),
        'short_video_account' => trim($_POST['short_video_account'] ?? ''),
         'industry'           => trim($_POST['industry'] ?? ''),
         'products_services'  => trim($_POST['products_services'] ?? ''),
         'product_highlights' => trim($_POST['product_highlights'] ?? ''),
         'brand_story'        => trim($_POST['brand_story'] ?? ''),
         'trust_endorsements' => trim($_POST['trust_endorsements'] ?? ''),
         'user_pain_points'   => trim($_POST['user_pain_points'] ?? ''),
         'customer_cases'     => trim($_POST['customer_cases'] ?? ''),
     ];

     if ($info) {
         $sql = "UPDATE company_info SET company_name=?,company_abbr=?,region=?,short_video_account=?,industry=?,products_services=?,product_highlights=?,brand_story=?,trust_endorsements=?,user_pain_points=?,customer_cases=? WHERE user_id=?";
        dbExecute($sql, [$data['company_name'], $data['company_abbr'], $data['region'], $data['short_video_account'], $data['industry'], $data['products_services'], $data['product_highlights'], $data['brand_story'], $data['trust_endorsements'], $data['user_pain_points'], $data['customer_cases'], $userId]);
     } else {
         $sql = "INSERT INTO company_info (user_id,company_name,company_abbr,region,short_video_account,industry,products_services,product_highlights,brand_story,trust_endorsements,user_pain_points,customer_cases,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
         dbExecute($sql, array_merge([$userId], array_values($data), [date('Y-m-d H:i:s')]));
     }
         // Ensure short_video_account is saved
    dbExecute("UPDATE company_info SET short_video_account = ? WHERE user_id = ?", [trim($_POST['short_video_account'] ?? ''), $userId]);
    setFlash('success', '企业信息已保存');
     redirect('index.php?route=admin/company&user_id=' . $userId);
 }
 ?>
 
 <div class="page-header">
     <h1>🏢 企业信息</h1>
     <p>编辑 <?= h($target['username']) ?> 的企业信息</p>
 </div>
 
 <form method="post" action="index.php?route=admin/company&user_id=<?= $userId ?>">
     <div class="knowledge-grid">
        <div class="knowledge-card">
            <div class="card-icon">📋</div>
            <h3>基本信息</h3>
            <div class="form-group">
                <label>公司名称</label>
                <input type="text" name="company_name" value="<?= h($info["company_name"] ?? "") ?>">
            </div>
            <div class="form-group">
                <label>公司简称</label>
                <input type="text" name="company_abbr" value="<?= h($info["company_abbr"] ?? "") ?>">
            </div>
            <div class="form-group">
                <label>地区</label>
                <input type="text" name="region" value="<?= h($info["region"] ?? "") ?>">
            </div>
            <div class="form-group">
                <label>抖音名称</label>
                <input type="text" name="short_video_account" value="<?= h($info["short_video_account"] ?? "") ?>" placeholder="抖音名称">
            </div>
            <div class="form-group">
                <label>行业</label>
                <input type="text" name="industry" value="<?= h($info["industry"] ?? "") ?>">
            </div>
        </div>

        <div class="knowledge-card">
            <div class="card-icon">🚀</div>
            <h3>产品服务</h3>
            <div class="form-group">
                <label>产品服务</label>
                <textarea name="products_services" rows="4"><?= h($info["products_services"] ?? "") ?></textarea>
            </div>
            <div class="form-group">
                <label>产品提点</label>
                <textarea name="product_highlights" rows="4" placeholder="每个提点一行"><?= h($info["product_highlights"] ?? "") ?></textarea>
            </div>
            <div class="form-group">
                <label>用户痛点</label>
                <textarea name="user_pain_points" rows="4" placeholder="每个痛点一行"><?= h($info["user_pain_points"] ?? "") ?></textarea>
            </div>
        </div>

        <div class="knowledge-card">
            <div class="card-icon">💎</div>
            <h3>品牌与信任</h3>
            <div class="form-group">
                <label>品牌故事</label>
                <textarea name="brand_story" rows="8"><?= h($info["brand_story"] ?? "") ?></textarea>
            </div>
            <div class="form-group">
                <label>信任背书</label>
                <textarea name="trust_endorsements" rows="8" placeholder="资质、认证、荣誉等"><?= h($info["trust_endorsements"] ?? "") ?></textarea>
            </div>
        </div>

        <div class="knowledge-card">
            <div class="card-icon">⭐</div>
            <h3>客户案例</h3>
            <div class="form-group">
            <div class="form-group">
                <label>客户案例</label>
                <textarea name="customer_cases" rows="20" placeholder="每个案例一行，格式：客户名称| 行业 | 效果"><?= h($info["customer_cases"] ?? "") ?></textarea>
            </div>
        </div>
    </div>

    <div style="margin-top:24px;display:flex;gap:12px;">
        <button type="submit" class="btn btn-primary">保存企业信息</button>
        <a href="index.php?route=dashboard" class="btn btn-outline">← 返回控制台</a>
    </div>

</form>
