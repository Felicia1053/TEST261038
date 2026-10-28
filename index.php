<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("db.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "execute_payment") {
    
    $user_id = $_SESSION["id"] ?? $_SESSION["member_ID"] ?? 0;
    $bought_products = $_POST["bought_products"] ?? [];

    if ($user_id >= 0 && !empty($bought_products)) {
        try {
            $pdo->beginTransaction();

            // ───建立final_orders ───
            $max_order_stmt = $pdo->query("SELECT MAX(order_ID) FROM final_orders");
            $new_order_id = (int)$max_order_stmt->fetchColumn() + 1;

            $sql_order = "INSERT INTO final_orders (order_ID, member_ID, status, created_at) VALUES (?, ?, '已購買', NOW())";
            $stmt_order = $pdo->prepare($sql_order);
            $stmt_order->execute([$new_order_id, $user_id]);

            // 抓取商品售價並寫入明細表final_order_items ───
            $placeholders = implode(',', array_fill(0, count($bought_products), '?'));
            $sql_prices = "SELECT product_ID, price, sale_price FROM final_products WHERE product_ID IN ($placeholders)";
            $stmt_prices = $pdo->prepare($sql_prices);
            $stmt_prices->execute($bought_products);
            $product_prices = $stmt_prices->fetchAll(PDO::FETCH_ASSOC);

            // 建立一個價格對照矩陣，方便直接用商品 ID 查找對應應付金額
            $price_map = [];
            foreach ($product_prices as $p) {
                $price_map[$p['product_ID']] = ($p['sale_price'] !== null) ? $p['sale_price'] : $p['price'];
            }

            // 準備寫入明細 SQL：欄位必須精準對齊你的資料庫（order_ID, product_ID, price(total_price), status）
            $sql_item = "INSERT INTO final_order_items (order_ID, product_ID, price, status) VALUES (?, ?, ?, '已購買')";
            $stmt_item = $pdo->prepare($sql_item);

            // 利用迴圈，把這次結帳的所有商品一件一件塞進明細表裡
            foreach ($bought_products as $p_id) {
                $game_actual_price = $price_map[$p_id] ?? 0; // 取得該商品的實際特價或原價
                $stmt_item->execute([$new_order_id, (int)$p_id, $game_actual_price]);
            }

            // 清空該會員購物車中這些已結帳的商品 (final_carts) ───
            $sql_cart = "DELETE FROM final_carts WHERE member_ID = ? AND product_ID IN ($placeholders)";
            $stmt_cart = $pdo->prepare($sql_cart);
            $execute_params = array_merge([$user_id], $bought_products);
            $stmt_cart->execute($execute_params);

            $pdo->commit();

            // 利用 PRG 模式跳轉，清除網址列的 POST 殘留狀態，完美防範重新整理按 F5 重複購買
            echo "<script>
                alert('💳 付款成功！感謝您的購買。\\n遊戲已成功寫入訂單明細，並移入您的【我的遊戲】庫！');
                window.location.href = 'index.php';
            </script>";
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            die("❌ 結帳儲存失敗，資料庫已安全回滾。詳細錯誤原因: " . $e->getMessage());
        }
    } else {
        echo "<script>
            alert('結帳失敗：未正確取得會員資訊或購買商品資料。請重新嘗試！');
            window.location.href = 'cart.php';
        </script>";
        exit();
    }
}

//接收搜尋欄傳過來的關鍵字
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$user_id = $_SESSION["id"] ?? $_SESSION["member_ID"] ?? 0; // 取得當前看首頁的人的會員 ID

