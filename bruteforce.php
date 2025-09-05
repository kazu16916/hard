<?php

echo '<div id="bf-root" class="bf-root">';
echo '<div style="background: #fff3cd; padding: 20px; border-radius: 5px; margin: 20px 0; border: 2px solid #ffeaa7;">';
echo '<h3 style="color: #d63031; margin-bottom: 15px;">🚨 位置別総当たり攻撃ツール</h3>';
echo '<p style="margin-bottom: 15px; color: #636e72;">このツールは1文字ずつ段階的にパスワードを特定する攻撃機能です。</p>';


$host = 'db';
$dbname = 'security_exercise';
$bf_username = 'root';
$bf_password = 'password';

try {
    $bf_pdo = new PDO("mysql:host=$host;dbname=$dbname", $bf_username, $bf_password);
    $bf_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo '<p style="color: red;">データベース接続エラー</p>';
    echo '</div></div>';
    return;
}


if (isset($_POST['bf_submit'])) {
    $bf_targetUser = $_POST['bf_target_user'] ?? '';
    $bf_maxLength = (int)($_POST['bf_max_length'] ?? 8);
    $bf_useNumbers = isset($_POST['bf_use_numbers']);
    $bf_useSpecialChars = isset($_POST['bf_use_special_chars']);
    
    if ($bf_targetUser) {
        echo '<div id="bf-attack-progress" style="background: white; padding: 15px; border-radius: 5px; margin: 15px 0;">';
        echo '<h4>🎯 位置別攻撃進行状況</h4>';
        echo '<div id="bf-progress-log" style="background: #2d3436; color: #00b894; padding: 10px; border-radius: 3px; font-family: monospace; max-height: 400px; overflow-y: auto;">';
        

        $bf_stmt = $bf_pdo->prepare("SELECT id, username, password, email FROM users WHERE username = ?");
        $bf_stmt->execute([$bf_targetUser]);
        $bf_targetUserData = $bf_stmt->fetch();
        
        if (!$bf_targetUserData) {
            echo '[ERROR] ターゲットユーザーが見つかりません<br>';
        } else {
            $bf_targetPassword = $bf_targetUserData['password'];
            $bf_targetPasswordLength = strlen($bf_targetPassword);
            
            echo '[INFO] ターゲット: ' . htmlspecialchars($bf_targetUserData['username']) . '<br>';
            echo '[INFO] ユーザーID: ' . $bf_targetUserData['id'] . '<br>';
            echo '[INFO] メールアドレス: ' . htmlspecialchars($bf_targetUserData['email']) . '<br>';
            echo '[INFO] 最大探索長: ' . $bf_maxLength . '文字<br>';
            echo '[INFO] 実際のパスワード長: ' . $bf_targetPasswordLength . '文字（デバッグ情報）<br><br>';
            

            $bf_characters = 'abcdefghijklmnopqrstuvwxyz';
            if ($bf_useNumbers) {
                $bf_characters .= '0123456789';
                echo '[CONFIG] 数字を含む<br>';
            }
            if ($bf_useSpecialChars) {
                $bf_characters .= '!@#$%^&*';
                echo '[CONFIG] 特殊文字を含む<br>';
            }
            echo '[CONFIG] 使用文字セット: ' . htmlspecialchars($bf_characters) . '<br><br>';
            
            $bf_discoveredPassword = '';
            $bf_totalAttempts = 0;
            $bf_startTime = microtime(true);
            $bf_found = false;
            

            echo '[MODE] 位置別総当たり攻撃を実行中...<br>';
            echo '[STRATEGY] 1文字目から順番に特定していきます<br><br>';
            

            for ($bf_position = 0; $bf_position < $bf_maxLength; $bf_position++) {
                echo '[POSITION] ' . ($bf_position + 1) . '文字目を探索中...<br>';
                $bf_positionFound = false;
                $bf_positionAttempts = 0;
                

                for ($bf_charIndex = 0; $bf_charIndex < strlen($bf_characters); $bf_charIndex++) {
                    $bf_testChar = $bf_characters[$bf_charIndex];
                    $bf_testPassword = $bf_discoveredPassword . $bf_testChar;
                    
                    $bf_totalAttempts++;
                    $bf_positionAttempts++;
                    

                    if ($bf_positionAttempts % 10 === 0) {
                        echo '[TEST] 位置' . ($bf_position + 1) . ' - 試行' . $bf_positionAttempts . ': "' . 
                             htmlspecialchars($bf_testPassword) . '"<br>';
                        flush();
                    }
                    

                    if ($bf_position < $bf_targetPasswordLength && 
                        $bf_testChar === $bf_targetPassword[$bf_position]) {
                        
                        $bf_discoveredPassword .= $bf_testChar;
                        echo '<span style="color: #00b894; font-weight: bold;">';
                        echo '[FOUND] 位置' . ($bf_position + 1) . 'の文字発見: "' . 
                             htmlspecialchars($bf_testChar) . '"</span><br>';
                        echo '[PROGRESS] 現在の発見済みパスワード: "' . 
                             htmlspecialchars($bf_discoveredPassword) . '"<br>';
                        echo '[INFO] この位置の試行回数: ' . $bf_positionAttempts . '<br><br>';
                        
                        $bf_positionFound = true;
                        break;
                    }
                    
                    usleep(5000);

                    if ($bf_totalAttempts >= 2000) {
                        echo '<span style="color: #e17055;">[LIMIT] 安全制限: 2000回で停止</span><br>';
                        break 2;
                    }
                }
                

                if (!$bf_positionFound) {
                    echo '<span style="color: #e17055;">[POSITION_FAILED] 位置' . 
                         ($bf_position + 1) . 'で有効な文字が見つかりませんでした</span><br>';
                    

                    if (strlen($bf_discoveredPassword) > 0) {
                        echo '[CHECK] 発見済み部分でのパスワード完全一致を確認...<br>';
                        if ($bf_discoveredPassword === $bf_targetPassword) {
                            $bf_found = true;
                            echo '<span style="color: #00b894; font-weight: bold; font-size: 1.2em;">';
                            echo '[SUCCESS] 🎯 完全パスワード発見: "' . 
                                 htmlspecialchars($bf_discoveredPassword) . '"</span><br>';
                            break;
                        }
                    }
                    break;
                }
                

                if ($bf_discoveredPassword === $bf_targetPassword) {
                    $bf_found = true;
                    echo '<span style="color: #00b894; font-weight: bold; font-size: 1.2em;">';
                    echo '[SUCCESS] 🎯 完全パスワード発見: "' . 
                         htmlspecialchars($bf_discoveredPassword) . '"</span><br>';
                    break;
                }
                

                if (strlen($bf_discoveredPassword) >= $bf_targetPasswordLength) {
                    echo '[INFO] 推定されるパスワード長に達しました<br>';
                    if ($bf_discoveredPassword === $bf_targetPassword) {
                        $bf_found = true;
                        echo '<span style="color: #00b894; font-weight: bold; font-size: 1.2em;">';
                        echo '[SUCCESS] 🎯 パスワード特定完了: "' . 
                             htmlspecialchars($bf_discoveredPassword) . '"</span><br>';
                    }
                    break;
                }
            }
            
            $bf_endTime = microtime(true);
            $bf_attackDuration = $bf_endTime - $bf_startTime;
            
            echo '<br>[INFO] 攻撃完了 - ' . date('H:i:s') . '<br>';
            echo '[INFO] 総試行回数: ' . number_format($bf_totalAttempts) . '<br>';
            echo '[INFO] 攻撃時間: ' . round($bf_attackDuration, 2) . '秒<br>';
            echo '[INFO] 平均秒間試行数: ' . round($bf_totalAttempts / $bf_attackDuration, 2) . '<br>';
            
            if (!$bf_found && strlen($bf_discoveredPassword) > 0) {
                echo '<span style="color: #fdcb6e;">[PARTIAL] 部分的成功: "' . 
                     htmlspecialchars($bf_discoveredPassword) . '" (' . 
                     strlen($bf_discoveredPassword) . '文字)</span><br>';
            } elseif (!$bf_found) {
                echo '<span style="color: #d63031;">[FAILED] パスワードの特定に失敗しました</span><br>';
            }
            

            if ($bf_found) {
                echo '<br><div style="background: #00b894; color: white; padding: 15px; border-radius: 5px; margin: 10px 0;">';
                echo '<h4>🎯 位置別攻撃成功 - アカウント情報</h4>';
                echo '<p><strong>ユーザー名:</strong> ' . htmlspecialchars($bf_targetUserData['username']) . '</p>';
                echo '<p><strong>特定したパスワード:</strong> ' . htmlspecialchars($bf_discoveredPassword) . '</p>';
                echo '<p><strong>パスワード長:</strong> ' . strlen($bf_discoveredPassword) . '文字</p>';
                echo '<p><strong>メールアドレス:</strong> ' . htmlspecialchars($bf_targetUserData['email']) . '</p>';
                echo '<p><strong>ユーザーID:</strong> ' . $bf_targetUserData['id'] . '</p>';
                echo '</div>';
                
                echo '<div style="background: #74b9ff; color: white; padding: 15px; border-radius: 5px; margin: 10px 0;">';
                echo '<h4>🔓 次のステップ:</h4>';
                echo '<p>1. 取得した認証情報でログイン</p>';
                echo '<p>2. そのアカウントの権限で更なる攻撃を実行</p>';
                echo '<p>3. 他のアカウントに対しても同様の攻撃を実行</p>';
                echo '<p>4. システム全体への不正アクセスを拡大</p>';
                echo '</div>';
            }
        }
        
        echo '</div>';
        echo '</div>';
    }
}


