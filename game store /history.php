<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 登入攔截
if (!isset($_SESSION["passed"]) || $_SESSION["passed"] !== "TRUE") {
    echo "<script>alert('請先登入會員！'); window.location.href = 'register_login.php?mode=login';</script>";
    exit();
}

require_once("db.php");
$member_id = $_SESSION["id"] ?? 0;
$member_name = $_SESSION["name"] ?? "會員";

$history_records = [];
try {
    // 透過三表連線：從明細表出發，連回大訂單看時間，再連去商品表拿名字與圖片
    $sql = "SELECT o.order_ID, o.created_at, oi.price, p.product_name, p.image_url
            FROM final_order_items oi
            INNER JOIN final_orders o ON oi.order_ID = o.order_ID
            INNER JOIN final_products p ON oi.product_ID = p.product_ID
            WHERE o.member_ID = ? AND o.status = '已購買'
            ORDER BY o.created_at DESC, o.order_ID DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$member_id]);
    $history_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "歷史訂單讀取失敗: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>購買歷史 - 遊戲商店</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background-color: #1a1a1a; 
            color: #ccc; 
            padding: 20px; 
        }

        /* 導覽列 */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0f1115;
            padding: 15px 30px;
            margin-bottom: 30px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        }

        .navbar a {
            color: #66c0f4;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            transition: color 0.3s;
        }

        .navbar a:hover { color: #a3d200; }

        .navbar .user-info {
            color: #a3d200;
            font-weight: bold;
            font-size: 14px;
        }

        /* 主容器 */
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            color: #66c0f4;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            color: #888;
            font-size: 14px;
        }

        /* 訂單卡片容器 */
        .orders-grid {
            display: grid;
            gap: 20px;
        }

        .order-card {
            background: #2a2a2a;
            border: 1px solid #3a3a3a;
            border-radius: 8px;
            padding: 20px;
            display: grid;
            grid-template-columns: 140px 1fr; /* 微調：配合橫式電商圖片外觀 */
            gap: 20px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .order-card:hover {
            background: #313131;
            border-color: #66c0f4;
            box-shadow: 0 6px 16px rgba(102, 192, 244, 0.2);
        }

        .order-image {
            width: 140px;
            height: 90px; /* 優化：歷史收據縮圖採用標準橫式 140x90 比例，排版更像 Steam */
            background: #111;
            border-radius: 6px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #444;
        }

        .order-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .order-info {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .order-info p {
            margin-bottom: 8px;
            font-size: 14px;
            line-height: 1.6;
        }

        .order-info p strong {
            color: #66c0f4;
        }

        .order-id {
            color: #888;
            font-size: 13px;
        }

        .product-name {
            color: #fff;
            font-size: 18px;
            font-weight: bold;
            margin: 3px 0;
        }

        .order-time {
            color: #888;
            font-size: 13px;
        }

        .order-price {
            color: #a3d200;
            font-size: 18px;
            font-weight: bold;
            text-align: right;
        }

        /* 空狀態 */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: #2a2a2a;
            border-radius: 8px;
            border: 1px dashed #444;
        }

        .empty-state p {
            color: #888;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .empty-state a {
            display: inline-block;
            background: #5c7e10;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            transition: background 0.3s;
        }

        .empty-state a:hover {
            background: #79a314;
        }

        /* 錯誤提示 */
        .error-message {
            background: #c53030;
            color: white;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        /* 統計資訊 */
        .stats {
            background: #2a2a2a;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            text-align: center;
            border: 1px solid #3a3a3a;
        }

        .stat-item h3 {
            color: #66c0f4;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .stat-item p {
            color: #888;
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .order-card {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .order-image {
                width: 100%;
                height: 150px;
            }

            .order-price {
                text-align: left;
                margin-top: 10px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .navbar {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php">◀ 返回商店首頁</a>
        <h2 style="color: #66c0f4; margin: 0;">📜 購買歷史</h2>
        <div class="user-info">👤 <?php echo htmlspecialchars($member_name); ?></div>
    </nav>

    <div class="container">
        <div class="header">
            <h1>🎮 我的購買歷史</h1>
            <p>查看您所有已購買的遊戲記錄</p>
        </div>

        <?php if (isset($error_msg)): ?>
            <div class="error-message">⚠️ <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <?php if (!empty($history_records)): ?>
            <div class="stats">
                <div class="stat-item">
                    <h3><?php echo count($history_records); ?></h3>
                    <p>購買遊戲數</p>
                </div>
                <div class="stat-item">
                    <h3>NT$ <?php echo number_format(array_sum(array_column($history_records, 'total_price'))); ?></h3>
                    <p>總消費金額</p>
                </div>
                <div class="stat-item">
                    <h3><?php echo date('Y-m-d', strtotime($history_records[0]['created_at'])); ?></h3>
                    <p>最新購買日期</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($history_records)): ?>
            <div class="empty-state">
                <p>🛒 目前尚未有購買記錄</p>
                <p style="color: #999; margin-bottom: 30px;">前往商店挑選您喜歡的遊戲吧！</p>
                <a href="index.php">開始購物</a>
            </div>
        <?php else: ?>
            <div class="orders-grid">
                <?php foreach ($history_records as $order): ?>
                    <div class="order-card">
                        <div class="order-image">
                            <?php
                            $imgSrc = (!empty($order['image_url']) && file_exists($order['image_url'])) 
                                      ? htmlspecialchars($order['image_url']) 
                                      : "uploads/default-avatar.png";
                            ?>
                            <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($order['product_name']); ?>">
                        </div>
                        <div class="order-info">
                            <div>
                                <div class="order-id">訂單編號 #<?php echo htmlspecialchars($order['order_ID']); ?></div>
                                <div class="product-name"><?php echo htmlspecialchars($order['product_name']); ?></div>
                                <div class="order-time">📅 購買時間：<?php echo htmlspecialchars($order['created_at']); ?></div>
                            </div>
                            <div class="order-price">💰 NT$ <?php echo number_format($order['price']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
