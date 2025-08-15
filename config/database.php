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

        // Triggers für updated_at
        $trigger_appointments = "
            CREATE TRIGGER IF NOT EXISTS update_appointments_updated_at 
            AFTER UPDATE ON appointments 
            BEGIN 
                UPDATE appointments SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
            END";

        $trigger_services = "
            CREATE TRIGGER IF NOT EXISTS update_services_updated_at 
            AFTER UPDATE ON services 
            BEGIN 
                UPDATE services SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
            END";

        $trigger_bookings = "
            CREATE TRIGGER IF NOT EXISTS update_bookings_updated_at 
            AFTER UPDATE ON bookings 
            BEGIN 
                UPDATE bookings SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
            END";

        try {
            $this->connection->exec($sql_appointments);
            $this->connection->exec($sql_services);
            $this->connection->exec($sql_bookings);
            $this->connection->exec($sql_booking_services);

            // Triggers erstellen
            $this->connection->exec($trigger_appointments);
            $this->connection->exec($trigger_services);
            $this->connection->exec($trigger_bookings);

            // Standard-Daten einfügen
            $this->insertDefaultServices();
            $this->generateTimeSlots();
        } catch (PDOException $e) {
            error_log("Table creation failed: " . $e->getMessage());
            die("Datenbankinitialisierung fehlgeschlagen. Bitte kontaktieren Sie den Administrator.");
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
                    ['Fahrzeugwäsche Außen', 'Gründliche Außenreinigung Ihres Fahrzeugs mit Hochdruckreiniger und Autoshampoo', 25.00, 45],
                    ['Fahrzeugwäsche Komplett', 'Komplette Außen- und Innenreinigung für perfekte Sauberkeit', 45.00, 90],
                    ['Polsterreinigung', 'Professionelle Tiefenreinigung der Fahrzeugsitze und Polster', 35.00, 60],
                    ['Motorwäsche', 'Schonende Motorraumreinigung mit speziellen Reinigungsmitteln', 30.00, 30],
                    ['Felgenreinigung', 'Intensive Reinigung und Pflege von Felgen und Reifen', 20.00, 30],
                    ['Lackpflege Premium', 'Professionelle Lackversiegelung und Politur für langanhaltenden Glanz', 60.00, 120]
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