try {
    if ($search !== '') {
        $sql = "SELECT p.*,
                       (SELECT COUNT(*) 
                        FROM final_order_items oi 
                        INNER JOIN final_orders o ON oi.order_ID = o.order_ID 
                        WHERE oi.product_ID = p.product_ID AND o.member_ID = ? AND o.status = '已購買') AS is_bought
                FROM final_products p 
                WHERE p.product_name LIKE ? 
                ORDER BY p.product_ID ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, "%$search%"]);
    } else {
        $sql = "SELECT p.*,
                       (SELECT COUNT(*) 
                        FROM final_order_items oi 
                        INNER JOIN final_orders o ON oi.order_ID = o.order_ID 
                        WHERE oi.product_ID = p.product_ID AND o.member_ID = ? AND o.status = '已購買') AS is_bought
                FROM final_products p 
                ORDER BY p.product_ID ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
    }
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫查詢失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>遊戲商店 - 首頁</title>
    <style>
        /* 基礎全站暗色系風格（極簡 Steam 遊戲風） */
        body { font-family: sans-serif; padding: 20px; background-color: #1a1a1a; color: #fff; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        /* 頂部導覽列樣式：完美切分左邊與右邊 */
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
        .nav-group button:hover, .nav-group a:hover {
            color: #66c0f4; /* 懸浮時變成亮藍色 */
        }
        

        .dropdown {
            position: relative;
            display: inline-block;
        }

        /* 2. 下拉按鈕的基本排版（繼承導覽列外觀） */
        .dropdown-btn {
            color: #a3d200; /* 綠色歡迎文字 */
            background: none;
            border: none;
            padding: 8px 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .dropdown:hover .dropdown-btn {
            color: #66c0f4; /* 滑鼠移入時按鈕變藍色 */
        }

        /* 3. 下拉選單選單盒（預設為隱藏 display: none） */
        .dropdown-content {
            display: none; 
            position: absolute;
            right: 0;             /* 靠右對齊，避免超出網頁右邊邊界 */
            background-color: #0f1115; /* 配合深色系導覽列 */
            min-width: 200px;     /* 設定寬度 */
            box-shadow: 0px 8px 16px rgba(0,0,0,0.6); /* 陰影讓選單有漂浮感 */
            border: 1px solid #333;
            border-radius: 4px;
            z-index: 999;         /* 確保選單展開時，絕對不會被下方的遊戲卡片圖片遮擋 */
            padding: 5px 0;
        }

        /* 4. 下拉選單裡面的每一個超連結選項 */
        .dropdown-content a {
            color: #ccc !important;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-size: 14px;
            text-align: left;    /* 文字靠左對齊 */
            transition: background 0.2s;
        }

        /* 5. 當滑鼠懸停在選項上時的背景變色效果 */
        .dropdown-content a:hover {
            background-color: #2a2a2a;
            color: #66c0f4 !important;
        }

        /* 6. 特殊樣式：登出選項滑鼠移入時變紅色 */
        .dropdown-content a.logout-link:hover {
            color: #ff6b6b !important;
        }

        /* 7. 選項裡面的細分隔線 */
        .dropdown-divider {
            height: 1px;
            background-color: #333;
            margin: 5px 0;
        }

        /* 當滑鼠移入 .dropdown 外盒時，底下的 .dropdown-content 立刻現形！ */
        .dropdown:hover .dropdown-content {
            display: block;
        }


        /* 搜尋欄區塊樣式 */
        .search-container {
            margin-bottom: 30px;
            text-align: center;
        }
        .search-container input {
            padding: 12px 20px;
            width: 380px;
            font-size: 16px;
            border: 1px solid #333;
            border-radius: 20px 0 0 20px;
            background-color: #2a2a2a;
            color: white;
        }
        .search-container button {
            padding: 12px 20px;
            font-size: 16px;
            border: none;
            background-color: #91b804;
            color: white;
            border-radius: 0 20px 20px 0;
            cursor: pointer;
            font-weight: bold;
        }
        .search-container button:hover { background-color: #4ca4d8; }

        /* 商品列表：格狀排版布局 (Grid Layout) */
        .game-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 25px;
        }

        /* 單個商品格子卡片：強制上下切分 */
        .game-item { 
            border: 1px solid #333; 
            padding: 0; 
            border-radius: 8px;
            background: #2a2a2a;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            transition: transform 0.2s;
        }
        .game-item:hover {
            transform: translateY(-5px); /* 滑鼠懸停微幅往上飄，增加質感 */
        }

        /* 上半部分：商品縮圖區塊 */
        .game-img-container {
            width: 100%;
            height: 150px;
            background: #000;
        }
        .game-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover; /* 確保圖片不變形且填滿格子 */
        }

        /* 下半部分：商品名稱、價格與新增至購物車按鈕 */
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
            text-overflow: ellipsis; /* 名字太長自動變冒號 ... */
        }
        .price { color: #a3d200; font-weight: bold; margin: 0 0 15px 0; font-size: 16px; }
        
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
        .btn-cart:hover {
            background-color: #79a314;
        }
        .status-msg { text-align: center; color: #888; grid-column: 1 / -1; padding: 30px; font-size: 18px; }
    </style>
</head>
<body>

    <!-- 頂部導覽列 (包含索引功能) -->
    <nav class="navbar">
        <!-- 左半部索引：首頁、特價、我的遊戲 -->
        <div class="nav-group">
            <a href="index.php" style="color: #a3d200;">首頁</a>
            <a href="on_sale.php" class="btn">特價</a>
            <a href="wish_list.php" class="btn">願望清單</a>
            <a href="my_game_list.php" class="btn">我的遊戲</a>
        </div>
        
        <!-- 右半部索引：購物車、登入狀態分流、個人檔案 -->
        <div class="nav-group">
            <a href="cart.php" class="btn">🛒 購物車</a>
            
            <!-- 利用 PHP 檢查登入 Session 狀態 -->
            <?php if (isset($_SESSION["passed"]) && $_SESSION["passed"] === "TRUE"): ?>
            <span class="nav-welcome">歡迎，<?php echo htmlspecialchars($_SESSION["name"]); ?></span>
                <!-- 【已登入狀態】：顯示會員暱稱、個人檔案按鈕與登出超連結 -->

                <div class="dropdown">
                    <button class="dropdown-btn">
                        <a href="profile.php?mode=login" class="btn">選單</a>
                    </button>

                    <div class="dropdown-content">
                        <a href="profile.php?mode=login">會員資料</a>
                        <a href="creator.php">創作者專區</a>
                        <a href="history.php">購買歷史</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="logout-link">登出</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- 【未登入狀態】：顯示登入/註冊按鈕，並對個人檔案進行攔截提示 -->
                <a href="register_login.php?mode=login">註冊/登入</a>
                <button onclick="alert('請先登入會員，才能查看個人檔案！')">個人檔案</button>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        
        <!-- 搜尋欄區塊 -->
        <div class="search-container">
            <form action="index.php" method="GET">
                <!-- 保持住使用者剛剛搜尋的關鍵字（利用 value 印出） -->
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="輸入遊戲關鍵字搜尋...">
                <button type="submit">搜尋</button>
            </form>
        </div>

        <h2>所有遊戲商品</h2>

        <!-- 商品列表區塊（利用 PHP foreach 迴圈直接從資料庫撈出並渲染） -->
        <div class="game-grid">
            <?php if (empty($products)): ?>
                <!-- 如果搜尋不到任何東西，或是資料庫裡沒資料時顯示 -->
                <div class="status-msg">目前商店內沒有找到符合條件的遊戲。</div>
            <?php else: ?>
                <?php foreach ($products as $game): ?>
                    <?php 
                    $imgSrc = !empty($game['image_url']) ? $game['image_url'] : "uploads/default-avatar.png";
                    ?>
                    <div class="game-item">
                        <!-- 商品縮圖 -->
                        <div class="game-img-container">
                            <a href="product_detail.php?id=<?php echo $game['product_ID']; ?>">
                                <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($game['product_name']); ?>">
                            </a>
                        </div>

                        <!-- 點擊按鈕直接觸發 POST 傳遞商品 ID -->
                        <div class="game-info-container">
                        <h3 title="<?php echo htmlspecialchars($game['product_name']); ?>">
                            <?php echo htmlspecialchars($game['product_name']); ?>
                        </h3>

                        <?php if ($game['status'] === '未發售'): ?>
                            <p class="price">尚未發售</p>
                            <form action="wish_list.php" method="POST">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_ID" value="<?php echo $game['product_ID']; ?>">
                                <button type="submit" class="btn-cart" style="background-color: #34495e;">加入願望清單</button>
                            </form>

                        <?php elseif ($game['status'] === '已購買'): ?>
                            <?php elseif ((int)($game['is_bought'] ?? 0) > 0): ?>
                                <p class="price" style="color: #66c0f4;">已擁有此遊戲</p>
                                <button type="button" class="btn-cart" style="background-color: #2c3e50; cursor: default;" disabled>已在遊戲庫中</button>

                        <?php else: ?>
                            <p class="price">NT$ <?php echo number_format($game['price']); ?></p>
                            <form action="add_to_cart.php" method="POST">
                                <input type="hidden" name="product_ID" value="<?php echo $game['product_ID']; ?>">
                                <button type="submit" class="btn-cart">新增至購物車</button>
                            </form>
                        <?php endif; ?>

                    </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>