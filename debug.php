<?php
// debug.php - Debug und Test-Tool für Entwicklung
session_start();

// Nur in Development-Umgebung verfügbar
if (
    !isset($_SERVER['HTTP_HOST']) ||
    (strpos($_SERVER['HTTP_HOST'], 'localhost') === false &&
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false)
) {
    die('Debug mode is only available in development environment.');
}

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/SecurityManager.php';
require_once 'classes/BookingManager.php';
require_once 'classes/EmailManager.php';

$database = new Database();
$db = $database->getConnection();
$bookingManager = new BookingManager($db);

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>🔧 MCS Debug Panel</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1a1a1a;
            color: #0f0;
            padding: 20px;
            margin: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            color: #0f0;
            border-bottom: 2px solid #0f0;
            padding-bottom: 10px;
        }

        .section {
            background: #000;
            border: 1px solid #0f0;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .section h2 {
            margin-top: 0;
            color: #ff0;
        }

        .info {
            background: #001100;
            padding: 10px;
            margin: 10px 0;
            border-left: 3px solid #0f0;
        }

        .error {
            background: #110000;
            border-left: 3px solid #f00;
            color: #ff6666;
        }

        .success {
            background: #001100;
            border-left: 3px solid #0f0;
            color: #66ff66;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        th,
        td {
            border: 1px solid #0f0;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #003300;
            color: #0f0;
        }

        .btn {
            background: #0f0;
            color: #000;
            border: none;
            padding: 8px 15px;
            cursor: pointer;
            font-weight: bold;
            margin: 5px;
        }

        .btn:hover {
            background: #0a0;
        }

        pre {
            background: #111;
            padding: 10px;
            overflow-x: auto;
            border: 1px solid #333;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔧 MCS Debug Panel</h1>

        <!-- System Info -->
        <div class="section">
            <h2>📊 System Information</h2>
            <div class="info">
                <strong>PHP Version:</strong> <?= phpversion() ?><br>
                <strong>SQLite Version:</strong> <?= SQLite3::version()['versionString'] ?><br>
                <strong>Environment:</strong> <?= defined('ENVIRONMENT') ? ENVIRONMENT : 'Not set' ?><br>
                <strong>Session ID:</strong> <?= session_id() ?><br>
                <strong>Server Time:</strong> <?= date('Y-m-d H:i:s') ?><br>
                <strong>Timezone:</strong> <?= date_default_timezone_get() ?>
            </div>
        </div>

        <!-- Configuration Check -->
        <div class="section">
            <h2>⚙️ Configuration</h2>
            <table>
                <tr>
                    <th>Setting</th>
                    <th>Value</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td>Business Name</td>
                    <td><?= defined('BUSINESS_NAME') ? BUSINESS_NAME : 'NOT SET' ?></td>
                    <td><?= defined('BUSINESS_NAME') ? '✅' : '❌' ?></td>
                </tr>
                <tr>
                    <td>Google Maps API</td>
                    <td><?= defined('GOOGLE_MAPS_API_KEY') && !empty(GOOGLE_MAPS_API_KEY) ? 'Configured' : 'NOT SET' ?></td>
                    <td><?= defined('GOOGLE_MAPS_API_KEY') && !empty(GOOGLE_MAPS_API_KEY) ? '✅' : '⚠️' ?></td>
                </tr>
                <tr>
                    <td>Travel Cost/km</td>
                    <td><?= defined('TRAVEL_COST_PER_KM') ? TRAVEL_COST_PER_KM . '€' : 'NOT SET' ?></td>
                    <td><?= defined('TRAVEL_COST_PER_KM') ? '✅' : '❌' ?></td>
                </tr>
                <tr>
                    <td>Free KM</td>
                    <td><?= defined('TRAVEL_FREE_KM') ? TRAVEL_FREE_KM . ' km' : 'NOT SET' ?></td>
                    <td><?= defined('TRAVEL_FREE_KM') ? '✅' : '❌' ?></td>
                </tr>
                <tr>
                    <td>Min Service Amount</td>
                    <td><?= defined('TRAVEL_MIN_SERVICE_AMOUNT') ? TRAVEL_MIN_SERVICE_AMOUNT . '€' : 'NOT SET' ?></td>
                    <td><?= defined('TRAVEL_MIN_SERVICE_AMOUNT') ? '✅' : '❌' ?></td>
                </tr>
            </table>
        </div>

        <!-- Database Status -->
        <div class="section">
            <h2>💾 Database Status</h2>
            <?php
            try {
                $tables = ['appointments', 'services', 'bookings', 'booking_services'];
                foreach ($tables as $table) {
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table");
                    $stmt->execute();
                    $count = $stmt->fetchColumn();
                    echo "<div class='info'>Table <strong>$table</strong>: $count records</div>";
                }
            } catch (Exception $e) {
                echo "<div class='error'>Database Error: " . $e->getMessage() . "</div>";
            }
            ?>
        </div>

        <!-- Test Functions -->
        <div class="section">
            <h2>🧪 Test Functions</h2>

            <?php
            // Test SecurityManager
            echo "<h3>SecurityManager Tests:</h3>";

            // Test E-Mail Validation
            $testEmails = ['test@example.com', 'invalid.email', 'test@test'];
            foreach ($testEmails as $email) {
                $result = SecurityManager::validateEmail($email);
                $status = $result ? 'success' : 'error';
                echo "<div class='$status'>validateEmail('$email'): " . ($result ? "✅ Valid: $result" : "❌ Invalid") . "</div>";
            }

            // Test Phone Validation
            $testPhones = ['+49 123 456789', '0123456789', 'abc'];
            foreach ($testPhones as $phone) {
                $result = SecurityManager::validatePhone($phone);
                $status = $result ? 'success' : 'error';
                echo "<div class='$status'>validatePhone('$phone'): " . ($result ? "✅ Valid: $result" : "❌ Invalid") . "</div>";
            }

            // Test CSRF Token
            $token = SecurityManager::generateCSRFToken();
            echo "<div class='info'>CSRF Token Generated: " . substr($token, 0, 20) . "...</div>";

            // Test Rate Limiting
            $ip = SecurityManager::getClientIP();
            echo "<div class='info'>Your IP: $ip</div>";
            $rateLimitOk = SecurityManager::rateLimitCheck($ip, 10, 60);
            echo "<div class='" . ($rateLimitOk ? 'success' : 'error') . "'>Rate Limit Check: " . ($rateLimitOk ? '✅ OK' : '❌ Limit exceeded') . "</div>";
            ?>

            <h3>BookingManager Tests:</h3>
            <?php
            // Test Available Dates
            $availableDates = $bookingManager->getAvailableDates(7);
            echo "<div class='info'>Available dates (next 7 days): " . count($availableDates) . " days</div>";

            // Test Services
            $services = $bookingManager->getAllServices();
            echo "<div class='info'>Active services: " . count($services) . " services</div>";
            ?>
        </div>

        <!-- Actions -->
        <div class="section">
            <h2>🎬 Debug Actions</h2>

            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="reset_rate_limit">
                <button type="submit" class="btn">Reset Rate Limit</button>
            </form>

            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="clear_session">
                <button type="submit" class="btn">Clear Session</button>
            </form>

            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="generate_test_slots">
                <button type="submit" class="btn">Generate Test Slots (7 days)</button>
            </form>

            <?php
            // Handle debug actions
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'reset_rate_limit':
                        SecurityManager::resetRateLimit(SecurityManager::getClientIP());
                        echo "<div class='success'>✅ Rate limit reset</div>";
                        break;

                    case 'clear_session':
                        session_destroy();
                        session_start();
                        echo "<div class='success'>✅ Session cleared</div>";
                        break;

                    case 'generate_test_slots':
                        $created = $bookingManager->generateTimeSlots(
                            date('Y-m-d', strtotime('+1 day')),
                            date('Y-m-d', strtotime('+7 days'))
                        );
                        echo "<div class='success'>✅ Generated $created time slots</div>";
                        break;
                }
            }
            ?>
        </div>

        <!-- Session Data -->
        <div class="section">
            <h2>📦 Session Data</h2>
            <pre><?= htmlspecialchars(print_r($_SESSION, true)) ?></pre>
        </div>

        <!-- Server Variables -->
        <div class="section">
            <h2>🌐 Server Variables</h2>
            <details>
                <summary style="cursor: pointer; color: #ff0;">Click to expand</summary>
                <pre><?= htmlspecialchars(print_r($_SERVER, true)) ?></pre>
            </details>
        </div>

        <!-- Logs -->
        <div class="section">
            <h2>📜 Recent Security Logs</h2>
            <?php
            $logFile = defined('LOG_DIR') ? LOG_DIR . '/security.log' : 'logs/security.log';
            if (file_exists($logFile)) {
                $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $logs = array_slice(array_reverse($logs), 0, 10);

                echo "<pre style='max-height: 300px; overflow-y: auto;'>";
                foreach ($logs as $log) {
                    $entry = json_decode($log, true);
                    if ($entry) {
                        $color = strpos($entry['event'], 'failed') !== false ? '#ff6666' : '#66ff66';
                        echo "<span style='color: $color;'>" . htmlspecialchars($log) . "</span>\n";
                    }
                }
                echo "</pre>";
            } else {
                echo "<div class='info'>No logs found</div>";
            }
            ?>
        </div>

        <div style="text-align: center; margin-top: 30px; color: #666;">
            <p>🔒 This page is only accessible in development environment</p>
            <a href="/" style="color: #0f0;">← Back to Main Site</a> |
            <a href="/admin" style="color: #0f0;">Admin Panel →</a>
        </div>
    </div>
</body>

</html>