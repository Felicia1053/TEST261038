<?php
// 1. 啟動 Session（檢查會員登入狀態）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. 攔截機制：沒登入的人不能查看或使用願望清單！
if (!isset($_SESSION["passed"]) || $_SESSION["passed"] !== "TRUE") {
    echo "<script>
        alert('請先登入會員，才能使用願望清單功能！');
        window.location.href = 'register_login.php?mode=login';
    </script>";
    exit();
}

// 3. 引入資料庫連線
require_once("db.php");
$member_id = $_SESSION["id"]; // 取得當前會員的 INT ID

// 4. 【後端處理中心】處理來自「首頁的新增」與「本頁的移除」請求 (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];

    // ------------------ 【功能 A：從首頁加入願望清單】 ------------------
    if ($action === "add") {
        $product_id = isset($_POST["product_ID"]) ? (int)$_POST["product_ID"] : 0;

        if ($product_id > 0) {
            try {
                // 檢查是否重複加入
                $check_sql = "SELECT COUNT(*) FROM final_wishes WHERE member_ID = ? AND product_ID = ?";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([$member_id, $product_id]);
                $is_exist = (int)$check_stmt->fetchColumn();

                if ($is_exist > 0) {
                    echo "<script>
                        alert('這款未發售遊戲已經在您的願望清單中囉！');
                        window.location.href = 'index.php';
                    </script>";
                    exit();
                } else {
                    // 寫入願望清單暫存表（ID自動遞增）
                    $insert_sql = "INSERT INTO final_wishes (member_ID, product_ID) VALUES (?, ?)";
                    $insert_stmt = $pdo->prepare($insert_sql);
                    $insert_stmt->execute([$member_id, $product_id]);

                    echo "<script>
                        alert('成功加入願望清單！我們將在發售時通知您。');
                        window.location.href = 'wish_list.php'; // 💡 加入成功後直接導向清單頁面查看
                    </script>";
                    exit();
                }
            } catch (PDOException $e) {
                die("加入願望清單失敗: " . $e->getMessage());
            }
        }
    }

    // ------------------ 【功能 B：從清單內移除商品】 ------------------
    if ($action === "remove") {
        $wish_record_id = isset($_POST["wish_ID"]) ? (int)$_POST["wish_ID"] : 0;

        if ($wish_record_id > 0) {
            try {
                // 安全防禦：確保該願望紀錄真的屬於目前登入的會員，防止越權刪除
                $delete_sql = "DELETE FROM final_wishes WHERE ID = ? AND member_ID = ?";
                $delete_stmt = $pdo->prepare($delete_sql);
                $delete_stmt->execute([$wish_record_id, $member_id]);

                echo "<script>
                    alert('已從願望清單中移除該商品！');
                    window.location.href = 'wish_list.php';
                </script>";
                exit();
            } catch (PDOException $e) {
                die("移除失敗: " . $e->getMessage());
            }
        }
    }
}

// 5. 每次重整頁面，即時用 GET 撈取該會員的願望清單明細 (JOIN)
try {
    // 💡 核心 SQL：將願望清單表(w) 與 商品表(p) 連線，撈出名字、價格與圖片
    $sql = "SELECT w.ID AS wish_ID, p.product_ID, p.product_name, p.price, p.image_url 
            FROM final_wishes w
            INNER JOIN final_products p ON w.product_ID = p.product_ID
            WHERE w.member_ID = ?
            ORDER BY w.ID DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$member_id]);
    $wish_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("願望清單讀取失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的願望清單 - 遊戲商店</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #1a1a1a; color: #fff; margin: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        
        /* 導覽列與分頁按鈕樣式 */
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
        .nav-welcome { color: #a3d200; cursor: default; }

        /* 願望清單列表排版 */
        .wish-list { margin-top: 20px; }
        .wish-item { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            background: #2a2a2a; 
            padding: 15px 20px; 
            margin-bottom: 15px; 
            border-radius: 6px;
            border: 1px solid #333;
        }

        
        /* 縮圖樣式 */
        .item-thumb { width: 100px; height: 60px; background: #000; border-radius: 4px; overflow: hidden; }
        .item-thumb img { width: 100%; height: 100%; object-fit: cover; }
        
        .item-details { flex-grow: 1; margin-left: 20px; }
        .item-name { font-size: 18px; font-weight: bold; margin: 0 0 5px 0; }
        .item-price { color: #f39c12; font-weight: bold; margin: 0; } /* 預計售價用橘色標記 */

        /* 移除按鈕 */
        .btn-remove { background: #bc3b3b; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-remove:hover { background: #e74c3c; }

        .empty-msg { text-align: center; color: #888; padding: 5px; font-size: 18px; margin-top: 40px; }
    </style>
</head>
<body>

    <!-- 頂部導覽列 -->
    <nav class="navbar">
        <div class="nav-group">
            <a href="index.php">首頁</a>
            <a href="on_sale.php">特價</a>
            <a href="wish_list.php">願望清單</a>
            <a href="my_game_list.php">我的遊戲</a>
        </div>
        <div class="nav-group">
            <a href="cart.php">🛒 購物車</a>
            <span class="nav-welcome"><?php echo htmlspecialchars($_SESSION["name"]); ?></span>
            <a href="profile.php">個人檔案</a>
            <a href="logout.php" style="color: #ff6b6b;">登出</a>
        </div>
    </nav>

    <div class="container">
        <h2>我的願望清單 (限時期待)</h2>
        <p>這裏收藏了您期待中、尚未正式發售的遊戲。發售當天可第一時間轉入購物車。</p>

        <?php if (empty($wish_items)): ?>
            <!-- 清單為空時顯示 -->
            <div class="empty-msg">
                您的願望清單目前空空如也。<br>
                <a href="index.php" style="color: #66c0f4; text-decoration: none; display: inline-block; margin-top: 15px;">👉 前往商店查看即將發售的新遊戲</a>
            </div>
        <?php else: ?>
            <!-- 清單有商品時，迴圈渲染明細 -->
            <div class="wish-list">
                <?php foreach ($wish_items as $index => $item): ?>
                    <?php 
                    $imgSrc = !empty($item['image_url']) ? $item['image_url'] : "uploads/default-avatar.png";
                    ?>
                    <div class="wish-item">
                        <!-- 商品圖 -->
                        <div class="item-thumb">
                            <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                        </div>
                        
                        <!-- 商品資訊 -->
                        <div class="item-details">
                            <p class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></p>
                            <p class="item-price">尚未發售</p>
                        </div>
                        
                        <!-- 傳統 POST 移除表單 -->
                        <form action="wish_list.php" method="POST" onsubmit="return confirm('確定要將此遊戲移出願望清單嗎？');">
                            <input type="hidden" name="action" value="remove">
                            <!-- 帶上此筆願望紀錄在 final_wishes 中的唯一整數 ID -->
                            <input type="hidden" name="wish_ID" value="<?php echo $item['wish_ID']; ?>">
                            <button type="submit" class="btn-remove">❌ 移除</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
