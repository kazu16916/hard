<?php


class XSSRouteValidator {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initValidationTable();
    }
    
    private function initValidationTable() {
        try {

            $sql = "CREATE TABLE IF NOT EXISTS xss_validation_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                validation_token VARCHAR(64) UNIQUE,
                email_id INT,
                contact_form_submitted BOOLEAN DEFAULT FALSE,
                admin_email_opened BOOLEAN DEFAULT FALSE,
                xss_payload_detected BOOLEAN DEFAULT FALSE,
                session_hijacked BOOLEAN DEFAULT FALSE,
                hijack_timestamp TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                is_legitimate_attack BOOLEAN DEFAULT FALSE,
                hijacked_session_id VARCHAR(128),
                original_session_id VARCHAR(128),
                admin_user_agent TEXT,
                hijack_user_agent TEXT,
                attack_source VARCHAR(100) DEFAULT 'unknown'
            )";
            $this->pdo->exec($sql);
        } catch(Exception $e) {
            error_log("XSS Validation Table Creation Error: " . $e->getMessage());
        }
    }
    

    public function generateValidationToken($emailId, $hasXSSPayload = false) {
        $token = bin2hex(random_bytes(32));
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO xss_validation_log 
                (validation_token, email_id, contact_form_submitted, xss_payload_detected, original_session_id) 
                VALUES (?, ?, TRUE, ?, ?)
            ");
            $stmt->execute([$token, $emailId, $hasXSSPayload, session_id()]);
            

            $_SESSION['xss_validation_token'] = $token;
            
            error_log("XSS Token Generated: {$token} for email {$emailId}");
            
            return $token;
        } catch(Exception $e) {
            error_log("Token Generation Error: " . $e->getMessage());
            return null;
        }
    }
    

    public function recordEmailOpen($token) {
        if (!$token) return false;
        
        try {
            $stmt = $this->pdo->prepare("
                UPDATE xss_validation_log 
                SET admin_email_opened = TRUE,
                    admin_user_agent = ?,
                    attack_source = 'dashboard_email'
                WHERE validation_token = ?
            ");
            $result = $stmt->execute([
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $token
            ]);
            

            if ($result && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                $_SESSION['opened_xss_token'] = $token;
                $_SESSION['legitimate_xss_attack'] = true;
                
                error_log("Email opened by admin with token: {$token}, Session: " . session_id());
            }
            
            return $result;
        } catch(Exception $e) {
            error_log("Email Open Recording Error: " . $e->getMessage());
            return false;
        }
    }
    

    public function recordSessionHijack($sessionId, $userAgent = '', $token = null) {
        try {

            if (!$token) {
                $token = $_SESSION['opened_xss_token'] ?? $_SESSION['xss_validation_token'] ?? null;
            }
            

            if (!$token) {
                $stmt = $this->pdo->prepare("
                    SELECT validation_token FROM xss_validation_log 
                    WHERE admin_email_opened = TRUE 
                    AND session_hijacked = FALSE 
                    AND xss_payload_detected = TRUE
                    AND created_at > DATE_SUB(NOW(), INTERVAL 60 MINUTE)
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute();
                $result = $stmt->fetch();
                $token = $result ? $result['validation_token'] : null;
            }
            
            if ($token) {
                $updateStmt = $this->pdo->prepare("
                    UPDATE xss_validation_log 
                    SET session_hijacked = TRUE, 
                        hijack_timestamp = NOW(),
                        completed_at = NOW(),
                        is_legitimate_attack = TRUE,
                        hijacked_session_id = ?,
                        hijack_user_agent = ?
                    WHERE validation_token = ?
                ");
                $result = $updateStmt->execute([$sessionId, $userAgent, $token]);
                
                if ($result) {

                    $_SESSION['legitimate_xss_attack'] = true;
                    $_SESSION['validation_token'] = $token;
                    $_SESSION['attack_completion_time'] = time();
                    
                    error_log("Session Hijack Recorded: Token={$token}, Session={$sessionId}");
                    
                    return $token;
                }
            } else {
                error_log("No valid token found for session hijack recording");
            }
            
            return null;
        } catch(Exception $e) {
            error_log("Session Hijack Recording Error: " . $e->getMessage());
            return null;
        }
    }
    

    public function validateLegitimateAttack($sessionId = null) {
        try {

            if (isset($_SESSION['legitimate_xss_attack']) && $_SESSION['legitimate_xss_attack'] === true) {
                error_log("Legitimate attack validated via session flag");
                return true;
            }
            

            if (isset($_SESSION['opened_xss_token']) || isset($_SESSION['validation_token'])) {
                $token = $_SESSION['opened_xss_token'] ?? $_SESSION['validation_token'];
                if ($token) {
                    $stmt = $this->pdo->prepare("
                        SELECT * FROM xss_validation_log 
                        WHERE validation_token = ? 
                        AND admin_email_opened = TRUE
                        AND xss_payload_detected = TRUE
                        AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) <= 120
                    ");
                    $stmt->execute([$token]);
                    
                    if ($stmt->fetch()) {
                        $_SESSION['legitimate_xss_attack'] = true;
                        error_log("Legitimate attack validated via token: {$token}");
                        return true;
                    }
                }
            }
            

            if ($sessionId) {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM xss_validation_log 
                    WHERE hijacked_session_id = ? 
                    AND is_legitimate_attack = TRUE
                    AND TIMESTAMPDIFF(MINUTE, completed_at, NOW()) <= 120
                    ORDER BY completed_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$sessionId]);
                
                if ($stmt->fetch()) {
                    $_SESSION['legitimate_xss_attack'] = true;
                    error_log("Legitimate attack validated via session ID: {$sessionId}");
                    return true;
                }
            }
            

            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM xss_validation_log 
                    WHERE is_legitimate_attack = TRUE
                    AND TIMESTAMPDIFF(MINUTE, completed_at, NOW()) <= 120
                    ORDER BY completed_at DESC
                    LIMIT 1
                ");
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    $_SESSION['legitimate_xss_attack'] = true;
                    error_log("Legitimate attack validated via recent admin attack");
                    return true;
                }
            }
            
            return false;
        } catch(Exception $e) {
            error_log("Attack Validation Error: " . $e->getMessage());
            return false;
        }
    }
    

    public function getAttackStatistics() {
        try {
            $stats = [];
            
   
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM xss_validation_log");
            $stats['total_attempts'] = $stmt->fetch()['total'];
            
   
            $stmt = $this->pdo->query("SELECT COUNT(*) as legitimate FROM xss_validation_log WHERE is_legitimate_attack = TRUE");
            $stats['legitimate_attacks'] = $stmt->fetch()['legitimate'];
            

            $stmt = $this->pdo->query("SELECT COUNT(*) as xss_sent FROM xss_validation_log WHERE xss_payload_detected = TRUE");
            $stats['xss_payloads_sent'] = $stmt->fetch()['xss_sent'];
            

            $stmt = $this->pdo->query("SELECT COUNT(*) as emails_opened FROM xss_validation_log WHERE admin_email_opened = TRUE");
            $stats['emails_opened'] = $stmt->fetch()['emails_opened'];
            
            return $stats;
        } catch(Exception $e) {
            error_log("Statistics Error: " . $e->getMessage());
            return ['total_attempts' => 0, 'legitimate_attacks' => 0, 'xss_payloads_sent' => 0, 'emails_opened' => 0];
        }
    }
    

    public function linkTokenToSession($token) {
        try {
            if ($token && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                $_SESSION['opened_xss_token'] = $token;
                $_SESSION['legitimate_xss_attack'] = true;
                

                $stmt = $this->pdo->prepare("
                    UPDATE xss_validation_log 
                    SET admin_email_opened = TRUE,
                        is_legitimate_attack = TRUE,
                        completed_at = NOW()
                    WHERE validation_token = ?
                ");
                $stmt->execute([$token]);
                
                error_log("Token manually linked to session: {$token}");
                return true;
            }
            return false;
        } catch(Exception $e) {
            error_log("Token Linking Error: " . $e->getMessage());
            return false;
        }
    }
    

    public function forceValidateAttack($sessionId = null) {
        try {
            $_SESSION['legitimate_xss_attack'] = true;
            $_SESSION['attack_completion_time'] = time();
            

            $stmt = $this->pdo->prepare("
                SELECT validation_token FROM xss_validation_log 
                WHERE xss_payload_detected = TRUE
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result) {
                $token = $result['validation_token'];
                $_SESSION['validation_token'] = $token;
                
                $updateStmt = $this->pdo->prepare("
                    UPDATE xss_validation_log 
                    SET admin_email_opened = TRUE,
                        session_hijacked = TRUE,
                        is_legitimate_attack = TRUE,
                        completed_at = NOW(),
                        hijacked_session_id = ?
                    WHERE validation_token = ?
                ");
                $updateStmt->execute([$sessionId ?: session_id(), $token]);
                
                error_log("Attack forcefully validated: {$token}");
                return true;
            }
            
            return false;
        } catch(Exception $e) {
            error_log("Force Validation Error: " . $e->getMessage());
            return false;
        }
    }
    

    public function getDebugInfo() {
        try {
            $debug = [
                'session_tokens' => [
                    'xss_validation_token' => $_SESSION['xss_validation_token'] ?? 'none',
                    'opened_xss_token' => $_SESSION['opened_xss_token'] ?? 'none',
                    'validation_token' => $_SESSION['validation_token'] ?? 'none'
                ],
                'session_flags' => [
                    'legitimate_xss_attack' => $_SESSION['legitimate_xss_attack'] ?? false,
                    'role' => $_SESSION['role'] ?? 'none',
                    'username' => $_SESSION['username'] ?? 'none'
                ]
            ];
            

            $stmt = $this->pdo->prepare("
                SELECT * FROM xss_validation_log 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $debug['recent_attacks'] = $stmt->fetchAll();
            
            return $debug;
        } catch(Exception $e) {
            error_log("Debug Info Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}