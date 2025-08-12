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
            
            -- Standard-Services einfügen
            INSERT OR IGNORE INTO services (id, name, description, price, duration, category, icon, popular) VALUES
            (1, 'Außenwäsche Basic', 'Grundreinigung der Fahrzeugaußenseite', 25.00, 30, 'exterior', 'fas fa-car', 1),
            (2, 'Außenwäsche Premium', 'Intensive Außenreinigung mit Wachs', 49.00, 60, 'exterior', 'fas fa-star', 1),
            (3, 'Innenreinigung', 'Komplette Fahrzeuginnenreinigung', 35.00, 45, 'interior', 'fas fa-couch', 1),
            (4, 'Polsterreinigung', 'Tiefenreinigung der Fahrzeugpolster', 60.00, 90, 'interior', 'fas fa-spray-can', 0),
            (5, 'Motorwäsche', 'Professionelle Motorraum-Reinigung', 40.00, 45, 'engine', 'fas fa-cog', 0),
            (6, 'Felgenreinigung', 'Intensive Felgen- und Reifenreinigung', 25.00, 30, 'wheels', 'fas fa-circle', 1),
            (7, 'Komplettpaket Standard', 'Außen- und Innenreinigung kombiniert', 55.00, 75, 'package', 'fas fa-check-circle', 1),
            (8, 'Komplettpaket Premium', 'Alle Services in einem Paket', 95.00, 120, 'package', 'fas fa-crown', 0);
        ";

        try {
            $this->connection->exec($sql);
        } catch (PDOException $e) {
            error_log('Table creation failed: ' . $e->getMessage());
            throw new Exception('Tabellenerstellung fehlgeschlagen');
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
            error_log('Query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
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
            try {
                $sql = "SELECT COUNT(*) as count FROM $table";
                $result = $this->fetchOne($sql);
                $stats[$table] = $result['count'] ?? 0;
            } catch (Exception $e) {
                $stats[$table] = 0;
            }
        }

        // Datenbankgröße
        $stats['database_size'] = file_exists(DB_PATH) ? filesize(DB_PATH) : 0;

        // Letzte Aktivität
        try {
            $sql = "SELECT MAX(created_at) as last_activity FROM bookings";
            $result = $this->fetchOne($sql);
            $stats['last_booking'] = $result['last_activity'];
        } catch (Exception $e) {
            $stats['last_booking'] = null;
        }

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

        $this->where[] = [$column, $operator, $value];
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
            $whereClause = [];
            foreach ($this->where as $condition) {
                $whereClause[] = "{$condition[0]} {$condition[1]} ?";
                $params[] = $condition[2];
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }

        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }

        if ($this->limit) {
            $sql .= " LIMIT {$this->limit}";
            if ($this->offset) {
                $sql .= " OFFSET {$this->offset}";
            }
        }

        return $this->db->fetchAll($sql, $params);
    }

    public function first()
    {
        $this->limit(1);
        $results = $this->get();
        return $results ? $results[0] : null;
    }
}
