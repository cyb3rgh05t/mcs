<?php
// config/config.php - Hauptkonfigurationsdatei
define('ENVIRONMENT', 'development');

// Business Configuration
define('BUSINESS_NAME', 'MCS Mobile Car Solutions');
define('BUSINESS_ADDRESS', 'Hüllerstraße 16, 44649 Herne');
define('BUSINESS_PHONE', '+49 123 456789');
define('BUSINESS_EMAIL', 'info@mcs-mobile.de');
define('BUSINESS_WEBSITE', 'www.mcs-mobile.de');

// Booking Configuration
define('TRAVEL_COST_PER_KM', 0.50);
define('WORKING_HOURS_START', 8);
define('WORKING_HOURS_END', 17);
define('WORKING_DAYS', [1, 2, 3, 4, 5, 6]); // Monday to Saturday

// Email Configuration
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@mcs-mobile.de');
define('SMTP_FROM_NAME', BUSINESS_NAME);
define('ADMIN_EMAIL', 'admin@mcs-mobile.de');

// Google Maps API (WICHTIG: Hier Ihren echten API-Key eintragen!)
define('GOOGLE_MAPS_API_KEY', 'AIzaSyBbVppnqML9ojgSNrtINJedxSZGR_iDxug'); // Beispiel: 'AIzaSyC...'

// Security Configuration
define('ADMIN_PASSWORD_HASH', password_hash('admin123', PASSWORD_DEFAULT)); // ÄNDERN SIE DAS PASSWORT!
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 300); // 5 minutes

// Database Configuration
define('DB_PATH', __DIR__ . '/../database/bookings.db');

// File Paths
define('BACKUP_DIR', __DIR__ . '/../backups');
define('LOG_DIR', __DIR__ . '/../logs');
define('UPLOAD_DIR', __DIR__ . '/../uploads');

// Timezone
date_default_timezone_set('Europe/Berlin');

// Error Reporting
if (defined('ENVIRONMENT') && 'ENVIRONMENT' === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_DIR . '/php_errors.log');
} else {
    // Development mode
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Security Headers
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
}

// Create necessary directories
$directories = [
    dirname(DB_PATH),
    BACKUP_DIR,
    LOG_DIR,
    UPLOAD_DIR
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Auto-cleanup old files (optional)
function cleanupOldFiles()
{
    // Cleanup old logs (older than 30 days)
    $logFiles = glob(LOG_DIR . '/*.log');
    foreach ($logFiles as $file) {
        if (filemtime($file) < time() - (30 * 24 * 60 * 60)) {
            unlink($file);
        }
    }

    // Cleanup old backups (keep only last 10)
    $backupFiles = glob(BACKUP_DIR . '/backup_*.sql');
    if (count($backupFiles) > 10) {
        usort($backupFiles, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        $filesToDelete = array_slice($backupFiles, 0, count($backupFiles) - 10);
        foreach ($filesToDelete as $file) {
            unlink($file);
        }
    }
}

// Run cleanup with 1% probability
if (rand(1, 100) === 1) {
    cleanupOldFiles();
}
