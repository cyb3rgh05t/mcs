<?php
// config/database.php - Datenbankverbindung und Setup

class Database
{
    private $db_file;
    private $connection;

    public function __construct()
    {
        $this->db_file = defined('DB_PATH') ? DB_PATH : __DIR__ . '/../database/bookings.db';
        $this->connect();
        $this->createTables();
    }

    private function connect()
    {
        try {
            // Erstelle database Ordner falls nicht vorhanden
            $db_dir = dirname($this->db_file);
            if (!file_exists($db_dir)) {
                mkdir($db_dir, 0755, true);
            }

            $this->connection = new PDO('sqlite:' . $this->db_file);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Enable foreign keys
            $this->connection->exec('PRAGMA foreign_keys = ON');
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Datenbankverbindung fehlgeschlagen. Bitte kontaktieren Sie den Administrator.");
        }
    }

    private function createTables()
    {
        // Tabellen mit updated_at von Anfang an
        $sql_appointments = "
        CREATE TABLE IF NOT EXISTS appointments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL,
            time TEXT NOT NULL,
            status TEXT DEFAULT 'available' CHECK(status IN ('available', 'booked', 'blocked')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(date, time)
        )";

        $sql_services = "
        CREATE TABLE IF NOT EXISTS services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT,
            price DECIMAL(10,2) NOT NULL CHECK(price >= 0),
            duration INTEGER DEFAULT 60 CHECK(duration > 0),
            active INTEGER DEFAULT 1 CHECK(active IN (0, 1)),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";

        $sql_bookings = "
        CREATE TABLE IF NOT EXISTS bookings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            appointment_id INTEGER NOT NULL,
            customer_name TEXT NOT NULL,
            customer_email TEXT NOT NULL,
            customer_phone TEXT NOT NULL,
            customer_address TEXT NOT NULL,
            distance DECIMAL(10,2) CHECK(distance >= 0),
            total_price DECIMAL(10,2) NOT NULL CHECK(total_price >= 0),
            status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'confirmed', 'completed', 'cancelled')),
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE RESTRICT
        )";

        $sql_booking_services = "
        CREATE TABLE IF NOT EXISTS booking_services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            booking_id INTEGER NOT NULL,
            service_id INTEGER NOT NULL,
            price_at_booking DECIMAL(10,2) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE RESTRICT,
            UNIQUE(booking_id, service_id)
        )";

        try {
            $this->connection->exec($sql_appointments);
            $this->connection->exec($sql_services);
            $this->connection->exec($sql_bookings);
            $this->connection->exec($sql_booking_services);

            // Prüfe und füge updated_at hinzu falls fehlend (für bestehende DBs)
            $this->ensureUpdatedAtColumns();

            // Erstelle Trigger
            $this->createUpdateTriggers();

            // Standard-Daten einfügen
            $this->insertDefaultServices();
            $this->generateTimeSlots();
        } catch (PDOException $e) {
            error_log("Table creation failed: " . $e->getMessage());
            die("Datenbankinitialisierung fehlgeschlagen. Bitte kontaktieren Sie den Administrator.");
        }
    }

    /**
     * Stellt sicher dass updated_at Spalten existieren
     */
    private function ensureUpdatedAtColumns()
    {
        $tables = ['appointments', 'services', 'bookings'];

        foreach ($tables as $table) {
            try {
                // Prüfe ob updated_at existiert
                $stmt = $this->connection->prepare("PRAGMA table_info($table)");
                $stmt->execute();
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1); // Hole nur Spaltennamen

                if (!in_array('updated_at', $columns)) {
                    // Füge updated_at hinzu
                    $this->connection->exec("ALTER TABLE $table ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP");
                    error_log("Added updated_at column to $table table");
                }
            } catch (PDOException $e) {
                // Spalte existiert bereits oder andere Fehler - ignorieren
                error_log("Could not add updated_at to $table: " . $e->getMessage());
            }
        }
    }

    /**
     * Erstellt Update-Trigger für updated_at
     */
    private function createUpdateTriggers()
    {
        // Lösche alte Trigger falls vorhanden
        $this->connection->exec("DROP TRIGGER IF EXISTS update_appointments_updated_at");
        $this->connection->exec("DROP TRIGGER IF EXISTS update_services_updated_at");
        $this->connection->exec("DROP TRIGGER IF EXISTS update_bookings_updated_at");

        // Erstelle neue Trigger
        $triggers = [
            "CREATE TRIGGER IF NOT EXISTS update_appointments_updated_at 
         AFTER UPDATE ON appointments 
         BEGIN 
            UPDATE appointments SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
         END",

            "CREATE TRIGGER IF NOT EXISTS update_services_updated_at 
         AFTER UPDATE ON services 
         BEGIN 
            UPDATE services SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
         END",

            "CREATE TRIGGER IF NOT EXISTS update_bookings_updated_at 
         AFTER UPDATE ON bookings 
         BEGIN 
            UPDATE bookings SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
         END"
        ];

        foreach ($triggers as $trigger) {
            try {
                $this->connection->exec($trigger);
            } catch (PDOException $e) {
                error_log("Trigger creation warning: " . $e->getMessage());
            }
        }
    }

    private function insertDefaultServices()
    {
        try {
            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM services");
            $stmt->execute();
            $count = $stmt->fetchColumn();

            if ($count == 0) {
                $services = [
                    ['Sorglos-Paket', 'Check + Pflege in einem Termin: Dein Auto wirdtechnisch geprüft und außen frisch gewaschen –perfekt, wenn du es einfach sorglos willst.', 119.00, 45],
                    ['Verkaufsklar-Paket', 'Auto verkaufen? Ich mache dein Fahrzeug sauber, fotografiere es und erstelle ein Profi-Inserat – so verkaufst du schneller und besser', 159.90, 90],
                    ['Check & Wechsel-Paket', 'Der Saisonklassiker: Räderwechsel vor Ort plus ein Sicherheits-Check – alles in einem Termin.', 89.90, 60],
                    ['Diagnose Paket', 'Dein Auto zeigt eine Warnlampe oder machtProbleme? Ich lese den Fehlerspeicher aus, prüfe diewichtigsten Punkte und erkläre dir klar, was wirklichlos ist – damit du weißt, woran du bist.', 39.90, 30],
                    ['Basis Check', 'Dein Auto zeigt eine Warnlampe oder machtProbleme? Ich lese den Fehlerspeicher aus, prüfe diewichtigsten Punkte und erkläre dir klar, was wirklichlos ist – damit du weißt, woran du bist.', 59.90, 30],
                    ['Komfort Check', 'Der umfassende Check: Zusätzlich zur Basis-Prüfunglese ich Fehler aus, kontrolliere Bremsen, Batterieund Unterboden – perfekt, wenn du sicher unterwegssein willst.', 79.90, 120],
                    ['Räderwechsel mobil', 'Ich komme zu dir, wechsel deine Räder direkt Vorort und checke Bremsen & Luftdruck gleich mit – kein Werkstatttermin, kein Schleppen.', 39.90, 60],
                    ['Hilfe beim Fahrzeugkauf', 'Du willst ein Auto kaufen, bist dir aber unsicher? Ich prüfe das Auto gründlich und sage dir ehrlich, ob esden Preis wert ist.', 79.90, 60],
                    ['Hilfe beim Fahrzeugverkauf', 'Ich mache dein Auto verkaufsfertig: Check, Profi Fotos, Preis-Analyse und ein ansprechender Inserat Text.', 59.90, 60],
                    ['Wash & Care PaketeAußenpflege Basic', 'Frischer Glanz: Handwäsche, Felgenreinigung, Trocknen.', 89.90, 60],
                    ['Außen- & Innenpflege Plus', 'Innen & außen sauber: Handwäsche, Innenraumreinigung, Oberflächenpflege.', 149.90, 60],
                    ['Außen- & Innenpflege Premium', 'Das volle Programm: intensive Pflege inkl. Versiegelung, Polster- & Kunststoffpflege.', 89.90, 60],
                    ['Batterie-Service', '(zzgl. Batteriepreis)„Batterie schwach? Ich teste, wechsle und programmiere sie – damit dein Auto sofort wieder startet.', 69.90, 60],
                    ['Scheinwerferaufbereitung', 'Matte Scheinwerfer? Ich schleife, poliere undversiegel sie – für klare Sicht und frische Optik.', 69.90, 60],
                    ['Ersatzteilbeschaffung', '+ Marge - Kein Lust auf Teile suchen? Ich besorge die passenden Ersatzteile und bringe sie dir – ohne Stress.', 14.90, 60]
                ];

                $stmt = $this->connection->prepare("INSERT INTO services (name, description, price, duration) VALUES (?, ?, ?, ?)");
                foreach ($services as $service) {
                    $stmt->execute($service);
                }

                error_log("Standard-Services eingefügt: " . count($services) . " Services");
            }
        } catch (PDOException $e) {
            error_log("Error inserting default services: " . $e->getMessage());
        }
    }

    private function generateTimeSlots()
    {
        try {
            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM appointments");
            $stmt->execute();
            $count = $stmt->fetchColumn();

            if ($count == 0) {
                $working_hours_start = defined('WORKING_HOURS_START') ? WORKING_HOURS_START : 8;
                $working_hours_end = defined('WORKING_HOURS_END') ? WORKING_HOURS_END : 17;
                $working_days = defined('WORKING_DAYS') ? WORKING_DAYS : [1, 2, 3, 4, 5, 6];

                $slots_created = 0;

                // Erstelle Termine für die nächsten 60 Tage
                for ($i = 1; $i <= 60; $i++) {
                    $date = date('Y-m-d', strtotime("+$i days"));
                    $dayOfWeek = date('N', strtotime($date));

                    // Nur an Arbeitstagen
                    if (in_array($dayOfWeek, $working_days)) {
                        for ($hour = $working_hours_start; $hour <= $working_hours_end; $hour++) {
                            $time = sprintf('%02d:00', $hour);

                            try {
                                $stmt = $this->connection->prepare("INSERT INTO appointments (date, time) VALUES (?, ?)");
                                $stmt->execute([$date, $time]);
                                $slots_created++;
                            } catch (PDOException $e) {
                                // Ignore duplicate entries
                                if (strpos($e->getMessage(), 'UNIQUE constraint failed') === false) {
                                    error_log("Error creating time slot: " . $e->getMessage());
                                }
                            }
                        }
                    }
                }

                error_log("Termine generiert: $slots_created Slots für die nächsten 60 Tage");
            }
        } catch (PDOException $e) {
            error_log("Error generating time slots: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function backup()
    {
        try {
            $backup_dir = defined('BACKUP_DIR') ? BACKUP_DIR : __DIR__ . '/../backups';
            if (!file_exists($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }

            $timestamp = date('Y-m-d_H-i-s');
            $backup_file = $backup_dir . "/backup_mcs_booking_{$timestamp}.sql";

            // Simple SQLite backup by copying the database file
            if (copy($this->db_file, $backup_file)) {
                return $backup_file;
            }

            return false;
        } catch (Exception $e) {
            error_log("Backup failed: " . $e->getMessage());
            return false;
        }
    }

    public function getStats()
    {
        try {
            $stats = [];

            // Booking statistics
            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
            $stmt->execute();
            $stats['pending_bookings'] = $stmt->fetchColumn();

            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
            $stmt->execute();
            $stats['confirmed_bookings'] = $stmt->fetchColumn();

            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'available' AND date >= date('now')");
            $stmt->execute();
            $stats['available_slots'] = $stmt->fetchColumn();

            $stmt = $this->connection->prepare("SELECT SUM(total_price) FROM bookings WHERE status IN ('confirmed', 'completed') AND date(created_at) >= date('now', '-30 days')");
            $stmt->execute();
            $stats['monthly_revenue'] = $stmt->fetchColumn() ?: 0;

            return $stats;
        } catch (PDOException $e) {
            error_log("Error getting stats: " . $e->getMessage());
            return [];
        }
    }

    public function __destruct()
    {
        $this->connection = null;
    }
}
