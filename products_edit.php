<?php
// 1. 啟動 Session 並確保登入
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["passed"]) || $_SESSION["passed"] !== "TRUE") {
    header("Location: register_login.php?mode=login");
    exit();
}

require_once("db.php");
$my_id = $_SESSION["id"];
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 2. 處理更新邏輯 (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "update") {
    $pid = (int)$_POST["product_ID"];
    $name = trim($_POST["product_name"]);
    $price = (int)$_POST["price"];
    $sale_price = ($_POST["sale_price"] === "") ? null : (int)$_POST["sale_price"];
    $info = trim($_POST["product_info"]);
    $status = $_POST["status"];
    $image_url = $_POST["current_image"] ?? ""; // 預設為舊圖片
    
    if (isset($_FILES["product_image"]) && $_FILES["product_image"]["error"] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        
        // 自動檢查 uploads 資料夾是否存在，若不存在則幫你建立
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext = pathinfo($_FILES["product_image"]["name"], PATHINFO_EXTENSION);
        $new_filename = "product_" . $pid . "_" . time() . "." . $file_ext;
        
        if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $upload_dir . $new_filename)) {
            // 上傳成功，將新的相對路徑指派給 $image_url
            $image_url = $upload_dir . $new_filename; 
        }
    }

    // 安全防禦：更新前再次檢查該商品是否屬於該使用者
    $sql = "UPDATE final_products SET product_name = ?, price = ?, sale_price = ?, product_info = ?, `status` = ?, image_url = ? 
            WHERE product_ID = ? AND member_ID = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $price, $sale_price, $info, $status, $image_url, $pid, $my_id]);

    echo "<script>alert('✅ 遊戲資訊已更新！'); window.location.href='creator.php';</script>";
    exit();
}

// 3. 讀取商品原始資料 (GET)
try {
    $sql = "SELECT * FROM final_products WHERE product_ID = ? AND member_ID = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id, $my_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        die("<script>alert('找不到該商品或無權編輯！'); window.location.href='creator.php';</script>");
    }
} catch (PDOException $e) {
    die("讀取錯誤: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>編輯遊戲 - <?php echo htmlspecialchars($game['product_name']); ?></title>
    <style>
        body { background: #1a1a1a; color: white; font-family: sans-serif; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #2a2a2a; padding: 30px; border-radius: 8px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #ccc; }
        input, select, textarea { width: 100%; padding: 10px; background: #111; color: white; border: 1px solid #444; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 10px 20px; background: #007acc; border: none; color: white; font-weight: bold; cursor: pointer; border-radius: 4px; }
    </style>
</head>
<body>
<div class="container">
    <h2>✏️ 編輯商品：<?php echo htmlspecialchars($game['product_name']); ?></h2>
    
    <form action="products_edit.php?id=<?php echo $product_id; ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="product_ID" value="<?php echo $game['product_ID']; ?>">
        
        <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($game['image_url'] ?? ''); ?>">
        
        <div class="form-group"><label>遊戲名稱</label><input type="text" name="product_name" value="<?php echo htmlspecialchars($game['product_name']); ?>" required></div>
        <div class="form-group"><label>定價</label><input type="number" name="price" value="<?php echo $game['price']; ?>" required></div>
        <div class="form-group"><label>特價 (選填)</label><input type="number" name="sale_price" value="<?php echo $game['sale_price']; ?>"></div>
        <div class="form-group"><label>詳細介紹</label><textarea name="product_info" rows="5"><?php echo htmlspecialchars($game['product_info'] ?? ''); ?></textarea></div>
        <div class="form-group"><label>發售狀態</label><select name="status"><option value="已發售" <?php if($game['status']=='已發售') echo 'selected';?>>已發售</option><option value="未發售" <?php if($game['status']=='未發售') echo 'selected';?>>未發售</option></select></div>
        <div class="form-group">
            <label>當前圖片：</label>
            <?php if (!empty($game['image_url']) && file_exists($game['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($game['image_url']); ?>" style="width:100px; display:block; margin-bottom:10px;">
            <?php else: ?>
                <p style="color: #888; font-size: 14px; margin-bottom: 10px;">目前沒有上傳遊戲圖片</p>
            <?php endif; ?>
            <label>選擇新圖片 (若不更改則留空)：</label>
            <input type="file" name="product_image" accept="image/*">
        </div>
        <button type="submit" class="btn">儲存更新</button>
        <a href="creator.php" style="color: #aaa; margin-left: 10px;">取消</a>
    </form>
</div>
</body>
</html>
