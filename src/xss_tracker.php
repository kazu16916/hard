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
    http_response_code(500);
    exit;
}

require_once 'xss_validation.php';
$validator = new XSSRouteValidator($pdo);

$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? 'track';

if ($token) {
    switch ($action) {
        case 'email_opened':
        case 'email_opened_dashboard':

            $validator->recordEmailOpen($token);
            

            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                $_SESSION['opened_xss_token'] = $token;
                $_SESSION['xss_ready_for_hijack'] = true;
                
                error_log("Dashboard email opened with XSS token: {$token} by admin: " . ($_SESSION['username'] ?? 'unknown'));
            }
            break;
            
        case 'store_token':

            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                $_SESSION['opened_xss_token'] = $token;
                $_SESSION['legitimate_xss_attack'] = true;
            }
            break;
            
        case 'trigger_hijack':

            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                $hijackToken = $validator->recordSessionHijack(
                    session_id(), 
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    $token
                );
                
                if ($hijackToken) {
                    $_SESSION['legitimate_xss_attack'] = true;
                    $_SESSION['validation_token'] = $hijackToken;
                    error_log("Manual XSS Attack Trigger: Token={$hijackToken}, Session=" . session_id());
                }
            }
            break;
            
        case 'auto_register':
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                $_SESSION['available_xss_token'] = $token;
                error_log("Auto-registered XSS token: {$token} for admin session");
            }
            break;
            
        case 'validate':
            $isValid = $validator->validateLegitimateAttack(session_id());
            header('Content-Type: application/json');
            echo json_encode([
                'valid' => $isValid,
                'token' => $token,
                'session_id' => session_id(),
                'has_opened_token' => isset($_SESSION['opened_xss_token']),
                'legitimate_attack' => isset($_SESSION['legitimate_xss_attack'])
            ]);
            exit;
            
        case 'mark_hijack':
            if (isset($_SESSION['opened_xss_token']) || $token) {
                $useToken = $token ?: $_SESSION['opened_xss_token'];
                $hijackToken = $validator->recordSessionHijack(
                    session_id(), 
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    $useToken
                );
                
                if ($hijackToken) {
                    $_SESSION['legitimate_xss_attack'] = true;
                    $_SESSION['validation_token'] = $hijackToken;
                    error_log("XSS Attack Success Marked: Token={$hijackToken}");
                }
            }
            break;
            
        case 'ping':
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'alive',
                'session_id' => session_id(),
                'timestamp' => time(),
                'has_xss_token' => isset($_SESSION['opened_xss_token'])
            ]);
            exit;
    }
}


if (isset($_GET['cookie']) && isset($_GET['source'])) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'source' => $_GET['source'] ?? 'unknown',
        'cookie' => substr($_GET['cookie'] ?? '', 0, 100),
        'user' => $_GET['user'] ?? 'unknown',
        'role' => $_GET['role'] ?? 'unknown',
        'session_id' => $_GET['sessionId'] ?? 'unknown',
        'token' => $token,
        'attack_vector' => $_GET['attack_vector'] ?? 'unknown'
    ];
    
    error_log("XSS Attack Data Received: " . json_encode($logData));
    

    if (isset($_GET['role']) && $_GET['role'] === 'admin' && $token) {
        $validator->recordSessionHijack(
            $_GET['sessionId'] ?? session_id(),
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $token
        );
    }
}


if (isset($_GET['debug'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'session_data' => [
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'opened_xss_token' => $_SESSION['opened_xss_token'] ?? null,
            'legitimate_xss_attack' => $_SESSION['legitimate_xss_attack'] ?? false
        ],
        'request_data' => $_GET,
        'validator_stats' => $validator->getAttackStatistics()
    ]);
    exit;
}


header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');


echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
?>