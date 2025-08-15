<?php
// classes/SecurityManager.php - Verbesserte Version mit Fixes

class SecurityManager
{
    /**
     * Bereinigt Input-Daten rekursiv
     */
    public static function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }

        if (is_string($input)) {
            // Entferne schädliche Zeichen und HTML
            $input = trim($input);
            $input = stripslashes($input);
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            return $input;
        }

        return $input;
    }

    /**
     * Validiert E-Mail-Adressen
     */
    public static function validateEmail($email)
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validiert deutsche Telefonnummern
     */
    public static function validatePhone($phone)
    {
        // Entferne alle Nicht-Ziffern außer +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        // Deutsche Telefonnummer-Patterns
        $patterns = [
            '/^(\+49|0049|49)[1-9]\d{7,11}$/',
            '/^0[1-9]\d{7,11}$/',
            '/^[1-9]\d{7,11}$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cleaned)) {
                return true;
            }
        }

        return strlen(preg_replace('/\D/', '', $phone)) >= 10;
    }

    /**
     * Generiert CSRF-Token - FIX: Verbesserte Session-Behandlung
     */
    public static function generateCSRFToken()
    {
        // Sicherstellen dass Session läuft
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // FIX: Verlängere Token-Lebensdauer und bessere Regenerierung
        if (
            !isset($_SESSION['csrf_token']) ||
            !isset($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time']) > 7200  // 2 Stunden statt 1
        ) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validiert CSRF-Token - FIX: Weniger strenge Validierung
     */
    public static function validateCSRFToken($token)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }

        // FIX: Längere Token-Ablaufzeit (2 Stunden)
        if ((time() - $_SESSION['csrf_token_time']) > 7200) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Rate Limiting - FIX: Weniger aggressive Limits für Development
     */
    public static function rateLimitCheck($identifier, $max_attempts = 20, $time_window = 300)
    {
        // FIX: Deaktiviere Rate Limiting für localhost
        if (self::isLocalhost()) {
            return true;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $key = 'rate_limit_' . hash('sha256', $identifier);

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }

        $now = time();
        $attempts = $_SESSION[$key];

        // Entferne alte Versuche außerhalb des Zeitfensters
        $attempts = array_filter($attempts, function ($timestamp) use ($now, $time_window) {
            return ($now - $timestamp) < $time_window;
        });

        if (count($attempts) >= $max_attempts) {
            self::logSecurityEvent('rate_limit_exceeded', [
                'identifier' => $identifier,
                'attempts' => count($attempts),
                'time_window' => $time_window
            ]);
            return false;
        }

        // Füge aktuellen Versuch hinzu
        $attempts[] = $now;
        $_SESSION[$key] = array_values($attempts);

        return true;
    }

    /**
     * FIX: Neue Funktion - Prüft ob localhost
     */
    private static function isLocalhost()
    {
        $localhost_ips = ['127.0.0.1', '::1', 'localhost'];
        $client_ip = self::getClientIP();
        $server_name = $_SERVER['SERVER_NAME'] ?? '';

        return in_array($client_ip, $localhost_ips) ||
            in_array($server_name, ['localhost', '127.0.0.1']);
    }

    /**
     * Validiert und bereinigt Adressen
     */
    public static function validateAddress($address)
    {
        $address = trim($address);

        if (strlen($address) < 10) {
            return false;
        }

        // Entferne gefährliche Zeichen
        $address = preg_replace('/[<>"\']/', '', $address);
        return $address;
    }

    /**
     * Loggt Sicherheitsereignisse - FIX: Verbesserte Fehlerbehandlung
     */
    public static function logSecurityEvent($event, $details = [])
    {
        try {
            $log_entry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => self::getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'event' => $event,
                'details' => $details
            ];

            $log_dir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/../logs';
            $log_file = $log_dir . '/security.log';

            // Erstelle logs Verzeichnis falls nicht vorhanden
            if (!file_exists($log_dir)) {
                mkdir($log_dir, 0755, true);
            }

            // Logge in Datei
            $log_line = json_encode($log_entry) . "\n";
            file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            // FIX: Verhindere Endlosschleifen bei Log-Fehlern
            error_log("Security logging failed: " . $e->getMessage());
        }
    }

    /**
     * Ermittelt echte Client-IP
     */
    public static function getClientIP()
    {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];

                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }

                $ip = trim($ip);

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Honeypot-Field für Spam-Schutz
     */
    public static function generateHoneypot()
    {
        return '<input type="text" name="website" style="display: none;" tabindex="-1" autocomplete="off">';
    }

    /**
     * Validiert Honeypot
     */
    public static function validateHoneypot($honeypot_value)
    {
        if (!empty($honeypot_value)) {
            self::logSecurityEvent('honeypot_triggered', [
                'value' => substr($honeypot_value, 0, 100)
            ]);
            return false;
        }
        return true;
    }

    /**
     * FIX: Neue Funktion - Reset Rate Limit für Development
     */
    public static function resetRateLimit($identifier = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($identifier) {
            $key = 'rate_limit_' . hash('sha256', $identifier);
            unset($_SESSION[$key]);
        } else {
            // Reset alle Rate Limits
            foreach ($_SESSION as $key => $value) {
                if (strpos($key, 'rate_limit_') === 0) {
                    unset($_SESSION[$key]);
                }
            }
        }
    }

    /**
     * FIX: Neue Funktion - Prüfe ob SQL-Injection-Versuch
     */
    public static function detectSQLInjection($input)
    {
        $sql_patterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bSELECT\b.*\bFROM\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
        ];

        if (is_array($input)) {
            foreach ($input as $value) {
                if (self::detectSQLInjection($value)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($sql_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                self::logSecurityEvent('sql_injection_attempt', [
                    'input' => substr($input, 0, 200),
                    'pattern' => $pattern
                ]);
                return true;
            }
        }

        return false;
    }
}
