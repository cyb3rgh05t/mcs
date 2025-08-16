<?php
// fix.php - Quick Fix für alle aktuellen Probleme
session_start();

// Lösche alte Session-Daten die Probleme verursachen könnten
if (isset($_SESSION['booking']['services']) && empty($_SESSION['booking']['services'])) {
    unset($_SESSION['booking']['services']);
}
if (isset($_SESSION['booking']['duration'])) {
    unset($_SESSION['booking']['duration']);
}

// Redirect basierend auf Parameter
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'reset':
        // Session komplett zurücksetzen
        session_destroy();
        session_start();
        echo "✅ Session zurückgesetzt. <a href='/'>Zur Startseite</a>";
        break;

    case 'admin':
        // Admin-Session zurücksetzen
        unset($_SESSION['admin_logged_in']);
        unset($_SESSION['admin_login_time']);
        echo "✅ Admin-Session zurückgesetzt. <a href='/admin'>Zum Admin-Panel</a>";
        break;

    default:
        // Fix anwenden und zur init_data weiterleiten
        header('Location: init_data.php');
        exit;
}
