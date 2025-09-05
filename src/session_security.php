<?php


function markSessionAsLegitimate() {

    $_SESSION['session_initialized'] = true;
    $_SESSION['login_method'] = 'direct';
    $_SESSION['login_time'] = time();
    $_SESSION['last_request_time'] = time();


    $_SESSION['browser_fingerprint'] = hash(
        'md5',
        ($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['REMOTE_ADDR'] ?? '')
    );
    $_SESSION['session_pattern'] = substr(session_id(), 0, 8);
}

function detectRealTimeSessionHijacking() {
    if (empty($_SESSION['user_id'])) {
        return ['hijacked'=>false, 'reason'=>'no_session', 'confidence'=>0];
    }
    

    if (($_SESSION['role'] ?? '') !== 'admin') {
        return ['hijacked'=>false, 'reason'=>'non_admin', 'confidence'=>0];
    }

    $ind = [];
    $now = time();

    $sess_bind   = $_SESSION['bind_token'] ?? null;
    $cookie_bind = $_COOKIE['BIND'] ?? null;
    if ($sess_bind && $cookie_bind !== $sess_bind) {
        $ind[] = 'bind_token_mismatch';
    }


    $fp = hash('md5', ($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (!empty($_SESSION['browser_fingerprint']) && $_SESSION['browser_fingerprint'] !== $fp) {
        $ind[] = 'fingerprint_mismatch';
    }

    $pat = $_SESSION['session_pattern'] ?? '';
    $cur = substr(session_id(), 0, 8);
    if ($pat && $pat !== $cur) {
        $ind[] = 'session_id_changed';
    } elseif (!$pat) {
        $_SESSION['session_pattern'] = $cur;
    }


    $age = $now - ($_SESSION['login_time'] ?? $now);
    if ($age < 5 && (($_SESSION['login_method'] ?? '') !== 'direct')) {
        $ind[] = 'very_new_session';
    }


    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri,'dashboard.php') !== false
        && strpos($ref,'login.php') === false
        && strpos($ref,'dashboard.php') === false
        && $age < 30
        && (($_SESSION['login_method'] ?? '') !== 'direct')) {
        $ind[] = 'direct_dashboard_access';
    }


    $conf = 0;
    foreach ($ind as $x) {
        if (in_array($x, ['bind_token_mismatch'])) { $conf += 70; }
        elseif (in_array($x, ['fingerprint_mismatch','session_id_changed'])) { $conf += 30; }
        elseif (in_array($x, ['very_new_session','direct_dashboard_access'])) { $conf += 15; }
    }
    $hijacked = $conf >= 50;

    return [
        'hijacked'   => $hijacked,
        'confidence' => $conf,
        'indicators' => $ind,
        'reason'     => implode(', ', $ind),
    ];
}

function updateSessionSecurity() {
    $d = detectRealTimeSessionHijacking();


    if (($_SESSION['role'] ?? '') === 'admin' && $d['hijacked']) {
        $_SESSION['login_method'] = 'hijacked';
        $_SESSION['hijack_reason'] = $d['reason'];
        $_SESSION['hijack_confidence'] = $d['confidence'];
        $_SESSION['hijack_detected_at'] = time();
        

        if (file_exists('xss_validation.php')) {
            require_once 'xss_validation.php';
            global $pdo;
            if (isset($pdo)) {
                $validator = new XSSRouteValidator($pdo);
                $token = $validator->recordSessionHijack(
                    session_id(), 
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                );
                
                if ($token) {
                    error_log("Legitimate XSS Attack Completed via Session Hijacking: Token={$token}, Session=" . session_id());
                }
            }
        }
        
        error_log("Session Hijacking Detected: user="
            . ($_SESSION['username'] ?? 'unknown')
            . " reason=".$d['reason']." conf=".$d['confidence']."%");
    }

    $_SESSION['last_request_time'] = time();
    return $d;
}