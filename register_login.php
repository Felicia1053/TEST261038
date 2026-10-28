<?php
// 1. 啟動 Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("db.php"); 
require_once("send_email.php"); 

$message = ""; 

// 3. 【後端處理】當收到 POST 表單送出請求時
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // ==================== 【處理註冊邏輯】 ====================
    if ($action === "register") {
        $name = trim($_POST["member_name"] ?? ""); 
        $mail = trim($_POST["mail"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($mail === "" || $password === "" || $name === "") {
            $message = "所有欄位皆為必填！";
        } else {
            // 檢查 Email 是否有人重複申請
            $sql = "SELECT COUNT(*) FROM final_members WHERE mail = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$mail]);
            $exists = (int) $stmt->fetchColumn();

            if ($exists !== 0) {
                $message = "該 Email 已經有人使用，請更換其他帳號。";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // 產生一組隨機驗證碼
                $token = bin2hex(random_bytes(16));

                // 將資料與 token 寫入資料庫 (is_active 預設為 0)
                $sql = "INSERT INTO final_members (member_name, mail, `password`, verification_token) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $mail, $hashed_password, $token]);

                $verify_link = "http://localhost/final-b04/verify.php?token=" . $token . "&mail=" . urlencode($mail);
                
                // 呼叫寄信函式
                $send_status = sendVerificationEmail($mail, $name, $verify_link);

                if ($send_status) {
                    // 寄信成功，跳轉至登入畫面並提示去收信
                    header("Location: register_login.php?mode=login&registered=1");
                } else {
                    echo "<script>alert('註冊成功，但驗證信寄送失敗，請聯繫客服。'); history.back();</script>";
                }
                exit();
            }
        }
    }

    // ==================== 【處理登入邏輯】 ====================
    if ($action === "login") {
        $mail = trim($_POST["mail"] ?? "");
        $password = $_POST["password"] ?? "";

        // 撈取會員資料與 is_active 狀態
        $sql = "SELECT member_ID, member_name, mail, `password`, is_active FROM final_members WHERE mail = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mail]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        $password_is_valid = false;

        if ($member) {
            $stored_password = (string) ($member["password"] ?? "");
            if (password_verify($password, $stored_password)) {
                $password_is_valid = true;
            } else {
                $password_is_valid = hash_equals($stored_password, $password);
            }
        }

        if (!$password_is_valid) {
            echo "<script>alert('信箱或密碼錯誤，請查明後再登入'); history.back();</script>";
            exit();
        } else {
            // 檢查帳號是否已經開通
            if ($member['is_active'] == 0) {
                echo "<script>alert('您的帳號尚未開通！請至信箱點擊驗證連結。'); history.back();</script>";
                exit();
            }

            // 登入成功：寫入 Session
            $_SESSION["id"] = (int)$member["member_ID"]; 
            $_SESSION["name"] = $member["member_name"];
            $_SESSION["mail"] = $member["mail"];
            $_SESSION["passed"] = "TRUE";
            $_SESSION["user_avatar"] = "default-avatar.png";

            header("Location: index.php");
            exit(); 
        }
    }
}

// 4. 【前端切換路由】
$mode = $_GET['mode'] ?? 'login';
if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $message = "註冊成功！我們已發送一封驗證信至您的信箱，請點擊信中連結開通帳號。"; 
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mode === 'register' ? '會員註冊' : '會員登入'; ?> - 遊戲商店</title>
    <style>
        body { font-family: sans-serif; background-color: #1a1a1a; color: white; padding: 50px; text-align: center; }
        .auth-box { max-width: 350px; margin: 0 auto; background: #2a2a2a; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        .input-group { margin-bottom: 15px; text-align: left; }
        .input-group label { display: block; margin-bottom: 5px; color: #ccc; }
        .input-group input { width: 100%; padding: 10px; border: 1px solid #444; background: #1a1a1a; color: white; border-radius: 4px; box-sizing: border-box; }
        .btn-submit { width: 100%; padding: 12px; background: #5c7e10; border: none; color: white; font-weight: bold; border-radius: 4px; cursor: pointer; margin-top: 10px; }
        .btn-submit:hover { background: #79a314; }
        .msg { color: #ff6b6b; margin-bottom: 15px; font-weight: bold; }
        .switch-link { margin-top: 20px; display: block; color: #66c0f4; text-decoration: none; }
    </style>
</head>
<body>

    <div class="auth-box">
        <?php if (!empty($message)): ?>
            <div class="msg" style="color: <?php echo isset($_GET['registered']) ? '#a3d200' : '#ff6b6b'; ?>;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($mode === 'register'): ?>
            <h2>會員註冊</h2>
            <form action="register_login.php" method="POST">
                <input type="hidden" name="action" value="register">
                
                <div class="input-group">
                    <label>會員暱稱：</label>
                    <input type="text" name="member_name" required placeholder="請輸入您的暱稱">
                </div>
                <div class="input-group">
                    <label>電子信箱 (Mail)：</label>
                    <input type="email" name="mail" required placeholder="example@mail.com">
                </div>
                <div class="input-group">
                    <label>設定密碼：</label>
                    <input type="password" name="password" required placeholder="請輸入密碼">
                </div>
                
                <button type="submit" class="btn-submit">確認註冊</button>
                <a href="register_login.php?mode=login" class="switch-link">已經有帳號？前往登入</a>
            </form>

        <?php else: ?>
            <h2>會員登入</h2>
            <form action="register_login.php" method="POST">
                <input type="hidden" name="action" value="login">
                
                <div class="input-group">
                    <label>電子信箱 (Mail)：</label>
                    <input type="email" name="mail" required placeholder="example@mail.com">
                </div>
                <div class="input-group">
                    <label>輸入密碼：</label>
                    <input type="password" name="password" required placeholder="請輸入密碼">
                </div>
                
                <button type="submit" class="btn-submit">登入</button>
                <a href="register_login.php?mode=register" class="switch-link">還沒有帳號？前往註冊</a>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>
