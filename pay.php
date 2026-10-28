<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("db.php");

//檢查會員是否登入
if (!isset($_SESSION["passed"]) || $_SESSION["passed"] !== "TRUE") {
    header("Location: register_login.php?mode=login&redirect=pay.php");
    exit();
}

$member_ID = $_SESSION["id"] ?? 0;

// 撈取購物車商品
$cart_sql = "SELECT p.product_ID, p.product_name, p.price, p.sale_price, p.image_url
             FROM final_carts c
             INNER JOIN final_products p ON c.product_ID = p.product_ID
             WHERE c.member_ID = ?";
$cart_stmt = $pdo->prepare($cart_sql);
$cart_stmt->execute([$member_ID]);
$cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cart_items)) {
    header("Location: index.php");
    exit();
}

//計算訂單總額
$subtotal = 0;
foreach ($cart_items as $item) {
    $item_price = $item['sale_price'] !== null ? $item['sale_price'] : $item['price'];
    $subtotal += $item_price;
}
$total = $subtotal;
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>結帳付款 - 遊戲商店</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #1a1a1a; color: #fff; margin: 0; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        
        /* 頂部導覽列 */
        .navbar { display: flex; justify-content: space-between; align-items: center; background: #0f1115; padding: 12px 30px; margin-bottom: 25px; border-radius: 6px; }
        .navbar a { color: #ccc; text-decoration: none; font-weight: bold; }
        .navbar a:hover { color: #66c0f4; }
        
        /* 步驟指示器 */
        .steps { display: flex; justify-content: center; gap: 40px; margin-bottom: 30px; }
        .step { text-align: center; }
        .step-number { width: 35px; height: 35px; background: #34495e; border-radius: 50%; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .step-number.active { background: #5c7e10; }
        .step-label { font-size: 13px; color: #aaa; }
        
        /* 主要內容區 */
        .content { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        
        /* 左側：訂單詳情 */
        .left-section { background: #222; padding: 25px; border-radius: 8px; }
        .section-title { font-size: 18px; color: #66c0f4; font-weight: bold; margin-bottom: 20px; border-bottom: 2px solid #5c7e10; padding-bottom: 10px; }
        
        /* 購物車商品列表 */
        .cart-item { display: grid; grid-template-columns: 80px 1fr 100px; gap: 15px; align-items: center; padding: 15px 0; border-bottom: 1px solid #333; }
        .cart-item:last-child { border-bottom: none; }
        .cart-item-img { width: 80px; height: 60px; background: #111; border-radius: 4px; overflow: hidden; }
        .cart-item-img img { width: 100%; height: 100%; object-fit: cover; }
        .cart-item-name { color: #ddd; font-weight: bold; }
        .cart-item-price { color: #a3d200; text-align: right; }
        
        /* 右側：訂單摘要 */
        .right-section { background: #2a2a2a; padding: 25px; border-radius: 8px; height: fit-content; position: sticky; top: 20px; }
        .summary-item { display: flex; justify-content: space-between; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #444; }
        .summary-item:last-child { border-bottom: none; padding-bottom: 0; }
        .summary-label { color: #aaa; }
        .summary-value { color: #ddd; font-weight: bold; }
        .summary-total { display: flex; justify-content: space-between; margin-top: 15px; padding-top: 15px; border-top: 2px solid #5c7e10; font-size: 16px; }
        .summary-total .total-label { color: #ddd; }
        .summary-total .total-value { color: #a3d200; font-weight: bold; font-size: 20px; }
        
        /* 按鈕 */
        .btn { padding: 12px 25px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 15px; transition: 0.3s; }
        .btn-primary { background: #5c7e10; color: white; width: 100%; }
        .btn-primary:hover { background: #79a314; }
        .btn-secondary { background: #34495e; color: white; width: 100%; margin-bottom: 10px; }
        .btn-secondary:hover { background: #475569; }
        
        /* 支付方式 */
        .payment-method { display: flex; align-items: center; padding: 12px; background: #111; border: 1px solid #444; border-radius: 4px; margin-bottom: 10px; cursor: pointer; }
        .payment-method input[type="radio"] { margin-right: 10px; cursor: pointer; }
        .payment-method:has(input:checked) { border-color: #66c0f4; background: #1a2230; }
        .payment-method label { margin-bottom: 0; cursor: pointer; flex: 1; }
        
        /* 警告提示 */
        .alert { padding: 12px; background: #2c1f1f; border-left: 4px solid #e74c3c; border-radius: 4px; margin-bottom: 20px; color: #f8a5a5; font-size: 13px; }
        .alert-success { background: #1f2c1f; border-left-color: #5c7e10; color: #a3d200; }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php">◀ 返回遊戲商店</a>
        <div style="color: #66c0f4; font-weight: bold;">💳 結帳付款</div>
    </nav>

    <div class="container">
        
        <div class="steps">
            <div class="step"><div class="step-number">✓</div><div class="step-label">檢視購物車</div></div>
            <div class="step"><div class="step-number active">2</div><div class="step-label">選擇支付方式</div></div>
            <div class="step"><div class="step-number">3</div><div class="step-label">完成付款</div></div>
        </div>

        <div class="content">
            
            <div class="left-section">
                
                <div style="margin-bottom: 30px;">
                    <div class="section-title">📦 訂單商品</div>
                    <div>
                        <?php foreach ($cart_items as $item): 
                            $item_price = $item['sale_price'] !== null ? $item['sale_price'] : $item['price'];
                            $imgSrc = (!empty($item['image_url']) && file_exists($item['image_url'])) ? $item['image_url'] : 'uploads/default-avatar.png';
                        ?>
                            <div class="cart-item">
                                <div class="cart-item-img">
                                    <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                </div>
                                <div class="cart-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="cart-item-price">NT$ <?php echo number_format($item_price); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <div class="section-title">💰 支付方式</div>
                    <form action="index.php" method="POST" id="paymentForm">
                        
                        <div class="payment-method">
                            <input type="radio" id="payment_credit" name="payment_method" value="credit_card" checked>
                            <label for="payment_credit">💳 信用卡 / 金融卡</label>
                        </div>
                        <div class="payment-method">
                            <input type="radio" id="payment_line" name="payment_method" value="line_pay">
                            <label for="payment_line">💚 LINE Pay</label>
                        </div>
                        <div class="payment-method">
                            <input type="radio" id="payment_ecpay" name="payment_method" value="ecpay">
                            <label for="payment_ecpay">🏦 超商繳費 </label>
                        </div>
                        <div class="payment-method">
                            <input type="radio" id="payment_transfer" name="payment_method" value="bank_transfer">
                            <label for="payment_transfer">🏦 銀行轉帳</label>
                        </div>

                        <div style="margin-top: 30px;">
                            <button type="button" onclick="window.history.back()" class="btn btn-secondary">◀ 返回購物車</button>
                            <button type="submit" class="btn btn-primary">確認結帳</button>
                        </div>

                        <input type="hidden" name="action" value="execute_payment">
                        <?php foreach ($cart_items as $item): ?>
                            <input type="hidden" name="bought_products[]" value="<?php echo $item['product_ID']; ?>">
                        <?php endforeach; ?>
                    </form>
                </div>

            </div>

            <div class="right-section">
                <div class="section-title">📊 訂單摘要</div>
                
                <div class="summary-item">
                    <span class="summary-label">商品金額：</span>
                    <span class="summary-value">NT$ <?php echo number_format($subtotal); ?></span>
                </div>
                
                <div class="summary-total">
                    <span class="total-label">合計應付：</span>
                    <span class="total-value">NT$ <?php echo number_format($total); ?></span>
                </div>

                <div class="alert alert-success" style="margin-top: 20px; line-height: 1.5;">
                    ✅ 數位商品在完成付款後將立即確認，您可以前往「我的遊戲」庫即時下載啟動。
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            // 利用 confirm 產生帶有「確定」與「取消」的瀏覽器原生高質感對話框
            const confirmPayment = confirm("⚠️ 扣款確認提示：\n您確定要使用選定的支付方式，支付總計 NT$ <?php echo number_format($total); ?> 的遊戲費用嗎？");
            
            // 如果使用者按了「取消」
            if (!confirmPayment) {
                e.preventDefault(); // 攔截並取消表單的 POST 送出行為，網頁停留在原地
                return false;
            }
            // 如果按了「確定」，則放行送出表單，讓 index.php 去處理後續扣款交易
        });
    </script>
    </body>
</html>