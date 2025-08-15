<?php
class SecurityManager
{

    public static function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }

        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validatePhone($phone)
    {
        // Remove all non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        // Check if it's a valid phone number format
        return preg_match('/^(\+49|0049|49|0)[1-9]\d{7,11}$/', $cleaned);
    }

    public static function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCSRFToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function rateLimitCheck($identifier, $max_attempts = 5, $time_window = 300)
    {
        $key = 'rate_limit_' . $identifier;

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }

        $now = time();
        $attempts = $_SESSION[$key];

        // Remove old attempts outside time window
        $attempts = array_filter($attempts, function ($timestamp) use ($now, $time_window) {
            return ($now - $timestamp) < $time_window;
        });

        if (count($attempts) >= $max_attempts) {
            return false; // Rate limit exceeded
        }

        // Add current attempt
        $attempts[] = $now;
        $_SESSION[$key] = $attempts;

        return true;
    }

    public static function logSecurityEvent($event, $details = [])
    {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'event' => $event,
            'details' => $details
        ];

        $log_file = __DIR__ . '/../logs/security.log';

        // Create logs directory if it doesn't exist
        if (!file_exists(dirname($log_file))) {
            mkdir(dirname($log_file), 0755, true);
        }

        file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    }
}
