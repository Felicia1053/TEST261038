<?php
// 1. 啟動 Session（檢查會員登入狀態）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. 攔截機制：沒登入的人不能看購物車！
if (!isset($_SESSION["passed"]) || $_SESSION["passed"] !== "TRUE") {
    echo "<script>
        alert('請先登入會員，才能查看您的購物車！');
        window.location.href = 'register_login.php?mode=login';
    </script>";
    exit();
}

// 3. 引入資料庫連線
require_once("db.php");
$member_id = $_SESSION["id"]; // 取得當前會員的 INT ID

// 4. 【後端處理】處理刪除購物車內特定項目的請求 (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "delete") {
    $cart_record_id = isset($_POST["cart_ID"]) ? (int)$_POST["cart_ID"] : 0;
    
    if ($cart_record_id > 0) {
        try {
            // 安全防禦：確保這個購物車項目真的屬於目前登入的會員，防止惡意刪除他人購物車
            $delete_sql = "DELETE FROM final_carts WHERE ID = ? AND member_ID = ?";
            $delete_stmt = $pdo->prepare($delete_sql);
            $delete_stmt->execute([$cart_record_id, $member_id]);
            
            echo "<script>
                alert('已將商品移出購物車！');
                window.location.href = 'cart.php';
            </script>";
            exit();
        } catch (PDOException $e) {
            die("刪除失敗: " . $e->getMessage());
        }
    }
}
try {
    // 順便把 p.sale_price（特價欄位）一起撈出來
    $sql = "SELECT c.ID AS cart_ID, p.product_ID, p.product_name, p.price, p.sale_price, p.image_url 
            FROM final_carts c
            INNER JOIN final_products p ON c.product_ID = p.product_ID
            WHERE c.member_ID = ?
            ORDER BY c.ID DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$member_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 【修正】計算總金額：如果這款遊戲有特價，就應該加特價，沒特價才加原價
    $total_price = 0;
    foreach ($cart_items as $item) {
        $item_price = ($item['sale_price'] !== null) ? $item['sale_price'] : $item['price'];
        $total_price += (int)$item_price;
    }

} catch (PDOException $e) {
    die("購物車資料讀取失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的購物車 - 遊戲商店</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #1a1a1a; color: #fff; margin: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        
        /* 導覽列樣式 */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0f1115;
            padding: 12px 30px;
            margin-bottom: 30px;
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

        /* 購物車列表樣式 */
        .cart-list { margin-top: 20px; }
        .cart-item { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            background: #2a2a2a; 
            padding: 15px 20px; 
            margin-bottom: 15px; 
            border-radius: 6px;
            border: 1px solid #333;
        }
        
        /* 縮圖控制 */
        .item-thumb { width: 100px; height: 60px; background: #000; border-radius: 4px; overflow: hidden; }
        .item-thumb img { width: 100%; height: 100%; object-fit: cover; }
        
        .item-details { flex-grow: 1; margin-left: 20px; }
        .item-name { font-size: 18px; font-weight: bold; margin: 0 0 5px 0; }
        .item-price { color: #a3d200; font-weight: bold; margin: 0; }

        /* 刪除按鈕 */
        .btn-delete { background: #bc3b3b; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-delete:hover { background: #e74c3c; }

        /* 總結區塊 */
        .cart-summary { 
            background: #0f1115; 
            padding: 20px; 
            border-radius: 6px; 
            margin-top: 30px; 
            text-align: right; 
            border: 1px solid #222;
        }
        .total-text { font-size: 20px; font-weight: bold; margin-bottom: 15px; }
        .total-amount { color: #a3d200; font-size: 24px; }
        
        .btn-checkout { 
            background: #5c7e10; color: white; border: none; padding: 12px 30px; border-radius: 4px; cursor: pointer; font-size: 18px; font-weight: bold; 
        }
        .btn-checkout:hover { background: #79a314; }
        
        .empty-msg { text-align: center; color: #888; padding: 5px; font-size: 18px; margin-top: 40px; }
    </style>
</head>
<body>

    <!-- 頂部導覽列 -->
    <nav class="navbar">
        <div class="nav-group">
            <a href="index.php">首頁</a>
            <a href="on_sale.php" class="btn">特價</a>
            <a href="wish_list.php" class="btn">願望清單</a>
            <a href="my_game_list.php">我的遊戲</a>
        </div>
        <div class="nav-group">
            <a href="cart.php" style="color: #a3d200;">🛒 購物車</a>
            <span class="nav-welcome">歡迎，<?php echo htmlspecialchars($_SESSION["name"]); ?> ！</span>
            <a href="profile.php">個人檔案</a>
            <a href="logout.php" style="color: #ff6b6b;">登出</a>
        </div>
    </nav>

    <div class="container">
        <h2>🛒 我的購物車清單</h2>
        <p>在這裡管理您即將結帳的遊戲。由於是數位商品，每款遊戲限購一套。</p>

        <?php if (empty($cart_items)): ?>
            <!-- 購物車為空時顯示 -->
            <div class="empty-msg">
                您的購物車目前沒有任何商品。<br>
                <a href="index.php" style="color: #a3d200; text-decoration: none; display: inline-block; margin-top: 15px;">👉 前往首頁選購遊戲</a>
            </div>
        <?php else: ?>
            <!-- 購物車有商品時，迴圈渲染列表 -->
            <div class="cart-list">
                <?php foreach ($cart_items as $index => $item): ?>
                    <?php 
                    $imgSrc = !empty($item['image_url']) ? $item['image_url'] : "uploads/default-avatar.png" ;
                    ?>
                    <div class="cart-item">
                        <div class="item-thumb">
                            <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                        </div>
                        
                        <div class="item-details">
                            <p class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></p>
                            <p class="item-price">
                                <?php if ($item['sale_price'] !== null): ?>
                                    <span style="text-decoration: line-through; color: #888; font-size: 14px; margin-right: 8px;">NT$ <?php echo number_format($item['price']); ?></span>
                                    NT$ <?php echo number_format($item['sale_price']); ?>
                                <?php else: ?>
                                    NT$ <?php echo number_format($item['price']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <form action="cart.php" method="POST" onsubmit="return confirm('確定要將此遊戲移出購物車嗎？');">
                            <input type="hidden" name="action" value="delete">
                            <!-- 帶上此筆購物車紀錄在 final_carts 中的唯一整數 ID -->
                            <input type="hidden" name="cart_ID" value="<?php echo $item['cart_ID']; ?>">
                            <button type="submit" class="btn-delete">❌ 移除</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- 購物車金額總計與結帳區 -->
            <div class="cart-summary">
                <div class="total-text">
                    預計結帳總金額：<span class="total-amount">NT$ <?php echo number_format($total_price); ?></span>
                </div>
                
                <form action="pay.php" method="GET">
                    <button type="submit" class="btn-checkout">💳 前往結帳</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
