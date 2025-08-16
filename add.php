<?php
// add_column.php - Fügt die fehlende sort_order Spalte hinzu
require_once 'config/config.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Füge sort_order Spalte hinzu
    $db->exec("ALTER TABLE services ADD COLUMN sort_order INTEGER DEFAULT 0");
    echo "✅ Spalte 'sort_order' wurde hinzugefügt!<br>";
} catch (Exception $e) {
    // Spalte existiert schon oder anderer Fehler
    echo "Info: " . $e->getMessage() . "<br>";
}

// Setze sort_order Werte
$db->exec("UPDATE services SET sort_order = id WHERE sort_order IS NULL OR sort_order = 0");
echo "✅ sort_order Werte gesetzt!<br>";

// Zeige alle Spalten der services Tabelle
echo "<h3>Aktuelle Tabellenstruktur:</h3>";
$stmt = $db->query("PRAGMA table_info(services)");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
foreach ($columns as $col) {
    echo $col['name'] . " (" . $col['type'] . ")\n";
}
echo "</pre>";

echo "<br><a href='/'>→ Zurück zur Buchung</a>";
