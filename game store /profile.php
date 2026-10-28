<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["passed"]) || $_SESSION["passed"] !== "TRUE") {
    echo "<script>
        alert('請先登入會員才能查看個人檔案');
        window.location.href = 'register_login.php?mode=login';
    </script>";
    exit();
}

require_once("db.php");
$member_id = $_SESSION["id"];
$message = "";
$msg_type = "error";

// 在最外層先撈出目前資料庫內的舊資料，這樣下方 POST 才能進行新舊比對
try {
    $stmt = $pdo->prepare("SELECT member_name, mail, avatar_url FROM final_members WHERE member_ID = ?");
    $stmt->execute([$member_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $current_avatar = !empty($user['avatar_url']) ? $user['avatar_url'] : "default-avatar.png";
} catch (PDOException $e) {
    die("載入會員資料失敗: " . $e->getMessage());
}

//當收到表單送出 (POST) 更新請求時
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_name = trim($_POST["member_name"] ?? "");
    $new_mail = trim($_POST["mail"] ?? "");
    
    if (empty($new_name) || empty($new_mail)) {
        $message = "暱稱與電子信箱不能為空";
    } else {
        try {
            $avatar_filename = $_POST["current_avatar"] ?? "default-avatar.png";
            $is_avatar_changed = false;

            // 檢查使用者有沒有選取新大頭貼
            if (isset($_FILES["avatar"]) && $_FILES["avatar"]["error"] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES["avatar"]["tmp_name"];
                $file_name = $_FILES["avatar"]["name"];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed = array('jpg', 'jpeg', 'png', 'gif');
                if (in_array($file_ext, $allowed)) {
                    // 避免同名檔案，重新用時間和會員ID命名新檔名
                    $avatar_filename = "avatar_" . $member_id . "_" . time() . "." . $file_ext;
                    
                    if (move_uploaded_file($file_tmp, "uploads/" . $avatar_filename)) {
                        $is_avatar_changed = true; 
                        // === 自動刪除舊檔案邏輯 ===
                        // 拿剛才撈出來的 $user['avatar_url'] 來判斷
                        // 確保舊資料不為空，且不是預設的 "default-avatar.png"
                        if (!empty($user['avatar_url']) && $user['avatar_url'] !== 'default-avatar.png') {
                            $old_file_path = "uploads/" . $user['avatar_url'];
                            
                            // 檢查實體檔案是否存在，存在就當場刪除
                            if (file_exists($old_file_path)) {
                                unlink($old_file_path);
                            }
                        }
                    }
                } else {
                    $message = "不支援的圖片格式！僅限 JPG, PNG, GIF。";
                }
            }

            if (empty($message)) {
                $update_fields = [];  // 存放要更新的欄位
                $bindings = [];       // 存放資料值

                // 判斷各個欄位是否變更
                if ($new_name !== $user['member_name']) {
                    $update_fields[] = "member_name = ?";
                    $bindings[] = $new_name;
                    $_SESSION["name"] = $new_name; 
                }

                if ($new_mail !== $user['mail']) {
                    $update_fields[] = "mail = ?";
                    $bindings[] = $new_mail;
                    $_SESSION["mail"] = $new_mail; 
                }

                if ($is_avatar_changed) {
                    $update_fields[] = "avatar_url = ?";
                    $bindings[] = $avatar_filename;
                    $_SESSION["user_avatar"] = $avatar_filename; 
                }


                if (!empty($update_fields)) {
                    $sql = "UPDATE final_members SET " . implode(", ", $update_fields) . " WHERE member_ID = ?"; //implode陣列所有元素拿出來，用指定的字串接成長字串
                    // 把會員 ID 塞進綁定陣列的最後
                    $bindings[] = $member_id;

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($bindings);

                    $message = "個人資料更新成功！";
                    $msg_type = "success";
                } else {
                    $message = "資料無任何變更。";
                    $msg_type = "success"; 
                }
            }

        } catch (PDOException $e) {
            $message = "更新失敗: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>個人檔案</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #1a1a1a; color: #fff; margin: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background: #0f1115; padding: 12px 30px; margin-bottom: 25px; border-radius: 6px; }
        .navbar a { color: #ccc; text-decoration: none; font-weight: bold; }
        .navbar a:hover { color: #66c0f4; }

        .profile-box { background: #2a2a2a; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); text-align: center; }
        .avatar-container { width: 130px; height: 130px; margin: 0 auto 20px; border-radius: 50%; overflow: hidden; border: 3px solid #66c0f4; background: #000; }
        .avatar-container img { width: 100%; height: 100%; object-fit: cover; }

        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 6px; color: #ccc; font-weight: bold; }
        .form-group input[type="text"], .form-group input[type="email"] { 
            width: 100%; padding: 12px; border: 1px solid #444; background: #1a1a1a; color: white; border-radius: 4px; box-sizing: border-box; 
        }
        .form-group input[type="file"] { color: #ccc; margin-top: 5px; }

        .btn-save { width: 100%; padding: 12px; background: #5c7e10; border: none; color: white; font-weight: bold; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 10px; }
        .btn-save:hover { background: #79a314; }
        .btn-logout { display: block; width: 100%; padding: 12px; background: #bc3b3b; border: none; color: white; font-weight: bold; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 15px; text-decoration: none; box-sizing: border-box; }
        .btn-logout:hover { background: #e74c3c; }

        .msg { margin-bottom: 20px; padding: 10px; border-radius: 4px; font-weight: bold; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div><a href="index.php">◀ 返回商店首頁</a></div>
        <div style="color: #a3d200; font-weight: bold;">個人檔案</div>
    </nav>

    <div class="container">
        <div class="profile-box">
            <h2>👤 修改個人檔案</h2>
            
            <?php if (!empty($message)): ?>
                <div class="msg" style="background-color: <?php echo $msg_type === 'success' ? 'rgba(92,126,16,0.2)' : 'rgba(188,59,59,0.2)'; ?>; color: <?php echo $msg_type === 'success' ? '#a3d200' : '#ff6b6b'; ?>;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="profile.php" method="POST" enctype="multipart/form-data">
                
                <div class="avatar-container">
                    <?php 
                    $avatar_path = (!empty($current_avatar) && file_exists("uploads/" . $current_avatar) && $current_avatar !== "default-avatar.png") 
                                ? "uploads/" . $current_avatar : "uploads/default-avatar.png"; 
                    ?>
                    <img src="<?php echo $avatar_path; ?>" alt="大頭貼">
                </div>

                <input type="hidden" name="current_avatar" value="<?php echo htmlspecialchars($current_avatar); ?>">

                <div class="form-group">
                    <label>更換大頭貼：</label>
                    <input type="file" name="avatar" accept="image/*">
                </div>

                <div class="form-group">
                    <label>會員暱稱：</label>
                    <input type="text" name="member_name" value="<?php echo htmlspecialchars($user['member_name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>電子信箱 (Mail)：</label>
                    <input type="email" name="mail" value="<?php echo htmlspecialchars($user['mail'] ?? ''); ?>" required>
                </div>

                <button type="submit" class="btn-save">💾 儲存修改</button>
                
                <a href="logout.php" class="btn-logout">🚪 登出帳號</a>
            </form>
        </div>
    </div>
</body>
</html>