echo '<form id="bf-form" method="POST" style="margin: 15px 0;">';

echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';


echo '<div>';
echo '<h4 style="color: #2d3436; margin-bottom: 15px;">🎯 攻撃対象設定</h4>';
echo '<div style="margin-bottom: 15px;">';
echo '<label style="display: block; margin-bottom: 5px; font-weight: bold;">攻撃対象ユーザー:</label>';
echo '<select name="bf_target_user" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">';
echo '<option value="">選択してください</option>';


$bf_stmt = $bf_pdo->prepare("SELECT username, email FROM users ORDER BY username");
$bf_stmt->execute();
$bf_users = $bf_stmt->fetchAll();

foreach ($bf_users as $bf_user) {
    echo '<option value="' . htmlspecialchars($bf_user['username']) . '">' . 
         htmlspecialchars($bf_user['username']) . ' (' . htmlspecialchars($bf_user['email']) . ')</option>';
}

echo '</select>';
echo '</div>';

echo '<div style="margin-bottom: 15px;">';
echo '<label style="display: block; margin-bottom: 5px; font-weight: bold;">最大パスワード長:</label>';
echo '<input type="number" name="bf_max_length" value="8" min="1" max="12" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">';
echo '<small style="color: #666;">位置別攻撃では12文字まで対応</small>';
echo '</div>';

