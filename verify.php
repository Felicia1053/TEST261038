<?php
// verify.php
require_once("db.php");

$token = $_GET['token'] ?? '';
$mail = $_GET['mail'] ?? '';

$message = "";
$is_success = false;

// 1. 檢查網址有沒有帶入必要的參數
if (empty($token) || empty($mail)) {
    $message = "無效的驗證連結！請確認您點擊的網址是否完整。";
} else {
    // 2. 去資料庫尋找符合這個信箱，而且 token 也完全一致的使用者
    $sql = "SELECT member_ID, is_active FROM final_members WHERE mail = ? AND verification_token = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mail, $token]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($member) {
        // 3. 檢查是不是已經開通轉過了
        if ($member['is_active'] == 1) {
            $message = "此帳號已經開通囉！請直接點擊下方按鈕前往登入。";
            $is_success = true;
        } else {
            // 4. 進行開通：更新狀態為 1 (已開通)，並將 token 清空以防未來被重複使用
            $update_sql = "UPDATE final_members SET is_active = 1, verification_token = NULL WHERE member_ID = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$member['member_ID']]);

            $message = "🎉 帳號開通成功！歡迎加入遊戲商店。";
            $is_success = true;
        }
    } else {
        $message = "驗證失敗，連結可能已失效、錯誤或帳號已被刪除。";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>信箱驗證 - 遊戲商店</title>
    <style>
        body { font-family: sans-serif; background-color: #1a1a1a; color: white; padding: 50px; text-align: center; }
        .auth-box { max-width: 400px; margin: 0 auto; background: #2a2a2a; padding: 40px 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        .msg { font-size: 1.2em; margin-bottom: 25px; font-weight: bold; line-height: 1.5; }
        .success { color: #a3d200; }
        .error { color: #ff6b6b; }
        .btn-login { display: inline-block; width: 100%; padding: 12px; background: #66c0f4; text-decoration: none; color: #1a1a1a; font-weight: bold; border-radius: 4px; box-sizing: border-box; transition: background 0.3s; }
        .btn-login:hover { background: #41a8e8; }
    </style>
</head>
<body>

    <div class="auth-box">
        <h2>會員開通狀態</h2>
        
        <div class="msg <?php echo $is_success ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>

        <a href="register_login.php?mode=login" class="btn-login">前往登入頁面</a>
    </div>

</body>
</html>
