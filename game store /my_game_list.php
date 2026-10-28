<?php
// 1. 啟動 Session（檢查會員登入狀態）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. 攔截機制：如果根本沒登入，不準看這個頁面，直接彈窗並送去登入頁
if (!isset($_SESSION["passed"]) || $_SESSION["passed"] !== "TRUE") {
    echo "<script>
        alert('請先登入會員，才能查看【我的遊戲】庫！');
        window.location.href = 'register_login.php?mode=login';
    </script>";
    exit();
}

// 3. 引入資料庫連線（對齊你的 db.php）
require_once("db.php");

$member_id = $_SESSION["id"]; // 取得目前登入者的整數 ID

try {
    // 透過三表連線，檢查大訂單是目前使用者買的，且狀態是 '已購買'
    $sql = "SELECT p.product_ID, p.product_name, p.image_url 
            FROM final_order_items oi
            INNER JOIN final_orders o ON oi.order_id = o.order_ID
            INNER JOIN final_products p ON oi.product_ID = p.product_ID
            WHERE o.member_ID = :member_id AND o.status = '已購買'
            ORDER BY o.created_at DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':member_id' => $member_id]);
    $my_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("收藏庫載入失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的遊戲收藏庫</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #1a1a1a; color: #fff; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        /* 導覽列樣式 */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0f1115;
            padding: 12px 30px;
            margin-bottom: 25px;
            border-radius: 6px;
        }
        .nav-group a, .nav-group button, .nav-welcome {
            color: #ccc;
            background: none;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
        }
        .nav-group button:hover, .nav-group a:hover { color: #66c0f4; }
        .nav-welcome { color: #a3d200; cursor: default; }

        /* 遊戲庫格狀排版布局 (Grid Layout) */
        .game-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        /* 遊戲格子卡片 */
        .game-item { 
            border: 1px solid #333; 
            border-radius: 8px;
            background: #2a2a2a;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 12 rgba(0,0,0,0.4);
        }

        /* 上半部分：商品縮圖 */
        .game-img-container {
            width: 100%;
            height: 150px;
            background: #000;
        }
        .game-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* 下半部分：名稱與啟動遊戲按鈕 */
        .game-info-container {
            padding: 15px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            justify-content: space-between;
        }
        .game-item h3 {
            margin: 0 0 15px 0;
            font-size: 18px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* 啟動下載按鈕 */
        .btn-play { 
            padding: 10px; 
            cursor: pointer; 
            width: 100%;
            background-color: #007acc;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            font-size: 14px;
        }
        .btn-play:hover { background-color: #0098ff; }
        .status-msg { text-align: center; color: #888; grid-column: 1 / -1; padding: 5px; font-size: 18px; }
    </style>
</head>
<body>

    <!-- 頂部導覽列 -->
    <nav class="navbar">
        <div class="nav-group">
            <a href="index.php">首頁</a>
            <button onclick="alert('功能開發中：篩選特價遊戲')">特價</button>
            <a href="wish_list.php" class="btn">願望清單</a>
            <!-- 當前頁面高亮 -->
            <a href="my_game_list.php" style="color: #a3d200;">我的遊戲</a>
        </div>
        
        <div class="nav-group">
            <a href="cart.php">🛒 購物車</a>
            <span class="nav-welcome"><?php echo htmlspecialchars($_SESSION["name"]); ?></span>
            <a href="profile.php" class="btn">個人檔案</a>
            <a href="logout.php" style="color: #ff6b6b;">登出</a>
        </div>
    </nav>

    <div class="container">
        <h2>🎮 我的遊戲收藏庫</h2>
        <p>這裏顯示您已成功購買的所有遊戲存檔。</p>

        <!-- 遊戲庫 Grid -->
        <div class="game-grid">
            <?php if (empty($my_games)): ?>
                <!-- 如果該會員一條付款訂單都沒有，則顯示這段 -->
                <div class="status-msg">
                    您的收藏庫空空如也...<br>
                    <a href="index.php" style="color: #a3d200; text-decoration: none; font-size: 16px; display: inline-block; margin-top: 10px;">👉 前往商店選購遊戲</a>
                </div>
            <?php else: ?>
                <!-- 迴圈撈出買過的遊戲 -->
                <?php foreach ($my_games as $index => $game): ?>
                    <?php 
                    $imgSrc = (!empty($game['image_url']) && file_exists($game['image_url'])) ? $game['image_url'] : "uploads/default-avatar.png";
                    ?>
                    <div class="game-item">
                        <div class="game-img-container">
                            <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($game['product_name']); ?>">
                        </div>
                        
                        <div class="game-info-container">
                            <h3><?php echo htmlspecialchars($game['product_name']); ?></h3>
                            <!-- 既然買過了，按鈕功能就從「購買」變成「下載/啟動」 -->
                            <button class="btn-play" onclick="alert('正在啟動《<?php echo htmlspecialchars($game['product_name']); ?>》...')">
                                ⬇️ 下載 / 啟動遊戲
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
