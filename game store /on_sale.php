<?php
// 1. 啟動 Session（檢查會員登入狀態）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. 引入你的資料庫連線檔案
require_once("db.php");

try {
    // 💡 關鍵 SQL 邏輯：只撈出 sale_price 不是 NULL 的特價商品
    $sql = "SELECT product_ID, product_name, price, sale_price, image_url 
            FROM final_products 
            WHERE sale_price IS NOT NULL 
            ORDER BY product_ID ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    // 撈出所有特價遊戲陣列
    $sale_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("特價清單查詢失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>限時特價專區 - 遊戲商店</title>
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
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
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

        /* 特價主題大橫幅 */
        .sale-banner {
            background: linear-gradient(135deg, #14b8b8 0%, #434a04 100%);
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }
        .sale-banner h1 { margin: 0 0 10px 0; color: #fff; font-size: 32px; letter-spacing: 2px; }
        .sale-banner p { margin: 0; color: #b7ffb3; font-size: 16px; }

        /* 商品列表：格狀排版布局 (Grid Layout) */
        .game-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 25px;
        }

        /* 商品格子卡片 */
        .game-item { 
            border: 1px solid #4a1212; /* 使用暗紅色邊框突顯特價感 */
            padding: 0; 
            border-radius: 8px;
            background: #2a2a2a;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            transition: transform 0.2s;
        }
        .game-item:hover { transform: translateY(-5px); }

        /* 上半部分：商品縮圖 */
        .game-img-container { width: 100%; height: 150px; background: #000; position: relative; }
        .game-img-container img { width: 100%; height: 100%; object-fit: cover; }
        
        /* 視覺優化：左上角斜切特價標籤 */
        .sale-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #bc3b3b;
            color: white;
            padding: 4px 8px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 3px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.5);
        }

        /* 下半部分：資訊與按鈕 */
        .game-info-container {
            padding: 15px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            justify-content: space-between;
        }
        .game-item h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* 💡 雙價格比對區塊 */
        .price-box { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
        .original-price { color: #888; text-decoration: line-through; font-size: 14px; } /* 原價劃掉 */
        .sale-price { color: #a3d200; font-weight: bold; font-size: 18px; } /* 特價放大變綠 */
        
        /* 購買按鈕 */
        .btn-cart { 
            padding: 10px; 
            cursor: pointer; 
            width: 100%;
            background-color: #5c7e10;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            font-size: 14px;
        }
        .btn-cart:hover { background-color: #79a314; }
        .status-msg { text-align: center; color: #888; grid-column: 1 / -1; padding: 30px; font-size: 18px; }
    </style>
</head>
<body>

    <!-- 頂部導覽列 -->
    <nav class="navbar">
        <div class="nav-group">
            <a href="index.php">首頁</a>
            <!-- 本頁高亮 -->
            <a href="on_sale.php" style="color: #66c0f4;">特價</a>
            <a href="wish_list.php" class="btn">願望清單</a>
            <a href="my_game_list.php">我的遊戲</a>
        </div>
        
        <div class="nav-group">
            <a href="cart.php">🛒 購物車</a>
            
            <?php if (isset($_SESSION["passed"]) && $_SESSION["passed"] === "TRUE"): ?>
                <span class="nav-welcome">歡迎，<?php echo htmlspecialchars($_SESSION["name"]); ?> ！</span>
                <a href="profile.php">會員中心</a>
                <a href="logout.php" style="color: #ff6b6b;">登出</a>
            <?php else: ?>
                <a href="register_login.php?mode=login">註冊/登入</a>
                <button onclick="alert('請先登入會員')">會員中心</button>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        
        <!-- 特價慶典大橫幅 -->
        <div class="sale-banner">
            <h1>🧊 夏季特賣 🧊</h1>
            <p>暑假期間限時特價，優惠最低五折起！</p>
        </div>

        <!-- 特價商品 Grid -->
        <div class="game-grid">
            <?php if (empty($sale_products)): ?>
                <div class="status-msg">目前沒有正在進行特價的遊戲，敬請期待！</div>
            <?php else: ?>
                <?php foreach ($sale_products as $index => $game): ?>
                    <?php 
                    $imgSrc = !empty($game['image_url']) ? $game['image_url'] : "https://picsum.photos" . $index;
                    
                    // 💡 計算折價成數（如：省下 20%）
                    $discount_percent = 100 - round(($game['sale_price'] / $game['price']) * 100);
                    ?>
                    <div class="game-item">
                        <div class="game-img-container">
                            <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($game['product_name']); ?>">
                            <!-- 秀出省了多少 % -->
                            <div class="sale-badge">省下 <?php echo $discount_percent; ?>%</div>
                        </div>
                        
                        <div class="game-info-container">
                            <h3 title="<?php echo htmlspecialchars($game['product_name']); ?>">
                                <?php echo htmlspecialchars($game['product_name']); ?>
                            </h3>
                            
                            <!-- 雙價格區塊 -->
                            <div class="price-box">
                                <span class="original-price">NT$ <?php echo number_format($game['price']); ?></span>
                                <span class="sale-price">NT$ <?php echo number_format($game['sale_price']); ?></span>
                            </div>
                            
                            <!-- 點擊後一樣傳送給你的購物車寫入程式碼處理 -->
                            <form action="add_to_cart.php" method="POST">
                                <input type="hidden" name="product_ID" value="<?php echo $game['product_ID']; ?>">
                                <button type="submit" class="btn-cart">新增至購物車</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
