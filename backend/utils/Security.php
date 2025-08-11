<?php

/**
 * Mobile Car Service - Security Utilities
 * Sicherheitsfunktionen für das Backend
 */

require_once __DIR__ . '/../config.php';

class Security
{
    private static $instance = null;
    private $rateLimits = [];
    private $blockedIps = [];

    private function __construct()
    {
        $this->loadBlockedIps();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Rate Limiting prüfen
     */
    public function checkRateLimit($identifier, $maxRequests = 60, $timeWindow = 3600)
    {
        $key = $this->getRateLimitKey($identifier);
        $now = time();

        // Alte Einträge bereinigen
        $this->cleanupRateLimit($key, $now - $timeWindow);

        // Aktuelle Anfragen zählen
        if (!isset($this->rateLimits[$key])) {
            $this->rateLimits[$key] = [];
        }

        $requestCount = count($this->rateLimits[$key]);

        if ($requestCount >= $maxRequests) {
            $this->logSecurityEvent('rate_limit_exceeded', [
                'identifier' => $identifier,
                'requests' => $requestCount,
                'limit' => $maxRequests,
                'window' => $timeWindow
            ]);

            throw new SecurityException('Rate limit exceeded', 429);
        }

        // Neue Anfrage hinzufügen
        $this->rateLimits[$key][] = $now;

        return true;
    }

    /**
     * IP-Adresse blockieren
     */
    public function blockIp($ip, $reason = 'Security violation', $duration = 3600)
    {
        $this->blockedIps[$ip] = [
            'blocked_at' => time(),
            'expires_at' => time() + $duration,
            'reason' => $reason
        ];

        $this->saveBlockedIps();
        $this->logSecurityEvent('ip_blocked', ['ip' => $ip, 'reason' => $reason]);
    }

    /**
     * IP-Blockierung prüfen
     */
    public function isIpBlocked($ip)
    {
        if (!isset($this->blockedIps[$ip])) {
            return false;
        }

        $block = $this->blockedIps[$ip];

        // Prüfen ob Blockierung abgelaufen ist
        if (time() > $block['expires_at']) {
            unset($this->blockedIps[$ip]);
            $this->saveBlockedIps();
            return false;
        }

        return true;
    }

    /**
     * CSRF-Token generieren
     */
    public static function generateCsrfToken()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;

        return $token;
    }

