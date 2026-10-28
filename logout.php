<?php
session_start();
// 清除所有 Session 變數
$_SESSION = array();

// 銷毀 Session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 跳轉回首頁
header("Location: index.php");
exit();
