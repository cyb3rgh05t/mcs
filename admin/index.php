<?php
// admin/index.php - Admin Panel mit konsistentem Dark Theme
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/BookingManager.php';
require_once '../classes/SecurityManager.php';

// Admin-Login prüfen
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Login-Formular anzeigen
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: index.php');
            exit;
        } else {
            $login_error = 'Falsches Passwort!';
        }
    }
?>
    <!DOCTYPE html>
    <html lang="de">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - MCS</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>

    <body>
        <div class="container">
            <div class="booking-container" style="max-width: 400px; margin: 100px auto;">
                <h2 class="step-title">🔐 Admin Login</h2>

                <?php if (isset($login_error)): ?>
                    <div class="error-message"><?= $login_error ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="password" class="form-label">Admin Passwort</label>
                        <input type="password"
                            name="password"
                            id="password"
                            class="form-input"
                            required
                            autofocus>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn-primary">Einloggen</button>
                        <a href="../" class="btn-secondary">Zurück zur Website</a>
                    </div>
                </form>

                <div class="admin-info">
                    <small>Standard-Passwort: admin123</small>
                </div>
            </div>
        </div>
    </body>

    </html>
<?php
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Admin ist eingeloggt
$database = new Database();
$bookingManager = new BookingManager($database);
$db = $database->getConnection();

// Handle Admin-Aktionen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
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
            }
        } catch (Exception $e) {
            $error_message = 'Fehler: ' . $e->getMessage();
        }
    }

    $redirect_tab = $_POST['redirect_tab'] ?? 'dashboard';
    header('Location: ?tab=' . $redirect_tab . (isset($success_message) ? '&success=1' : '') . (isset($error_message) ? '&error=1' : ''));
    exit;
}

$current_tab = $_GET['tab'] ?? 'dashboard';
$stats = $database->getStats();
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
</head>