    /**
     * CSRF-Token validieren
     */
    public static function validateCsrfToken($token)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * XSS-Schutz
     */
    public static function sanitizeOutput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeOutput'], $data);
        }

        if (is_string($data)) {
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $data;
    }

    /**
     * SQL-Injection Schutz (zusätzlich zu prepared statements)
     */
    public static function detectSqlInjection($input)
    {
        $suspiciousPatterns = [
            '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b)/i',
            '/(\bOR\b\s+\d+\s*=\s*\d+|\bAND\b\s+\d+\s*=\s*\d+)/i',
            '/(\'|\"|;|--|\/\*|\*\/)/i',
            '/(\bEXEC\b|\bEXECUTE\b|\bSP_\b)/i'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Path Traversal Schutz
     */
    public static function validatePath($path)
    {
        // Gefährliche Zeichen und Patterns
        $dangerous = ['../', '.\\', '%2e%2e', '%252e%252e', '..%c0%af', '..%c1%9c'];

        foreach ($dangerous as $pattern) {
            if (strpos(strtolower($path), $pattern) !== false) {
                return false;
            }
        }

        // Absoluten Pfad normalisieren
        $realPath = realpath($path);
        $basePath = realpath(__DIR__ . '/../');

        // Prüfen ob Pfad innerhalb des erlaubten Bereichs liegt
        return $realPath && strpos($realPath, $basePath) === 0;
    }

    /**
     * File Upload Sicherheit
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = null)
    {
        $errors = [];

        // Grundlegende Validierung
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload-Fehler: ' . $file['error'];
            return $errors;
        }

        // Dateigröße prüfen
        $maxSize = $maxSize ?: MAX_FILE_SIZE;
        if ($file['size'] > $maxSize) {
            $errors[] = 'Datei zu groß (max. ' . ($maxSize / 1024 / 1024) . 'MB)';
        }

        // MIME-Type prüfen
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
            $errors[] = 'Dateityp nicht erlaubt: ' . $mimeType;
        }

        // Dateiinhalt prüfen (Double Extension Attack)
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ALLOWED_IMAGE_TYPES;

        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = 'Dateierweiterung nicht erlaubt: ' . $extension;
        }

        // Executable-Erkennung
        if (self::isExecutableFile($file['tmp_name'])) {
            $errors[] = 'Ausführbare Dateien sind nicht erlaubt';
        }

        // Dateiname sanitisieren
        $sanitizedName = self::sanitizeFilename($file['name']);
        if ($sanitizedName !== $file['name']) {
            $errors[] = 'Dateiname enthält ungültige Zeichen';
        }

        return $errors;
    }

    /**
     * Prüfen ob Datei ausführbar ist
     */
    private static function isExecutableFile($filePath)
    {
        $executableSignatures = [
            "\x4D\x5A", // PE (Windows .exe)
            "\x7F\x45\x4C\x46", // ELF (Linux)
            "\xFE\xED\xFA\xCE", // Mach-O (macOS)
            "\xFE\xED\xFA\xCF", // Mach-O (macOS 64-bit)
            "#!/", // Shell script
            "<?php" // PHP script
        ];

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        foreach ($executableSignatures as $signature) {
            if (strpos($header, $signature) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dateiname sanitisieren
     */
    public static function sanitizeFilename($filename)
    {
        // Gefährliche Zeichen entfernen
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Mehrfache Punkte entfernen (außer vor Erweiterung)
        $filename = preg_replace('/\.{2,}/', '.', $filename);

        // Leer oder zu lang
        if (empty($filename) || strlen($filename) > 255) {
            $filename = 'file_' . time();
        }

        return $filename;
    }

    /**
     * Passwort hashen
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 Iterationen
            'threads' => 3          // 3 Threads
        ]);
    }

    /**
     * Passwort verifizieren
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Sichere Zufallsstring generieren
     */
    public static function generateRandomString($length = 32)
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * API-Key generieren
     */
    public static function generateApiKey($prefix = 'mcs')
    {
        return $prefix . '_' . self::generateRandomString(40);
    }

    /**
     * JWT-ähnlichen Token generieren (vereinfacht)
     */
    public static function generateToken($payload, $secret = null)
    {
        $secret = $secret ?: config('app.secret', 'default_secret');

        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }

    /**
     * Token verifizieren
     */
    public static function verifyToken($token, $secret = null)
    {
        $secret = $secret ?: config('app.secret', 'default_secret');

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;

        // Signature prüfen
        $validSignature = hash_hmac('sha256', $header . "." . $payload, $secret, true);
        $validBase64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($validSignature));

        if (!hash_equals($signature, $validBase64Signature)) {
            return false;
        }

        // Payload dekodieren
        $payloadData = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);

        // Expiry prüfen
        if (isset($payloadData['exp']) && time() > $payloadData['exp']) {
            return false;
        }

        return $payloadData;
    }

    /**
     * Input gegen verschiedene Angriffe prüfen
     */
    public static function validateInput($input, $context = 'general')
    {
        $issues = [];

        // SQL Injection
        if (self::detectSqlInjection($input)) {
            $issues[] = 'sql_injection';
        }

        // XSS
        if (self::detectXss($input)) {
            $issues[] = 'xss';
        }

        // Path Traversal
        if (strpos($input, '../') !== false || strpos($input, '..\\') !== false) {
            $issues[] = 'path_traversal';
        }

        // Command Injection
        if (self::detectCommandInjection($input)) {
            $issues[] = 'command_injection';
        }

        // Kontext-spezifische Validierung
        switch ($context) {
            case 'email':
                if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                    $issues[] = 'invalid_email';
                }
                break;

            case 'url':
                if (!filter_var($input, FILTER_VALIDATE_URL)) {
                    $issues[] = 'invalid_url';
                }
                break;

            case 'filename':
                if (!preg_match('/^[a-zA-Z0-9._-]+$/', $input)) {
                    $issues[] = 'invalid_filename';
                }
                break;
        }

        return $issues;
    }

    /**
     * XSS-Erkennung
     */
    private static function detectXss($input)
    {
        $xssPatterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<object[^>]*>.*?<\/object>/is',
            '/<embed[^>]*>/i',
            '/<applet[^>]*>.*?<\/applet>/is'
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Command Injection Erkennung
     */
    private static function detectCommandInjection($input)
    {
        $cmdPatterns = [
            '/[;&|`$(){}[\]<>]/',
            '/\b(cat|ls|dir|type|copy|move|del|rm|mkdir|rmdir)\b/i',
            '/\b(wget|curl|nc|netcat|telnet|ssh)\b/i'
        ];

        foreach ($cmdPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Session-Sicherheit
     */
    public static function secureSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Sichere Session-Parameter
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');

            // Session-Name ändern
            session_name('MCS_SESSION');

            session_start();

            // Session-Fixation verhindern
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
            }

            // Session-Timeout
            if (
                isset($_SESSION['last_activity']) &&
                time() - $_SESSION['last_activity'] > SESSION_TIMEOUT
            ) {
                session_unset();
                session_destroy();
                session_start();
            }

            $_SESSION['last_activity'] = time();
        }
    }

    /**
     * HTTP Security Headers setzen
     */
    public static function setSecurityHeaders()
    {
        // XSS-Protection
        header('X-XSS-Protection: 1; mode=block');

        // Content-Type-Sniffing verhindern
        header('X-Content-Type-Options: nosniff');

        // Clickjacking verhindern
        header('X-Frame-Options: SAMEORIGIN');

        // HTTPS erzwingen
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Content Security Policy (anpassen nach Bedarf)
        $csp = "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' cdnjs.cloudflare.com; " .
            "style-src 'self' 'unsafe-inline' cdnjs.cloudflare.com; " .
            "font-src 'self' cdnjs.cloudflare.com; " .
            "img-src 'self' data:; " .
            "connect-src 'self'";

        header("Content-Security-Policy: $csp");
    }

    /**
     * Rate Limit Key generieren
     */
    private function getRateLimitKey($identifier)
    {
        return 'rate_limit_' . md5($identifier);
    }

    /**
     * Rate Limit bereinigen
     */
    private function cleanupRateLimit($key, $cutoff)
    {
        if (isset($this->rateLimits[$key])) {
            $this->rateLimits[$key] = array_filter(
                $this->rateLimits[$key],
                function ($timestamp) use ($cutoff) {
                    return $timestamp > $cutoff;
                }
            );
        }
    }

    /**
     * Blockierte IPs laden
     */
    private function loadBlockedIps()
    {
        $file = LOGS_PATH . 'blocked_ips.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $this->blockedIps = $data ?: [];
        }
    }

    /**
     * Blockierte IPs speichern
     */
    private function saveBlockedIps()
    {
        $file = LOGS_PATH . 'blocked_ips.json';
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($file, json_encode($this->blockedIps, JSON_PRETTY_PRINT));
    }

    /**
     * Security Event loggen
     */
    private function logSecurityEvent($event, $details = [])
    {
        $logData = [
            'timestamp' => date('c'),
            'event' => $event,
            'ip' => getClientIp(),
            'user_agent' => getUserAgent(),
            'details' => $details
        ];

        $logFile = LOGS_PATH . 'security.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);

        // Bei kritischen Events auch in error.log
        if (in_array($event, ['sql_injection', 'xss_attempt', 'rate_limit_exceeded'])) {
            error_log("SECURITY: $event - " . json_encode($details));
        }
    }

    /**
     * Honeypot-Feld prüfen
     */
    public static function checkHoneypot($fieldName = 'website', $data = null)
    {
        $data = $data ?: $_POST;

        // Honeypot-Feld sollte leer sein
        if (isset($data[$fieldName]) && !empty($data[$fieldName])) {
            throw new SecurityException('Bot detected (honeypot)', 403);
        }

        return true;
    }

    /**
     * Zeitbasierte CSRF-Protection
     */
    public static function checkSubmissionTime($minTime = 3, $maxTime = 3600)
    {
        if (!isset($_POST['form_timestamp'])) {
            throw new SecurityException('Missing form timestamp', 400);
        }

        $submissionTime = time() - (int)$_POST['form_timestamp'];

        if ($submissionTime < $minTime) {
            throw new SecurityException('Form submitted too quickly', 429);
        }

        if ($submissionTime > $maxTime) {
            throw new SecurityException('Form expired', 400);
        }

        return true;
    }
}

/**
 * SecurityException für Sicherheitsverletzungen
 */
class SecurityException extends Exception
{
    private $httpCode;

    public function __construct($message, $httpCode = 400)
    {
        parent::__construct($message);
        $this->httpCode = $httpCode;
    }

    public function getHttpCode()
    {
        return $this->httpCode;
    }
}

/**
 * Helper-Funktionen
 */

/**
 * Security-Instanz abrufen
 */
function security()
{
    return Security::getInstance();
}

/**
 * Input validieren
 */
function secureInput($input, $context = 'general')
{
    $issues = Security::validateInput($input, $context);

    if (!empty($issues)) {
        throw new SecurityException('Input validation failed: ' . implode(', ', $issues));
    }

    return true;
}

/**
 * CSRF-Token HTML generieren
 */
function csrfField()
{
    $token = Security::generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Honeypot-Feld HTML generieren
 */
function honeypotField($name = 'website')
{
    return '<input type="text" name="' . htmlspecialchars($name) . '" style="position:absolute;left:-9999px;" tabindex="-1" autocomplete="off">';
}

/**
 * Zeitstempel-Feld HTML generieren
 */
function timestampField()
{
    return '<input type="hidden" name="form_timestamp" value="' . time() . '">';
}
