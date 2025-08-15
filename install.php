<?php
// install.php - Automatisches Installationsskript für MCS Booking System
session_start();

// Installationsschritte
$steps = [
    1 => 'Systemprüfung',
    2 => 'Grundkonfiguration',
    3 => 'Datenbankinitialisierung',
    4 => 'Admin-Konto',
    5 => 'Geschäftsdaten',
    6 => 'E-Mail-Konfiguration',
    7 => 'Google Maps API',
    8 => 'Sicherheitseinstellungen',
    9 => 'Abschluss'
];

$current_step = $_GET['step'] ?? 1;
$installation_data = $_SESSION['installation_data'] ?? [];

// Nach erfolgreicher Installation löschen
if (file_exists('config/config.php') && !isset($_GET['force'])) {
    if ($_GET['action'] ?? '' !== 'reinstall') {
        die('<!DOCTYPE html>
        <html><head><title>Installation bereits abgeschlossen</title>
        <style>body{font-family:Arial,sans-serif;max-width:600px;margin:100px auto;padding:20px;background:#1a1a1a;color:#fff;text-align:center;}
        .message{background:rgba(255,107,53,0.1);border:1px solid #ff6b35;padding:20px;border-radius:10px;}
        a{color:#ff6b35;text-decoration:none;}</style></head>
        <body><div class="message">
        <h2>✅ Installation bereits abgeschlossen</h2>
        <p>Das MCS Booking System ist bereits installiert und konfiguriert.</p>
        <p><a href="index.php">→ Zur Anwendung</a> | <a href="admin/">→ Admin-Panel</a></p>
        <p><small><a href="install.php?action=reinstall">Neuinstallation erzwingen</a></small></p>
        </div></body></html>');
    }
}

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $installation_data = array_merge($installation_data, $_POST);
    $_SESSION['installation_data'] = $installation_data;

    // Zur nächsten Stufe
    $current_step++;
    if ($current_step > count($steps)) {
        // Installation abschließen
        finalizeInstallation($installation_data);
        $current_step = count($steps);
    }

    header("Location: install.php?step=$current_step");
    exit;
}

/**
 * Installation abschließen
 */
function finalizeInstallation($data)
{
    try {
        // 1. Konfigurationsdatei erstellen
        createConfigFile($data);

        // 2. Verzeichnisse erstellen
        createDirectories();

        // 3. Datenbank initialisieren
        initializeDatabase();

        // 4. Standard-Daten einfügen
        insertDefaultData($data);

        // 5. .htaccess kopieren (falls nicht vorhanden)
        setupHtaccess();

        // 6. Installationssession löschen
        unset($_SESSION['installation_data']);

        // 7. Installations-Lock erstellen
        file_put_contents('.installed', date('Y-m-d H:i:s'));

        return true;
    } catch (Exception $e) {
        error_log("Installation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Erstellt config/config.php
 */
function createConfigFile($data)
{
    $config_content = '<?php
// config/config.php - Automatisch generiert am ' . date('Y-m-d H:i:s') . '

// Business Configuration
define(\'BUSINESS_NAME\', \'' . addslashes($data['business_name'] ?? 'MCS Mobile Car Solutions') . '\');
define(\'BUSINESS_ADDRESS\', \'' . addslashes($data['business_address'] ?? 'Musterstraße 123, 48431 Rheine') . '\');
define(\'BUSINESS_PHONE\', \'' . addslashes($data['business_phone'] ?? '+49 123 456789') . '\');
define(\'BUSINESS_EMAIL\', \'' . addslashes($data['business_email'] ?? 'info@mcs-mobile.de') . '\');
define(\'BUSINESS_WEBSITE\', \'' . addslashes($data['business_website'] ?? 'www.mcs-mobile.de') . '\');

// Booking Configuration
define(\'TRAVEL_COST_PER_KM\', ' . floatval($data['travel_cost'] ?? 0.50) . ');
define(\'WORKING_HOURS_START\', ' . intval($data['working_hours_start'] ?? 8) . ');
define(\'WORKING_HOURS_END\', ' . intval($data['working_hours_end'] ?? 17) . ');
define(\'WORKING_DAYS\', [1, 2, 3, 4, 5, 6]); // Monday to Saturday

// Email Configuration
define(\'SMTP_HOST\', \'' . addslashes($data['smtp_host'] ?? 'localhost') . '\');
define(\'SMTP_PORT\', ' . intval($data['smtp_port'] ?? 587) . ');
define(\'SMTP_USERNAME\', \'' . addslashes($data['smtp_username'] ?? '') . '\');
define(\'SMTP_PASSWORD\', \'' . addslashes($data['smtp_password'] ?? '') . '\');
define(\'SMTP_FROM_EMAIL\', \'' . addslashes($data['smtp_from_email'] ?? $data['business_email'] ?? 'noreply@mcs-mobile.de') . '\');
define(\'SMTP_FROM_NAME\', \'' . addslashes($data['business_name'] ?? 'MCS Mobile Car Solutions') . '\');
define(\'ADMIN_EMAIL\', \'' . addslashes($data['admin_email'] ?? $data['business_email'] ?? 'admin@mcs-mobile.de') . '\');

// Google Maps API
define(\'GOOGLE_MAPS_API_KEY\', \'' . addslashes($data['google_maps_api_key'] ?? '') . '\');

// Security Configuration
define(\'ADMIN_PASSWORD_HASH\', \'' . password_hash($data['admin_password'] ?? 'admin123', PASSWORD_DEFAULT) . '\');
define(\'SESSION_TIMEOUT\', 3600); // 1 hour
define(\'MAX_LOGIN_ATTEMPTS\', 5);
define(\'LOGIN_LOCKOUT_TIME\', 300); // 5 minutes

// Database Configuration
define(\'DB_PATH\', __DIR__ . \'/../database/bookings.db\');

// File Paths
define(\'BACKUP_DIR\', __DIR__ . \'/../backups\');
define(\'LOG_DIR\', __DIR__ . \'/../logs\');
define(\'UPLOAD_DIR\', __DIR__ . \'/../uploads\');

// Timezone
date_default_timezone_set(\'Europe/Berlin\');

// Environment
define(\'ENVIRONMENT\', \'' . ($data['environment'] ?? 'production') . '\');

// Error Reporting
if (ENVIRONMENT === \'production\') {
    error_reporting(0);
    ini_set(\'display_errors\', 0);
    ini_set(\'log_errors\', 1);
    ini_set(\'error_log\', LOG_DIR . \'/php_errors.log\');
} else {
    error_reporting(E_ALL);
    ini_set(\'display_errors\', 1);
}

// Security Headers
if (!headers_sent()) {
    header(\'X-Frame-Options: SAMEORIGIN\');
    header(\'X-Content-Type-Options: nosniff\');
    header(\'X-XSS-Protection: 1; mode=block\');
    header(\'Referrer-Policy: strict-origin-when-cross-origin\');
}

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set(\'session.cookie_httponly\', 1);
    ini_set(\'session.cookie_secure\', isset($_SERVER[\'HTTPS\']) ? 1 : 0);
    ini_set(\'session.cookie_samesite\', \'Strict\');
    ini_set(\'session.gc_maxlifetime\', SESSION_TIMEOUT);
}

// Auto-cleanup (runs randomly)
if (rand(1, 100) === 1) {
    // Cleanup old logs (older than 30 days)
    $old_logs = glob(LOG_DIR . \'/*.log\');
    foreach ($old_logs as $log) {
        if (filemtime($log) < time() - (30 * 24 * 60 * 60)) {
            unlink($log);
        }
    }
}
?>';

    if (!is_dir('config')) {
        mkdir('config', 0755, true);
    }

    file_put_contents('config/config.php', $config_content);
}

/**
 * Erstellt erforderliche Verzeichnisse
 */
function createDirectories()
{
    $directories = [
        'config',
        'classes',
        'views',
        'assets/css',
        'assets/js',
        'assets/images',
        'admin',
        'api',
        'database',
        'backups',
        'logs',
        'uploads'
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

/**
 * Initialisiert die Datenbank
 */
function initializeDatabase()
{
    require_once 'config/config.php';
    require_once 'config/database.php';

    $database = new Database();
    // Die Database-Klasse erstellt automatisch alle Tabellen und Standard-Daten
}

/**
 * Fügt benutzerdefinierte Standard-Daten ein
 */
function insertDefaultData($data)
{
    require_once 'config/config.php';
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Zusätzliche Services wenn angegeben
    if (!empty($data['additional_services'])) {
        $services = explode("\n", $data['additional_services']);
        $stmt = $db->prepare("INSERT INTO services (name, description, price, duration) VALUES (?, ?, ?, ?)");

        foreach ($services as $service) {
            $parts = explode('|', trim($service));
            if (count($parts) >= 3) {
                $stmt->execute([
                    trim($parts[0]), // Name
                    trim($parts[1] ?? ''), // Description
                    floatval($parts[2]), // Price
                    intval($parts[3] ?? 60) // Duration
                ]);
            }
        }
    }
}

/**
 * Setup .htaccess
 */
function setupHtaccess()
{
    if (!file_exists('.htaccess')) {
        // Kopiere .htaccess aus dem Template oder erstelle eine einfache Version
        $htaccess_content = '
RewriteEngine On
RewriteRule ^admin/?$ admin/index.php [L]
RewriteRule ^api/([^/]+)/?$ api/$1.php [L]

<Files "*.db">
    Order Deny,Allow
    Deny from all
</Files>

<Files "config.php">
    Order Deny,Allow
    Deny from all
</Files>
';
        file_put_contents('.htaccess', $htaccess_content);
    }
}

/**
 * Systemanforderungen prüfen
 */
function checkSystemRequirements()
{
    $requirements = [
        'PHP Version' => [
            'check' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'required' => 'PHP 7.4 oder höher',
            'current' => PHP_VERSION
        ],
        'PDO Extension' => [
            'check' => extension_loaded('pdo'),
            'required' => 'PDO Extension',
            'current' => extension_loaded('pdo') ? 'Verfügbar' : 'Nicht verfügbar'
        ],
        'SQLite Extension' => [
            'check' => extension_loaded('pdo_sqlite'),
            'required' => 'PDO SQLite Extension',
            'current' => extension_loaded('pdo_sqlite') ? 'Verfügbar' : 'Nicht verfügbar'
        ],
        'JSON Extension' => [
            'check' => extension_loaded('json'),
            'required' => 'JSON Extension',
            'current' => extension_loaded('json') ? 'Verfügbar' : 'Nicht verfügbar'
        ],
        'cURL Extension' => [
            'check' => extension_loaded('curl'),
            'required' => 'cURL Extension (für Google Maps)',
            'current' => extension_loaded('curl') ? 'Verfügbar' : 'Nicht verfügbar'
        ],
        'Schreibrechte Hauptverzeichnis' => [
            'check' => is_writable('.'),
            'required' => 'Schreibrechte für Hauptverzeichnis',
            'current' => is_writable('.') ? 'Verfügbar' : 'Nicht verfügbar'
        ]
    ];

    return $requirements;
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCS Booking System - Installation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #ffffff;
            min-height: 100vh;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px 0;
        }

        .header h1 {
            color: #ff6b35;
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            color: #ccc;
            font-size: 1.2em;
        }

        .progress-bar {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 30px;
            overflow-x: auto;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            min-width: 600px;
        }

        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
            padding: 10px 5px;
            font-size: 12px;
        }

        .progress-step.active {
            color: #ff6b35;
            font-weight: bold;
        }

        .progress-step.completed {
            color: #28a745;
        }

        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #333;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 5px;
            font-weight: bold;
        }

        .progress-step.active .step-number {
            background: #ff6b35;
        }

        .progress-step.completed .step-number {
            background: #28a745;
        }

        .installation-content {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 20px;
        }

        .step-title {
            color: #ff6b35;
            font-size: 1.8em;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #ffffff;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: #ffffff;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #ff6b35;
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        .btn {
            background: linear-gradient(45deg, #ff6b35, #ff8c42);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 16px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 53, 0.4);
        }

        .btn-secondary {
            background: transparent;
            border: 2px solid #666;
            color: #ccc;
        }

        .btn-secondary:hover {
            border-color: #ff6b35;
            color: #ff6b35;
            box-shadow: none;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .requirements-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .requirements-table th,
        .requirements-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .requirements-table th {
            background: rgba(255, 107, 53, 0.2);
            color: #ff6b35;
            font-weight: bold;
        }

        .status-ok {
            color: #28a745;
            font-weight: bold;
        }

        .status-error {
            color: #dc3545;
            font-weight: bold;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid #28a745;
            color: #4caf50;
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid #ffc107;
            color: #ff9800;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid #dc3545;
            color: #ff6666;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .installation-content {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .progress-steps {
                flex-direction: column;
                min-width: auto;
            }

            .btn-group {
                flex-direction: column;
            }
        }

        .help-text {
            font-size: 14px;
            color: #999;
            margin-top: 5px;
        }

        .installation-complete {
            text-align: center;
            padding: 40px 20px;
        }

        .success-icon {
            font-size: 4em;
            color: #28a745;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🚗 MCS Booking System</h1>
            <p>Installation & Konfiguration</p>
        </div>

        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-steps">
                <?php foreach ($steps as $step_num => $step_name): ?>
                    <div class="progress-step <?= $step_num < $current_step ? 'completed' : ($step_num == $current_step ? 'active' : '') ?>">
                        <div class="step-number">
                            <?= $step_num < $current_step ? '✓' : $step_num ?>
                        </div>
                        <div><?= $step_name ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="installation-content">
            <?php if ($current_step == 1): ?>
                <!-- Schritt 1: Systemprüfung -->
                <h2 class="step-title">Systemprüfung</h2>

                <?php
                $requirements = checkSystemRequirements();
                $all_requirements_met = true;
                ?>

                <table class="requirements-table">
                    <thead>
                        <tr>
                            <th>Anforderung</th>
                            <th>Erforderlich</th>
                            <th>Aktuell</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requirements as $name => $req): ?>
                            <tr>
                                <td><?= $name ?></td>
                                <td><?= $req['required'] ?></td>
                                <td><?= $req['current'] ?></td>
                                <td class="<?= $req['check'] ? 'status-ok' : 'status-error' ?>">
                                    <?= $req['check'] ? '✅ OK' : '❌ Fehlt' ?>
                                </td>
                            </tr>
                            <?php if (!$req['check']) $all_requirements_met = false; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($all_requirements_met): ?>
                    <div class="alert alert-success">
                        ✅ Alle Systemanforderungen sind erfüllt! Sie können mit der Installation fortfahren.
                    </div>
                    <div class="btn-group">
                        <a href="install.php?step=2" class="btn">Weiter</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-error">
                        ❌ Einige Systemanforderungen sind nicht erfüllt. Bitte beheben Sie diese Probleme vor der Installation.
                    </div>
                    <div class="btn-group">
                        <a href="install.php?step=1" class="btn-secondary">Erneut prüfen</a>
                    </div>
                <?php endif; ?>

            <?php elseif ($current_step == 2): ?>
                <!-- Schritt 2: Grundkonfiguration -->
                <h2 class="step-title">Grundkonfiguration</h2>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Umgebung</label>
                        <select name="environment" class="form-input form-select" required>
                            <option value="production" <?= ($installation_data['environment'] ?? '') === 'production' ? 'selected' : '' ?>>
                                Produktion (Live-Website)
                            </option>
                            <option value="development" <?= ($installation_data['environment'] ?? '') === 'development' ? 'selected' : '' ?>>
                                Entwicklung (Test-Installation)
                            </option>
                        </select>
                        <div class="help-text">In der Produktionsumgebung werden Fehlermeldungen ausgeblendet.</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Arbeitszeit Start</label>
                            <select name="working_hours_start" class="form-input form-select">
                                <?php for ($i = 6; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($installation_data['working_hours_start'] ?? 8) == $i ? 'selected' : '' ?>>
                                        <?= sprintf('%02d:00', $i) ?> Uhr
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Arbeitszeit Ende</label>
                            <select name="working_hours_end" class="form-input form-select">
                                <?php for ($i = 14; $i <= 22; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($installation_data['working_hours_end'] ?? 17) == $i ? 'selected' : '' ?>>
                                        <?= sprintf('%02d:00', $i) ?> Uhr
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Anfahrtskosten pro Kilometer (€)</label>
                        <input type="number" name="travel_cost" step="0.01" min="0" max="10"
                            class="form-input" value="<?= $installation_data['travel_cost'] ?? '0.50' ?>" required>
                        <div class="help-text">Üblicher Wert: 0,30 - 1,00 €</div>
                    </div>

                    <div class="btn-group">
                        <a href="install.php?step=1" class="btn-secondary">Zurück</a>
                        <button type="submit" class="btn">Weiter</button>
                    </div>
                </form>

            <?php elseif ($current_step == 3): ?>
                <!-- Schritt 3: Datenbankinitialisierung -->
                <h2 class="step-title">Datenbankinitialisierung</h2>

                <div class="alert alert-success">
                    ✅ Die SQLite-Datenbank wird automatisch erstellt und initialisiert.
                </div>

                <p>Das System erstellt automatisch:</p>
                <ul style="margin: 20px 0; padding-left: 20px;">
                    <li>Datenbank-Datei im <code>database/</code> Verzeichnis</li>
                    <li>Alle erforderlichen Tabellen</li>
                    <li>Standard-Services für Fahrzeugpflege</li>
                    <li>Beispiel-Termine für die nächsten 60 Tage</li>
                </ul>

                <form method="POST">
                    <div class="btn-group">
                        <a href="install.php?step=2" class="btn-secondary">Zurück</a>
                        <button type="submit" class="btn">Datenbank erstellen</button>
                    </div>
                </form>

            <?php elseif ($current_step == 4): ?>
                <!-- Schritt 4: Admin-Konto -->
                <h2 class="step-title">Administrator-Konto</h2>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Admin-Passwort</label>
                        <input type="password" name="admin_password" class="form-input"
                            value="<?= $installation_data['admin_password'] ?? '' ?>"
                            placeholder="Starkes Passwort eingeben" required minlength="8">
                        <div class="help-text">Mindestens 8 Zeichen. Standard ist 'admin123' (nicht empfohlen).</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Admin E-Mail-Adresse</label>
                        <input type="email" name="admin_email" class="form-input"
                            value="<?= $installation_data['admin_email'] ?? '' ?>"
                            placeholder="admin@ihredomain.de" required>
                        <div class="help-text">Diese E-Mail erhält Benachrichtigungen über neue Buchungen.</div>
                    </div>

                    <div class="btn-group">
                        <a href="install.php?step=3" class="btn-secondary">Zurück</a>
                        <button type="submit" class="btn">Weiter</button>
                    </div>
                </form>

            <?php elseif ($current_step == 5): ?>
                <!-- Schritt 5: Geschäftsdaten -->
                <h2 class="step-title">Geschäftsdaten</h2>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Firmenname</label>
                        <input type="text" name="business_name" class="form-input"
                            value="<?= $installation_data['business_name'] ?? 'MCS Mobile Car Solutions' ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Geschäftsadresse</label>
                        <input type="text" name="business_address" class="form-input"
                            value="<?= $installation_data['business_address'] ?? 'Musterstraße 123, 48431 Rheine' ?>" required>
                        <div class="help-text">Vollständige Adresse für Entfernungsberechnungen</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Telefonnummer</label>
                            <input type="tel" name="business_phone" class="form-input"
                                value="<?= $installation_data['business_phone'] ?? '+49 123 456789' ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">E-Mail-Adresse</label>
                            <input type="email" name="business_email" class="form-input"
                                value="<?= $installation_data['business_email'] ?? 'info@mcs-mobile.de' ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Website (optional)</label>
                        <input type="url" name="business_website" class="form-input"
                            value="<?= $installation_data['business_website'] ?? 'www.mcs-mobile.de' ?>"
                            placeholder="https://www.ihredomain.de">
                    </div>

                    <div class="btn-group">
                        <a href="install.php?step=4" class="btn-secondary">Zurück</a>
                        <button type="submit" class="btn">Weiter</button>
                    </div>
                </form>

            <?php elseif ($current_step == 6): ?>
                <!-- Schritt 6: E-Mail-Konfiguration -->
                <h2 class="step-title">E-Mail-Konfiguration</h2>

                <div class="alert alert-warning">
                    ⚠️ E-Mail-Konfiguration ist optional. Das System funktioniert auch ohne E-Mail-Versendung.
                </div>

                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">SMTP Host (optional)</label>
                            <input type="text" name="smtp_host" class="form-input"
                                value="<?= $installation_data['smtp_host'] ?? 'localhost' ?>"
                                placeholder="smtp.gmail.com">
                        </div>

                        <div class="form-group">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" name="smtp_port" class="form-input"
                                value="<?= $installation_data['smtp_port'] ?? '587' ?>"
                                placeholder="587">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">SMTP Benutzername (optional)</label>
                            <input type="text" name="smtp_username" class="form-input"
                                value="<?= $installation_data['smtp_username'] ?? '' ?>"
                                placeholder="ihr@email.de">
                        </div>

                        <div class="form-group">
                            <label class="form-label">SMTP Passwort (optional)</label>
                            <input type="password" name="smtp_password" class="form-input"
                                value="<?= $installation_data['smtp_password'] ?? '' ?>"
                                placeholder="App-Passwort">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Absender E-Mail</label>
                        <input type="email" name="smtp_from_email" class="form-input"
                            value="<?= $installation_data['smtp_from_email'] ?? 'noreply@mcs-mobile.de' ?>"
                            placeholder="noreply@ihredomain.de" required>
                    </div>

                    <div class="btn-group">
                        <a href="install.php?step=5" class="btn-secondary">Zurück</a>
                        <button type="submit" class="btn">Weiter</button>
                    </div>
                </form>

            <?php elseif ($current_step == 7): ?>
                <!-- Schritt 7: Google Maps API -->
                <h2 class="step-title">Google Maps API</h2>

                <div class="alert alert-warning">
                    ⚠️ Google Maps API ist optional, aber empfohlen für automatische Entfernungsberechnung.
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Google Maps API Key (optional)</label>
                        <input type="text" name="google_maps_api_key" class="form-input"
                            value="<?= $installation_data['google_maps_api_key'] ?? '' ?>"
                            placeholder="AIzaSyC...">
                        <div class="help-text">
                            Ohne API-Key werden Entfernungen geschätzt.
                            <a href="https://console.cloud.google.com/" target="_blank" style="color: #ff6b35;">
                                API-Key beantragen
                            </a>
                        </div>
                    </div>

                    <p style="margin: 20px 0; font-size: 14px; color: #ccc;">
                        <strong>Benötigte APIs:</strong><br>
                        • Maps JavaScript API<br>
                        • Distance Matrix API<br>
                        • Places API
                    </p>

                    <div class="btn-group">
                        <a href="install.php?step=6" class="btn-secondary">Zurück</a>
                        <button type="submit" class="btn">Weiter</button>
                    </div>
                </form>

            <?php elseif ($current_step == 8): ?>
                <!-- Schritt 8: Sicherheitseinstellungen -->
                <h2 class="step-title">Sicherheitseinstellungen</h2>

                <div class="alert alert-success">
                    ✅ Das System ist bereits mit umfangreichen Sicherheitsfeatures ausgestattet:
                </div>

                <ul style="margin: 20px 0; padding-left: 20px; color: #ccc;">
                    <li>CSRF-Schutz für alle Formulare</li>
                    <li>Rate-Limiting gegen Spam-Angriffe</li>
                    <li>Input-Sanitization und Validierung</li>
                    <li>SQL-Injection-Schutz</li>
                    <li>Sichere Session-Verwaltung</li>
                    <li>Security-Headers</li>
                    <li>Automatische Sicherheitslogs</li>
                </ul>

                <div class="alert alert-warning">
                    💡 <strong>Zusätzliche Empfehlungen:</strong><br>
                    • Verwenden Sie HTTPS in der Produktion<br>
                    • Erstellen Sie regelmäßige Backups<br>
                    • Halten Sie PHP aktuell<br>
                    • Überwachen Sie die Sicherheitslogs
                </div>

                <form method="POST">
                    <div class="btn-group">
                        <a href="install.php?step=7" class="btn-secondary">Zurück</a>
                        <button type="submit" class="btn">Installation abschließen</button>
                    </div>
                </form>

            <?php else: ?>
                <!-- Schritt 9: Abschluss -->
                <div class="installation-complete">
                    <div class="success-icon">🎉</div>
                    <h2 class="step-title">Installation erfolgreich!</h2>

                    <div class="alert alert-success">
                        ✅ Das MCS Booking System wurde erfolgreich installiert und konfiguriert.
                    </div>

                    <div style="text-align: left; margin: 30px 0;">
                        <h3 style="color: #ff6b35; margin-bottom: 15px;">Was wurde installiert:</h3>
                        <ul style="color: #ccc; padding-left: 20px;">
                            <li>✅ Vollständiges Buchungssystem</li>
                            <li>✅ SQLite-Datenbank mit Standard-Daten</li>
                            <li>✅ Admin-Panel für Verwaltung</li>
                            <li>✅ E-Mail-Benachrichtigungen</li>
                            <li>✅ Sicherheitsfeatures</li>
                            <li>✅ Backup-System</li>
                        </ul>
                    </div>

                    <div style="text-align: left; margin: 30px 0;">
                        <h3 style="color: #ff6b35; margin-bottom: 15px;">Nächste Schritte:</h3>
                        <ol style="color: #ccc; padding-left: 20px;">
                            <li>Testen Sie das Buchungssystem</li>
                            <li>Passen Sie Services im Admin-Panel an</li>
                            <li>Erstellen Sie weitere Termine</li>
                            <li>Richten Sie automatische Backups ein</li>
                            <li>Löschen Sie diese Installationsdatei</li>
                        </ol>
                    </div>

                    <div style="background: rgba(220, 53, 69, 0.1); border: 1px solid #dc3545; border-radius: 10px; padding: 20px; margin: 20px 0; color: #ff6666;">
                        <h4>🔒 Wichtiger Sicherheitshinweis:</h4>
                        <p>Löschen Sie diese <code>install.php</code> Datei aus Sicherheitsgründen!</p>
                    </div>

                    <div class="btn-group">
                        <a href="index.php" class="btn">🏠 Zur Anwendung</a>
                        <a href="admin/" class="btn">⚙️ Admin-Panel</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-scroll zu Fehlern
        document.addEventListener('DOMContentLoaded', function() {
            const errorElement = document.querySelector('.alert-error');
            if (errorElement) {
                errorElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = this.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#dc3545';
                        isValid = false;
                    } else {
                        field.style.borderColor = '';
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    alert('Bitte füllen Sie alle Pflichtfelder aus.');
                }
            });
        });

        // Password strength indicator
        const passwordField = document.querySelector('input[name="admin_password"]');
        if (passwordField) {
            passwordField.addEventListener('input', function() {
                const strength = getPasswordStrength(this.value);
                const colors = ['#dc3545', '#ff9800', '#ffc107', '#4caf50'];
                this.style.borderColor = colors[strength] || '#dc3545';
            });
        }

        function getPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            return Math.min(strength, 3);
        }
    </script>
</body>

</html>