<?php
// index.php - Hauptbuchungsseite mit verbesserter Sicherheit
session_start();

// Include all required classes
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/BookingManager.php';
require_once 'classes/EmailManager.php';
require_once 'classes/SecurityManager.php';

// Helper-Funktionen für sichere Environment-Checks
function isDevelopment()
{
    return (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ||
        in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']) ||
        in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);
}

$database = new Database();
$bookingManager = new BookingManager($database);
$emailManager = new EmailManager();

$step = $_GET['step'] ?? 1;
$errors = [];

// FIX: Verbesserte POST-Verarbeitung mit besserer Fehlerbehandlung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // FIX: Honeypot-Validierung zuerst (gegen Bots)
    if (!SecurityManager::validateHoneypot($_POST['website'] ?? '')) {
        $errors[] = 'Spam-Schutz aktiviert. Bitte versuchen Sie es später erneut.';
        SecurityManager::logSecurityEvent('honeypot_spam_detected');
    }

    // CSRF Protection - FIX: Bessere Fehlerbehandlung
    if (empty($errors) && !SecurityManager::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        // FIX: Weniger aggressive CSRF-Behandlung
        if (isset($_POST['csrf_token']) && !empty($_POST['csrf_token'])) {
            $errors[] = 'Sicherheitstoken abgelaufen. Bitte versuchen Sie es erneut.';
            SecurityManager::logSecurityEvent('csrf_token_expired');
        } else {
            $errors[] = 'Sicherheitsfehler. Bitte versuchen Sie es erneut.';
            SecurityManager::logSecurityEvent('csrf_token_missing');
        }
    }

    // Rate Limiting - FIX: Angepasste Limits
    $client_ip = SecurityManager::getClientIP();
    if (empty($errors) && !SecurityManager::rateLimitCheck($client_ip, 15, 300)) {
        $errors[] = 'Zu viele Anfragen. Bitte warten Sie 5 Minuten.';
        SecurityManager::logSecurityEvent('rate_limit_exceeded', ['ip' => $client_ip]);
    }
}

// Session-Daten verwalten
if (!isset($_SESSION['booking'])) {
    $_SESSION['booking'] = [];
}

// FIX: POST-Verarbeitung nur wenn keine Sicherheitsfehler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    // Sanitize all input data
    $_POST = SecurityManager::sanitizeInput($_POST);

    // FIX: SQL-Injection Detection
    foreach ($_POST as $key => $value) {
        if (SecurityManager::detectSQLInjection($value)) {
            $errors[] = 'Ungültige Eingabe erkannt.';
            SecurityManager::logSecurityEvent('sql_injection_detected', ['field' => $key]);
            break;
        }
    }

    // Nur verarbeiten wenn keine Sicherheitsprobleme
    if (empty($errors)) {
        switch ($step) {
            case 1: // Datum gewählt
                if (empty($_POST['selected_date'])) {
                    $errors[] = 'Bitte wählen Sie ein Datum aus.';
                } else {
                    $_SESSION['booking']['date'] = $_POST['selected_date'];
                    header('Location: ?step=2');
                    exit;
                }
                break;

            case 2: // Uhrzeit gewählt
                if (empty($_POST['appointment_id'])) {
                    $errors[] = 'Bitte wählen Sie eine Uhrzeit aus.';
                } else {
                    $_SESSION['booking']['appointment_id'] = $_POST['appointment_id'];
                    $_SESSION['booking']['time'] = $_POST['selected_time'];
                    header('Location: ?step=3');
                    exit;
                }
                break;

            case 3: // Services gewählt
                if (empty($_POST['services'])) {
                    $errors[] = 'Bitte wählen Sie mindestens eine Leistung aus.';
                } else {
                    $_SESSION['booking']['services'] = $_POST['services'];
                    header('Location: ?step=4');
                    exit;
                }
                break;

            case 4: // Kundendaten
                $required = ['name', 'email', 'phone', 'address'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        $errors[] = "Bitte füllen Sie das Feld '$field' aus.";
                    }
                }

                // Email validation
                if (!empty($_POST['email']) && !SecurityManager::validateEmail($_POST['email'])) {
                    $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
                }

                // Phone validation
                if (!empty($_POST['phone']) && !SecurityManager::validatePhone($_POST['phone'])) {
                    $errors[] = 'Bitte geben Sie eine gültige Telefonnummer ein.';
                }

                // Privacy checkbox
                if (empty($_POST['privacy'])) {
                    $errors[] = 'Bitte stimmen Sie der Datenschutzerklärung zu.';
                }

                if (empty($errors)) {
                    $_SESSION['booking']['customer'] = [
                        'name' => $_POST['name'],
                        'email' => $_POST['email'],
                        'phone' => $_POST['phone'],
                        'address' => $_POST['address'],
                        'notes' => $_POST['notes'] ?? ''
                    ];

                    // Entfernung berechnen
                    $_SESSION['booking']['distance'] = $bookingManager->calculateDistance($_POST['address']);
                    $_SESSION['booking']['total_price'] = $bookingManager->calculateTotalPrice(
                        $_SESSION['booking']['services'],
                        $_SESSION['booking']['distance']
                    );

                    header('Location: ?step=5');
                    exit;
                }
                break;

            case 5: // Bestätigung
                // Terms checkbox
                if (empty($_POST['terms'])) {
                    $errors[] = 'Bitte bestätigen Sie die AGB.';
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
                    $bookingDetails = $bookingManager->getBookingDetails($bookingId);
                    $emailManager->sendBookingConfirmation($bookingDetails);

                    // FIX: Log successful booking
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

    <!-- FIX: Meta-Tags für bessere Security -->
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
                    <div class="progress-step <?= $step >= 3 ? 'active' : '' ?>">3. Leistungen</div>
                    <div class="progress-step <?= $step >= 4 ? 'active' : '' ?>">4. Kundendaten</div>
                    <div class="progress-step <?= $step >= 5 ? 'active' : '' ?>">5. Bestätigung</div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="error-messages">
                        <?php foreach ($errors as $error): ?>
                            <div class="error"><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- FIX: Development Helper für CSRF/Rate Limit Reset -->
                <?php if (isDevelopment() && !empty($errors)): ?>
                    <div style="background: rgba(255, 193, 7, 0.1); border: 1px solid #ffc107; padding: 10px; margin: 10px 0; border-radius: 5px;">
                        <small style="color: #856404;">
                            <strong>Development-Modus:</strong>
                            <a href="?reset_security=1" style="color: #ff6b35;">Security-Checks zurücksetzen</a>
                        </small>
                    </div>

                    <?php
                    // FIX: Reset für Development
                    if (isset($_GET['reset_security'])) {
                        SecurityManager::resetRateLimit();
                        $_SESSION['csrf_token_time'] = time();
                        echo '<script>window.location.href = "?step=' . $step . '";</script>';
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
                            include 'views/step3_services.php';
                            break;
                        case 4:
                            include 'views/step4_customer.php';
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

        // FIX: Error Handling für bessere UX
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