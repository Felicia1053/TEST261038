<?php
// 1. 啟動 Session（檢查是否登入）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. 登入攔截：沒登入不能進後台
if (!isset($_SESSION["passed"]) || $_SESSION["passed"] !== "TRUE") {
    echo "<script>alert('請先登入會員！'); window.location.href = 'register_login.php?mode=login';</script>";
    exit();
}

require_once("db.php");
$my_id = $_SESSION["id"]; // 創作者自己的會員 ID
$message = "";

// 3. 【後端處理中心】處理「上架」提交 (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "add") {
    
    // 【優化與防呆】先把變數收好，並用 ?? 給予預設值，徹底解決 Undefined 警告
    $name = trim($_POST["product_name"] ?? "");
    $price = (int)($_POST["price"] ?? 0);
    $sale_price = (isset($_POST["sale_price"]) && $_POST["sale_price"] !== "") ? (int)$_POST["sale_price"] : null;
    $info = trim($_POST["product_info"] ?? "");
    $status = $_POST["status"] ?? "已發售";
    $image_path = '';

    // 處理圖片上傳
    if (isset($_FILES["product_image"]) && $_FILES["product_image"]["error"] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext = pathinfo($_FILES["product_image"]["name"], PATHINFO_EXTENSION);
        $new_filename = "product_" . time() . "_" . rand(100, 999) . "." . $file_ext;
        
        if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $upload_dir . $new_filename)) {
            $image_path = $upload_dir . $new_filename;
        }
    }

    if (!empty($name)) {
        try {
            // 💡 自動撈取目前最大 ID + 1
            $max_stmt = $pdo->query("SELECT MAX(product_ID) FROM final_products");
            $new_pid = (int)$max_stmt->fetchColumn() + 1;

            // 修正：補上 product_ID 欄位並傳入
            $sql = "INSERT INTO final_products (product_ID, member_ID, product_name, price, sale_price, product_info, status, image_url) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            // 帶入剛剛在上方已經做好安全防呆防噴 Warning 的變數
            $stmt->execute([
                $new_pid,
                $my_id, 
                $name, 
                $price, 
                $sale_price, 
                $info, 
                $status, 
                $image_path 
            ]);

            // 成功後彈窗並利用 PRG 模式重導向，防止重新整理重複上架
            echo "<script>alert('✅ 遊戲上架成功！'); window.location.href='creator.php';</script>";
            exit();

        } catch (PDOException $e) {
            die("上架儲存失敗: " . $e->getMessage());
        }
    }
}

