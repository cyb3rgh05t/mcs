<?php
// debug.php - Development Helper für Fixes und Testing
session_start();

// Nur in Development-Umgebung verfügbar
if (!defined('ENVIRONMENT') || ENVIRONMENT !== 'development') {
    require_once 'config/config.php';
}

// Sicherheitscheck für Localhost
$allowed_ips = ['127.0.0.1', '::1', 'localhost'];
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$server_name = $_SERVER['SERVER_NAME'] ?? '';

if (!in_array($client_ip, $allowed_ips) && !in_array($server_name, ['localhost', '127.0.0.1'])) {
    http_response_code(403);
    die('Access denied. Development helper only available on localhost.');
}

require_once 'classes/SecurityManager.php';

$action = $_GET['action'] ?? '';
$message = '';

// Actions verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($action)) {
    switch ($action) {
        case 'reset_rate_limits':
            SecurityManager::resetRateLimit();
            $message = '✅ Rate Limits wurden zurückgesetzt.';
            break;

        case 'reset_csrf':
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            $message = '✅ CSRF Tokens wurden zurückgesetzt.';
            break;

        case 'clear_logs':
            $log_dir = defined('LOG_DIR') ? LOG_DIR : 'logs';
            if (is_dir($log_dir)) {
                $files = glob($log_dir . '/*.log');
                foreach ($files as $file) {
                    if (filesize($file) > 1024 * 1024) { // Nur große Logs (>1MB) leeren
                        file_put_contents($file, '');
                    }
                }
                $message = '✅ Große Log-Dateien wurden geleert.';
            }
            break;

        case 'test_security':
            // Test Security-Funktionen
            $tests = [
                'CSRF Token' => SecurityManager::generateCSRFToken() ? 'OK' : 'FEHLER',
                'Rate Limit Check' => SecurityManager::rateLimitCheck('test', 10, 60) ? 'OK' : 'FEHLER',
                'Email Validation' => SecurityManager::validateEmail('test@example.com') ? 'OK' : 'FEHLER',
                'Phone Validation' => SecurityManager::validatePhone('+49 123 456789') ? 'OK' : 'FEHLER',
            ];
            $message = '🧪 Security-Tests: ' . json_encode($tests);
            break;

        case 'view_session':
            $message = '📋 Session-Daten: <pre>' . htmlspecialchars(print_r($_SESSION, true)) . '</pre>';
            break;
    }
}

// Log-Statistiken laden
function getLogStats()
{
    $log_dir = defined('LOG_DIR') ? LOG_DIR : 'logs';
    $stats = [];

    if (is_dir($log_dir)) {
        $files = glob($log_dir . '/*.log');
        foreach ($files as $file) {
            $name = basename($file);
            $stats[$name] = [
                'size' => filesize($file),
                'lines' => count(file($file)),
                'modified' => date('d.m.Y H:i:s', filemtime($file))
            ];
        }
    }

    return $stats;
}

$log_stats = getLogStats();

// Security Log analysieren
function analyzeSecurityLog()
{
    $log_file = (defined('LOG_DIR') ? LOG_DIR : 'logs') . '/security.log';

    if (!file_exists($log_file)) {
        return ['status' => 'Keine Security-Logs vorhanden'];
    }

    $lines = file($log_file);
    $recent_lines = array_slice($lines, -50); // Letzte 50 Einträge

    $events = [];
    foreach ($recent_lines as $line) {
        $data = json_decode($line, true);
        if ($data && isset($data['event'])) {
            $events[$data['event']] = ($events[$data['event']] ?? 0) + 1;
        }
    }

    return $events;
}

