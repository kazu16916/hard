<?php
session_start();


$host = 'db';
$dbname = 'security_exercise';
$username = 'root';
$password = 'password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

$message = '';


if ($_POST) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $body = $_POST['message'] ?? '';
    
    if ($name && $email && $subject && $body) {

        $to = "admin@company.com";
        $headers = "From: $email\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        

        require_once 'xss_validation.php';
        $validator = new XSSRouteValidator($pdo);
        

        $hasXSSPayload = (
            strpos($body, '<script>') !== false ||
            strpos($body, 'onerror') !== false ||
            strpos($body, 'javascript:') !== false ||
            strpos($subject, '<') !== false ||
            strpos($body, '<img') !== false ||
            strpos($body, 'fetch') !== false ||
            strpos($body, 'XMLHttpRequest') !== false
        );
        

        $emailBody = "
        <html>
        <head><title>お問い合わせ</title></head>
        <body>
            <h2>新しいお問い合わせ</h2>
            <p><strong>名前:</strong> $name</p>
            <p><strong>メールアドレス:</strong> $email</p>
            <p><strong>件名:</strong> $subject</p>
            <p><strong>メッセージ:</strong></p>
            <div>$body</div>
            <hr>
            <p><small>このメールは自動送信されました。</small></p>
        </body>
        </html>
        ";
        

        $stmt = $pdo->prepare("INSERT INTO emails (to_email, from_email, subject, body) VALUES (?, ?, ?, ?)");
        $stmt->execute([$to, $email, $subject, $emailBody]);
        $emailId = $pdo->lastInsertId();
        

        require_once 'xss_validation.php';
        $validator = new XSSRouteValidator($pdo);
        

        if ($hasXSSPayload) {
            $validationToken = $validator->generateValidationToken($emailId, true);
            
            if ($validationToken) {

                $emailBodyWithToken = $emailBody . 
                    "<img src='http://localhost:8088/xss_tracker.php?token=" . $validationToken . "' style='display:none;' />";
                

                $updateStmt = $pdo->prepare("UPDATE emails SET body = ? WHERE id = ?");
                $updateStmt->execute([$emailBodyWithToken, $emailId]);
                
                error_log("XSS payload detected and token generated: {$validationToken}");
            }
        }
        

        $message = "お問い合わせありがとうございました。管理者に通知されました。<br>";
        $message .= "<small style='color: #666;'>（演習用: 実際のメール送信は行われていません）</small>";
        

        if (strpos($body, '<') !== false || strpos($subject, '<') !== false) {
            $message .= "<br><div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
            $message .= "<strong>送信内容プレビュー:</strong><br>";
            $message .= "件名: $subject<br>";
            $message .= "内容: $body";
            if ($hasXSSPayload && isset($validationToken)) {
                $message .= "<br><span style='color: #28a745; font-weight: bold;'>XSS攻撃トークンが生成されました</span>";
            }
            $message .= "</div>";
        }
    } else {
        $message = "すべての項目を入力してください。";
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お問い合わせ - ABC Corporation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; }
        .header { background: #2c3e50; color: white; padding: 1rem 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 0 20px; }
        .nav { display: flex; justify-content: space-between; align-items: center; }
        .nav h1 { font-size: 1.8rem; }
        .nav ul { display: flex; list-style: none; gap: 20px; }
        .nav a { color: white; text-decoration: none; padding: 10px 15px; border-radius: 5px; transition: background 0.3s; }
        .nav a:hover { background: #34495e; }
        .main { padding: 40px 0; }
        .form-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-group textarea { height: 120px; resize: vertical; }
        .btn { background: #667eea; color: white; padding: 12px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; transition: background 0.3s; }
        .btn:hover { background: #5a67d8; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .back-link { margin-top: 20px; }
        .back-link a { color: #667eea; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .attack-hint { background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-size: 14px; }
        .payload-examples { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .payload-code { background: #2d3436; color: #00b894; padding: 10px; border-radius: 3px; font-family: monospace; margin: 10px 0; overflow-x: auto; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <h1>ABC Corporation</h1>
                <ul>
                    <li><a href="index.php">ホーム</a></li>
                    <li><a href="services.php">サービス</a></li>
                    <li><a href="contact.php">お問い合わせ</a></li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li><a href="dashboard.php">ダッシュボード</a></li>
                        <li><a href="logout.php">ログアウト</a></li>
                    <?php else: ?>
                        <li><a href="login.php">ログイン</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <div class="form-container">
                <h2>お問い合わせ</h2>
                <p style="margin-bottom: 30px; color: #666;">ご質問やご要望がございましたら、お気軽にお問い合わせください。</p>

                

                <?php if ($message): ?>
                    <div class="message success"><?php echo $message; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="name">お名前:</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">自分のサンプルメールアドレス:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="subject">件名:</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label for="message">メッセージ:</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>
                    <button type="submit" class="btn">送信</button>
                </form>

                <div class="back-link">
                    <a href="index.php">← トップページに戻る</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>