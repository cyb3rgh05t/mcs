<?php
// debug.php - Debug-Seite für Anfahrtskosten-Tests
session_start();
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/BookingManager.php';

$database = new Database();
$db = $database->getConnection();
$bookingManager = new BookingManager($db);

// Test-Szenarien für Anfahrtskosten
$testScenarios = [
    ['distance' => 5, 'services_total' => 30],
    ['distance' => 10, 'services_total' => 30],
    ['distance' => 15, 'services_total' => 30],
    ['distance' => 5, 'services_total' => 60],
    ['distance' => 10, 'services_total' => 60],
    ['distance' => 15, 'services_total' => 60],
    ['distance' => 20, 'services_total' => 60],
    ['distance' => 25, 'services_total' => 60],
    ['distance' => 30, 'services_total' => 60],
    ['distance' => 35, 'services_total' => 60],
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Anfahrtskosten-Logik</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #1a1a1a;
            color: #fff;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            color: #ff6b35;
            border-bottom: 2px solid #ff6b35;
            padding-bottom: 10px;
        }

        .section {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
            border: 1px solid rgba(255, 107, 53, 0.3);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        th {
            background: rgba(255, 107, 53, 0.2);
            font-weight: bold;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .success {
            color: #4CAF50;
        }

        .warning {
            color: #FFC107;
        }

        .error {
            color: #f44336;
        }

        .info {
            background: rgba(33, 150, 243, 0.1);
            padding: 10px;
            border-left: 4px solid #2196F3;
            margin: 10px 0;
        }

        .test-result {
            padding: 5px 10px;
            border-radius: 5px;
            display: inline-block;
        }

        .test-result.free {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }

        .test-result.paid {
            background: rgba(255, 193, 7, 0.2);
            color: #FFC107;
        }

        .test-result.blocked {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }
    </style>
</head>

<body>
    <h1>🔧 Debug: Anfahrtskosten-Logik</h1>

    <!-- Konfiguration -->
    <div class="section">
        <h2>⚙️ Aktuelle Konfiguration</h2>
        <table>
            <tr>
                <th>Parameter</th>
                <th>Wert</th>
                <th>Beschreibung</th>
            </tr>
            <tr>
                <td>TRAVEL_COST_PER_KM</td>
                <td class="warning"><?= TRAVEL_COST_PER_KM ?> €</td>
                <td>Kosten pro Kilometer (nach Freikilometern)</td>
            </tr>
            <tr>
                <td>TRAVEL_FREE_KM</td>
                <td class="success"><?= TRAVEL_FREE_KM ?> km</td>
                <td>Kostenlose Kilometer bei Leistungen ≥ <?= TRAVEL_MIN_SERVICE_AMOUNT ?>€</td>
            </tr>
            <tr>
                <td>TRAVEL_MIN_SERVICE_AMOUNT</td>
                <td class="info"><?= TRAVEL_MIN_SERVICE_AMOUNT ?> €</td>
                <td>Mindestbetrag für erweiterten Radius</td>
            </tr>
            <tr>
                <td>TRAVEL_MAX_DISTANCE_SMALL</td>
                <td><?= TRAVEL_MAX_DISTANCE_SMALL ?> km</td>
                <td>Max. Entfernung bei Leistungen < <?= TRAVEL_MIN_SERVICE_AMOUNT ?>€</td>
            </tr>
            <tr>
                <td>TRAVEL_MAX_DISTANCE_LARGE</td>
                <td><?= TRAVEL_MAX_DISTANCE_LARGE ?> km</td>
                <td>Max. Entfernung bei Leistungen ≥ <?= TRAVEL_MIN_SERVICE_AMOUNT ?>€</td>
            </tr>
            <tr>
                <td>TRAVEL_ABSOLUTE_MAX_DISTANCE</td>
                <td class="error"><?= TRAVEL_ABSOLUTE_MAX_DISTANCE ?> km</td>
                <td>Absolute Obergrenze (niemals überschreitbar)</td>
            </tr>
        </table>
    </div>

    <!-- Test-Szenarien -->
    <div class="section">
        <h2>🧪 Test-Szenarien</h2>
        <table>
            <thead>
                <tr>
                    <th>Entfernung</th>
                    <th>Leistungssumme</th>
                    <th>Status</th>
                    <th>Anfahrtskosten</th>
                    <th>Berechnung</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($testScenarios as $scenario):
                    $distance = $scenario['distance'];
                    $servicesTotal = $scenario['services_total'];

                    try {
                        $validation = $bookingManager->validateBookingDistance($distance, $servicesTotal);
                        if ($validation['valid']) {
                            $travelCost = $bookingManager->calculateTravelCost($distance, $servicesTotal);
                            $status = 'Buchbar';
                            $statusClass = $travelCost > 0 ? 'paid' : 'free';
                            $calculation = '';

                            if ($servicesTotal < TRAVEL_MIN_SERVICE_AMOUNT) {
                                $calculation = "Unter {TRAVEL_MIN_SERVICE_AMOUNT}€: Komplett kostenlos bis {TRAVEL_MAX_DISTANCE_SMALL}km";
                            } else {
                                if ($distance <= TRAVEL_FREE_KM) {
                                    $calculation = "Erste {TRAVEL_FREE_KM}km gratis";
                                } else {
                                    $chargeableKm = $distance - TRAVEL_FREE_KM;
                                    $calculation = "{$chargeableKm}km × {TRAVEL_COST_PER_KM}€ = " . number_format($travelCost, 2) . "€";
                                }
                            }
                        } else {
                            $travelCost = null;
                            $status = 'Nicht buchbar';
                            $statusClass = 'blocked';
                            $calculation = $validation['message'];
                        }
                    } catch (Exception $e) {
                        $travelCost = null;
                        $status = 'Fehler';
                        $statusClass = 'blocked';
                        $calculation = $e->getMessage();
                    }
                ?>
                    <tr>
                        <td><strong><?= $distance ?> km</strong></td>
                        <td><?= number_format($servicesTotal, 2) ?> €</td>
                        <td>
                            <span class="test-result <?= $statusClass ?>">
                                <?= $status ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($travelCost !== null): ?>
                                <strong><?= number_format($travelCost, 2) ?> €</strong>
                            <?php else: ?>
                                <span class="error">-</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= $calculation ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Regeln-Übersicht -->
    <div class="section">
        <h2>📋 Geschäftsregeln</h2>
        <div class="info">
            <h3>Regel 1: Leistungen unter <?= TRAVEL_MIN_SERVICE_AMOUNT ?>€</h3>
            <ul>
                <li>Maximale Entfernung: <strong><?= TRAVEL_MAX_DISTANCE_SMALL ?> km</strong></li>
                <li>Anfahrtskosten: <strong class="success">Komplett kostenlos</strong></li>
                <li>Begründung: Kleine Aufträge, lokaler Service</li>
            </ul>
        </div>

        <div class="info">
            <h3>Regel 2: Leistungen ab <?= TRAVEL_MIN_SERVICE_AMOUNT ?>€</h3>
            <ul>
                <li>Maximale Entfernung: <strong><?= TRAVEL_MAX_DISTANCE_LARGE ?> km</strong></li>
                <li>Erste <?= TRAVEL_FREE_KM ?> km: <strong class="success">Kostenlos</strong></li>
                <li>Ab <?= TRAVEL_FREE_KM ?> km: <strong><?= TRAVEL_COST_PER_KM ?>€ pro km</strong></li>
                <li>Begründung: Größere Aufträge rechtfertigen weitere Anfahrt</li>
            </ul>
        </div>

        <div class="info">
            <h3>Regel 3: Absolute Obergrenze</h3>
            <ul>
                <li>Maximale Entfernung: <strong class="error"><?= TRAVEL_ABSOLUTE_MAX_DISTANCE ?> km</strong></li>
                <li>Gilt unabhängig von der Leistungssumme</li>
                <li>Begründung: Wirtschaftlichkeit und Zeitmanagement</li>
            </ul>
        </div>
    </div>

    <!-- Live-Test -->
    <div class="section">
        <h2>🚀 Live-Test</h2>
        <form method="get" action="">
            <table style="width: auto;">
                <tr>
                    <td><label for="test_distance">Entfernung (km):</label></td>
                    <td><input type="number" id="test_distance" name="test_distance" value="<?= $_GET['test_distance'] ?? 15 ?>" min="0" max="50" step="0.5" style="background: #333; color: #fff; padding: 5px; border: 1px solid #666;"></td>
                </tr>
                <tr>
                    <td><label for="test_services">Leistungssumme (€):</label></td>
                    <td><input type="number" id="test_services" name="test_services" value="<?= $_GET['test_services'] ?? 60 ?>" min="0" max="500" step="0.01" style="background: #333; color: #fff; padding: 5px; border: 1px solid #666;"></td>
                </tr>
                <tr>
                    <td colspan="2">
                        <button type="submit" style="background: #ff6b35; color: #fff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px;">Berechnen</button>
                    </td>
                </tr>
            </table>
        </form>

        <?php if (isset($_GET['test_distance']) && isset($_GET['test_services'])):
            $testDistance = floatval($_GET['test_distance']);
            $testServices = floatval($_GET['test_services']);

            try {
                $validation = $bookingManager->validateBookingDistance($testDistance, $testServices);
                if ($validation['valid']) {
                    $testTravelCost = $bookingManager->calculateTravelCost($testDistance, $testServices);
                    $testTotal = $testServices + $testTravelCost;
        ?>
                    <div style="margin-top: 20px; padding: 20px; background: rgba(76, 175, 80, 0.1); border: 1px solid #4CAF50; border-radius: 5px;">
                        <h3 class="success">✅ Buchung möglich!</h3>
                        <table>
                            <tr>
                                <td>Entfernung:</td>
                                <td><strong><?= number_format($testDistance, 1) ?> km</strong></td>
                            </tr>
                            <tr>
                                <td>Leistungen:</td>
                                <td><?= number_format($testServices, 2) ?> €</td>
                            </tr>
                            <tr>
                                <td>Anfahrtskosten:</td>
                                <td><strong><?= number_format($testTravelCost, 2) ?> €</strong></td>
                            </tr>
                            <tr>
                                <td>Gesamtpreis:</td>
                                <td><strong style="font-size: 1.2em;"><?= number_format($testTotal, 2) ?> €</strong></td>
                            </tr>
                        </table>
                    </div>
                <?php } else { ?>
                    <div style="margin-top: 20px; padding: 20px; background: rgba(244, 67, 54, 0.1); border: 1px solid #f44336; border-radius: 5px;">
                        <h3 class="error">❌ Buchung nicht möglich!</h3>
                        <p><?= htmlspecialchars($validation['message']) ?></p>
                    </div>
                <?php }
            } catch (Exception $e) { ?>
                <div style="margin-top: 20px; padding: 20px; background: rgba(244, 67, 54, 0.1); border: 1px solid #f44336; border-radius: 5px;">
                    <h3 class="error">⚠️ Fehler bei der Berechnung</h3>
                    <p><?= htmlspecialchars($e->getMessage()) ?></p>
                </div>
        <?php }
        endif; ?>
    </div>

    <!-- System-Status -->
    <div class="section">
        <h2>💻 System-Status</h2>
        <div class="info">
            <strong>PHP Version:</strong> <?= phpversion() ?><br>
            <strong>Environment:</strong> <?= defined('ENVIRONMENT') ? ENVIRONMENT : 'Not set' ?><br>
            <strong>Session ID:</strong> <?= session_id() ?><br>
            <strong>Server Time:</strong> <?= date('Y-m-d H:i:s') ?><br>
            <strong>Timezone:</strong> <?= date_default_timezone_get() ?>
        </div>

        <?php
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM services WHERE active = 1");
            $stmt->execute();
            $serviceCount = $stmt->fetchColumn();

            $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'available' AND date >= ?");
            $stmt->execute([date('Y-m-d')]);
            $appointmentCount = $stmt->fetchColumn();

            $stmt = $db->prepare("SELECT COUNT(*) FROM bookings");
            $stmt->execute();
            $bookingCount = $stmt->fetchColumn();
        ?>
            <div class="info">
                <strong>Aktive Services:</strong> <?= $serviceCount ?><br>
                <strong>Verfügbare Termine:</strong> <?= $appointmentCount ?><br>
                <strong>Bisherige Buchungen:</strong> <?= $bookingCount ?>
            </div>
        <?php } catch (Exception $e) {
            echo '<div class="error">Datenbankfehler: ' . $e->getMessage() . '</div>';
        } ?>
    </div>

    <div style="text-align: center; margin-top: 40px; padding: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
        <a href="index.php" style="color: #ff6b35; text-decoration: none; font-size: 18px;">← Zurück zur Anwendung</a>
    </div>
</body>

</html>