$security_analysis = analyzeSecurityLog();
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Helper - MCS Booking System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #1a1a1a;
            color: #fff;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(45deg, #ffffff, #ff8c42);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .card h3 {
            color: #ffffff;
            margin-bottom: 15px;
        }

        .btn {
            background: linear-gradient(45deg, #ffffff, #ff8c42);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: transparent;
            border: 2px solid #666;
            color: #ccc;
        }

        .btn-secondary:hover {
            border-color: #ffffff;
            color: #ffffff;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .message {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid #28a745;
            color: #4caf50;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .warning {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid #ffc107;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        pre {
            background: #222;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🔧 MCS Debug Helper</h1>
            <p>Development-Tools für Fehlerdiagnose und Testing</p>
        </div>

        <?php if ($message): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>

        <div class="warning">
            ⚠️ <strong>Hinweis:</strong> Dieses Tool ist nur in der Development-Umgebung verfügbar und funktioniert nur auf localhost.
        </div>

        <div class="grid">
            <!-- Quick Actions -->
            <div class="card">
                <h3>🚀 Quick Actions</h3>
                <a href="?action=reset_rate_limits" class="btn">Reset Rate Limits</a>
                <a href="?action=reset_csrf" class="btn">Reset CSRF Tokens</a>
                <a href="?action=clear_logs" class="btn btn-secondary">Log-Dateien leeren</a>
                <a href="?action=test_security" class="btn btn-secondary">Security testen</a>
                <a href="?action=view_session" class="btn btn-secondary">Session anzeigen</a>
            </div>

            <!-- Log-Statistiken -->
            <div class="card">
                <h3>📊 Log-Statistiken</h3>
                <?php if (!empty($log_stats)): ?>
                    <?php foreach ($log_stats as $name => $stats): ?>
                        <div class="stat-item">
                            <span><?= $name ?></span>
                            <span><?= number_format($stats['size']) ?> Bytes (<?= $stats['lines'] ?> Zeilen)</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #ccc;">Keine Log-Dateien gefunden.</p>
                <?php endif; ?>
            </div>

            <!-- Security-Analyse -->
            <div class="card">
                <h3>🔒 Security-Events (letzte 50)</h3>
                <?php if (!empty($security_analysis) && is_array($security_analysis)): ?>
                    <?php foreach ($security_analysis as $event => $count): ?>
                        <div class="stat-item">
                            <span><?= htmlspecialchars($event) ?></span>
                            <span><?= $count ?>x</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #ccc;"><?= is_string($security_analysis) ? $security_analysis['status'] : 'Keine Security-Events gefunden.' ?></p>
                <?php endif; ?>
            </div>

            <!-- System-Info -->
            <div class="card">
                <h3>💻 System-Info</h3>
                <div class="stat-item">
                    <span>PHP Version</span>
                    <span><?= PHP_VERSION ?></span>
                </div>
                <div class="stat-item">
                    <span>Session Status</span>
                    <span><?= session_status() === PHP_SESSION_ACTIVE ? 'Aktiv' : 'Inaktiv' ?></span>
                </div>
                <div class="stat-item">
                    <span>Memory Usage</span>
                    <span><?= round(memory_get_usage() / 1024 / 1024, 2) ?> MB</span>
                </div>
                <div class="stat-item">
                    <span>Environment</span>
                    <span><?= defined('ENVIRONMENT') ? ENVIRONMENT : 'Unbekannt' ?></span>
                </div>
            </div>

            <!-- Bekannte Probleme & Lösungen -->
            <div class="card">
                <h3>🩺 Bekannte Probleme & Lösungen</h3>
                <div style="font-size: 14px;">
                    <p><strong>CSRF Token Mismatch:</strong></p>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>Session wird nicht korrekt gestartet</li>
                        <li>Token ist abgelaufen (jetzt 2h statt 1h)</li>
                        <li>Lösung: "Reset CSRF Tokens" klicken</li>
                    </ul>

                    <p><strong>Rate Limit Exceeded:</strong></p>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>Zu viele Requests in kurzer Zeit</li>
                        <li>Limit jetzt 20 statt 10 Requests</li>
                        <li>Localhost ist ausgenommen</li>
                        <li>Lösung: "Reset Rate Limits" klicken</li>
                    </ul>

                    <p><strong>Google Maps API:</strong></p>
                    <ul style="margin-left: 20px;">
                        <li>API-Key in config/config.php prüfen</li>
                        <li>Billing in Google Console aktivieren</li>
                        <li>Distance Matrix API aktivieren</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>📋 Letzte Security-Log Einträge</h3>
            <?php
            $log_file = (defined('LOG_DIR') ? LOG_DIR : 'logs') . '/security.log';
            if (file_exists($log_file)) {
                $lines = array_slice(file($log_file), -10);
                echo '<pre>';
                foreach ($lines as $line) {
                    $data = json_decode($line, true);
                    if ($data) {
                        echo htmlspecialchars($data['timestamp'] . ' - ' . $data['event']) . "\n";
                    }
                }
                echo '</pre>';
            } else {
                echo '<p style="color: #ccc;">Keine Security-Logs verfügbar.</p>';
            }
            ?>
        </div>

        <div style="text-align: center; padding: 20px; color: #666; font-size: 14px;">
            <a href="/" class="btn btn-secondary">← Zurück zur Anwendung</a>
            <a href="/admin/" class="btn btn-secondary">Admin-Panel</a>
        </div>
    </div>
</body>

</html>