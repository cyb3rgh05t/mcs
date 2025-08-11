<?php

/**
 * Mobile Car Service - SQLite Datenbank-Klasse
 * Sichere Datenbankoperationen mit prepared statements
 */

require_once 'config.php';

class Database
{
    private static $instance = null;
    private $connection = null;
    private $transactionLevel = 0;

    private function __construct()
    {
        $this->connect();
        $this->createTablesIfNotExist();
    }

    /**
     * Singleton-Pattern für Datenbankverbindung
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Datenbankverbindung herstellen
     */
    private function connect()
    {
        try {
            // Datenbank-Verzeichnis erstellen falls nicht vorhanden
            $dbDir = dirname(DB_PATH);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }

            // SQLite-Verbindung herstellen
            $this->connection = new PDO('sqlite:' . DB_PATH);

            // PDO-Optionen setzen
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            // SQLite-spezifische Einstellungen
            $this->connection->exec('PRAGMA foreign_keys = ON');
            $this->connection->exec('PRAGMA journal_mode = WAL');
            $this->connection->exec('PRAGMA synchronous = NORMAL');
            $this->connection->exec('PRAGMA cache_size = 1000');
            $this->connection->exec('PRAGMA temp_store = MEMORY');
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Datenbankverbindung fehlgeschlagen');
        }
    }

    /**
     * Tabellen erstellen falls sie nicht existieren
     */
    private function createTablesIfNotExist()
    {
        $sql = "
            -- Kunden-Tabelle
            CREATE TABLE IF NOT EXISTS customers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                phone TEXT NOT NULL,
                street TEXT NOT NULL,
                zip TEXT NOT NULL,
                city TEXT NOT NULL,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            -- Services-Tabelle
            CREATE TABLE IF NOT EXISTS services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                detailed_description TEXT,
                price DECIMAL(10,2) NOT NULL,
                duration INTEGER NOT NULL,
                category TEXT,
                icon TEXT,
                popular BOOLEAN DEFAULT 0,
                active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            -- Buchungen-Tabelle
            CREATE TABLE IF NOT EXISTS bookings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                booking_number TEXT UNIQUE NOT NULL,
                customer_id INTEGER NOT NULL,
                booking_date DATE NOT NULL,
                booking_time TIME NOT NULL,
                distance DECIMAL(8,2),
                travel_cost DECIMAL(10,2),
                total_price DECIMAL(10,2),
                status TEXT DEFAULT 'confirmed',
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id)
            );
            
            -- Buchung-Services Zwischentabelle
            CREATE TABLE IF NOT EXISTS booking_services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                booking_id INTEGER NOT NULL,
                service_id INTEGER NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
                FOREIGN KEY (service_id) REFERENCES services(id)
            );
            
            -- Einstellungen-Tabelle
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            -- Logs-Tabelle
            CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                level TEXT NOT NULL,
                message TEXT NOT NULL,
                context TEXT,
                ip_address TEXT,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            -- Indizes für Performance
            CREATE INDEX IF NOT EXISTS idx_customers_email ON customers(email);
            CREATE INDEX IF NOT EXISTS idx_bookings_date ON bookings(booking_date);
            CREATE INDEX IF NOT EXISTS idx_bookings_customer ON bookings(customer_id);
            CREATE INDEX IF NOT EXISTS idx_booking_services_booking ON booking_services(booking_id);
            CREATE INDEX IF NOT EXISTS idx_logs_level_date ON logs(level, created_at);
        ";

        try {
            $this->connection->exec($sql);
            $this->insertDefaultServices();
        } catch (PDOException $e) {
            error_log('Table creation failed: ' . $e->getMessage());
            throw new Exception('Tabellen konnten nicht erstellt werden');
        }
    }

    /**
     * Standard-Services in die Datenbank einfügen
     */
    private function insertDefaultServices()
    {
        // Prüfen ob Services bereits existieren
        $count = $this->query("SELECT COUNT(*) as count FROM services")->fetch()['count'];

        if ($count > 0) {
            return; // Services bereits vorhanden
        }

        $services = [
            [
                'name' => 'Basis-Reinigung',
                'description' => 'Außenreinigung, Innenraumreinigung, Staubsaugen',
                'detailed_description' => 'Komplette Außenwäsche mit Shampoo, Innenraumreinigung inklusive Staubsaugen aller Sitze und Fußmatten, Armaturenbrett abwischen.',
                'price' => 45.00,
                'duration' => 60,
                'category' => 'basic',
                'icon' => 'fas fa-spray-can',
                'popular' => 0
            ],
            [
                'name' => 'Premium-Reinigung',
                'description' => 'Basis + Felgenreinigung, Armaturenpflege, Scheibenpolitur',
                'detailed_description' => 'Alle Leistungen der Basis-Reinigung plus professionelle Felgenreinigung, Armaturenpflege mit hochwertigen Produkten und Scheibenpolitur für kristallklare Sicht.',
                'price' => 75.00,
                'duration' => 90,
                'category' => 'premium',
                'icon' => 'fas fa-star',
                'popular' => 1
            ],
            [
                'name' => 'Komplett-Reinigung',
                'description' => 'Premium + Wachsbehandlung, Lederpflege, Motorraumreinigung',
                'detailed_description' => 'Das Komplettpaket für Ihr Fahrzeug. Alle Premium-Leistungen plus Wachsversiegelung, professionelle Lederpflege und schonende Motorraumreinigung.',
                'price' => 120.00,
                'duration' => 150,
                'category' => 'luxury',
                'icon' => 'fas fa-crown',
                'popular' => 0
            ],
            [
                'name' => 'Innenraumaufbereitung',
                'description' => 'Komplette Innenraumaufbereitung inkl. Polsterreinigung',
                'detailed_description' => 'Spezialisierte Tiefenreinigung des Innenraums mit Polsterreinigung, Teppichreinigung und Geruchsbeseitigung.',
                'price' => 65.00,
                'duration' => 80,
                'category' => 'interior',
                'icon' => 'fas fa-chair',
                'popular' => 0
            ],
            [
                'name' => 'Felgenspezialist',
                'description' => 'Professionelle Felgenreinigung und -versiegelung',
                'detailed_description' => 'Intensive Reinigung und Pflege Ihrer Felgen mit speziellen Reinigungsmitteln und abschließender Versiegelung für langanhaltenden Schutz.',
                'price' => 35.00,
                'duration' => 45,
                'category' => 'wheels',
                'icon' => 'fas fa-circle',
                'popular' => 0
            ],
            [
                'name' => 'Wachsversiegelung',
                'description' => 'Hochwertige Wachsversiegelung für langanhaltenden Schutz',
                'detailed_description' => 'Professionelle Hartwachs-Versiegelung die Ihren Lack für Monate vor Umwelteinflüssen schützt und für tiefen Glanz sorgt.',
                'price' => 85.00,
                'duration' => 120,
                'category' => 'protection',
                'icon' => 'fas fa-shield-alt',
                'popular' => 1
            ],
            [
                'name' => 'Schnell-Service',
                'description' => 'Express-Außenreinigung für zwischendurch',
                'detailed_description' => 'Schnelle aber gründliche Außenreinigung für alle die wenig Zeit haben aber trotzdem ein sauberes Auto möchten.',
                'price' => 25.00,
                'duration' => 30,
                'category' => 'express',
                'icon' => 'fas fa-bolt',
                'popular' => 0
            ],
            [
                'name' => 'Motorreinigung',
                'description' => 'Schonende professionelle Motorraumreinigung',
                'detailed_description' => 'Fachgerechte Reinigung des Motorraums mit speziellen Reinigungsmitteln unter Schutz aller elektronischen Komponenten.',
                'price' => 40.00,
                'duration' => 45,
                'category' => 'engine',
                'icon' => 'fas fa-cog',
                'popular' => 0
            ]
        ];

        $sql = "INSERT INTO services (name, description, detailed_description, price, duration, category, icon, popular) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->prepare($sql);

        foreach ($services as $service) {
            $stmt->execute([
                $service['name'],
                $service['description'],
                $service['detailed_description'],
                $service['price'],
                $service['duration'],
                $service['category'],
                $service['icon'],
                $service['popular']
            ]);
        }
    }

    /**
     * SQL-Query ausführen
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Database query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            throw new Exception('Datenbankfehler: ' . $e->getMessage());
        }
    }

    /**
     * Prepared Statement vorbereiten
     */
    public function prepare($sql)
    {
        try {
            return $this->connection->prepare($sql);
        } catch (PDOException $e) {
            error_log('Statement preparation failed: ' . $e->getMessage());
            throw new Exception('Statement-Vorbereitung fehlgeschlagen');
        }
    }

    /**
     * Einzelnen Datensatz abrufen
     */
    public function fetchOne($sql, $params = [])
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Alle Datensätze abrufen
     */
    public function fetchAll($sql, $params = [])
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * INSERT-Operation
     */
    public function insert($table, $data)
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->query($sql, array_values($data));
        return $this->connection->lastInsertId();
    }

    /**
     * UPDATE-Operation
     */
    public function update($table, $data, $where, $whereParams = [])
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "$column = ?";
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $setParts),
            $where
        );

        $params = array_merge(array_values($data), $whereParams);
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * DELETE-Operation
     */
    public function delete($table, $where, $whereParams = [])
    {
        $sql = sprintf("DELETE FROM %s WHERE %s", $table, $where);
        return $this->query($sql, $whereParams)->rowCount();
    }

    /**
     * Transaktion starten
     */
    public function beginTransaction()
    {
        if ($this->transactionLevel === 0) {
            $this->connection->beginTransaction();
        }
        $this->transactionLevel++;
    }

    /**
     * Transaktion committen
     */
    public function commit()
    {
        $this->transactionLevel--;
        if ($this->transactionLevel === 0) {
            $this->connection->commit();
        }
    }

    /**
     * Transaktion zurückrollen
     */
    public function rollback()
    {
        if ($this->transactionLevel > 0) {
            $this->transactionLevel = 0;
            $this->connection->rollback();
        }
    }

    /**
     * Letzte eingefügte ID abrufen
     */
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Anzahl betroffener Zeilen
     */
    public function rowCount($stmt)
    {
        return $stmt->rowCount();
    }

    /**
     * Tabelle existiert prüfen
     */
    public function tableExists($tableName)
    {
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name=?";
        $result = $this->fetchOne($sql, [$tableName]);
        return $result !== null;
    }

    /**
     * Datenbank-Schema abrufen
     */
    public function getSchema()
    {
        $sql = "SELECT name, sql FROM sqlite_master WHERE type='table'";
        return $this->fetchAll($sql);
    }

    /**
     * Datenbank-Statistiken
     */
    public function getStats()
    {
        $stats = [];

        // Tabellenzählungen
        $tables = ['customers', 'bookings', 'services', 'booking_services', 'logs'];
        foreach ($tables as $table) {
            $sql = "SELECT COUNT(*) as count FROM $table";
            $result = $this->fetchOne($sql);
            $stats[$table] = $result['count'] ?? 0;
        }

        // Datenbankgröße
        $stats['database_size'] = file_exists(DB_PATH) ? filesize(DB_PATH) : 0;

        // Letzte Aktivität
        $sql = "SELECT MAX(created_at) as last_activity FROM bookings";
        $result = $this->fetchOne($sql);
        $stats['last_booking'] = $result['last_activity'];

        return $stats;
    }

    /**
     * Datenbank sichern
     */
    public function backup()
    {
        $backupDir = DB_BACKUP_PATH;
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $backupFile = $backupDir . 'backup_' . date('Y-m-d_H-i-s') . '.sqlite';

        if (copy(DB_PATH, $backupFile)) {
            return $backupFile;
        }

        throw new Exception('Backup fehlgeschlagen');
    }

    /**
     * Alte Backups bereinigen
     */
    public function cleanupOldBackups($keepDays = 30)
    {
        $backupDir = DB_BACKUP_PATH;
        if (!is_dir($backupDir)) {
            return;
        }

        $files = glob($backupDir . 'backup_*.sqlite');
        $cutoffTime = time() - ($keepDays * 24 * 3600);

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }

    /**
     * Datenbankverbindung abrufen (für spezielle Operationen)
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Destruktor - Verbindung schließen
     */
    public function __destruct()
    {
        if ($this->connection && $this->transactionLevel > 0) {
            $this->rollback();
        }
        $this->connection = null;
    }
}

