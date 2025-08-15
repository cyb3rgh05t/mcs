<?php
// utils/backup.php - Professionelles Backup-System

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class DatabaseBackup
{
    private $db_path;
    private $backup_dir;
    private $max_backups;
    private $log_file;

    public function __construct($db_path = null, $backup_dir = null, $max_backups = 10)
    {
        $this->db_path = $db_path ?: (defined('DB_PATH') ? DB_PATH : __DIR__ . '/../database/bookings.db');
        $this->backup_dir = $backup_dir ?: (defined('BACKUP_DIR') ? BACKUP_DIR : __DIR__ . '/../backups');
        $this->max_backups = $max_backups;
        $this->log_file = (defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/../logs') . '/backup.log';

        $this->ensureDirectories();
    }

    /**
     * Erstellt alle erforderlichen Verzeichnisse
     */
    private function ensureDirectories()
    {
        $directories = [
            $this->backup_dir,
            dirname($this->log_file)
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Erstellt ein vollständiges Backup
     */
    public function createBackup($type = 'manual')
    {
        $start_time = microtime(true);
        $timestamp = date('Y-m-d_H-i-s');
        $backup_filename = "backup_mcs_booking_{$timestamp}_{$type}.sql";
        $backup_path = $this->backup_dir . '/' . $backup_filename;

        try {
            $this->log("Starting backup: $backup_filename");

            // Prüfe ob Quelldatenbank existiert
            if (!file_exists($this->db_path)) {
                throw new Exception("Source database not found: {$this->db_path}");
            }

            // Erstelle SQLite-Dump
            $backup_content = $this->generateSQLDump();

            // Schreibe Backup-Datei
            if (file_put_contents($backup_path, $backup_content) === false) {
                throw new Exception("Failed to write backup file: $backup_path");
            }

            // Komprimiere Backup (optional)
            $compressed_path = $this->compressBackup($backup_path);

            // Verifizie Backup
            $this->verifyBackup($compressed_path ?: $backup_path);

            // Alte Backups aufräumen
            $this->cleanupOldBackups();

            $duration = round(microtime(true) - $start_time, 2);
            $size = filesize($compressed_path ?: $backup_path);

            $result = [
                'success' => true,
                'filename' => basename($compressed_path ?: $backup_path),
                'path' => $compressed_path ?: $backup_path,
                'size' => $size,
                'duration' => $duration,
                'type' => $type,
                'timestamp' => $timestamp
            ];

            $this->log("Backup completed successfully: " . json_encode($result));

            return $result;
        } catch (Exception $e) {
            $error_result = [
                'success' => false,
                'error' => $e->getMessage(),
                'type' => $type,
                'timestamp' => $timestamp
            ];

            $this->log("Backup failed: " . json_encode($error_result));

            // Aufräumen bei Fehler
            if (file_exists($backup_path)) {
                unlink($backup_path);
            }

            return $error_result;
        }
    }

    /**
     * Generiert SQL-Dump der SQLite-Datenbank
     */
    private function generateSQLDump()
    {
        $db = new PDO('sqlite:' . $this->db_path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "-- MCS Booking System Database Backup\n";
        $sql .= "-- Generated on: " . date('Y-m-d H:i:s T') . "\n";
        $sql .= "-- Database: " . basename($this->db_path) . "\n";
        $sql .= "-- Version: 1.0\n\n";

        $sql .= "PRAGMA foreign_keys=OFF;\n";
        $sql .= "BEGIN TRANSACTION;\n\n";

        // Hole alle Tabellen
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $sql .= "-- Table structure for table `$table`\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";

            // Hole CREATE TABLE Statement
            $create_stmt = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'")->fetchColumn();
            $sql .= "$create_stmt;\n\n";

            // Hole Daten
            $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $sql .= "-- Dumping data for table `$table`\n";

                $columns = array_keys($rows[0]);
                $sql .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";

                $values = [];
                foreach ($rows as $row) {
                    $escaped_values = array_map(function ($value) use ($db) {
                        if ($value === null) {
                            return 'NULL';
                        }
                        return $db->quote($value);
                    }, array_values($row));
                    $values[] = '(' . implode(', ', $escaped_values) . ')';
                }

                $sql .= implode(",\n", $values) . ";\n\n";
            }
        }

        // Indices und Trigger
        $indices = $db->query("SELECT sql FROM sqlite_master WHERE type='index' AND sql IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($indices)) {
            $sql .= "-- Indices\n";
            foreach ($indices as $index) {
                $sql .= "$index;\n";
            }
            $sql .= "\n";
        }

        $triggers = $db->query("SELECT sql FROM sqlite_master WHERE type='trigger'")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($triggers)) {
            $sql .= "-- Triggers\n";
            foreach ($triggers as $trigger) {
                $sql .= "$trigger;\n";
            }
            $sql .= "\n";
        }

        $sql .= "COMMIT;\n";
        $sql .= "PRAGMA foreign_keys=ON;\n";

        return $sql;
    }

    /**
     * Komprimiert Backup-Datei
     */
    private function compressBackup($backup_path)
    {
        if (!function_exists('gzencode')) {
            return null; // Komprimierung nicht verfügbar
        }

        try {
            $content = file_get_contents($backup_path);
            $compressed = gzencode($content, 9);

            $compressed_path = $backup_path . '.gz';

            if (file_put_contents($compressed_path, $compressed) !== false) {
                unlink($backup_path); // Lösche unkomprimierte Version
                $this->log("Backup compressed: " . basename($compressed_path));
                return $compressed_path;
            }
        } catch (Exception $e) {
            $this->log("Compression failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Verifiziert Backup-Integrität
     */
    private function verifyBackup($backup_path)
    {
        if (!file_exists($backup_path)) {
            throw new Exception("Backup file not found for verification");
        }

        $size = filesize($backup_path);
        if ($size === 0) {
            throw new Exception("Backup file is empty");
        }

        // Für .gz Dateien: Prüfe ob dekomprimierbar
        if (pathinfo($backup_path, PATHINFO_EXTENSION) === 'gz') {
            if (!function_exists('gzdecode')) {
                return; // Kann nicht verifizieren
            }

            $compressed_content = file_get_contents($backup_path);
            $content = gzdecode($compressed_content);

            if ($content === false) {
                throw new Exception("Backup file is corrupted (compression)");
            }

            // Prüfe SQL-Syntax grob
            if (strpos($content, 'CREATE TABLE') === false) {
                throw new Exception("Backup file does not contain valid SQL");
            }
        }

        $this->log("Backup verified successfully: " . basename($backup_path) . " ($size bytes)");
    }

    /**
     * Löscht alte Backups
     */
    private function cleanupOldBackups()
    {
        $backups = glob($this->backup_dir . '/backup_mcs_booking_*.{sql,sql.gz}', GLOB_BRACE);

        if (count($backups) <= $this->max_backups) {
            return;
        }

        // Sortiere nach Änderungszeit
        usort($backups, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        $to_remove = array_slice($backups, 0, count($backups) - $this->max_backups);

        foreach ($to_remove as $file) {
            if (unlink($file)) {
                $this->log("Deleted old backup: " . basename($file));
            }
        }
    }

    /**
     * Listet verfügbare Backups auf
     */
    public function listBackups()
    {
        $backups = glob($this->backup_dir . '/backup_mcs_booking_*.{sql,sql.gz}', GLOB_BRACE);

        $backup_info = [];
        foreach ($backups as $file) {
            $info = [
                'filename' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'size_formatted' => $this->formatBytes(filesize($file)),
                'created' => filemtime($file),
                'created_formatted' => date('d.m.Y H:i:s', filemtime($file)),
                'type' => $this->extractBackupType($file),
                'compressed' => pathinfo($file, PATHINFO_EXTENSION) === 'gz'
            ];
            $backup_info[] = $info;
        }

        // Sortiere nach Erstellungszeit (neueste zuerst)
        usort($backup_info, function ($a, $b) {
            return $b['created'] - $a['created'];
        });

        return $backup_info;
    }

    /**
     * Extrahiert Backup-Typ aus Dateiname
     */
    private function extractBackupType($filename)
    {
        if (preg_match('/_([^_]+)\.(sql|sql\.gz)$/', $filename, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }

    /**
     * Formatiert Bytes zu lesbarer Größe
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Automatisches Backup basierend auf Einstellungen
     */
    public function autoBackup()
    {
        $last_backup_file = $this->backup_dir . '/.last_auto_backup';
        $backup_interval = 86400; // 24 Stunden

        // Prüfe wann letztes Auto-Backup war
        if (file_exists($last_backup_file)) {
            $last_backup = filemtime($last_backup_file);
            if ((time() - $last_backup) < $backup_interval) {
                return ['skipped' => true, 'reason' => 'Too recent'];
            }
        }

        // Erstelle automatisches Backup
        $result = $this->createBackup('auto');

        if ($result['success']) {
            touch($last_backup_file);
        }

        return $result;
    }

    /**
     * Wiederherstellen aus Backup
     */
    public function restoreFromBackup($backup_file)
    {
        if (!file_exists($backup_file)) {
            throw new Exception("Backup file not found: $backup_file");
        }

        try {
            $this->log("Starting restore from: " . basename($backup_file));

            // Erstelle Backup der aktuellen DB vor Wiederherstellung
            $safety_backup = $this->createBackup('pre_restore');

            // Lese Backup-Inhalt
            $content = file_get_contents($backup_file);

            // Dekomprimiere falls nötig
            if (pathinfo($backup_file, PATHINFO_EXTENSION) === 'gz') {
                $content = gzdecode($content);
                if ($content === false) {
                    throw new Exception("Failed to decompress backup file");
                }
            }

            // Stelle Datenbank wieder her
            $temp_db = $this->db_path . '.restore_temp';
            $db = new PDO('sqlite:' . $temp_db);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Führe SQL aus
            $db->exec($content);

            // Validiere wiederhergestellte DB
            $this->validateRestoredDatabase($temp_db);

            // Ersetze Original-Datenbank
            if (!rename($temp_db, $this->db_path)) {
                throw new Exception("Failed to replace original database");
            }

            $this->log("Restore completed successfully from: " . basename($backup_file));

            return [
                'success' => true,
                'backup_file' => basename($backup_file),
                'safety_backup' => $safety_backup
            ];
        } catch (Exception $e) {
            // Aufräumen
            if (file_exists($temp_db)) {
                unlink($temp_db);
            }

            $this->log("Restore failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validiert wiederhergestellte Datenbank
     */
    private function validateRestoredDatabase($db_path)
    {
        $db = new PDO('sqlite:' . $db_path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Prüfe ob wichtige Tabellen existieren
        $required_tables = ['appointments', 'services', 'bookings', 'booking_services'];

        $existing_tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($required_tables as $table) {
            if (!in_array($table, $existing_tables)) {
                throw new Exception("Required table missing: $table");
            }
        }

        // Prüfe Datenintegrität
        $db->exec("PRAGMA integrity_check");
    }

    /**
     * Loggt Backup-Aktivitäten
     */
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message\n";

        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Holt Backup-Statistiken
     */
    public function getStats()
    {
        $backups = $this->listBackups();

        $total_size = array_sum(array_column($backups, 'size'));
        $types = array_count_values(array_column($backups, 'type'));

        return [
            'total_backups' => count($backups),
            'total_size' => $total_size,
            'total_size_formatted' => $this->formatBytes($total_size),
            'types' => $types,
            'oldest' => !empty($backups) ? end($backups)['created_formatted'] : null,
            'newest' => !empty($backups) ? $backups[0]['created_formatted'] : null,
            'backup_dir' => $this->backup_dir
        ];
    }
}

// CLI-Ausführung
if (php_sapi_name() === 'cli') {
    $backup = new DatabaseBackup();

    $command = $argv[1] ?? 'create';

    switch ($command) {
        case 'create':
            $type = $argv[2] ?? 'manual';
            $result = $backup->createBackup($type);
            if ($result['success']) {
                echo "✅ Backup created: {$result['filename']} ({$result['size']} bytes)\n";
            } else {
                echo "❌ Backup failed: {$result['error']}\n";
                exit(1);
            }
            break;

        case 'list':
            $backups = $backup->listBackups();
            echo "📋 Available backups:\n";
            foreach ($backups as $b) {
                echo "  - {$b['filename']} ({$b['size_formatted']}) - {$b['created_formatted']}\n";
            }
            break;

        case 'auto':
            $result = $backup->autoBackup();
            if (isset($result['skipped'])) {
                echo "⏭️ Auto backup skipped: {$result['reason']}\n";
            } elseif ($result['success']) {
                echo "✅ Auto backup created: {$result['filename']}\n";
            } else {
                echo "❌ Auto backup failed: {$result['error']}\n";
                exit(1);
            }
            break;

        case 'stats':
            $stats = $backup->getStats();
            echo "📊 Backup Statistics:\n";
            echo "  Total backups: {$stats['total_backups']}\n";
            echo "  Total size: {$stats['total_size_formatted']}\n";
            echo "  Backup directory: {$stats['backup_dir']}\n";
            if ($stats['newest']) {
                echo "  Newest: {$stats['newest']}\n";
            }
            if ($stats['oldest']) {
                echo "  Oldest: {$stats['oldest']}\n";
            }
            break;

        default:
            echo "Usage: php backup.php [create|list|auto|stats] [type]\n";
            echo "  create [manual|auto|scheduled] - Create new backup\n";
            echo "  list                           - List all backups\n";
            echo "  auto                           - Run automatic backup\n";
            echo "  stats                          - Show backup statistics\n";
            exit(1);
    }
}

// Web-Ausführung (für Admin-Panel)
elseif (!empty($_GET['action']) && $_GET['action'] === 'backup') {
    header('Content-Type: application/json');

    try {
        $backup = new DatabaseBackup();
        $result = $backup->createBackup('web');
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