echo '</div>';

echo '<div>';
echo '<h4 style="color: #2d3436; margin-bottom: 15px;">⚙️ 詳細設定</h4>';

echo '<div style="margin-bottom: 15px;">';
echo '<label style="display: flex; align-items: center; margin-bottom: 10px;">';
echo '<input type="checkbox" name="bf_use_numbers" value="1" style="margin-right: 8px;">';
echo '<span>数字を含む (0-9)</span>';
echo '</label>';

echo '<label style="display: flex; align-items: center; margin-bottom: 10px;">';
echo '<input type="checkbox" name="bf_use_special_chars" value="1" style="margin-right: 8px;">';
echo '<span>特殊文字を含む (!@#$%^&*)</span>';
echo '</label>';
echo '</div>';


echo '</div>';
echo '</div>';

echo '<div style="text-align: center; margin: 20px 0;">';
echo '<button type="submit" name="bf_submit" style="background: #d63031; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold;">🚀 位置別攻撃開始</button>';
echo '</div>';
echo '</form>';




echo '</div>';


echo '<style>';
echo '#bf-root * { box-sizing: border-box; }';
echo '#bf-root #bf-progress-log { animation: bf-pulse 2s infinite; }';
echo '@keyframes bf-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }';
echo '</style>';


echo '<script>';
echo '(function() {';
echo '  const root = document.getElementById("bf-root");';
echo '  if (!root) return;';
echo '  ';
echo '  const form = root.querySelector("#bf-form");';
echo '  if (form) {';
echo '    form.addEventListener("submit", function(e) {';
echo '      const targetUser = form.querySelector("select[name=bf_target_user]").value;';
echo '      if (!targetUser) {';
echo '        e.preventDefault();';
echo '        alert("攻撃対象ユーザーを選択してください");';
echo '        return;';
echo '      }';
echo '      if (!confirm("位置別総当たり攻撃を実行します。続行しますか？")) {';
echo '        e.preventDefault();';
echo '      }';
echo '    });';
echo '  }';
echo '  ';
echo '  // プログレスログの自動スクロール';
echo '  const log = root.querySelector("#bf-progress-log");';
echo '  if (log) {';
echo '    log.scrollTop = log.scrollHeight;';
echo '    setInterval(function() {';
echo '      log.scrollTop = log.scrollHeight;';
echo '    }, 1000);';
echo '  }';
echo '})();';
echo '</script>';

echo '</div>';
?>