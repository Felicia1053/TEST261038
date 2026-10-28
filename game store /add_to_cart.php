<?php
// 1. 啟動 Session（用來抓取目前是哪位登入的會員要買遊戲）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. 攔截安全檢查：如果根本沒登入，不准使用購物車功能
if (!isset($_SESSION["passed"]) || $_SESSION["passed"] !== "TRUE") {
    echo "<script>
        alert('請先登入會員，才能將遊戲加入購物車！');
        window.location.href = 'register_login.php?mode=login';
    </script>";
    exit();
}

// 3. 引入你的資料庫連線檔案（對齊你的 db.php）
require_once("db.php");

$member_id = $_SESSION["id"]; // 取得當前會員的整數 ID (例如 1)
$product_id = isset($_POST["product_ID"]) ? (int)$_POST["product_ID"] : 0; // 接收首頁表單傳過來的商品 ID

// 安全性防禦：檢查傳過來的商品 ID 是否合法
if ($product_id <= 0) {
    echo "<script>
        alert('無效的商品 ID，請重新選購！');
        window.location.href = 'index.php';
    </script>";
    exit();
}

try {
    // 4. 核心邏輯 A：防止重複加入。檢查該會員的購物車內是否已經有這款遊戲
    $check_sql = "SELECT COUNT(*) FROM final_carts WHERE member_ID = ? AND product_ID = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$member_id, $product_id]);
    $is_exist = (int)$check_stmt->fetchColumn();

    if ($is_exist > 0) {
        // 如果重複買了，彈出視窗並退回首頁，不重複寫入
        echo "<script>
            alert('這款遊戲已經在您的購物車內囉！');
            window.location.href = 'index.php';
        </script>";
        exit();
    } else {
        // 5. 核心邏輯 B：💡 數字自動遞增優化！
        // 因為你的 final_carts 的 ID 欄位是 INT 自動遞增，
        // 這裡在 INSERT 時完全不用寫 ID 欄位，直接把 member_ID 和 product_ID 塞進去，資料庫自己會幫你跳 1, 2, 3 號！
        $insert_sql = "INSERT INTO final_carts (member_ID, product_ID) VALUES (?, ?)";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([$member_id, $product_id]);

        // 成功寫入 HeidiSQL 資料庫，跳出成功喜訊，並秒送回首頁讓使用者繼續逛
        echo "<script>
            alert('🛒 成功加入購物車！');
            window.location.href = 'index.php';
        </script>";
        exit();
    }

} catch (PDOException $e) {
    // 萬一資料庫執行出錯，列印出錯誤訊息方便你除錯
    die("購物車作業失敗，錯誤原因: " . $e->getMessage());
}
?>
