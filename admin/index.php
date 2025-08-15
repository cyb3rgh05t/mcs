<?php
// admin/index.php - Vollständiges Admin-Panel mit Sicherheit
session_start();

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/BookingManager.php';
require_once '../classes/SecurityManager.php';

// Admin-Authentifizierung
function checkAdminLogin()
{
    if (isset($_POST['password'])) {
        $password = $_POST['password'];
        $admin_password_hash = defined('ADMIN_PASSWORD_HASH') ? ADMIN_PASSWORD_HASH : password_hash('admin123', PASSWORD_DEFAULT);

        if (password_verify($password, $admin_password_hash)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_login_time'] = time();
            SecurityManager::logSecurityEvent('admin_login_success');
            return true;
        } else {
            SecurityManager::logSecurityEvent('admin_login_failed', ['password_attempt' => substr($password, 0, 3) . '***']);
            return false;
        }
    }
    return false;
}

// Logout-Handler
if (isset($_GET['logout'])) {
    SecurityManager::logSecurityEvent('admin_logout');
    session_destroy();
    header('Location: /admin/');
    exit;
}

// Login versuchen
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (!checkAdminLogin()) {
        $login_error = 'Ungültiges Passwort';
    }
}

// Prüfe Login-Status
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Session-Timeout prüfen (2 Stunden)
if ($is_logged_in && isset($_SESSION['admin_login_time'])) {
    if ((time() - $_SESSION['admin_login_time']) > 7200) {
        session_destroy();
        $is_logged_in = false;
        $login_error = 'Session abgelaufen. Bitte melden Sie sich erneut an.';
    }
}

