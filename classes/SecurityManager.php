<?php
// classes/SecurityManager.php - Erweiterte Sicherheitsklasse mit allen benötigten Methoden
class SecurityManager
{
    private static $logFile = null;

    /**
     * Initialisierung
     */
    public static function init()
    {
        if (self::$logFile === null) {
            self::$logFile = defined('LOG_DIR') ? LOG_DIR . '/security.log' : __DIR__ . '/../logs/security.log';

            // Erstelle Log-Verzeichnis falls nicht vorhanden
            $logDir = dirname(self::$logFile);
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }
        }
    }

    /**
     * Session-Timeout prüfen
     */
    public static function checkSessionTimeout()
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;

        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $timeout) {
                session_destroy();
                session_start();
                $_SESSION = [];
                return false;
            }
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * CSRF-Token generieren
     */
    public static function generateCSRFToken()
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * CSRF-Token verifizieren
     */
    public static function verifyCSRFToken($token)
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Honeypot-Feld generieren
     */
    public static function generateHoneypot()
    {
        return '<input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">';
    }

    /**
     * Input validieren und säubern
     */
    public static function validateInput($input, $type = 'text')
    {
        if (empty($input)) {
            return false;
        }

        // Basis-Bereinigung
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        switch ($type) {
            case 'email':
                $input = filter_var($input, FILTER_SANITIZE_EMAIL);
                if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                    return false;
                }
                break;

            case 'phone':
                // Entferne alle Nicht-Zahlen außer + und Leerzeichen
                $input = preg_replace('/[^0-9+\s\-\(\)]/', '', $input);
                if (strlen($input) < 10) {
                    return false;
                }
                break;

            case 'name':
                // Nur Buchstaben, Leerzeichen, Bindestriche und deutsche Umlaute
                if (!preg_match("/^[a-zA-ZäöüÄÖÜß\s\-\.]+$/u", $input)) {
                    return false;
                }
                break;

            case 'number':
                if (!is_numeric($input)) {
                    return false;
                }
                break;

            case 'date':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
                    return false;
                }
                break;

            default:
                // Standard Text-Validierung
                break;
        }

        return $input;
    }

    /**
     * Adresse validieren
     */
    public static function validateAddress($address)
    {
        if (empty($address)) {
            return false;
        }

        $address = trim($address);
        $address = stripslashes($address);
        $address = htmlspecialchars($address, ENT_QUOTES, 'UTF-8');

        // Mindestlänge für eine gültige Adresse
        if (strlen($address) < 10) {
            return false;
        }

        // Maximal 300 Zeichen
        if (strlen($address) > 300) {
            return false;
        }

        // Prüfe auf verdächtige Zeichen
        if (preg_match('/[<>\"\'%;()&+]/', $address)) {
            return false;
        }

        return $address;
    }

    /**
     * Rate Limiting prüfen
     */
    public static function rateLimitCheck($identifier, $maxAttempts = 5, $timeWindow = 300)
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        $key = 'rate_limit_' . md5($identifier);
        $now = time();

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 1,
                'first_attempt' => $now
            ];
            return true;
        }

        // Zeit-Fenster abgelaufen?
        if ($now - $_SESSION[$key]['first_attempt'] > $timeWindow) {
            $_SESSION[$key] = [
                'attempts' => 1,
                'first_attempt' => $now
            ];
            return true;
        }

        // Zu viele Versuche?
        if ($_SESSION[$key]['attempts'] >= $maxAttempts) {
            self::logSecurityEvent('rate_limit_exceeded', [
                'identifier' => $identifier,
                'attempts' => $_SESSION[$key]['attempts']
            ]);
            return false;
        }

        $_SESSION[$key]['attempts']++;
        return true;
    }

    /**
     * IP-Adresse des Clients ermitteln
     */
    public static function getClientIP()
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);

                if (filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                ) !== false) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Sicherheitsereignis protokollieren
     */
    public static function logSecurityEvent($event, $details = [])
    {
        self::init();

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'details' => $details
        ];

        $logLine = json_encode($logEntry) . PHP_EOL;

        // In Datei schreiben
        file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);

        // Bei kritischen Events: Admin benachrichtigen
        $criticalEvents = ['sql_injection_attempt', 'xss_attempt', 'unauthorized_access', 'brute_force_attempt'];
        if (in_array($event, $criticalEvents)) {
            self::notifyAdmin($event, $logEntry);
        }
    }

    /**
     * Admin bei kritischen Events benachrichtigen
     */
    private static function notifyAdmin($event, $details)
    {
        if (defined('ADMIN_EMAIL') && defined('SMTP_FROM_EMAIL')) {
            $subject = "🚨 Sicherheitswarnung: $event";
            $message = "Ein kritisches Sicherheitsereignis wurde erkannt:\n\n";
            $message .= json_encode($details, JSON_PRETTY_PRINT);

            $headers = "From: " . SMTP_FROM_EMAIL . "\r\n";
            $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            @mail(ADMIN_EMAIL, $subject, $message, $headers);
        }
    }

    /**
     * SQL Injection verhindern
     */
    public static function preventSQLInjection($input)
    {
        $suspicious_patterns = [
            '/union.*select/i',
            '/select.*from/i',
            '/insert.*into/i',
            '/delete.*from/i',
            '/drop.*table/i',
            '/update.*set/i',
            '/exec\(/i',
            '/execute\(/i',
            '/script/i',
            '/javascript:/i',
            '/onerror=/i',
            '/onload=/i',
            '/onclick=/i'
        ];

        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                self::logSecurityEvent('sql_injection_attempt', [
                    'input' => substr($input, 0, 100),
                    'pattern' => $pattern
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * XSS verhindern
     */
    public static function preventXSS($input)
    {
        $dangerous_tags = ['<script', '<iframe', '<object', '<embed', '<link', '<meta'];

        foreach ($dangerous_tags as $tag) {
            if (stripos($input, $tag) !== false) {
                self::logSecurityEvent('xss_attempt', [
                    'input' => substr($input, 0, 100),
                    'tag' => $tag
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Datei-Upload validieren
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880)
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Dateigröße prüfen
        if ($file['size'] > $maxSize) {
            return false;
        }

        // MIME-Type prüfen
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                self::logSecurityEvent('invalid_file_upload', [
                    'mime_type' => $mimeType,
                    'file_name' => $file['name']
                ]);
                return false;
            }
        }

        // Dateiname säubern
        $fileName = basename($file['name']);
        $fileName = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', $fileName);

        // Gefährliche Erweiterungen blockieren
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'exe', 'sh', 'bat'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($ext, $dangerousExtensions)) {
            self::logSecurityEvent('dangerous_file_upload', [
                'extension' => $ext,
                'file_name' => $file['name']
            ]);
            return false;
        }

        return $fileName;
    }

    /**
     * Passwort-Stärke prüfen
     */
    public static function checkPasswordStrength($password)
    {
        $strength = 0;

        // Mindestlänge
        if (strlen($password) < 8) {
            return ['strong' => false, 'message' => 'Passwort muss mindestens 8 Zeichen lang sein'];
        }

        // Verschiedene Zeichentypen prüfen
        if (preg_match('/[a-z]/', $password)) $strength++;
        if (preg_match('/[A-Z]/', $password)) $strength++;
        if (preg_match('/[0-9]/', $password)) $strength++;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $strength++;

        if ($strength < 3) {
            return [
                'strong' => false,
                'message' => 'Passwort sollte Groß- und Kleinbuchstaben, Zahlen und Sonderzeichen enthalten'
            ];
        }

        return ['strong' => true, 'strength' => $strength];
    }

    /**
     * Admin-Login validieren
     */
    public static function validateAdminLogin($password)
    {
        if (!defined('ADMIN_PASSWORD_HASH')) {
            return false;
        }

        $isValid = password_verify($password, ADMIN_PASSWORD_HASH);

        if ($isValid) {
            self::logSecurityEvent('admin_login_success', []);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_login_time'] = time();
        } else {
            self::logSecurityEvent('admin_login_failed', []);
        }

        return $isValid;
    }

    /**
     * Alias für verifyCSRFToken für Rückwärtskompatibilität
     */
    public static function validateCSRFToken($token)
    {
        return self::verifyCSRFToken($token);
    }

    /**
     * E-Mail validieren
     */
    public static function validateEmail($email)
    {
        $email = trim($email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Zusätzliche Prüfungen
        if (strlen($email) > 255) {
            return false;
        }

        // Prüfe auf verdächtige Muster
        if (preg_match('/[<>\"\'%;()&+]/', $email)) {
            return false;
        }

        return $email;
    }

    /**
     * Telefonnummer validieren
     */
    public static function validatePhone($phone)
    {
        // Entferne alle Nicht-Zahlen außer + und Leerzeichen
        $phone = preg_replace('/[^0-9+\s\-\(\)]/', '', $phone);

        // Mindestlänge prüfen
        if (strlen(preg_replace('/[^0-9]/', '', $phone)) < 10) {
            return false;
        }

        // Maximal 20 Zeichen
        if (strlen($phone) > 20) {
            return false;
        }

        return $phone;
    }

    /**
     * Rate Limit zurücksetzen
     */
    public static function resetRateLimit($identifier)
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        $key = 'rate_limit_' . md5($identifier);

        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);

            self::logSecurityEvent('rate_limit_reset', [
                'identifier' => $identifier
            ]);

            return true;
        }

        return false;
    }

    /**
     * Prüfe Admin-Session
     */
    public static function isAdminLoggedIn()
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            return false;
        }

        // Session-Timeout prüfen
        $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
        if (isset($_SESSION['admin_login_time'])) {
            if (time() - $_SESSION['admin_login_time'] > $timeout) {
                unset($_SESSION['admin_logged_in']);
                unset($_SESSION['admin_login_time']);
                return false;
            }
        }

        // Session erneuern
        $_SESSION['admin_login_time'] = time();

        return true;
    }
}
