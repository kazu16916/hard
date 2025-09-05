<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once 'session_security.php';


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

$error = '';


if ($_POST) {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    
    if ($user && $pass) {

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$user, $pass]);
        $userData = $stmt->fetch();
        
        if ($userData) {

            $_SESSION = array();
            

            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            

            session_destroy();
            session_start();
            session_regenerate_id(true);
            

            $_SESSION = [];
            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['username'] = $userData['username'];
            $_SESSION['role'] = $userData['role'];
            $_SESSION['user_specific_id'] = $userData['id'];
            $_SESSION['session_hash'] = hash('sha256', $userData['id'] . $userData['username'] . session_id() . time());


            $bind = bin2hex(random_bytes(16));
            $_SESSION['bind_token'] = $bind;


            setcookie('BIND', $bind, [
                'expires'  => 0,
                'path'     => '/',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);


            $_SESSION['browser_fingerprint'] = hash(
                'md5',
                ($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['REMOTE_ADDR'] ?? '')
            );
            $_SESSION['session_pattern'] = substr(session_id(), 0, 8);

            markSessionAsLegitimate();

            $_SESSION['login_time'] = $_SESSION['login_time'] ?? time();
            $_SESSION['session_initialized'] = true;
            $_SESSION['login_method'] = $_SESSION['login_method'] ?? 'direct';
            session_write_close();

            

            error_log("Legitimate Login: User={$userData['username']}, SessionID=" . session_id());
            
            header('Location: dashboard.php?login_success=1');
            exit;
        } else {
            $error = 'ユーザー名またはパスワードが間違っています。';
        }
    } else {
        $error = 'すべての項目を入力してください。';
    }
}

$customFeatures = '';
$stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE is_active = 1");
$stmt->execute();
$activeFiles = $stmt->fetchAll();

foreach ($activeFiles as $file) {
    if (file_exists($file['file_path']) && pathinfo($file['filename'], PATHINFO_EXTENSION) === 'php') {
        ob_start();
        include_once $file['file_path'];
        $customFeatures .= ob_get_clean();
    }
}


$currentSession = [
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'] ?? 'not set',
    'username' => $_SESSION['username'] ?? 'not set',
    'role' => $_SESSION['role'] ?? 'not set',
    'login_method' => $_SESSION['login_method'] ?? 'unknown',
];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - ABC Corporation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px 0; }
        .login-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 15px 30px rgba(0,0,0,0.2); width: 100%; max-width: 500px; }
        .login-header { text-align: center; margin-bottom: 30px; }
        .login-header h1 { color: #2c3e50; margin-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .btn { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; transition: background 0.3s; }
        .btn:hover { background: #5a67d8; }
        .error { background: #e74c3c; color: white; padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #667eea; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .custom-features { margin-top: 30px; paddingトップ: 20px; border-top: 2px solid #eee; }
        .debug-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; font-size: 0.8em; color: #666; }
        .session-debug { background: #e8f4f8; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .hijacking-test { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>ログイン</h1>
            <p>企業管理システムにアクセス</p>
        </div>


        

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">ユーザー名:</label>
                <input type="text" id="username" name="username" required>
                <small>テスト用: admin または user1</small>
            </div>
            <div class="form-group">
                <label for="password">パスワード:</label>
                <input type="password" id="password" name="password" required>
                <small>admin: admin123 | user1: user123</small>
            </div>
            <button type="submit" class="btn">ログイン</button>
        </form>

        <div class="back-link">
            <a href="index.php">← トップページに戻る</a>
        </div>


        


        <div class="custom-features">
            <?php echo $customFeatures; ?>
        </div>
    </div>

    <script>

        console.log('=== ログインページのセッション情報 ===');
        console.log('セッションID: <?php echo $currentSession['session_id']; ?>');
        console.log('ユーザー: <?php echo $currentSession['username']; ?>');
        console.log('権限: <?php echo $currentSession['role']; ?>');
        console.log('ログイン方法: <?php echo $currentSession['login_method']; ?>');
        console.log('初期化フラグ: <?php echo isset($_SESSION['session_initialized']) ? "true" : "false"; ?>');
        console.log('Cookie: ' + document.cookie);
        console.log('=====================================');


        function testSessionHijacking() {
            console.log('セッションハイジャック検出テスト:');
            console.log('現在のブラウザフィンガープリント:', navigator.userAgent);
            console.log('現在のCookie:', document.cookie);
        }


        function toggleCustomFeatures() {
            var content = document.getElementById('customFeaturesContent');
            if (content) {
                if (content.style.display === 'none' || content.style.display === '') {
                    content.style.display = 'block';
                } else {
                    content.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>
