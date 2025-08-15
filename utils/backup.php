<?php
require_once '../config/database.php';

class DatabaseBackup
{
    private $db_path;
    private $backup_dir;

    public function __construct($db_path = null)
    {
        $this->db_path = $db_path ?: DB_PATH;
        $this->backup_dir = __DIR__ . '/../backups';

        if (!file_exists($this->backup_dir)) {
            mkdir($this->backup_dir, 0755, true);
        }
    }

    public function createBackup()
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backup_filename = "backup_mcs_booking_{$timestamp}.sql";
        $backup_path = $this->backup_dir . '/' . $backup_filename;

        try {
            $db = new PDO('sqlite:' . $this->db_path);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $backup_content = $this->generateSQLDump($db);

            file_put_contents($backup_path, $backup_content);

            // Keep only last 10 backups
            $this->cleanupOldBackups();

            return [
                'success' => true,
                'filename' => $backup_filename,
                'path' => $backup_path,
                'size' => filesize($backup_path)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function generateSQLDump($db)
    {
        $sql = "-- MCS Booking System Database Backup\n";
        $sql .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";

        // Get all tables
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $sql .= "-- Table: $table\n";

            // Get table structure
            $create_table = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'")->fetchColumn();
            $sql .= "$create_table;\n\n";

            // Get table data
            $rows = $db->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $sql .= "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES\n";

                $values = [];
                foreach ($rows as $row) {
                    $escaped_values = array_map(function ($value) {
                        return $value === null ? 'NULL' : "'" . str_replace("'", "''", $value) . "'";
                    }, array_values($row));
                    $values[] = '(' . implode(', ', $escaped_values) . ')';
                }

                $sql .= implode(",\n", $values) . ";\n\n";
            }
        }

        return $sql;
    }

    private function cleanupOldBackups()
    {
        $backups = glob($this->backup_dir . '/backup_mcs_booking_*.sql');

        if (count($backups) > 10) {
            // Sort by modification time
            usort($backups, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Remove oldest backups
            $to_remove = array_slice($backups, 0, count($backups) - 10);
            foreach ($to_remove as $file) {
                unlink($file);
            }
        }
    }

    public function listBackups()
    {
        $backups = glob($this->backup_dir . '/backup_mcs_booking_*.sql');

        $backup_info = [];
        foreach ($backups as $file) {
            $backup_info[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'created' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

        // Sort by creation time (newest first)
        usort($backup_info, function ($a, $b) {
            return strtotime($b['created']) - strtotime($a['created']);
        });

        return $backup_info;
    }
}

// Automatisches Backup (kann per Cron-Job aufgerufen werden)
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $backup = new DatabaseBackup();
    $result = $backup->createBackup();

    if ($result['success']) {
        echo "Backup created successfully: " . $result['filename'] . "\n";
        echo "Size: " . number_format($result['size'] / 1024, 2) . " KB\n";
    } else {
        echo "Backup failed: " . $result['error'] . "\n";
    }
}
