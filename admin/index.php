<?php
// admin/index.php
session_start();

// Einfache Admin-Authentifizierung
$admin_password = 'admin123'; // In Produktion sollte dies sicherer sein!

if ($_POST['password'] ?? '' === $admin_password) {
    $_SESSION['admin_logged_in'] = true;
}

if ($_GET['logout'] ?? false) {
    unset($_SESSION['admin_logged_in']);
    header('Location: /admin/');
    exit;
}

if (!($_SESSION['admin_logged_in'] ?? false)) {
?>
    <!DOCTYPE html>
    <html lang="de">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MCS Admin - Login</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>

    <body>
        <div class="container">
            <div class="booking-container" style="max-width: 400px; margin: 100px auto;">
                <h2 class="step-title">Admin Login</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="password" class="form-label">Passwort</label>
                        <input type="password" id="password" name="password" class="form-input" required>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn-primary">Anmelden</button>
                    </div>
                </form>
            </div>
        </div>
    </body>

    </html>
<?php
    exit;
}

require_once '../config/database.php';
require_once '../classes/BookingManager.php';

$database = new Database();
$bookingManager = new BookingManager($database);
$db = $database->getConnection();

// Handle Actions
if ($_POST['action'] ?? false) {
    switch ($_POST['action']) {
        case 'update_booking_status':
            $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$_POST['status'], $_POST['booking_id']]);
            break;

        case 'add_service':
            $stmt = $db->prepare("INSERT INTO services (name, description, price, duration) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['description'], $_POST['price'], $_POST['duration']]);
            break;

        case 'update_service':
            $stmt = $db->prepare("UPDATE services SET name = ?, description = ?, price = ?, duration = ?, active = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['description'], $_POST['price'], $_POST['duration'], $_POST['active'], $_POST['service_id']]);
            break;

        case 'generate_time_slots':
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];

            $current = new DateTime($start_date);
            $end = new DateTime($end_date);

            while ($current <= $end) {
                $dayOfWeek = $current->format('N');

                // Nur Montag bis Samstag (1-6)
                if ($dayOfWeek <= 6) {
                    for ($hour = 8; $hour <= 17; $hour++) {
                        $time = sprintf('%02d:00', $hour);
                        $date = $current->format('Y-m-d');

                        // Prüfen ob Slot bereits existiert
                        $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE date = ? AND time = ?");
                        $stmt->execute([$date, $time]);

                        if ($stmt->fetchColumn() == 0) {
                            $stmt = $db->prepare("INSERT INTO appointments (date, time) VALUES (?, ?)");
                            $stmt->execute([$date, $time]);
                        }
                    }
                }
                $current->modify('+1 day');
            }
            break;
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=' . ($_POST['redirect_tab'] ?? 'bookings'));
    exit;
}

$current_tab = $_GET['tab'] ?? 'bookings';

// Get Statistics
$stats = [];

$stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
$stmt->execute();
$stats['pending_bookings'] = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
$stmt->execute();
$stats['confirmed_bookings'] = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT SUM(total_price) FROM bookings WHERE status IN ('confirmed', 'completed') AND date(created_at) >= date('now', '-30 days')");
$stmt->execute();
$stats['monthly_revenue'] = $stmt->fetchColumn() ?: 0;

$stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'available' AND date >= date('now')");
$stmt->execute();
$stats['available_slots'] = $stmt->fetchColumn();
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
        }

        .admin-nav a {
            color: #ccc;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .admin-nav a.active,
        .admin-nav a:hover {
            background: #ff6b35;
            color: white;
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
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #ff6b35;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #ccc;
            font-size: 14px;
        }

        .admin-table {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            overflow: hidden;
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
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
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
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .admin-form h3 {
            color: #ff6b35;
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header">
            <div class="logo">
                <h2 style="color: #ff6b35;">MCS Admin Panel</h2>
            </div>
            <div>
                <a href="?logout=1" class="btn-secondary">Abmelden</a>
            </div>
        </header>

        <main class="main-content">
            <!-- Navigation -->
            <div class="admin-nav">
                <a href="?tab=bookings" class="<?= $current_tab === 'bookings' ? 'active' : '' ?>">Buchungen</a>
                <a href="?tab=services" class="<?= $current_tab === 'services' ? 'active' : '' ?>">Leistungen</a>
                <a href="?tab=schedule" class="<?= $current_tab === 'schedule' ? 'active' : '' ?>">Terminplan</a>
                <a href="?tab=statistics" class="<?= $current_tab === 'statistics' ? 'active' : '' ?>">Statistiken</a>
            </div>

            <!-- Statistics Dashboard -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['pending_bookings'] ?></div>
                    <div class="stat-label">Offene Buchungen</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['confirmed_bookings'] ?></div>
                    <div class="stat-label">Bestätigte Termine</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['monthly_revenue'], 0) ?>€</div>
                    <div class="stat-label">Umsatz (30 Tage)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['available_slots'] ?></div>
                    <div class="stat-label">Freie Termine</div>
                </div>
            </div>

            <?php if ($current_tab === 'bookings'): ?>
                <!-- Bookings Management -->
                <h3 style="color: #ff6b35; margin-bottom: 20px;">Buchungsübersicht</h3>

                <?php
                $stmt = $db->prepare("
                    SELECT b.*, a.date, a.time, 
                           GROUP_CONCAT(s.name) as services
                    FROM bookings b 
                    JOIN appointments a ON b.appointment_id = a.id 
                    LEFT JOIN booking_services bs ON b.id = bs.booking_id
                    LEFT JOIN services s ON bs.service_id = s.id
                    GROUP BY b.id
                    ORDER BY a.date DESC, a.time DESC
                ");
                $stmt->execute();
                $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kunde</th>
                            <th>Termin</th>
                            <th>Leistungen</th>
                            <th>Preis</th>
                            <th>Status</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>#<?= str_pad($booking['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($booking['customer_name']) ?></strong><br>
                                    <small><?= htmlspecialchars($booking['customer_email']) ?></small><br>
                                    <small><?= htmlspecialchars($booking['customer_phone']) ?></small>
                                </td>
                                <td>
                                    <?= date('d.m.Y', strtotime($booking['date'])) ?><br>
                                    <small><?= $booking['time'] ?> Uhr</small>
                                </td>
                                <td><?= htmlspecialchars($booking['services'] ?: 'Keine') ?></td>
                                <td><?= number_format($booking['total_price'], 2) ?> €</td>
                                <td>
                                    <span class="status-badge status-<?= $booking['status'] ?>">
                                        <?= ucfirst($booking['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_booking_status">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="redirect_tab" value="bookings">
                                        <select name="status" onchange="this.form.submit()" style="background: #333; color: white; border: 1px solid #666; padding: 5px;">
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
                <h3 style="color: #ff6b35; margin-bottom: 20px;">Leistungen verwalten</h3>

                <!-- Add New Service -->
                <div class="admin-form">
                    <h3>Neue Leistung hinzufügen</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_service">
                        <input type="hidden" name="redirect_tab" value="services">

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Preis (€)</label>
                                <input type="number" name="price" step="0.01" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Dauer (Min.)</label>
                                <input type="number" name="duration" class="form-input" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Beschreibung</label>
                            <textarea name="description" class="form-input" rows="3"></textarea>
                        </div>

                        <button type="submit" class="btn-primary">Leistung hinzufügen</button>
                    </form>
                </div>

                <!-- Services List -->
                <?php
                $stmt = $db->prepare("SELECT * FROM services ORDER BY name");
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
                            <tr>
                                <td><?= htmlspecialchars($service['name']) ?></td>
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
                                        <input type="hidden" name="action" value="update_service">
                                        <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                        <input type="hidden" name="redirect_tab" value="services">
                                        <input type="hidden" name="name" value="<?= htmlspecialchars($service['name']) ?>">
                                        <input type="hidden" name="description" value="<?= htmlspecialchars($service['description']) ?>">
                                        <input type="hidden" name="price" value="<?= $service['price'] ?>">
                                        <input type="hidden" name="duration" value="<?= $service['duration'] ?>">
                                        <input type="hidden" name="active" value="<?= $service['active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn-secondary" style="padding: 5px 10px; font-size: 12px;">
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
                <h3 style="color: #ff6b35; margin-bottom: 20px;">Terminplan verwalten</h3>

                <!-- Generate Time Slots -->
                <div class="admin-form">
                    <h3>Neue Termine erstellen</h3>
                    <form method="POST">
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
                            Erstellt Termine von 8:00 bis 17:00 Uhr, Montag bis Samstag
                        </p>

                        <button type="submit" class="btn-primary">Termine generieren</button>
                    </form>
                </div>

                <!-- Upcoming Appointments -->
                <?php
                $stmt = $db->prepare("
                    SELECT a.*, b.customer_name, b.total_price
                    FROM appointments a 
                    LEFT JOIN bookings b ON a.id = b.appointment_id
                    WHERE a.date >= date('now')
                    ORDER BY a.date, a.time
                    LIMIT 50
                ");
                $stmt->execute();
                $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

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
                                    <span class="status-badge status-<?= $appointment['status'] === 'available' ? 'pending' : 'confirmed' ?>">
                                        <?= $appointment['status'] === 'available' ? 'Frei' : 'Gebucht' ?>
                                    </span>
                                </td>
                                <td><?= $appointment['customer_name'] ? htmlspecialchars($appointment['customer_name']) : '-' ?></td>
                                <td><?= $appointment['total_price'] ? number_format($appointment['total_price'], 2) . ' €' : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php else: ?>
                <!-- Statistics -->
                <h3 style="color: #ff6b35; margin-bottom: 20px;">Detaillierte Statistiken</h3>

                <div class="admin-form">
                    <h3>Umsatz nach Monaten</h3>
                    <?php
                    $stmt = $db->prepare("
                        SELECT 
                            strftime('%Y-%m', created_at) as month,
                            COUNT(*) as bookings,
                            SUM(total_price) as revenue
                        FROM bookings 
                        WHERE status IN ('confirmed', 'completed')
                        GROUP BY strftime('%Y-%m', created_at)
                        ORDER BY month DESC
                        LIMIT 12
                    ");
                    $stmt->execute();
                    $monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Monat</th>
                                <th>Buchungen</th>
                                <th>Umsatz</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_stats as $stat): ?>
                                <tr>
                                    <td><?= date('m/Y', strtotime($stat['month'] . '-01')) ?></td>
                                    <td><?= $stat['bookings'] ?></td>
                                    <td><?= number_format($stat['revenue'], 2) ?> €</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="admin-form">
                    <h3>Beliebteste Leistungen</h3>
                    <?php
                    $stmt = $db->prepare("
                        SELECT 
                            s.name,
                            COUNT(*) as bookings,
                            SUM(s.price) as revenue
                        FROM booking_services bs
                        JOIN services s ON bs.service_id = s.id
                        JOIN bookings b ON bs.booking_id = b.id
                        WHERE b.status IN ('confirmed', 'completed')
                        GROUP BY s.id, s.name
                        ORDER BY bookings DESC
                    ");
                    $stmt->execute();
                    $service_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Leistung</th>
                                <th>Buchungen</th>
                                <th>Umsatz</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($service_stats as $stat): ?>
                                <tr>
                                    <td><?= htmlspecialchars($stat['name']) ?></td>
                                    <td><?= $stat['bookings'] ?></td>
                                    <td><?= number_format($stat['revenue'], 2) ?> €</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </main>
    </div>
</body>

</html>