<body>
    <div class="container">
        <header class="header">
            <div class="logo">
                <h1>🛠️ MCS Admin Panel</h1>
            </div>
            <div class="nav">
                <span class="admin-user">👤 Admin</span>
                <a href="?logout=1" class="btn-secondary">Abmelden</a>
            </div>
        </header>

        <main class="main-content">
            <div class="booking-container">

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
                    <h2 class="step-title">Dashboard Übersicht</h2>

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

                    <!-- Recent Bookings -->
                    <?php $recent_bookings = $bookingManager->getBookingsForAdmin(10); ?>

                    <div class="admin-form">
                        <h3 class="summary-title">📋 Neueste Buchungen</h3>
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
                                            <td>
                                                <span class="status-badge status-<?= $booking['status'] ?>">
                                                    <?= ucfirst($booking['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="no-data">Keine Buchungen vorhanden.</p>
                        <?php endif; ?>
                    </div>

                <?php elseif ($current_tab === 'bookings'): ?>
                    <!-- Bookings Management -->
                    <h2 class="step-title">Buchungsverwaltung</h2>

                    <?php $all_bookings = $bookingManager->getBookingsForAdmin(100); ?>

                    <div class="admin-form">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Kunde</th>
                                    <th>Kontakt</th>
                                    <th>Termin</th>
                                    <th>Services</th>
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
                                            <small class="text-muted"><?= htmlspecialchars($booking['customer_address']) ?></small>
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
                                            <small><?= htmlspecialchars($booking['services'] ?: 'Keine') ?></small>
                                        </td>
                                        <td><?= number_format($booking['total_price'], 2) ?> €</td>
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
                                                <select name="status" onchange="this.form.submit()" class="form-input" style="width: auto; padding: 5px;">
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
                    </div>

                <?php elseif ($current_tab === 'services'): ?>
                    <!-- Services Management -->
                    <h2 class="step-title">Leistungsverwaltung</h2>

                    <!-- Add New Service -->
                    <div class="admin-form">
                        <h3 class="summary-title">Neue Leistung hinzufügen</h3>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="add_service">
                            <input type="hidden" name="redirect_tab" value="services">

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-input" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Preis (€)</label>
                                    <input type="number" name="price" class="form-input" step="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Dauer (Min)</label>
                                    <input type="number" name="duration" class="form-input" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Beschreibung</label>
                                <textarea name="description" class="form-input" rows="3" required></textarea>
                            </div>

                            <button type="submit" class="btn-primary">Service hinzufügen</button>
                        </form>
                    </div>

                    <!-- Existing Services -->
                    <?php
                    $stmt = $db->prepare("SELECT * FROM services ORDER BY name");
                    $stmt->execute();
                    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <div class="admin-form">
                        <h3 class="summary-title">Bestehende Leistungen</h3>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Beschreibung</th>
                                    <th>Preis</th>
                                    <th>Dauer</th>
                                    <th>Status</th>
                                    <th>Aktion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $service): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($service['name']) ?></td>
                                        <td><?= htmlspecialchars($service['description']) ?></td>
                                        <td><?= number_format($service['price'], 2) ?> €</td>
                                        <td><?= $service['duration'] ?> Min</td>
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
                                                <button type="submit" class="btn-secondary btn-small">
                                                    <?= $service['active'] ? 'Deaktivieren' : 'Aktivieren' ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($current_tab === 'schedule'): ?>
                    <!-- Schedule Management -->
                    <h2 class="step-title">Terminplanung</h2>

                    <!-- Generate Time Slots -->
                    <div class="admin-form">
                        <h3 class="summary-title">Neue Termine erstellen</h3>
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

                            <p class="form-help">
                                ℹ️ Erstellt Termine von <?= WORKING_HOURS_START ?>:00 bis <?= WORKING_HOURS_END ?>:00 Uhr, Montag bis Samstag
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
                        <h3 class="summary-title">Kommende Termine</h3>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Uhrzeit</th>
                                    <th>Status</th>
                                    <th>Kunde</th>
                                    <th>Wert</th>
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
                                        <td><?= $appointment['customer_name'] ?: '-' ?></td>
                                        <td><?= $appointment['total_price'] ? number_format($appointment['total_price'], 2) . ' €' : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php else: ?>
                    <!-- Settings -->
                    <h2 class="step-title">Systemeinstellungen</h2>

                    <!-- System Info -->
                    <div class="admin-form">
                        <h3 class="summary-title">Systeminformationen</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">PHP Version:</span>
                                <span class="info-value"><?= PHP_VERSION ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">SQLite Version:</span>
                                <span class="info-value"><?= SQLite3::version()['versionString'] ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Database Größe:</span>
                                <span class="info-value"><?= file_exists(DB_PATH) ? number_format(filesize(DB_PATH) / 1024, 2) . ' KB' : 'N/A' ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Log Dir:</span>
                                <span class="info-value status-badge <?= is_writable(LOG_DIR) ? 'status-confirmed' : 'status-cancelled' ?>">
                                    <?= is_writable(LOG_DIR) ? '✅ Schreibbar' : '❌ Nicht schreibbar' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Database Backup -->
                    <div class="admin-form">
                        <h3 class="summary-title">Datenbank Backup</h3>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="backup_database">
                            <input type="hidden" name="redirect_tab" value="settings">

                            <p class="form-help">Erstellt ein Backup der kompletten Datenbank im Backup-Verzeichnis.</p>

                            <button type="submit" class="btn-primary">Backup erstellen</button>
                        </form>
                    </div>

                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh für Dashboard
        if (window.location.search.includes('tab=dashboard') || !window.location.search.includes('tab=')) {
            setTimeout(() => {
                if (!document.hidden) {
                    window.location.reload();
                }
            }, 30000);
        }

        // Bestätigungsdialoge
        document.querySelectorAll('select[name="status"]').forEach(select => {
            select.addEventListener('change', function(e) {
                if (this.value === 'cancelled') {
                    if (!confirm('Buchung wirklich stornieren?')) {
                        e.preventDefault();
                        this.value = this.defaultValue;
                        return false;
                    }
                }
            });
        });
    </script>
</body>

</html>