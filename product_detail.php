<?php
// 1. 啟動 Session（檢查會員登入狀態）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("db.php");

// 2. 接收網址傳過來的遊戲 ID (GET 參數)
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    die("<h2 style='text-align:center; color:#ff6b6b; margin-top:50px;'>❌ 錯誤：找不到此遊戲商品。</h2>");
}

try {
    // 3. 撈取遊戲詳細資料（透過 INNER JOIN 抓出創作者 final_members.member_name）
    $sql = "SELECT p.*, m.member_name AS creator_name 
            FROM final_products p
            INNER JOIN final_members m ON p.member_ID = m.member_ID
            WHERE p.product_ID = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        die("<h2 style='text-align:center; color:#ff6b6b; margin-top:50px;'>❌ 錯誤：該遊戲不存在或已被移除。</h2>");
    }

    // 4. 撈取這款遊戲底下的所有歷史遊玩評論（透過 JOIN 抓出發表評論的會員暱稱）
    $review_sql = "SELECT r.*, m.member_name AS reviewer_name 
                   FROM final_reviews r
                   INNER JOIN final_members m ON r.member_ID = m.member_ID
                   WHERE r.product_ID = ?
                   ORDER BY r.review_ID DESC";
    $review_stmt = $pdo->prepare($review_sql);
    $review_stmt->execute([$product_id]);
    $reviews = $review_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("系統載入錯誤: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($game['product_name']); ?> - 遊戲詳情</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #1a1a1a; color: #fff; margin: 0; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        
        /* 頂部導覽列 */
        .navbar { display: flex; justify-content: space-between; align-items: center; background: #0f1115; padding: 12px 30px; margin-bottom: 25px; border-radius: 6px; }
        .navbar a { color: #ccc; text-decoration: none; font-weight: bold; }
        .navbar a:hover { color: #66c0f4; }
        
        /* 遊戲畫面示意圖相簿 (上方大圖、下方小圖) */
        .gallery-section { background: #000; padding: 15px; border-radius: 8px; border: 1px solid #333; }
        .main-img-box { width: 100%; height: 400px; overflow: hidden; border-radius: 4px; }
        .main-img-box img { width: 100%; height: 100%; object-fit: cover; transition: opacity 0.15s; }
        .thumb-box { display: flex; gap: 12px; margin-top: 15px; justify-content: center; }
        .thumb-box img { width: 100px; height: 60px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 2px solid transparent; opacity: 0.6; }
        .thumb-box img.active, .thumb-box img:hover { border-color: #66c0f4; opacity: 1; }

        /* 遊戲詳細資訊區 */
        .info-section { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 30px; }
        .info-left h2 { margin-top: 0; color: #66c0f4; font-size: 28px; }
        .info-right { background: #2a2a2a; padding: 20px; border-radius: 8px; height: fit-content; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        .price { font-size: 22px; color: #a3d200; font-weight: bold; margin-bottom: 15px; }
        
        /* 評論區塊樣式 */
        .review-section { margin-top: 50px; border-top: 1px solid #333; padding-top: 30px; }
        .review-card { background: #222; padding: 15px 20px; border-radius: 6px; border-left: 4px solid #66c0f4; margin-bottom: 15px; }
        .review-meta { display: flex; justify-content: space-between; font-size: 13px; color: #888; margin-bottom: 8px; }
        .review-content { font-size: 15px; line-height: 1.5; color: #ddd; }
        
        /* 發表評論表單 */
        .review-form textarea { width: 100%; height: 100px; background: #111; color: white; border: 1px solid #444; border-radius: 4px; padding: 10px; box-sizing: border-box; resize: none; font-size: 14px; }
        .review-form textarea:focus { outline: none; border-color: #66c0f4; }
        .btn { padding: 10px 20px; background: #5c7e10; border: none; color: white; font-weight: bold; border-radius: 4px; cursor: pointer; margin-top: 10px; }
        .btn:hover { background: #79a314; }
    </style>
</head>
<body>

    <!-- 頂部功能導覽列 -->
    <nav class="navbar">
        <a href="index.php">◀ 返回遊戲商店首頁</a>
        <div style="color: #66c0f4; font-weight: bold;">🎮 遊戲詳細規格說明</div>
    </nav>

    <div class="container">
        
        <!-- ================== 上方：遊戲畫面示意圖相簿 (純 CSS+JS) ================== -->
        <div class="gallery-section">
            <div class="main-img-box">
                <!-- 預設大圖：若資料庫內無圖片，自動抓網路高畫質預設遊戲佔位圖 -->
                <?php $baseImg = !empty($game['image_url']) ? $game['image_url'] : "https://picsum.photos" . $product_id; ?>
                <img id="largeImage" src="<?php echo $baseImg; ?>" alt="遊戲大圖">
            </div>
            <!-- 下方 4 張示意小圖，滑鼠點選會立刻呼叫 JavaScript 取代大圖位置 -->
            <div class="thumb-box">
                <img class="thumb-item active" src="<?php echo $baseImg; ?>" onclick="switchImage(this)">
                <img class="thumb-item" src="https://picsum.photos101" onclick="switchImage(this)">
                <img class="thumb-item" src="https://picsum.photos102" onclick="switchImage(this)">
                <img class="thumb-item" src="https://picsum.photos103" onclick="switchImage(this)">
            </div>
        </div>

        <!-- ================== 中方：遊戲簡介、售價與購買按鈕 ================== -->
        <div class="info-section">
            <div class="info-left">
                <h2><?php echo htmlspecialchars($game['product_name']); ?></h2>
                <p style="color:#aaa; font-size:14px;">
                    📅 發行日期：<?php echo $game['release_date']; ?> &nbsp;|&nbsp; 
                    🏢 開發商/創作者：<span style="color:#66c0f4; font-weight:bold;"><?php echo htmlspecialchars($game['creator_name']); ?></span>
                </p>
                
                <p style="color:#ddd; font-weight:bold; margin-top:25px;">【遊戲詳細介紹】</p>
                <!-- 對齊你在資料庫新增的 product_info 欄位 -->
                <div style="line-height:1.7; color:#ccc;">
                    <?php echo !empty($game['product_info']) ? nl2br(htmlspecialchars($game['product_info'])) : "暫無此遊戲的詳細簡介資料。"; ?>
                </div>
            </div>
            
            <!-- 右側欄位：根據發售狀態動態切換價格與表單 action -->
            <div class="info-right">
                <?php if ($game['status'] === '未發售'): ?>
                    <p class="price" style="color:#f39c12;">⏳ 尚未發售</p>
                    <form action="wish_list.php" method="POST">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_ID" value="<?php echo $game['product_ID']; ?>">
                        <button type="submit" class="btn" style="background:#34495e; width:100%;">加入願望清單</button>
                    </form>
                <?php else: ?>
                    <p class="price">
                        <?php if($game['sale_price'] !== null): ?>
                            <span style="font-size:14px; text-decoration:line-through; color:#888; margin-right:8px;">NT$ <?php echo number_format($game['price']); ?></span>
                            NT$ <?php echo number_format($game['sale_price']); ?>
                        <?php else: ?>
                            NT$ <?php echo number_format($game['price']); ?>
                        <?php endif; ?>
                    </p>
                    <form action="add_to_cart_process.php" method="POST">
                        <input type="hidden" name="product_ID" value="<?php echo $game['product_ID']; ?>">
                        <button type="submit" class="btn" style="width:100%;">新增至購物車</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================== 下方：遊玩評論管理區塊 ================== -->
        <div class="review-section">
            <h2>💬 玩家遊玩評論 (<?php echo count($reviews); ?>)</h2>
            
            <!-- 發表新評論區塊（限定登入者才能填寫） -->
            <div class="review-card" style="background:#202327; border-left-color:#5c7e10;">
                <?php if (isset($_SESSION["passed"]) && $_SESSION["passed"] === "TRUE"): ?>
                    <p style="margin-top:0; font-weight:bold; color:#a3d200;">✍️ 發表您的遊戲心得：</p>
                    <form action="add_review_process.php" method="POST">
                        <input type="hidden" name="product_ID" value="<?php echo $product_id; ?>">
                        <textarea name="content" required placeholder="這款遊戲體驗如何？給其他玩家一些實用的通關心得吧..."></textarea>
                        <button type="submit" class="btn">送出遊玩評論</button>
                    </form>
                <?php else: ?>
                    <p style="margin:0; text-align:center; color:#aaa;">想要對此遊戲發表評論嗎？<a href="register_login.php?mode=login" style="color:#66c0f4; text-decoration:none; font-weight:bold;">👉 點此登入會員</a></p>
                <?php endif; ?>
            </div>

            <!-- 歷史留言評論列表 -->
                        <div id="reviewsContainer">
                <?php if (empty($reviews)): ?>
                    <p style="color:#666; text-align:center; padding:30px; font-size:15px;">目前此遊戲還沒有玩家發表過評論，快來分享你的心得吧！</p>
                <?php else: ?>
                    <?php foreach ($reviews as $rev): ?>
                        <div class="review-card">
                            <div class="review-meta">
                                <span style="font-weight:bold; color:#66c0f4;">👤 <?php echo htmlspecialchars($rev['reviewer_name']); ?></span>
                                <span>📅 <?php echo $rev['created_at']; ?></span>
                            </div>
                            <div class="review-content"><?php echo nl2br(htmlspecialchars($rev['content'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        </div> </div> <script>
        function switchImage(element) {
            const largeImage = document.getElementById('largeImage');
            if (!largeImage) return;
            
            largeImage.style.opacity = 0.3; // 淡淡的淡出視覺效果
            
            setTimeout(() => {
                largeImage.src = element.src; // 取代大圖網址
                largeImage.style.opacity = 1; // 重新淡入
            }, 120);

            // 清除所有小圖的藍色邊框激活樣式
            const thumbs = document.querySelectorAll('.thumb-item');
            thumbs.forEach(thumb => thumb.classList.remove('active'));
            
            // 幫當前點擊的小圖鎖上藍色高亮外框
            element.classList.add('active');
        }
    </script>
</body>
</html>