/**
 * Query Builder für einfachere Datenbankoperationen
 */
class QueryBuilder
{
    private $db;
    private $table;
    private $select = '*';
    private $where = [];
    private $orderBy = [];
    private $limit = null;
    private $offset = null;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function table($table)
    {
        $this->table = $table;
        return $this;
    }

    public function select($columns)
    {
        $this->select = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }

    public function where($column, $operator, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = ['column' => $column, 'operator' => $operator, 'value' => $value];
        return $this;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        $this->orderBy[] = "$column $direction";
        return $this;
    }

    public function limit($limit, $offset = null)
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    public function get()
    {
        $sql = "SELECT {$this->select} FROM {$this->table}";
        $params = [];

        if (!empty($this->where)) {
            $whereParts = [];
            foreach ($this->where as $condition) {
                $whereParts[] = "{$condition['column']} {$condition['operator']} ?";
                $params[] = $condition['value'];
            }
            $sql .= " WHERE " . implode(' AND ', $whereParts);
        }

        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }

        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit;
            if ($this->offset) {
                $sql .= " OFFSET " . $this->offset;
            }
        }

        return $this->db->fetchAll($sql, $params);
    }

    public function first()
    {
        $this->limit(1);
        $results = $this->get();
        return !empty($results) ? $results[0] : null;
    }
}

// Helper-Funktion für einfachen Datenbankzugriff
function db()
{
    return Database::getInstance();
}

// Query Builder Helper
function query()
{
    return new QueryBuilder(Database::getInstance());
}
