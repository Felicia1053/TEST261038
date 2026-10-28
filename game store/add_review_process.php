<?php
// 啟動 Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("db.php");

if (!isset($_SESSION["passed"]) || $_SESSION["passed"] !== "TRUE") {
    die("<h2 style='text-align:center; color:#ff6b6b; margin-top:50px;'>❌ 錯誤：您必須登入才能發表評論。</h2>");
}

// 取得必要參數
$product_ID = isset($_POST['product_ID']) ? (int)$_POST['product_ID'] : 0;
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$member_ID = isset($_SESSION['member_ID']) ? (int)$_SESSION['member_ID'] : 0;

// 驗證參數
if ($product_ID <= 0 || empty($content) || $member_ID <= 0) {
    die("<h2 style='text-align:center; color:#ff6b6b; margin-top:50px;'>❌ 錯誤：缺少必要資訊，無法新增評論。</h2>");
}

try {
    // 驗證產品是否存在
    $check_sql = "SELECT product_ID FROM final_products WHERE product_ID = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$product_ID]);
    if (!$check_stmt->fetch()) {
        die("<h2 style='text-align:center; color:#ff6b6b; margin-top:50px;'>❌ 錯誤：該遊戲不存在。</h2>");
    }

    // 新增評論至 final_reviews
    $insert_sql = "INSERT INTO final_reviews (product_ID, member_ID, content, created_at) 
                   VALUES (?, ?, ?, NOW())";
    $insert_stmt = $pdo->prepare($insert_sql);
    $insert_stmt->execute([$product_ID, $member_ID, $content]);

    // 重定向回商品詳情頁
    header("Location: product_detail.php?id=" . $product_ID);
    exit();

} catch (PDOException $e) {
    die("系統錯誤: " . $e->getMessage());
}
?>
