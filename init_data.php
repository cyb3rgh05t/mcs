<?php
// init_data.php - Initialisierung der Datenbank mit Standard-Daten
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/BookingManager.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>MCS - Datenbank Initialisierung</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #1a1a1a; 
            color: #fff; 
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }
        h1 { color: #ff6b35; }
        .success { 
            background: #28a745; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0;
        }
        .error { 
            background: #dc3545; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0;
        }
        .info { 
            background: #17a2b8; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0;
        }
        a {
            color: #ff6b35;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>🚀 MCS Datenbank Initialisierung</h1>";

try {
    $database = new Database();
    $db = $database->getConnection();
    $bookingManager = new BookingManager($database);

    echo "<div class='info'>📊 Starte Datenbank-Initialisierung...</div>";

    // 1. Services einfügen
    echo "<h2>1. Services erstellen</h2>";

    // Prüfe ob Services existieren
    $stmt = $db->prepare("SELECT COUNT(*) FROM services");
    $stmt->execute();
    $serviceCount = $stmt->fetchColumn();

    if ($serviceCount == 0) {
        $services = [
            ['Fahrzeugwäsche Außen', 'Gründliche Außenreinigung mit Hochdruckreiniger und Spezialshampoo', 25.00, 30, 1, 1],
            ['Fahrzeugwäsche Komplett', 'Komplette Außen- und Innenreinigung für perfekte Sauberkeit', 45.00, 60, 1, 2],
            ['Innenraumreinigung', 'Gründliche Reinigung des kompletten Innenraums', 35.00, 45, 1, 3],
            ['Polsterreinigung', 'Professionelle Tiefenreinigung der Sitze und Polster', 40.00, 60, 1, 4],
            ['Motorwäsche', 'Schonende Motorraumreinigung mit Spezialreinigern', 30.00, 30, 1, 5],
            ['Felgenreinigung', 'Intensive Reinigung und Pflege von Felgen und Reifen', 20.00, 30, 1, 6],
            ['Lackpolitur', 'Professionelle Politur für strahlenden Glanz', 50.00, 90, 1, 7],
            ['Lackversiegelung Premium', 'Langzeitschutz mit Nano-Versiegelung', 80.00, 120, 1, 8],
            ['Scheibenreinigung Spezial', 'Kristallklare Scheiben innen und außen', 15.00, 20, 1, 9],
            ['Geruchsneutralisation', 'Beseitigung unangenehmer Gerüche mit Ozon', 35.00, 45, 1, 10]
        ];

        $stmt = $db->prepare("INSERT INTO services (name, description, price, duration, active, sort_order) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($services as $service) {
            $stmt->execute($service);
            echo "<div class='success'>✅ Service erstellt: {$service[0]} - {$service[2]}€</div>";
        }

        echo "<div class='info'>✨ " . count($services) . " Services erfolgreich erstellt!</div>";
    } else {
        echo "<div class='info'>ℹ️ Es existieren bereits $serviceCount Services in der Datenbank.</div>";
    }

    // 2. Termine generieren
    echo "<h2>2. Termine generieren</h2>";

    // Prüfe ob Termine existieren
    $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE date >= date('now')");
    $stmt->execute();
    $appointmentCount = $stmt->fetchColumn();

    if ($appointmentCount < 100) {
        // Generiere Termine für die nächsten 60 Tage
        $startDate = date('Y-m-d', strtotime('+1 day'));
        $endDate = date('Y-m-d', strtotime('+60 days'));

        $slotsCreated = $bookingManager->generateTimeSlots($startDate, $endDate);

        echo "<div class='success'>✅ $slotsCreated neue Termine erstellt für $startDate bis $endDate</div>";
        echo "<div class='info'>📅 Arbeitszeiten: " . WORKING_HOURS_START . ":00 - " . WORKING_HOURS_END . ":00 Uhr</div>";
    } else {
        echo "<div class='info'>ℹ️ Es existieren bereits $appointmentCount zukünftige Termine.</div>";
    }

    // 3. Datenbank-Statistiken
    echo "<h2>3. Datenbank-Statistiken</h2>";

    $tables = ['services', 'appointments', 'bookings', 'booking_services'];
    foreach ($tables as $table) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM $table");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        echo "<div class='info'>📊 Tabelle <strong>$table</strong>: $count Einträge</div>";
    }

    // 4. Teste eine Service-Abfrage
    echo "<h2>4. Service-Test</h2>";
    $services = $bookingManager->getAllServices();
    if (count($services) > 0) {
        echo "<div class='success'>✅ Services können abgerufen werden. " . count($services) . " aktive Services gefunden.</div>";
        echo "<ul>";
        foreach ($services as $service) {
            echo "<li>{$service['name']} - {$service['price']}€ - {$service['duration']} Min.</li>";
        }
        echo "</ul>";
    } else {
        echo "<div class='error'>❌ Keine aktiven Services gefunden!</div>";
    }

    // 5. Session bereinigen
    session_start();
    if (isset($_SESSION['booking'])) {
        unset($_SESSION['booking']);
        echo "<div class='success'>✅ Booking-Session zurückgesetzt</div>";
    }

    echo "<div class='success' style='margin-top: 30px; font-size: 18px;'>
        🎉 <strong>Initialisierung erfolgreich abgeschlossen!</strong>
    </div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Fehler: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "
    <div style='margin-top: 40px; padding: 20px; background: #333; border-radius: 10px;'>
        <h3>🔗 Navigation</h3>
        <p>
            <a href='/'>→ Zur Hauptseite</a><br>
            <a href='/admin'>→ Zum Admin-Panel</a><br>
            <a href='/debug.php'>→ Zum Debug-Panel</a>
        </p>
    </div>
</body>
</html>";
