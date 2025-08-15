<?php
class Database
{
    private $db_file = 'database/bookings.db';
    private $connection;

    public function __construct()
    {
        $this->connect();
        $this->createTables();
    }

    private function connect()
    {
        try {
            // Erstelle database Ordner falls nicht vorhanden
            if (!file_exists('database')) {
                mkdir('database', 0777, true);
            }

            $this->connection = new PDO('sqlite:' . $this->db_file);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    private function createTables()
    {
        $sql_appointments = "
            CREATE TABLE IF NOT EXISTS appointments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date TEXT NOT NULL,
                time TEXT NOT NULL,
                status TEXT DEFAULT 'available',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";

        $sql_services = "
            CREATE TABLE IF NOT EXISTS services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                price DECIMAL(10,2) NOT NULL,
                duration INTEGER DEFAULT 60,
                active INTEGER DEFAULT 1
            )";

        $sql_bookings = "
            CREATE TABLE IF NOT EXISTS bookings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                appointment_id INTEGER,
                customer_name TEXT NOT NULL,
                customer_email TEXT NOT NULL,
                customer_phone TEXT NOT NULL,
                customer_address TEXT NOT NULL,
                distance DECIMAL(10,2),
                total_price DECIMAL(10,2),
                status TEXT DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (appointment_id) REFERENCES appointments (id)
            )";

        $sql_booking_services = "
            CREATE TABLE IF NOT EXISTS booking_services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                booking_id INTEGER,
                service_id INTEGER,
                FOREIGN KEY (booking_id) REFERENCES bookings (id),
                FOREIGN KEY (service_id) REFERENCES services (id)
            )";

        try {
            $this->connection->exec($sql_appointments);
            $this->connection->exec($sql_services);
            $this->connection->exec($sql_bookings);
            $this->connection->exec($sql_booking_services);

            // Standard-Services hinzufügen falls Tabelle leer
            $this->insertDefaultServices();
            $this->generateTimeSlots();
        } catch (PDOException $e) {
            die("Table creation failed: " . $e->getMessage());
        }
    }

    private function insertDefaultServices()
    {
        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM services");
        $stmt->execute();
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            $services = [
                ['Fahrzeugwäsche Außen', 'Gründliche Außenreinigung Ihres Fahrzeugs', 25.00, 45],
                ['Fahrzeugwäsche Komplett', 'Außen- und Innenreinigung', 45.00, 90],
                ['Polsterreinigung', 'Professionelle Reinigung der Fahrzeugsitze', 35.00, 60],
                ['Motorwäsche', 'Schonende Motorraumreinigung', 30.00, 30],
                ['Felgenreinigung', 'Intensive Felgen- und Reifenpflege', 20.00, 30],
                ['Lackpflege', 'Versiegelung und Politur', 60.00, 120]
            ];

            $stmt = $this->connection->prepare("INSERT INTO services (name, description, price, duration) VALUES (?, ?, ?, ?)");
            foreach ($services as $service) {
                $stmt->execute($service);
            }
        }
    }

    private function generateTimeSlots()
    {
        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM appointments");
        $stmt->execute();
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            // Erstelle Termine für die nächsten 30 Tage (Mo-Sa, 8-18 Uhr)
            for ($i = 1; $i <= 30; $i++) {
                $date = date('Y-m-d', strtotime("+$i days"));
                $dayOfWeek = date('N', strtotime($date));

                // Nur Montag bis Samstag (1-6)
                if ($dayOfWeek <= 6) {
                    for ($hour = 8; $hour <= 17; $hour++) {
                        $time = sprintf('%02d:00', $hour);
                        $stmt = $this->connection->prepare("INSERT INTO appointments (date, time) VALUES (?, ?)");
                        $stmt->execute([$date, $time]);
                    }
                }
            }
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }
}
