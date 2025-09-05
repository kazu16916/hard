<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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


$customFeatures = '';
$filesList = [];

try {
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE is_active = 1 ORDER BY created_at DESC");
    $stmt->execute();
    $activeFiles = $stmt->fetchAll();
    
    foreach ($activeFiles as $file) {
        $filesList[] = $file;
        if (file_exists($file['file_path']) && pathinfo($file['filename'], PATHINFO_EXTENSION) === 'php') {
            ob_start();
            include $file['file_path'];
            $customFeatures .= ob_get_clean();
        }
    }
} catch(Exception $e) {
    $error = "カスタム機能の読み込みエラー: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>カスタム機能 - ABC Corporation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #f8f9fa; color: #333; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
        .nav { display: flex; justify-content: space-between; align-items: center; }
        .nav h1 { font-size: 1.8rem; }
        .nav ul { display: flex; list-style: none; gap: 20px; }
        .nav a { color: white; text-decoration: none; padding: 10px 15px; border-radius: 5px; transition: background 0.3s; }
        .nav a:hover { background: #34495e; }
        .features-panel { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .status-info { background: #e8f4f8; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .file-list { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .file-item { background: white; padding: 10px; margin-bottom: 10px; border-radius: 3px; border-left: 4px solid #007bff; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <nav class="nav">
                <h1>カスタム機能管理</h1>
                <ul>
                    <li><a href="index.php">ホーム</a></li>
                    <li><a href="login.php">ログイン</a></li>
                    <li><a href="dashboard.php">ダッシュボード</a></li>
                </ul>
            </nav>
        </div>

        <div class="features-panel">
            <h2>アップロード済みカスタム機能</h2>
            
            <div class="status-info">
                <strong>システム情報:</strong><br>
                セッション状態: <?php echo isset($_SESSION['user_id']) ? 'ログイン中 (' . $_SESSION['username'] . ')' : 'ログアウト中'; ?><br>
                アクティブファイル数: <?php echo count($filesList); ?><br>
                現在時刻: <?php echo date('Y-m-d H:i:s'); ?>
            </div>

            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (empty($filesList)): ?>
                <div class="error">
                    アップロードされたカスタム機能はありません。<br>
                    管理者でログインして、dashboard.phpでPHPファイルをアップロードしてください。
                </div>
            <?php else: ?>
                <div class="success">
                    <?php echo count($filesList); ?> 個のカスタム機能が見つかりました。
                </div>

                <div class="file-list">
                    <h3>ファイル一覧:</h3>
                    <?php foreach($filesList as $file): ?>
                        <div class="file-item">
                            <strong><?php echo htmlspecialchars($file['original_name']); ?></strong><br>
                            <small>
                                ファイル: <?php echo htmlspecialchars($file['filename']); ?> | 
                                パス: <?php echo htmlspecialchars($file['file_path']); ?> | 
                                状態: <?php echo $file['is_active'] ? '有効' : '無効'; ?> | 
                                アップロード日: <?php echo $file['created_at']; ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <hr style="margin: 30px 0;">

            <h2>実行可能カスタム機能</h2>
            
            <?php if ($customFeatures): ?>
                <div class="success">
                    カスタム機能が正常に読み込まれました。
                </div>
                <div style="border: 2px solid #007bff; border-radius: 10px; padding: 20px; margin: 20px 0;">
                    <?php echo $customFeatures; ?>
                </div>
            <?php else: ?>
                <div class="error">
                    実行可能なカスタム機能が見つかりません。<br>
                    PHPファイルが正しくアップロードされているか確認してください。
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="login.php" style="background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;">ログインページに戻る</a>
        </div>
    </div>
</body>
</html>