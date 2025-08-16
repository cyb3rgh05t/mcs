<?php
// index.php - Hauptdatei mit neuer Step-Reihenfolge
session_start();

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/BookingManager.php';
require_once 'classes/SecurityManager.php';

$database = new Database();
$bookingManager = new BookingManager($database->getConnection());

// Session-Timeout prüfen
SecurityManager::checkSessionTimeout();

// NEUE Step-Reihenfolge:
// 1. Datum
// 2. Uhrzeit
// 3. Kundendaten & Entfernungsprüfung (NEU POSITION)
// 4. Leistungen (NEU POSITION)
// 5. Zusammenfassung
// 6. Bestätigung

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$step = max(1, min(6, $step));

// Session initialisieren
if (!isset($_SESSION['booking'])) {
    $_SESSION['booking'] = [];
}

// Error handling
$errors = [];
$success = false;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!SecurityManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sicherheitsfehler. Bitte versuchen Sie es erneut.';
    }

    // Honeypot Check
    if (!empty($_POST['website'])) {
        SecurityManager::logSecurityEvent('honeypot_triggered', ['step' => $step]);
        $errors[] = 'Ungültige Anfrage erkannt.';
    }

    if (empty($errors)) {
        switch ($step) {
            case 1:
                // Datum validieren
                if (isset($_POST['selected_date'])) {
                    $selectedDate = $_POST['selected_date'];
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
                        $_SESSION['booking']['date'] = $selectedDate;
                        header('Location: ?step=2');
                        exit;
                    } else {
                        $errors[] = 'Ungültiges Datumsformat.';
                    }
                }
                break;

            case 2:
                // Zeit validieren
                if (isset($_POST['appointment_id'])) {
                    $appointmentId = intval($_POST['appointment_id']);
                    if ($bookingManager->isAppointmentAvailable($appointmentId)) {
                        $_SESSION['booking']['appointment_id'] = $appointmentId;

                        // Hole Zeit-Details
                        $stmt = $database->getConnection()->prepare("SELECT time FROM appointments WHERE id = ?");
                        $stmt->execute([$appointmentId]);
                        $_SESSION['booking']['time'] = $stmt->fetchColumn();

                        header('Location: ?step=3'); // Jetzt zu Kundendaten
                        exit;
                    } else {
                        $errors[] = 'Dieser Termin ist nicht mehr verfügbar.';
                    }
                }
                break;

            case 3:
                // NEUE POSITION: Kundendaten & Entfernungsprüfung
                if (
                    !empty($_POST['name']) && !empty($_POST['email']) &&
                    !empty($_POST['phone']) && !empty($_POST['address'])
                ) {

                    // Validierung
                    $name = SecurityManager::validateInput($_POST['name'], 'name');
                    $email = SecurityManager::validateInput($_POST['email'], 'email');
                    $phone = SecurityManager::validateInput($_POST['phone'], 'phone');
                    $address = SecurityManager::validateAddress($_POST['address']);

                    if ($name && $email && $phone && $address) {
                        // Entfernungsberechnung
                        $distance = isset($_POST['calculated_distance']) ? floatval($_POST['calculated_distance']) : 0;

                        // Wenn keine Entfernung berechnet wurde, schätzen
                        if ($distance <= 0) {
                            $distance = 15; // Fallback-Schätzung
                        }

                        // Entfernungsprüfung - Absolute Obergrenze
                        if ($distance > TRAVEL_ABSOLUTE_MAX_DISTANCE) {
                            $errors[] = 'Ihre Adresse liegt leider außerhalb unseres Servicegebiets (max. ' . TRAVEL_ABSOLUTE_MAX_DISTANCE . ' km).';
                        } else {
                            // Speichere Kundendaten
                            $_SESSION['booking']['customer'] = [
                                'name' => $name,
                                'email' => $email,
                                'phone' => $phone,
                                'address' => $address,
                                'notes' => $_POST['notes'] ?? ''
                            ];
                            $_SESSION['booking']['distance'] = $distance;

                            // Berechne maximale Entfernung basierend auf später gewählten Services
                            // Dies wird in Step 4 nochmal geprüft
                            $_SESSION['booking']['max_allowed_distance'] = TRAVEL_MAX_DISTANCE_LARGE;

                            header('Location: ?step=4'); // Weiter zu Leistungen
                            exit;
                        }
                    } else {
                        $errors[] = 'Bitte füllen Sie alle Pflichtfelder korrekt aus.';
                    }
                }
                break;

            case 4:
                // NEUE POSITION: Leistungen mit Entfernungsvalidierung
                if (!empty($_POST['services']) && is_array($_POST['services'])) {
                    $serviceIds = array_map('intval', $_POST['services']);

                    // Berechne Gesamtpreis der Services
                    $servicesTotal = 0;
                    $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
                    $stmt = $database->getConnection()->prepare("SELECT SUM(price) FROM services WHERE id IN ($placeholders) AND active = 1");
                    $stmt->execute($serviceIds);
                    $servicesTotal = $stmt->fetchColumn() ?: 0;

                    // Hole gespeicherte Entfernung
                    $distance = $_SESSION['booking']['distance'] ?? 0;

                    // Entfernungsvalidierung basierend auf Leistungssumme
                    $maxDistance = ($servicesTotal >= TRAVEL_MIN_SERVICE_AMOUNT) ?
                        TRAVEL_MAX_DISTANCE_LARGE :
                        TRAVEL_MAX_DISTANCE_SMALL;

                    if ($distance > $maxDistance) {
                        if ($servicesTotal < TRAVEL_MIN_SERVICE_AMOUNT) {
                            $errors[] = 'Bei einer Leistungssumme unter ' . number_format(TRAVEL_MIN_SERVICE_AMOUNT, 2) .
                                '€ ist die maximale Anfahrtsentfernung ' . TRAVEL_MAX_DISTANCE_SMALL . ' km. ' .
                                'Ihre Entfernung beträgt ' . number_format($distance, 1) . ' km. ' .
                                'Bitte wählen Sie zusätzliche Leistungen oder kontaktieren Sie uns direkt.';
                        } else {
                            $errors[] = 'Ihre Adresse liegt außerhalb unseres Servicegebiets für diese Leistungen.';
                        }
                    } else {
                        // Berechne Anfahrtskosten mit neuer Logik
                        $travelCost = 0;
                        if ($servicesTotal >= TRAVEL_MIN_SERVICE_AMOUNT) {
                            // Bei Leistungen >= 59.90€: normale Berechnung mit 10km gratis
                            if ($distance > TRAVEL_FREE_KM) {
                                $chargeableDistance = $distance - TRAVEL_FREE_KM;
                                $travelCost = $chargeableDistance * TRAVEL_COST_PER_KM;
                            }
                        }
                        // Bei Leistungen < 59.90€: Anfahrt ist komplett gratis (max 10km)

                        $_SESSION['booking']['services'] = $serviceIds;
                        $_SESSION['booking']['services_total'] = $servicesTotal;
                        $_SESSION['booking']['travel_cost'] = $travelCost;
                        $_SESSION['booking']['total_price'] = $servicesTotal + $travelCost;

                        // Berechne benötigte Dauer
                        $totalDuration = $bookingManager->calculateTotalDuration($serviceIds);
                        $_SESSION['booking']['duration'] = $totalDuration;

                        header('Location: ?step=5');
                        exit;
                    }
                } else {
                    $errors[] = 'Bitte wählen Sie mindestens eine Leistung aus.';
                }
                break;

            case 5:
                // Bestätigung
                if (isset($_POST['accept_terms']) && $_POST['accept_terms'] === 'on') {
                    // Nochmalige Validierung aller Daten
                    if (
                        !isset($_SESSION['booking']['appointment_id']) ||
                        !isset($_SESSION['booking']['customer']) ||
                        !isset($_SESSION['booking']['services'])
                    ) {
                        $errors[] = 'Ihre Session ist abgelaufen. Bitte starten Sie die Buchung erneut.';
                        $_SESSION['booking'] = [];
                        echo '<script>window.location.href = "?step=1";</script>';
                        break;
                    }

                    try {
                        $bookingId = $bookingManager->createBooking(
                            $_SESSION['booking']['appointment_id'],
                            $_SESSION['booking']['customer'],
                            $_SESSION['booking']['services'],
                            $_SESSION['booking']['distance'],
                            $_SESSION['booking']['total_price']
                        );

                        $_SESSION['booking']['id'] = $bookingId;

                        // Send confirmation emails
                        require_once 'classes/EmailManager.php';
                        $emailManager = new EmailManager();
                        $bookingDetails = $bookingManager->getBookingDetails($bookingId);
                        $emailManager->sendBookingConfirmation($bookingDetails);

                        // Log successful booking
                        SecurityManager::logSecurityEvent('booking_completed', [
                            'booking_id' => $bookingId,
                            'customer_email' => $_SESSION['booking']['customer']['email'],
                            'total_price' => $_SESSION['booking']['total_price']
                        ]);

                        header('Location: ?step=6');
                        exit;
                    } catch (Exception $e) {
                        $errors[] = 'Fehler beim Erstellen der Buchung. Bitte versuchen Sie es erneut.';
                        SecurityManager::logSecurityEvent('booking_creation_failed', [
                            'error' => $e->getMessage(),
                            'session_data' => $_SESSION['booking']
                        ]);
                    }
                }
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termin buchen - <?= defined('BUSINESS_NAME') ? BUSINESS_NAME : 'MCS Mobile Car Solutions' ?></title>
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Meta-Tags für bessere Security -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' https://maps.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://maps.googleapis.com;">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
</head>

<body>
    <div class="container">
        <header class="header">
            <div class="logo">
                <img src="assets/images/mcs-logo.png" alt="MCS Logo">
            </div>
            <nav class="nav">
                <a href="#" class="nav-link">START</a>
                <a href="#" class="nav-link">LEISTUNGEN & PREISE</a>
                <a href="#" class="nav-link">FAQ</a>
                <a href="#" class="nav-link">ÜBER UNS</a>
                <a href="#" class="nav-link">KONTAKT</a>
            </nav>
            <a href="#" class="btn-primary">JETZT BUCHEN</a>
        </header>

        <main class="main-content">
            <div class="booking-container">
                <div class="progress-bar">
                    <div class="progress-step <?= $step >= 1 ? 'active' : '' ?>">1. Datum</div>
                    <div class="progress-step <?= $step >= 2 ? 'active' : '' ?>">2. Uhrzeit</div>
                    <div class="progress-step <?= $step >= 3 ? 'active' : '' ?>">3. Kundendaten</div>
                    <div class="progress-step <?= $step >= 4 ? 'active' : '' ?>">4. Leistungen</div>
                    <div class="progress-step <?= $step >= 5 ? 'active' : '' ?>">5. Zusammenfassung</div>
                    <div class="progress-step <?= $step >= 6 ? 'active' : '' ?>">6. Bestätigung</div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="error-messages">
                        <?php foreach ($errors as $error): ?>
                            <div class="error-message"><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php
                    // JavaScript für Scroll zu Fehlern
                    if (!empty($errors)) {
                        echo '<script>window.scrollTo({ top: 0, behavior: "smooth" });</script>';
                        echo '<script>window.currentStep = ' . $step . ';</script>';
                    }
                    ?>
                <?php endif; ?>

                <div class="booking-content">
                    <?php
                    switch ($step) {
                        case 1:
                            include 'views/step1_date.php';
                            break;
                        case 2:
                            include 'views/step2_time.php';
                            break;
                        case 3:
                            include 'views/step3_customer.php'; // NEUE POSITION
                            break;
                        case 4:
                            include 'views/step4_services.php'; // NEUE POSITION
                            break;
                        case 5:
                            include 'views/step5_summary.php';
                            break;
                        case 6:
                            include 'views/step6_confirmation.php';
                            break;
                        default:
                            include 'views/step1_date.php';
                    }
                    ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Google Maps API Key für JavaScript verfügbar machen
        window.GOOGLE_MAPS_API_KEY = '<?= defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '' ?>';

        // CSRF Token für JavaScript verfügbar machen
        window.CSRF_TOKEN = '<?= SecurityManager::generateCSRFToken() ?>';

        // Neue Konfigurationswerte für JavaScript
        window.TRAVEL_CONFIG = {
            costPerKm: <?= TRAVEL_COST_PER_KM ?>,
            freeKm: <?= TRAVEL_FREE_KM ?>,
            minServiceAmount: <?= TRAVEL_MIN_SERVICE_AMOUNT ?>,
            maxDistanceSmall: <?= TRAVEL_MAX_DISTANCE_SMALL ?>,
            maxDistanceLarge: <?= TRAVEL_MAX_DISTANCE_LARGE ?>,
            absoluteMaxDistance: <?= TRAVEL_ABSOLUTE_MAX_DISTANCE ?>
        };

        // Error Handling für bessere UX
        window.addEventListener('load', function() {
            // Auto-scroll zu Fehlermeldungen
            const errorMessages = document.querySelector('.error-messages');
            if (errorMessages) {
                errorMessages.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });
    </script>
    <script src="assets/js/booking.js"></script>
</body>

</html>