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
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ABC Corporation - 企業管理システム</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; line-height: 1.6; color: #333; }
        .header { background: #2c3e50; color: white; padding: 1rem 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .nav { display: flex; justify-content: space-between; align-items: center; }
        .nav h1 { font-size: 1.8rem; }
        .nav ul { display: flex; list-style: none; gap: 20px; }
        .nav a { color: white; text-decoration: none; padding: 10px 15px; border-radius: 5px; transition: background 0.3s; }
        .nav a:hover { background: #34495e; }
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 80px 0; text-align: center; }
        .hero h2 { font-size: 3rem; margin-bottom: 20px; }
        .hero p { font-size: 1.2rem; margin-bottom: 30px; }
        .btn { display: inline-block; background: #e74c3c; color: white; padding: 12px 30px; text-decoration: none; border-radius: 25px; transition: background 0.3s; }
        .btn:hover { background: #c0392b; }
        .features { padding: 80px 0; background: #f8f9fa; }
        .features h3 { text-align: center; font-size: 2.5rem; margin-bottom: 50px; }
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; }
        .feature-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; }
        .feature-card h4 { color: #2c3e50; margin-bottom: 15px; }
        .login-status { background: #27ae60; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .footer { background: #2c3e50; color: white; text-align: center; padding: 30px 0; }
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

    <?php if(isset($_SESSION['user_id'])): ?>
    <div class="container">
        <div class="login-status">
            ようこそ、<?php echo htmlspecialchars($_SESSION['username']); ?>さん！
            <?php if($_SESSION['role'] === 'admin'): ?>
                <span style="background: #e74c3c; padding: 5px 10px; border-radius: 3px; margin-left: 10px;">管理者権限</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <section class="hero">
        <div class="container">
            <h2>企業管理システム</h2>
            <p>効率的なビジネス運営をサポートする統合管理プラットフォーム</p>
            <a href="login.php" class="btn">システムにログイン</a>
        </div>
    </section>

    <section class="features">
        <div class="container">
            <h3>主な機能</h3>
            <div class="feature-grid">
                <div class="feature-card">
                    <h4>📊 データ管理</h4>
                    <p>企業の重要なデータを安全に管理・分析できます</p>
                </div>
                <div class="feature-card">
                    <h4>👥 ユーザー管理</h4>
                    <p>社員のアカウント管理と権限設定を簡単に行えます</p>
                </div>
                <div class="feature-card">
                    <h4>📧 メール連携</h4>
                    <p>システムからの自動メール送信機能を提供します</p>
                </div>
                <div class="feature-card">
                    <h4>⚙️ カスタム機能</h4>
                    <p>管理者はカスタム機能をアップロードして拡張できます</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 ABC Corporation. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>