// 4. 【數據化圖表分析 - 核心 SQL】
try {
    $chart_sql = "
        SELECT 
            p.product_ID,
            p.product_name,
            p.price,
            p.status,
            p.image_url,
            p.sale_price,
            (SELECT COUNT(*) FROM final_order_items oi INNER JOIN final_orders o ON oi.order_id = o.order_ID WHERE oi.product_ID = p.product_ID AND o.status = '已付款') AS total_sales,
            (SELECT COUNT(*) FROM final_wishes w WHERE w.product_ID = p.product_ID) AS total_wishes
        FROM final_products p
        WHERE p.member_ID = ?
        ORDER BY p.product_ID ASC";
        
    $stmt = $pdo->prepare($chart_sql);
    $stmt->execute([$my_id]);
    $my_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("後台載入失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>遊戲創作者工作坊後台</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #1a1a1a; color: #fff; margin: 0; }
        .container { max-width: 1100px; margin: 0 auto; padding: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .full-width { grid-column: 1 / -1; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background: #0f1115; padding: 12px 30px; margin-bottom: 25px; border-radius: 6px; }
        .navbar a { color: #ccc; text-decoration: none; font-weight: bold; }
        .navbar a:hover { color: #66c0f4; }
        .panel { background: #2a2a2a; padding: 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.4); }
        h2 { margin-top: 0; color: #66c0f4; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #ccc; font-weight: bold; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #444; background: #1a1a1a; color: white; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; color: white; }
        .btn-green { background-color: #5c7e10; } .btn-green:hover { background-color: #79a314; }
        .btn-blue { background-color: #007acc; } .btn-blue:hover { background-color: #0098ff; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #202020; }
        th, td { padding: 12px; border: 1px solid #333; text-align: left; }
        th { background-color: #0f1115; color: #66c0f4; }
        .chart-container { display: flex; flex-direction: column; align-items: center; background: #111; padding: 15px; border-radius: 6px; border: 1px solid #333; margin-top: 15px; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div><a href="index.php">◀ 返回商店首頁</a></div>
        <div style="color: #a3d200; font-weight: bold;">🛠️ 創作者面板：<?php echo htmlspecialchars($_SESSION["name"]); ?></div>
    </nav>

    <div class="container">
        
        <div class="panel">
            <h2>🚀 上架全新遊戲</h2>
            <form action="creator.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group"><label>遊戲名稱：</label><input type="text" name="product_name" required placeholder="例如：絕對武力 2"></div>
                <div class="form-group"><label>設定售價：</label><input type="number" name="price" required placeholder="NT$" min="0"></div>
                
                <div class="form-group"><label>特價 (選填)：</label><input type="number" name="sale_price" placeholder="NT$ (若無特價請留空)" min="0"></div>
                <div class="form-group"><label>遊戲詳細介紹：</label><textarea name="product_info" rows="3" placeholder="輸入遊戲的賣點特色與系統需求..."></textarea></div>
                
                <div class="form-group"><label>發售狀態：</label><select name="status"><option value="已發售">已發售</option><option value="未發售">未發售</option></select></div>
                <div class="form-group">
                    <label>商品圖片：</label>
                    <input type="file" name="product_image" accept="image/*">
                </div>
                <button type="submit" class="btn btn-green">➕ 確認上架</button>
            </form>
        </div>

        <div class="panel">
            <h2>📊 數據化圖表分析 (銷售 vs 願望清單)</h2>
            <p style="font-size:13px; color:#aaa;">即時統計您旗下遊戲的受歡迎程度與實際收益狀況。</p>
            
            <div class="chart-container">
                <canvas id="analyticsChart" width="450" height="280"></canvas>
            </div>
            <div style="display:flex; justify-content:center; gap:20px; margin-top:10px; font-size:12px;">
                <span style="color:#2ecc71;">■ 實際銷量 (套)</span>
                <span style="color:#f39c12;">■ 願望清單關注度 (人次)</span>
            </div>
        </div>

        <div class="panel full-width">
            <h2>🎮 我發行的遊戲清單控制台</h2>
            <?php if (empty($my_products)): ?>
                <p style="color:#888; text-align:center; padding:20px;">您目前尚未在商店發行過任何遊戲。</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>遊戲名稱</th><th>狀態</th><th>定價 (特價)</th><th>累積銷量</th><th>願望清單</th><th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_products as $p): ?>
                            <tr>
                                <td><?php echo $p['product_ID']; ?></td>
                                <td style="font-weight:bold; color:#66c0f4;"><?php echo htmlspecialchars($p['product_name']); ?></td>
                                <td>
                                    <?php if ($p['status'] === '未發售'): ?>
                                        <span style="color:#e74c3c; font-weight:bold;">未發售</span>
                                    <?php else: ?>
                                        <span style="color:#2ecc71; font-weight:bold;">已發售</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        if ($p['sale_price'] !== null) {
                                            echo "<span style='text-decoration:line-through; color:#888;'>NT$" . $p['price'] . "</span> <span style='color:#e67e22; font-weight:bold;'>NT$" . $p['sale_price'] . "</span>";
                                        } else {
                                            echo "NT$" . $p['price'];
                                        }
                                    ?>
                                </td>
                                <td><span style="color:#2ecc71; font-weight:bold;"><?php echo $p['total_sales']; ?> 套</span></td>
                                <td><span style="color:#f39c12; font-weight:bold;"><?php echo $p['total_wishes']; ?> 人次</span></td>
                                <td>
                                    <a href="products_edit.php?id=<?php echo $p['product_ID']; ?>" class="btn btn-blue" style="text-decoration: none; padding: 6px 12px; font-size: 13px;">✏️ 進入編輯頁面</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const productsData = <?php echo json_encode($my_products); ?>;
        const canvas = document.getElementById('analyticsChart');
        const ctx = canvas.getContext('2d');

        if (productsData.length === 0) {
            ctx.fillStyle = '#888';
            ctx.font = '16px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('目前尚未發行遊戲，無數據可供分析', canvas.width / 2, canvas.height / 2);
        } else {
            let maxVal = 0;
            productsData.forEach(p => {
                if (parseInt(p.total_sales) > maxVal) maxVal = parseInt(p.total_sales);
                if (parseInt(p.total_wishes) > maxVal) maxVal = parseInt(p.total_wishes);
            });
            maxVal = maxVal < 5 ? 5 : maxVal;

            const paddingX = 40;
            const paddingY = 30;
            const chartWidth = canvas.width - paddingX * 2;
            const chartHeight = canvas.height - paddingY * 2;

            ctx.beginPath();
            ctx.moveTo(paddingX, paddingY);
            ctx.lineTo(paddingX, canvas.height - paddingY);
            ctx.lineTo(canvas.width - paddingX, canvas.height - paddingY);
            ctx.strokeStyle = '#555';
            ctx.lineWidth = 2;
            ctx.stroke();

            const barWidth = 15; 
            const groupSpacing = chartWidth / productsData.length; 

            productsData.forEach((p, index) => {
                const x = paddingX + 20 + (index * groupSpacing);
                
                const salesHeight = (p.total_sales / maxVal) * chartHeight;
                ctx.fillStyle = '#2ecc71';
                ctx.fillRect(x, canvas.height - paddingY - salesHeight, barWidth, salesHeight);

                const wishesHeight = (p.total_wishes / maxVal) * chartHeight;
                ctx.fillStyle = '#f39c12';
                ctx.fillRect(x + barWidth, canvas.height - paddingY - wishesHeight, barWidth, wishesHeight);

                ctx.fillStyle = '#ccc';
                ctx.font = '12px sans-serif';
                ctx.textAlign = 'center';
                let label = p.product_name;
                if (label.length > 5) label = label.substring(0, 5) + '...';
                ctx.fillText(label, x + barWidth, canvas.height - paddingY + 20);
            });
            
            ctx.fillStyle = '#888';
            ctx.textAlign = 'right';
            ctx.fillText(maxVal, paddingX - 10, paddingY + 10);
        }
    </script>
</body>
</html>
