<?php

/**
 * Mobile Car Service - Setup Script
 * Erstinstallation des Backend-Systems
 */

// Nur bei Erstinstallation erlauben
if (file_exists(__DIR__ . '/data/database.sqlite') && filesize(__DIR__ . '/data/database.sqlite') > 0) {
    if (!isset($_GET['force'])) {
        die('Setup bereits abgeschlossen. Verwenden Sie ?force=1 zum Zurücksetzen.');
    }
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Setup-Klasse
 */
class Setup
{
    private $db;
    private $errors = [];
    private $steps = [];

    public function __construct()
    {
        $this->steps = [
            'permissions' => 'Berechtigungen prüfen',
            'database' => 'Datenbank erstellen',
            'tables' => 'Tabellen erstellen',
            'services' => 'Standard-Services einfügen',
            'config' => 'Konfiguration validieren',
            'test' => 'System testen'
        ];
    }

    /**
     * Setup ausführen
     */
    public function run()
    {
        $this->outputHeader();

        foreach ($this->steps as $step => $description) {
            $this->outputStep($step, $description);

            $method = 'setup' . ucfirst($step);
            if (method_exists($this, $method)) {
                try {
                    $result = $this->$method();
                    $this->outputResult($result);
                } catch (Exception $e) {
                    $this->errors[] = $e->getMessage();
                    $this->outputError($e->getMessage());
                }
            }
        }

        $this->outputSummary();
    }

    /**
     * Berechtigungen prüfen
     */
    private function setupPermissions()
    {
        $requiredPaths = [
            __DIR__ . '/data' => 'Datenbank-Verzeichnis',
            __DIR__ . '/logs' => 'Log-Verzeichnis',
            __DIR__ . '/uploads' => 'Upload-Verzeichnis'
        ];

        $results = [];

        foreach ($requiredPaths as $path => $description) {
            // Verzeichnis erstellen falls nicht vorhanden
            if (!is_dir($path)) {
                if (!mkdir($path, 0755, true)) {
                    throw new Exception("Konnte $description nicht erstellen: $path");
                }
            }

            // Schreibberechtigung prüfen
            if (!is_writable($path)) {
                throw new Exception("$description ist nicht beschreibbar: $path");
            }

            $results[] = "$description: ✓ OK";
        }

        // .htaccess für Datenschutz erstellen
        $this->createProtectionFiles();

        return $results;
    }

    /**
     * Datenbank erstellen
     */
    private function setupDatabase()
    {
        try {
            $this->db = Database::getInstance();
            return ['Datenbank-Verbindung: ✓ OK'];
        } catch (Exception $e) {
            throw new Exception('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Tabellen erstellen
     */
    private function setupTables()
    {
        $tables = $this->db->getSchema();

        if (empty($tables)) {
            throw new Exception('Keine Tabellen gefunden');
        }

        $results = [];
        foreach ($tables as $table) {
            $results[] = "Tabelle '{$table['name']}': ✓ erstellt";
        }

        return $results;
    }

    /**
     * Standard-Services einfügen
     */
    private function setupServices()
    {
        $serviceCount = $this->db->fetchOne("SELECT COUNT(*) as count FROM services")['count'];

        if ($serviceCount > 0) {
            return ["Services: ✓ $serviceCount Services bereits vorhanden"];
        }

        return ['Services: ✓ Standard-Services eingefügt'];
    }

    /**
     * Konfiguration validieren
     */
    private function setupConfig()
    {
        $results = [];

        // Kritische Konfigurationswerte prüfen
        $criticalSettings = [
            'APP_NAME' => APP_NAME,
            'DB_PATH' => DB_PATH,
            'COMPANY_NAME' => COMPANY_NAME,
            'SMTP_FROM_EMAIL' => SMTP_FROM_EMAIL
        ];

        foreach ($criticalSettings as $setting => $value) {
            if (empty($value)) {
                $this->errors[] = "Konfigurationswert $setting ist leer";
            } else {
                $results[] = "$setting: ✓ gesetzt";
            }
        }

        // PHP-Erweiterungen prüfen
        $requiredExtensions = ['pdo', 'pdo_sqlite', 'json', 'mbstring'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                throw new Exception("PHP-Erweiterung '$ext' ist erforderlich");
            }
            $results[] = "PHP $ext: ✓ verfügbar";
        }

        return $results;
    }

    /**
     * System testen
     */
    private function setupTest()
    {
        $results = [];

        // Datenbank-Test
        try {
            $stats = $this->db->getStats();
            $results[] = "Datenbank-Test: ✓ " . $stats['customers'] . " Kunden, " . $stats['services'] . " Services";
        } catch (Exception $e) {
            throw new Exception('Datenbank-Test fehlgeschlagen: ' . $e->getMessage());
        }

        // API-Test (vereinfacht)
        $testUrl = APP_URL . '/backend/api.php/system/health';
        $health = $this->testApiEndpoint($testUrl);

        if ($health) {
            $results[] = "API-Test: ✓ Endpunkt erreichbar";
        } else {
            $this->errors[] = "API-Test: ✗ Endpunkt nicht erreichbar ($testUrl)";
        }

        return $results;
    }

    /**
     * Schutz-Dateien erstellen
     */
    private function createProtectionFiles()
    {
        // .htaccess für data-Verzeichnis
        $dataHtaccess = __DIR__ . '/data/.htaccess';
        file_put_contents($dataHtaccess, "Order Deny,Allow\nDeny from all\n");

        // .htaccess für logs-Verzeichnis
        $logsHtaccess = __DIR__ . '/logs/.htaccess';
        file_put_contents($logsHtaccess, "Order Deny,Allow\nDeny from all\n");

        // index.php für Verzeichnisse
        $indexContent = "<?php\nheader('HTTP/1.0 403 Forbidden');\ndie('Zugriff verweigert');\n";

        file_put_contents(__DIR__ . '/data/index.php', $indexContent);
        file_put_contents(__DIR__ . '/logs/index.php', $indexContent);

        if (!is_dir(__DIR__ . '/uploads')) {
            mkdir(__DIR__ . '/uploads', 0755, true);
        }
        file_put_contents(__DIR__ . '/uploads/index.php', $indexContent);
    }

    /**
     * API-Endpunkt testen
     */
    private function testApiEndpoint($url)
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);

        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            return false;
        }

        $data = json_decode($result, true);
        return isset($data['success']) && $data['success'] === true;
    }

    /**
     * HTML-Ausgabe Methoden
     */
    private function outputHeader()
    {
        echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Car Service - Setup</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6; 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #2c3e50;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            border-radius: 0 5px 5px 0;
        }
        .step-title {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .result {
            margin: 10px 0;
            padding: 8px 12px;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .summary {
            margin-top: 40px;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .summary.success {
            background: #d4edda;
            border: 2px solid #28a745;
        }
        .summary.error {
            background: #f8d7da;
            border: 2px solid #dc3545;
        }
        .next-steps {
            margin-top: 30px;
            padding: 20px;
            background: #e3f2fd;
            border-radius: 8px;
        }
        code {
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: "Courier New", monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚗 Mobile Car Service</h1>
            <h2>Backend-Setup</h2>
            <p>Installation und Konfiguration des Buchungssystems</p>
        </div>';
    }

    private function outputStep($step, $description)
    {
        echo "<div class='step'>
                <div class='step-title'>📋 $description</div>";
    }

    private function outputResult($results)
    {
        if (is_array($results)) {
            foreach ($results as $result) {
                echo "<div class='result success'>$result</div>";
            }
        } else {
            echo "<div class='result success'>$results</div>";
        }
        echo "</div>";
    }

    private function outputError($error)
    {
        echo "<div class='result error'>✗ $error</div></div>";
    }

    private function outputSummary()
    {
        $hasErrors = !empty($this->errors);
        $summaryClass = $hasErrors ? 'error' : 'success';
        $summaryIcon = $hasErrors ? '❌' : '✅';
        $summaryText = $hasErrors ? 'Setup mit Fehlern abgeschlossen' : 'Setup erfolgreich abgeschlossen!';

        echo "<div class='summary $summaryClass'>
                <h2>$summaryIcon $summaryText</h2>";

        if ($hasErrors) {
            echo "<h3>Gefundene Probleme:</h3><ul>";
            foreach ($this->errors as $error) {
                echo "<li>$error</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>Ihr Mobile Car Service Backend ist erfolgreich installiert und konfiguriert.</p>";
        }

        echo "</div>";

        if (!$hasErrors) {
            $this->outputNextSteps();
        }

        echo "</div></body></html>";
    }

    private function outputNextSteps()
    {
        echo "<div class='next-steps'>
                <h3>🚀 Nächste Schritte:</h3>
                <ol>
                    <li><strong>Frontend testen:</strong> Öffnen Sie <code>" . APP_URL . "/index.html</code></li>
                    <li><strong>API testen:</strong> Rufen Sie <code>" . APP_URL . "/backend/api.php/system/health</code> auf</li>
                    <li><strong>Erste Buchung:</strong> Testen Sie eine Probebuchung über das Frontend</li>
                    <li><strong>E-Mails konfigurieren:</strong> Passen Sie die SMTP-Einstellungen in <code>config.php</code> an</li>
                    <li><strong>Sicherheit:</strong> Löschen Sie diese Setup-Datei nach der Installation</li>
                </ol>
                
                <h3>📁 Wichtige Dateien:</h3>
                <ul>
                    <li><code>backend/config.php</code> - Hauptkonfiguration</li>
                    <li><code>backend/data/database.sqlite</code> - Datenbank</li>
                    <li><code>backend/logs/</code> - Log-Dateien</li>
                </ul>
                
                <h3>🔧 Konfiguration anpassen:</h3>
                <p>Bearbeiten Sie <code>backend/config.php</code> um Firmenadresse, E-Mail-Einstellungen und andere Parameter anzupassen.</p>
            </div>";
    }
}

// Setup ausführen
try {
    $setup = new Setup();
    $setup->run();
} catch (Exception $e) {
    echo "<div style='color: red; padding: 20px; border: 2px solid red; margin: 20px;'>
            <h2>❌ Kritischer Setup-Fehler</h2>
            <p><strong>Fehler:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p><strong>Datei:</strong> " . $e->getFile() . "</p>
            <p><strong>Zeile:</strong> " . $e->getLine() . "</p>
          </div>";
}
