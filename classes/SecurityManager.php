<?php
// classes/SecurityManager.php - Umfassende Sicherheitsfunktionen

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

            // Entferne potentiell schädliche Patterns
            $input = preg_replace('/[<>"\']/', '', $input);

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

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Zusätzliche Checks
        $domain = substr(strrchr($email, "@"), 1);

        // Prüfe ob Domain existiert
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            return false;
        }

        // Blocke bekannte Wegwerf-E-Mail-Domains
        $disposable_domains = [
            '10minutemail.com',
            'guerrillamail.com',
            'mailinator.com',
            'yopmail.com',
            'tempmail.org',
            'throwaway.email'
        ];

        if (in_array(strtolower($domain), $disposable_domains)) {
            return false;
        }

        return true;
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
            '/^(\+49|0049|49)[1-9]\d{7,11}$/',  // Deutsche Mobilnummern
            '/^0[1-9]\d{7,11}$/',               // Deutsche Festnetz (mit 0)
            '/^[1-9]\d{7,11}$/',                // Deutsche Nummer ohne Vorwahl
            '/^(\+49|0049|49)\s?\(0\)\d{2,}\s?\d+$/', // Format mit Klammern
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cleaned)) {
                return true;
            }
        }

        // Fallback: Mindestens 10 Ziffern
        return strlen(preg_replace('/\D/', '', $phone)) >= 10;
    }

    /**
     * Generiert CSRF-Token
     */
    public static function generateCSRFToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (
            !isset($_SESSION['csrf_token']) ||
            !isset($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time']) > 3600
        ) {

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validiert CSRF-Token
     */
    public static function validateCSRFToken($token)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }

        // Token-Ablaufzeit prüfen (1 Stunde)
        if ((time() - $_SESSION['csrf_token_time']) > 3600) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Rate Limiting Implementation
     */
    public static function rateLimitCheck($identifier, $max_attempts = 5, $time_window = 300)
    {
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
     * Validiert und bereinigt Adressen
     */
    public static function validateAddress($address)
    {
        $address = trim($address);

        if (strlen($address) < 10) {
            return false;
        }

        // Grundlegende Validierung für deutsche Adressen
        if (!preg_match('/\d{5}/', $address)) { // PLZ erforderlich
            return false;
        }

        // Entferne gefährliche Zeichen
        $address = preg_replace('/[<>"\']/', '', $address);

        return $address;
    }

    /**
     * Loggt Sicherheitsereignisse
     */
    public static function logSecurityEvent($event, $details = [])
    {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id(),
            'event' => $event,
            'details' => $details,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
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

        // Bei kritischen Events auch in PHP Error Log
        if (in_array($event, ['csrf_token_mismatch', 'rate_limit_exceeded', 'sql_injection_attempt'])) {
            error_log("Security Event: $event - IP: " . $log_entry['ip']);
        }
    }

    /**
     * Ermittelt echte Client-IP
     */
    public static function getClientIP()
    {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load Balancer
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

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Prüft auf SQL-Injection-Versuche
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
            '/(\'|\")(;|--|\#)/i',
            '/(\bOR\b.*=.*)/i',
            '/(\bAND\b.*=.*)/i'
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

    /**
     * Generiert sichere Session-ID
     */
    public static function regenerateSession()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);

            // Update CSRF token
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            self::generateCSRFToken();
        }
    }

    /**
     * Validiert Session
     */
    public static function validateSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }

        // Prüfe Session-Timeout
        if (isset($_SESSION['last_activity'])) {
            $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
            if ((time() - $_SESSION['last_activity']) > $timeout) {
                session_destroy();
                return false;
            }
        }

        $_SESSION['last_activity'] = time();

        // Prüfe IP-Wechsel (optional)
        if (isset($_SESSION['ip_address'])) {
            if ($_SESSION['ip_address'] !== self::getClientIP()) {
                self::logSecurityEvent('session_ip_change', [
                    'old_ip' => $_SESSION['ip_address'],
                    'new_ip' => self::getClientIP()
                ]);
                // Optional: Session zerstören bei IP-Wechsel
                // session_destroy();
                // return false;
            }
        } else {
            $_SESSION['ip_address'] = self::getClientIP();
        }

        return true;
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
}