// Login-Formular anzeigen
if (!$is_logged_in) {
?>
    <!DOCTYPE html>
    <html lang="de">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MCS Admin - Login</title>
        <link rel="stylesheet" href="../assets/css/style.css">
        <style>
            .login-container {
                max-width: 400px;
                margin: 100px auto;
                background: rgba(255, 255, 255, 0.05);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 20px;
                padding: 40px;
            }

            .login-title {
                text-align: center;
                color: #ff6b35;
                font-size: 28px;
                margin-bottom: 30px;
            }

            .login-error {
                background: rgba(220, 53, 69, 0.1);
                border: 1px solid #dc3545;
                color: #ff6666;
                padding: 10px 15px;
                border-radius: 5px;
                margin-bottom: 20px;
                text-align: center;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <div class="login-container">
                <h2 class="login-title">🔐 MCS Admin</h2>

                <?php if ($login_error): ?>
                    <div class="login-error"><?= htmlspecialchars($login_error) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="password" class="form-label">Admin-Passwort</label>
                        <input type="password" id="password" name="password" class="form-input" required autofocus>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn-primary" style="width: 100%;">Anmelden</button>
                    </div>
                </form>

                <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #999;">
                    Standard-Passwort: admin123<br>
                    <small>(Bitte in config/config.php ändern!)</small>
                </div>
            </div>
        </div>
    </body>

    </html>
<?php
    exit;
}

// Admin ist eingeloggt - lade Daten
$database = new Database();
$bookingManager = new BookingManager($database);
$db = $database->getConnection();

// Handle Admin-Aktionen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF-Schutz für Admin-Aktionen
    if (!SecurityManager::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Sicherheitsfehler. Bitte versuchen Sie es erneut.';
    } else {
        try {
            switch ($_POST['action']) {
                case 'update_booking_status':
                    $bookingManager->updateBookingStatus($_POST['booking_id'], $_POST['status']);
                    $success_message = 'Buchungsstatus aktualisiert.';
                    break;

                case 'add_service':
                    $stmt = $db->prepare("INSERT INTO services (name, description, price, duration) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$_POST['name'], $_POST['description'], $_POST['price'], $_POST['duration']]);
                    $success_message = 'Service hinzugefügt.';
                    break;

                case 'update_service':
                    $stmt = $db->prepare("UPDATE services SET name = ?, description = ?, price = ?, duration = ?, active = ? WHERE id = ?");
                    $stmt->execute([$_POST['name'], $_POST['description'], $_POST['price'], $_POST['duration'], $_POST['active'], $_POST['service_id']]);
                    $success_message = 'Service aktualisiert.';
                    break;

                case 'generate_time_slots':
                    $slots_created = $bookingManager->generateTimeSlots($_POST['start_date'], $_POST['end_date']);
                    $success_message = "$slots_created neue Termine erstellt.";
                    break;

                case 'backup_database':
                    $backup_file = $database->backup();
                    if ($backup_file) {
                        $success_message = 'Backup erfolgreich erstellt: ' . basename($backup_file);
                    } else {
                        $error_message = 'Backup fehlgeschlagen.';
                    }
                    break;

                default:
                    $error_message = 'Unbekannte Aktion.';
            }
        } catch (Exception $e) {
            $error_message = 'Fehler: ' . $e->getMessage();
            SecurityManager::logSecurityEvent('admin_action_failed', [
                'action' => $_POST['action'],
                'error' => $e->getMessage()
            ]);
        }
    }

    // Redirect um doppelte Submissions zu vermeiden
    $redirect_tab = $_POST['redirect_tab'] ?? 'dashboard';
    header('Location: ?tab=' . $redirect_tab . (isset($success_message) ? '&success=1' : '') . (isset($error_message) ? '&error=1' : ''));
    exit;
}

$current_tab = $_GET['tab'] ?? 'dashboard';

// Lade Statistiken
$stats = $database->getStats();

// Messages
$success_message = isset($_GET['success']) ? 'Aktion erfolgreich ausgeführt.' : '';
$error_message = isset($_GET['error']) ? 'Ein Fehler ist aufgetreten.' : '';
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCS Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-nav {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            border-bottom: 1px solid #333;
            padding-bottom: 20px;
            flex-wrap: wrap;
        }

        .admin-nav a {
            color: #ccc;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .admin-nav a.active,
        .admin-nav a:hover {
            background: #ff6b35;
            color: white;
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #ff6b35;
            margin-bottom: 8px;
            display: block;
        }

        .stat-label {
            color: #ccc;
            font-size: 14px;
            font-weight: 500;
        }

        .admin-table {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .admin-table th,
        .admin-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .admin-table th {
            background: rgba(255, 107, 53, 0.2);
            font-weight: bold;
            color: #ff6b35;
        }

        .admin-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .status-confirmed {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .status-completed {
            background: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
        }

        .status-cancelled {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .admin-form {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .admin-form h3 {
            color: #ff6b35;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .success-message {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid #28a745;
            color: #4caf50;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error-message {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid #dc3545;
            color: #ff6666;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .quick-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .quick-action {
            background: linear-gradient(45deg, #ff6b35, #ff8c42);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.3s ease;
        }

        .quick-action:hover {
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header">
            <div class="logo">
                <h2 style="color: #ff6b35;">🛠️ MCS Admin Panel</h2>
            </div>
            <div style="display: flex; gap: 15px; align-items: center;">
                <span style="color: #ccc;">Willkommen, Admin</span>
                <a href="?logout=1" class="btn-secondary">Abmelden</a>
            </div>
        </header>

        <main class="main-content">
            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="success-message">✅ <?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="error-message">❌ <?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <!-- Navigation -->
            <div class="admin-nav">
                <a href="?tab=dashboard" class="<?= $current_tab === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
                <a href="?tab=bookings" class="<?= $current_tab === 'bookings' ? 'active' : '' ?>">📅 Buchungen</a>
                <a href="?tab=services" class="<?= $current_tab === 'services' ? 'active' : '' ?>">🚗 Leistungen</a>
                <a href="?tab=schedule" class="<?= $current_tab === 'schedule' ? 'active' : '' ?>">⏰ Terminplan</a>
                <a href="?tab=settings" class="<?= $current_tab === 'settings' ? 'active' : '' ?>">⚙️ Einstellungen</a>
            </div>

            <?php if ($current_tab === 'dashboard'): ?>
                <!-- Dashboard -->
                <h2 style="color: #ff6b35; margin-bottom: 20px;">📊 Dashboard</h2>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-number"><?= $stats['pending_bookings'] ?></span>
                        <div class="stat-label">Offene Buchungen</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?= $stats['confirmed_bookings'] ?></span>
                        <div class="stat-label">Bestätigte Termine</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?= number_format($stats['monthly_revenue'], 0) ?>€</span>
                        <div class="stat-label">Umsatz (30 Tage)</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?= $stats['available_slots'] ?></span>
                        <div class="stat-label">Freie Termine</div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="?tab=bookings" class="quick-action">📋 Buchungen verwalten</a>
                    <a href="?tab=schedule" class="quick-action">⏰ Termine generieren</a>
                    <a href="../" class="quick-action" target="_blank">🌐 Website ansehen</a>
                </div>

                <!-- Recent Bookings -->
                <?php
                $recent_bookings = $bookingManager->getBookingsForAdmin(10);
                ?>
                <div class="admin-form">
                    <h3>📋 Neueste Buchungen</h3>
                    <?php if (!empty($recent_bookings)): ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Kunde</th>
                                    <th>Termin</th>
                                    <th>Preis</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recent_bookings, 0, 5) as $booking): ?>
                                    <tr>
                                        <td>#<?= str_pad($booking['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                        <td><?= htmlspecialchars($booking['customer_name']) ?></td>
                                        <td><?= date('d.m.Y H:i', strtotime($booking['date'] . ' ' . $booking['time'])) ?></td>
                                        <td><?= number_format($booking['total_price'], 2) ?> €</td>
                                        <td><span class="status-badge status-<?= $booking['status'] ?>"><?= ucfirst($booking['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #ccc;">Keine Buchungen vorhanden.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($current_tab === 'bookings'): ?>
                <!-- Bookings Management -->
                <h2 style="color: #ff6b35; margin-bottom: 20px;">📅 Buchungsverwaltung</h2>

                <?php
                $all_bookings = $bookingManager->getBookingsForAdmin(100);
                ?>

                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kunde</th>
                            <th>Kontakt</th>
                            <th>Termin</th>
                            <th>Leistungen</th>
                            <th>Preis</th>
                            <th>Status</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_bookings as $booking): ?>
                            <tr>
                                <td>#<?= str_pad($booking['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($booking['customer_name']) ?></strong><br>
                                    <small style="color: #999;"><?= htmlspecialchars($booking['customer_address']) ?></small>
                                </td>
                                <td>
                                    <small>📧 <?= htmlspecialchars($booking['customer_email']) ?></small><br>
                                    <small>📞 <?= htmlspecialchars($booking['customer_phone']) ?></small>
                                </td>
                                <td>
                                    <?= date('d.m.Y', strtotime($booking['date'])) ?><br>
                                    <small><?= $booking['time'] ?> Uhr</small>
                                </td>
                                <td>
                                    <small><?= htmlspecialchars($booking['services'] ?: 'Keine') ?></small><br>
                                    <small style="color: #999;"><?= $booking['service_count'] ?> Service(s)</small>
                                </td>
                                <td><strong><?= number_format($booking['total_price'], 2) ?> €</strong></td>
                                <td>
                                    <span class="status-badge status-<?= $booking['status'] ?>">
                                        <?= ucfirst($booking['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="update_booking_status">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="redirect_tab" value="bookings">
                                        <select name="status" onchange="this.form.submit()" style="background: #333; color: white; border: 1px solid #666; padding: 5px; border-radius: 4px;">
                                            <option value="pending" <?= $booking['status'] === 'pending' ? 'selected' : '' ?>>Offen</option>
                                            <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Bestätigt</option>
                                            <option value="completed" <?= $booking['status'] === 'completed' ? 'selected' : '' ?>>Abgeschlossen</option>
                                            <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>Storniert</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'services'): ?>
                <!-- Services Management -->
                <h2 style="color: #ff6b35; margin-bottom: 20px;">🚗 Leistungen verwalten</h2>

                <!-- Add New Service -->
                <div class="admin-form">
                    <h3>➕ Neue Leistung hinzufügen</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="add_service">
                        <input type="hidden" name="redirect_tab" value="services">

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Service-Name</label>
                                <input type="text" name="name" class="form-input" required maxlength="100">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Preis (€)</label>
                                <input type="number" name="price" step="0.01" min="0" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Dauer (Min.)</label>
                                <input type="number" name="duration" min="1" class="form-input" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Beschreibung</label>
                            <textarea name="description" class="form-input" rows="3" maxlength="500"></textarea>
                        </div>

                        <button type="submit" class="btn-primary">Service hinzufügen</button>
                    </form>
                </div>

                <!-- Services List -->
                <?php
                $stmt = $db->prepare("SELECT * FROM services ORDER BY active DESC, name ASC");
                $stmt->execute();
                $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Beschreibung</th>
                            <th>Preis</th>
                            <th>Dauer</th>
                            <th>Status</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr style="<?= !$service['active'] ? 'opacity: 0.6;' : '' ?>">
                                <td><strong><?= htmlspecialchars($service['name']) ?></strong></td>
                                <td><?= htmlspecialchars($service['description']) ?></td>
                                <td><?= number_format($service['price'], 2) ?> €</td>
                                <td><?= $service['duration'] ?> Min.</td>
                                <td>
                                    <span class="status-badge <?= $service['active'] ? 'status-confirmed' : 'status-cancelled' ?>">
                                        <?= $service['active'] ? 'Aktiv' : 'Inaktiv' ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="update_service">
                                        <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                        <input type="hidden" name="redirect_tab" value="services">
                                        <input type="hidden" name="name" value="<?= htmlspecialchars($service['name']) ?>">
                                        <input type="hidden" name="description" value="<?= htmlspecialchars($service['description']) ?>">
                                        <input type="hidden" name="price" value="<?= $service['price'] ?>">
                                        <input type="hidden" name="duration" value="<?= $service['duration'] ?>">
                                        <input type="hidden" name="active" value="<?= $service['active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                                            <?= $service['active'] ? 'Deaktivieren' : 'Aktivieren' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'schedule'): ?>
                <!-- Schedule Management -->
                <h2 style="color: #ff6b35; margin-bottom: 20px;">⏰ Terminplanung</h2>

                <!-- Generate Time Slots -->
                <div class="admin-form">
                    <h3>📅 Neue Termine erstellen</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="generate_time_slots">
                        <input type="hidden" name="redirect_tab" value="schedule">

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Startdatum</label>
                                <input type="date" name="start_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Enddatum</label>
                                <input type="date" name="end_date" class="form-input" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                            </div>
                        </div>

                        <p style="color: #ccc; font-size: 14px; margin-bottom: 15px;">
                            ℹ️ Erstellt Termine von <?= defined('WORKING_HOURS_START') ? WORKING_HOURS_START : 8 ?>:00 bis <?= defined('WORKING_HOURS_END') ? WORKING_HOURS_END : 17 ?>:00 Uhr, Montag bis Samstag
                        </p>

                        <button type="submit" class="btn-primary">Termine generieren</button>
                    </form>
                </div>

                <!-- Upcoming Appointments -->
                <?php
                $stmt = $db->prepare("
                    SELECT a.*, b.customer_name, b.total_price, b.status as booking_status
                    FROM appointments a 
                    LEFT JOIN bookings b ON a.id = b.appointment_id
                    WHERE a.date >= date('now')
                    ORDER BY a.date ASC, a.time ASC
                    LIMIT 50
                ");
                $stmt->execute();
                $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <div class="admin-form">
                    <h3>📋 Kommende Termine (nächste 50)</h3>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Uhrzeit</th>
                                <th>Status</th>
                                <th>Kunde</th>
                                <th>Wert</th>
                                <th>Buchungsstatus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td><?= date('d.m.Y', strtotime($appointment['date'])) ?></td>
                                    <td><?= $appointment['time'] ?> Uhr</td>
                                    <td>
                                        <span class="status-badge <?= $appointment['status'] === 'available' ? 'status-pending' : 'status-confirmed' ?>">
                                            <?= $appointment['status'] === 'available' ? 'Frei' : 'Gebucht' ?>
                                        </span>
                                    </td>
                                    <td><?= $appointment['customer_name'] ? htmlspecialchars($appointment['customer_name']) : '-' ?></td>
                                    <td><?= $appointment['total_price'] ? number_format($appointment['total_price'], 2) . ' €' : '-' ?></td>
                                    <td>
                                        <?php if ($appointment['booking_status']): ?>
                                            <span class="status-badge status-<?= $appointment['booking_status'] ?>">
                                                <?= ucfirst($appointment['booking_status']) ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <!-- Settings -->
                <h2 style="color: #ff6b35; margin-bottom: 20px;">⚙️ Systemeinstellungen</h2>

                <!-- System Info -->
                <div class="admin-form">
                    <h3>📊 Systeminformationen</h3>
                    <div class="form-row">
                        <div>
                            <strong>PHP Version:</strong> <?= PHP_VERSION ?><br>
                            <strong>SQLite Version:</strong> <?= SQLite3::version()['versionString'] ?><br>
                            <strong>System:</strong> <?= php_uname('s') . ' ' . php_uname('r') ?>
                        </div>
                        <div>
                            <strong>Database Größe:</strong> <?= file_exists(DB_PATH) ? number_format(filesize(DB_PATH) / 1024, 2) . ' KB' : 'N/A' ?><br>
                            <strong>Log Dir:</strong> <?= defined('LOG_DIR') ? (is_writable(LOG_DIR) ? '✅ Schreibbar' : '❌ Nicht schreibbar') : 'Nicht definiert' ?><br>
                            <strong>Backup Dir:</strong> <?= defined('BACKUP_DIR') ? (is_writable(BACKUP_DIR) ? '✅ Schreibbar' : '❌ Nicht schreibbar') : 'Nicht definiert' ?>
                        </div>
                    </div>
                </div>

                <!-- Database Backup -->
                <div class="admin-form">
                    <h3>💾 Datenbank-Backup</h3>
                    <p style="color: #ccc; margin-bottom: 15px;">Erstellen Sie regelmäßig Backups Ihrer Datenbank.</p>

                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="backup_database">
                        <input type="hidden" name="redirect_tab" value="settings">
                        <button type="submit" class="btn-primary">🗄️ Backup erstellen</button>
                    </form>

                    <!-- List existing backups -->
                    <?php
                    $backup_dir = defined('BACKUP_DIR') ? BACKUP_DIR : __DIR__ . '/../backups';
                    if (is_dir($backup_dir)) {
                        $backups = glob($backup_dir . '/backup_*.sql');
                        if (!empty($backups)) {
                            rsort($backups); // Neueste zuerst
                            echo '<h4 style="color: #ff6b35; margin-top: 20px;">📋 Vorhandene Backups:</h4>';
                            echo '<ul style="color: #ccc;">';
                            foreach (array_slice($backups, 0, 10) as $backup) {
                                $filename = basename($backup);
                                $size = number_format(filesize($backup) / 1024, 2);
                                $date = date('d.m.Y H:i', filemtime($backup));
                                echo "<li>$filename ($size KB) - $date</li>";
                            }
                            echo '</ul>';
                        }
                    }
                    ?>
                </div>

                <!-- Configuration -->
                <div class="admin-form">
                    <h3>🔧 Konfiguration</h3>
                    <div class="form-row">
                        <div>
                            <strong>Geschäftsname:</strong> <?= defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Nicht konfiguriert' ?><br>
                            <strong>Arbeitszeiten:</strong> <?= defined('WORKING_HOURS_START') ? WORKING_HOURS_START : '8' ?>:00 - <?= defined('WORKING_HOURS_END') ? WORKING_HOURS_END : '17' ?>:00 Uhr<br>
                            <strong>Anfahrtskosten:</strong> <?= defined('TRAVEL_COST_PER_KM') ? number_format(TRAVEL_COST_PER_KM, 2) : '0.50' ?> €/km
                        </div>
                        <div>
                            <strong>Google Maps API:</strong> <?= defined('GOOGLE_MAPS_API_KEY') && !empty(GOOGLE_MAPS_API_KEY) ? '✅ Konfiguriert' : '❌ Nicht konfiguriert' ?><br>
                            <strong>E-Mail:</strong> <?= defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'Nicht konfiguriert' ?><br>
                            <strong>Admin E-Mail:</strong> <?= defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'Nicht konfiguriert' ?>
                        </div>
                    </div>
                    <p style="color: #ccc; font-size: 14px; margin-top: 15px;">
                        💡 Einstellungen können in der Datei <code>config/config.php</code> angepasst werden.
                    </p>
                </div>

                <!-- Security Log -->
                <div class="admin-form">
                    <h3>🔒 Sicherheitsprotokoll (letzte 10 Einträge)</h3>
                    <?php
                    $log_file = defined('LOG_DIR') ? LOG_DIR . '/security.log' : __DIR__ . '/../logs/security.log';
                    if (file_exists($log_file)) {
                        $logs = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        $logs = array_slice(array_reverse($logs), 0, 10);

                        if (!empty($logs)) {
                            echo '<div style="background: #222; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto;">';
                            foreach ($logs as $log) {
                                $entry = json_decode($log, true);
                                if ($entry) {
                                    $color = strpos($entry['event'], 'failed') !== false ? '#ff6666' : '#4caf50';
                                    echo '<div style="color: ' . $color . '; margin-bottom: 5px;">';
                                    echo htmlspecialchars($entry['timestamp'] . ' - ' . $entry['event'] . ' (' . $entry['ip'] . ')');
                                    echo '</div>';
                                }
                            }
                            echo '</div>';
                        } else {
                            echo '<p style="color: #ccc;">Keine Sicherheitsereignisse protokolliert.</p>';
                        }
                    } else {
                        echo '<p style="color: #ccc;">Sicherheitsprotokoll nicht verfügbar.</p>';
                    }
                    ?>
                </div>

            <?php endif; ?>
        </main>
    </div>

    <script>
        // Auto-refresh für Dashboard alle 30 Sekunden
        if (window.location.search.includes('tab=dashboard') || !window.location.search.includes('tab=')) {
            setTimeout(() => {
                if (!document.hidden) {
                    window.location.reload();
                }
            }, 30000);
        }

        // Bestätigungsdialoge für kritische Aktionen
        document.querySelectorAll('select[name="status"]').forEach(select => {
            select.addEventListener('change', function(e) {
                if (this.value === 'cancelled') {
                    if (!confirm('Buchung wirklich stornieren? Der Termin wird wieder freigegeben.')) {
                        e.preventDefault();
                        this.value = this.defaultValue;
                        return false;
                    }
                }
            });
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
    </script>
</body>

</html>