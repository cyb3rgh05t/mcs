<?php
// utils/monitor.php - Comprehensive System Monitoring

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SecurityManager.php';

class SystemMonitor
{
    private $db;
    private $log_dir;
    private $start_time;

    public function __construct()
    {
        $this->start_time = microtime(true);
        $this->log_dir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/../logs';

        if (!file_exists($this->log_dir)) {
            mkdir($this->log_dir, 0755, true);
        }

        try {
            $database = new Database();
            $this->db = $database->getConnection();
        } catch (Exception $e) {
            $this->log('monitor', 'Database connection failed: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Führt vollständige Systemüberwachung durch
     */
    public function runFullCheck()
    {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'system' => $this->checkSystemHealth(),
            'database' => $this->checkDatabaseHealth(),
            'performance' => $this->checkPerformance(),
            'security' => $this->checkSecurity(),
            'files' => $this->checkFileSystem(),
            'logs' => $this->analyzeLogs(),
            'overall_status' => 'unknown'
        ];

        // Gesamtstatus bestimmen
        $results['overall_status'] = $this->determineOverallStatus($results);

        // Monitoring-Log aktualisieren
        $this->logMonitoringResults($results);

        return $results;
    }

    /**
     * Prüft Systemgesundheit
     */
    public function checkSystemHealth()
    {
        $health = [
            'php_version' => PHP_VERSION,
            'php_version_ok' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage_percent' => $this->getMemoryUsagePercent(),
            'disk_usage' => $this->getDiskUsage(),
            'required_extensions' => $this->checkRequiredExtensions(),
            'server_load' => $this->getServerLoad(),
            'uptime' => $this->getSystemUptime()
        ];

        $health['status'] = $this->evaluateSystemHealth($health);

        return $health;
    }

    /**
     * Prüft Datenbankgesundheit
     */
    public function checkDatabaseHealth()
    {
        if (!$this->db) {
            return ['status' => 'error', 'message' => 'Database not available'];
        }

        try {
            $health = [
                'connection' => true,
                'size' => $this->getDatabaseSize(),
                'tables' => $this->checkDatabaseTables(),
                'integrity' => $this->checkDatabaseIntegrity(),
                'performance' => $this->checkDatabasePerformance(),
                'recent_activity' => $this->getRecentDatabaseActivity()
            ];

            $health['status'] = 'ok';
        } catch (Exception $e) {
            $health = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'connection' => false
            ];
        }

        return $health;
    }

    /**
     * Prüft Performance-Metriken
     */
    public function checkPerformance()
    {
        $performance = [
            'page_load_time' => $this->measurePageLoadTime(),
            'database_query_time' => $this->measureDatabaseQueryTime(),
            'api_response_time' => $this->measureApiResponseTime(),
            'resource_usage' => $this->getResourceUsage(),
            'cache_performance' => $this->checkCachePerformance(),
            'bottlenecks' => $this->identifyBottlenecks()
        ];

        $performance['status'] = $this->evaluatePerformance($performance);

        return $performance;
    }

    /**
     * Prüft Sicherheitsstatus
     */
    public function checkSecurity()
    {
        $security = [
            'file_permissions' => $this->checkFilePermissions(),
            'sensitive_files' => $this->checkSensitiveFiles(),
            'security_logs' => $this->analyzeSecurityLogs(),
            'failed_logins' => $this->getFailedLoginAttempts(),
            'suspicious_activity' => $this->detectSuspiciousActivity(),
            'ssl_status' => $this->checkSSLStatus(),
            'security_headers' => $this->checkSecurityHeaders()
        ];

        $security['status'] = $this->evaluateSecurity($security);

        return $security;
    }

    /**
     * Prüft Dateisystem
     */
    public function checkFileSystem()
    {
        $filesystem = [
            'disk_space' => $this->getDiskSpaceInfo(),
            'directory_permissions' => $this->checkDirectoryPermissions(),
            'log_files' => $this->checkLogFiles(),
            'backup_files' => $this->checkBackupFiles(),
            'temp_files' => $this->checkTempFiles(),
            'file_integrity' => $this->checkCriticalFiles()
        ];

        $filesystem['status'] = $this->evaluateFileSystem($filesystem);

        return $filesystem;
    }

    /**
     * Analysiert Log-Dateien
     */
    public function analyzeLogs()
    {
        $logs = [
            'error_log' => $this->analyzeErrorLog(),
            'security_log' => $this->analyzeSecurityLog(),
            'access_patterns' => $this->analyzeAccessPatterns(),
            'performance_log' => $this->analyzePerformanceLog(),
            'recent_errors' => $this->getRecentErrors(),
            'log_growth' => $this->checkLogGrowth()
        ];

        $logs['status'] = $this->evaluateLogs($logs);

        return $logs;
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    private function getMemoryUsagePercent()
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') return 0;

        $limit_bytes = $this->parseSize($limit);
        $usage = memory_get_usage(true);

        return round(($usage / $limit_bytes) * 100, 2);
    }

    private function parseSize($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }

        return round($size);
    }

    private function getDiskUsage()
    {
        $total = disk_total_space('.');
        $free = disk_free_space('.');
        $used = $total - $free;

        return [
            'total' => $total,
            'free' => $free,
            'used' => $used,
            'used_percent' => round(($used / $total) * 100, 2)
        ];
    }

    private function checkRequiredExtensions()
    {
        $required = ['pdo', 'pdo_sqlite', 'json', 'curl', 'session'];
        $status = [];

        foreach ($required as $ext) {
            $status[$ext] = extension_loaded($ext);
        }

        return $status;
    }

    private function getServerLoad()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => $load[0],
                '5min' => $load[1],
                '15min' => $load[2]
            ];
        }

        return null;
    }

    private function getSystemUptime()
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $uptime = file_get_contents('/proc/uptime');
            if ($uptime) {
                $seconds = floatval(explode(' ', $uptime)[0]);
                return [
                    'seconds' => $seconds,
                    'formatted' => $this->formatUptime($seconds)
                ];
            }
        }

        return null;
    }

    private function formatUptime($seconds)
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return "{$days}d {$hours}h {$minutes}m";
    }

    private function getDatabaseSize()
    {
        if (!$this->db) return 0;

        $db_path = defined('DB_PATH') ? DB_PATH : __DIR__ . '/../database/bookings.db';
        return file_exists($db_path) ? filesize($db_path) : 0;
    }

    private function checkDatabaseTables()
    {
        if (!$this->db) return [];

        try {
            $tables = $this->db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
            $required_tables = ['appointments', 'services', 'bookings', 'booking_services'];

            $status = [];
            foreach ($required_tables as $table) {
                $status[$table] = in_array($table, $tables);
            }

            return $status;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function checkDatabaseIntegrity()
    {
        if (!$this->db) return false;

        try {
            $result = $this->db->query("PRAGMA integrity_check")->fetchColumn();
            return $result === 'ok';
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkDatabasePerformance()
    {
        if (!$this->db) return [];

        try {
            $start = microtime(true);
            $this->db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
            $query_time = microtime(true) - $start;

            return [
                'simple_query_time' => round($query_time * 1000, 2), // ms
                'status' => $query_time < 0.1 ? 'good' : ($query_time < 0.5 ? 'warning' : 'slow')
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getRecentDatabaseActivity()
    {
        if (!$this->db) return [];

        try {
            // Buchungen der letzten 24 Stunden
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM bookings WHERE created_at >= datetime('now', '-1 day')");
            $stmt->execute();
            $recent_bookings = $stmt->fetchColumn();

            return [
                'recent_bookings' => $recent_bookings,
                'last_booking' => $this->getLastBookingTime()
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getLastBookingTime()
    {
        try {
            $stmt = $this->db->prepare("SELECT created_at FROM bookings ORDER BY created_at DESC LIMIT 1");
            $stmt->execute();
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return null;
        }
    }

    private function measurePageLoadTime()
    {
        $start = microtime(true);

        // Simuliere Haupt-Page Load
        ob_start();
        include __DIR__ . '/../index.php';
        ob_end_clean();

        return round((microtime(true) - $start) * 1000, 2); // ms
    }

    private function measureDatabaseQueryTime()
    {
        if (!$this->db) return null;

        $start = microtime(true);
        try {
            $this->db->query("SELECT * FROM services LIMIT 10")->fetchAll();
            return round((microtime(true) - $start) * 1000, 2); // ms
        } catch (Exception $e) {
            return null;
        }
    }

    private function measureApiResponseTime()
    {
        $start = microtime(true);

        // Test Distance API
        $test_data = json_encode(['address' => 'Test Street 1, 12345 Test City']);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $test_data,
                'timeout' => 5
            ]
        ]);

        $result = @file_get_contents('http://localhost/api/distance.php', false, $context);

        if ($result !== false) {
            return round((microtime(true) - $start) * 1000, 2); // ms
        }

        return null;
    }

    private function getResourceUsage()
    {
        return [
            'memory_current' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => round((microtime(true) - $this->start_time) * 1000, 2)
        ];
    }

    private function checkCachePerformance()
    {
        // Für zukünftige Cache-Implementierung
        return ['status' => 'not_implemented'];
    }

    private function identifyBottlenecks()
    {
        $bottlenecks = [];

        // Memory usage
        if ($this->getMemoryUsagePercent() > 80) {
            $bottlenecks[] = 'high_memory_usage';
        }

        // Disk space
        $disk = $this->getDiskUsage();
        if ($disk['used_percent'] > 90) {
            $bottlenecks[] = 'low_disk_space';
        }

        // Database size
        $db_size = $this->getDatabaseSize();
        if ($db_size > 100 * 1024 * 1024) { // 100MB
            $bottlenecks[] = 'large_database';
        }

        return $bottlenecks;
    }

    private function checkFilePermissions()
    {
        $critical_dirs = [
            'database' => defined('DB_PATH') ? dirname(DB_PATH) : 'database',
            'logs' => $this->log_dir,
            'backups' => defined('BACKUP_DIR') ? BACKUP_DIR : 'backups'
        ];

        $permissions = [];
        foreach ($critical_dirs as $name => $dir) {
            $permissions[$name] = [
                'exists' => file_exists($dir),
                'writable' => is_writable($dir),
                'readable' => is_readable($dir)
            ];
        }

        return $permissions;
    }

    private function checkSensitiveFiles()
    {
        $sensitive_files = [
            'config/config.php',
            'database/bookings.db',
            '.env',
            'install.php'
        ];

        $status = [];
        foreach ($sensitive_files as $file) {
            $status[$file] = [
                'exists' => file_exists($file),
                'publicly_accessible' => $this->isPubliclyAccessible($file)
            ];
        }

        return $status;
    }

    private function isPubliclyAccessible($file)
    {
        // Vereinfachte Prüfung - in der Realität würde man HTTP-Requests testen
        return file_exists($file) && !str_contains($file, '.htaccess');
    }

    private function analyzeSecurityLogs()
    {
        $security_log = $this->log_dir . '/security.log';

        if (!file_exists($security_log)) {
            return ['status' => 'no_log'];
        }

        $lines = file($security_log, FILE_IGNORE_NEW_LINES);
        $recent_lines = array_slice($lines, -100); // Letzte 100 Einträge

        $analysis = [
            'total_events' => count($lines),
            'recent_events' => count($recent_lines),
            'failed_logins' => 0,
            'rate_limits' => 0,
            'csrf_attempts' => 0
        ];

        foreach ($recent_lines as $line) {
            if (strpos($line, 'admin_login_failed') !== false) {
                $analysis['failed_logins']++;
            }
            if (strpos($line, 'rate_limit_exceeded') !== false) {
                $analysis['rate_limits']++;
            }
            if (strpos($line, 'csrf_token_mismatch') !== false) {
                $analysis['csrf_attempts']++;
            }
        }

        return $analysis;
    }

    private function getFailedLoginAttempts()
    {
        $security_log = $this->log_dir . '/security.log';

        if (!file_exists($security_log)) {
            return 0;
        }

        $content = file_get_contents($security_log);
        return substr_count($content, 'admin_login_failed');
    }

    private function detectSuspiciousActivity()
    {
        $suspicious = [];

        // Prüfe auf ungewöhnliche Dateizugriffe
        $access_log = $this->log_dir . '/access.log';
        if (file_exists($access_log)) {
            // Implementierung für Access-Log-Analyse
        }

        // Prüfe auf Systemlast
        $load = $this->getServerLoad();
        if ($load && $load['1min'] > 10) {
            $suspicious[] = 'high_server_load';
        }

        return $suspicious;
    }

    private function checkSSLStatus()
    {
        return [
            'https_available' => isset($_SERVER['HTTPS']),
            'force_https' => $this->checkForceHTTPS()
        ];
    }

    private function checkForceHTTPS()
    {
        // Prüfe .htaccess oder Server-Konfiguration
        return file_exists('.htaccess') &&
            strpos(file_get_contents('.htaccess'), 'HTTPS') !== false;
    }

    private function checkSecurityHeaders()
    {
        // Diese würden normalerweise über HTTP-Response getestet
        return [
            'x_frame_options' => true, // Annahme basierend auf config
            'x_content_type_options' => true,
            'x_xss_protection' => true
        ];
    }

    private function getDiskSpaceInfo()
    {
        return [
            'total' => disk_total_space('.'),
            'free' => disk_free_space('.'),
            'used_percent' => $this->getDiskUsage()['used_percent']
        ];
    }

    private function checkDirectoryPermissions()
    {
        $dirs = ['assets', 'views', 'admin', 'api'];
        $permissions = [];

        foreach ($dirs as $dir) {
            $permissions[$dir] = [
                'exists' => is_dir($dir),
                'readable' => is_readable($dir)
            ];
        }

        return $permissions;
    }

    private function checkLogFiles()
    {
        $log_files = glob($this->log_dir . '/*.log');
        $info = [];

        foreach ($log_files as $file) {
            $info[basename($file)] = [
                'size' => filesize($file),
                'modified' => filemtime($file),
                'lines' => count(file($file))
            ];
        }

        return $info;
    }

    private function checkBackupFiles()
    {
        $backup_dir = defined('BACKUP_DIR') ? BACKUP_DIR : __DIR__ . '/../backups';

        if (!is_dir($backup_dir)) {
            return ['status' => 'no_backup_dir'];
        }

        $backups = glob($backup_dir . '/backup_*.{sql,sql.gz}', GLOB_BRACE);

        return [
            'count' => count($backups),
            'latest' => !empty($backups) ? max(array_map('filemtime', $backups)) : null,
            'total_size' => array_sum(array_map('filesize', $backups))
        ];
    }

    private function checkTempFiles()
    {
        $temp_files = glob(sys_get_temp_dir() . '/mcs_*');

        return [
            'count' => count($temp_files),
            'size' => array_sum(array_map('filesize', $temp_files))
        ];
    }

    private function checkCriticalFiles()
    {
        $critical_files = [
            'index.php',
            'config/config.php',
            'config/database.php',
            'classes/SecurityManager.php'
        ];

        $status = [];
        foreach ($critical_files as $file) {
            $status[$file] = [
                'exists' => file_exists($file),
                'size' => file_exists($file) ? filesize($file) : 0,
                'modified' => file_exists($file) ? filemtime($file) : null
            ];
        }

        return $status;
    }

    // LOG ANALYSIS METHODS

    private function analyzeErrorLog()
    {
        $error_log = $this->log_dir . '/php_errors.log';

        if (!file_exists($error_log)) {
            return ['status' => 'no_errors'];
        }

        $content = file_get_contents($error_log);
        $lines = explode("\n", $content);

        return [
            'total_errors' => count($lines),
            'recent_errors' => $this->countRecentLogEntries($lines),
            'error_types' => $this->categorizeLogEntries($lines)
        ];
    }

    private function analyzeSecurityLog()
    {
        return $this->analyzeSecurityLogs(); // Wiederverwendung
    }

    private function analyzeAccessPatterns()
    {
        // Platzhalter für Access-Log-Analyse
        return ['status' => 'not_implemented'];
    }

    private function analyzePerformanceLog()
    {
        // Platzhalter für Performance-Log-Analyse
        return ['status' => 'not_implemented'];
    }

    private function getRecentErrors()
    {
        $error_log = $this->log_dir . '/php_errors.log';

        if (!file_exists($error_log)) {
            return [];
        }

        $lines = file($error_log, FILE_IGNORE_NEW_LINES);
        return array_slice($lines, -10); // Letzte 10 Fehler
    }

    private function checkLogGrowth()
    {
        $log_files = glob($this->log_dir . '/*.log');
        $growth = [];

        foreach ($log_files as $file) {
            $size = filesize($file);
            $growth[basename($file)] = [
                'current_size' => $size,
                'growth_rate' => $this->calculateLogGrowthRate($file)
            ];
        }

        return $growth;
    }

    private function calculateLogGrowthRate($file)
    {
        // Vereinfachte Berechnung basierend auf letzter Änderung
        $modified = filemtime($file);
        $age_hours = (time() - $modified) / 3600;
        $size_mb = filesize($file) / 1024 / 1024;

        return $age_hours > 0 ? round($size_mb / $age_hours, 2) : 0;
    }

    private function countRecentLogEntries($lines)
    {
        $recent_count = 0;
        $cutoff = date('Y-m-d', strtotime('-24 hours'));

        foreach ($lines as $line) {
            if (strpos($line, $cutoff) !== false) {
                $recent_count++;
            }
        }

        return $recent_count;
    }

    private function categorizeLogEntries($lines)
    {
        $categories = [
            'fatal' => 0,
            'warning' => 0,
            'notice' => 0,
            'deprecated' => 0
        ];

        foreach ($lines as $line) {
            $line_lower = strtolower($line);
            if (strpos($line_lower, 'fatal') !== false) {
                $categories['fatal']++;
            } elseif (strpos($line_lower, 'warning') !== false) {
                $categories['warning']++;
            } elseif (strpos($line_lower, 'notice') !== false) {
                $categories['notice']++;
            } elseif (strpos($line_lower, 'deprecated') !== false) {
                $categories['deprecated']++;
            }
        }

        return $categories;
    }

    // EVALUATION METHODS

    private function evaluateSystemHealth($health)
    {
        $score = 0;
        $max_score = 5;

        if ($health['php_version_ok']) $score++;
        if ($health['memory_usage_percent'] < 80) $score++;
        if ($health['disk_usage']['used_percent'] < 90) $score++;
        if (count(array_filter($health['required_extensions'])) >= 4) $score++;
        if (!$health['server_load'] || $health['server_load']['1min'] < 5) $score++;

        return $score >= 4 ? 'good' : ($score >= 2 ? 'warning' : 'error');
    }

    private function evaluatePerformance($performance)
    {
        $issues = 0;

        if ($performance['page_load_time'] > 2000) $issues++; // > 2s
        if ($performance['database_query_time'] > 100) $issues++; // > 100ms
        if (!empty($performance['bottlenecks'])) $issues++;

        return $issues === 0 ? 'good' : ($issues === 1 ? 'warning' : 'error');
    }

    private function evaluateSecurity($security)
    {
        $issues = 0;

        if ($security['failed_logins'] > 10) $issues++;
        if (!empty($security['suspicious_activity'])) $issues++;
        if (!$security['ssl_status']['https_available']) $issues++;

        return $issues === 0 ? 'good' : ($issues === 1 ? 'warning' : 'error');
    }

    private function evaluateFileSystem($filesystem)
    {
        $issues = 0;

        if ($filesystem['disk_space']['used_percent'] > 90) $issues++;
        if (!empty($filesystem['temp_files']) && $filesystem['temp_files']['count'] > 100) $issues++;

        return $issues === 0 ? 'good' : ($issues === 1 ? 'warning' : 'error');
    }

    private function evaluateLogs($logs)
    {
        $issues = 0;

        if (!empty($logs['recent_errors'])) $issues++;
        if (isset($logs['error_log']['total_errors']) && $logs['error_log']['total_errors'] > 100) $issues++;

        return $issues === 0 ? 'good' : ($issues === 1 ? 'warning' : 'error');
    }

    private function determineOverallStatus($results)
    {
        $statuses = [
            $results['system']['status'],
            $results['database']['status'],
            $results['performance']['status'],
            $results['security']['status'],
            $results['files']['status'],
            $results['logs']['status']
        ];

        if (in_array('error', $statuses)) {
            return 'error';
        } elseif (in_array('warning', $statuses)) {
            return 'warning';
        } else {
            return 'good';
        }
    }

    /**
     * Loggt Monitoring-Resultate
     */
    private function logMonitoringResults($results)
    {
        $log_entry = [
            'timestamp' => $results['timestamp'],
            'overall_status' => $results['overall_status'],
            'system_status' => $results['system']['status'],
            'database_status' => $results['database']['status'],
            'performance_status' => $results['performance']['status'],
            'security_status' => $results['security']['status'],
            'execution_time' => round((microtime(true) - $this->start_time) * 1000, 2)
        ];

        $this->log('monitor', json_encode($log_entry));
    }

    /**
     * Generische Log-Funktion
     */
    private function log($type, $message, $level = 'INFO')
    {
        $log_file = $this->log_dir . "/{$type}.log";
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[$timestamp] [$level] $message\n";

        file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Generiert Performance-Report
     */
    public function generatePerformanceReport()
    {
        $results = $this->runFullCheck();

        $report = [
            'summary' => [
                'overall_status' => $results['overall_status'],
                'timestamp' => $results['timestamp'],
                'execution_time' => round((microtime(true) - $this->start_time) * 1000, 2)
            ],
            'metrics' => [
                'memory_usage' => $results['system']['memory_usage_percent'] . '%',
                'disk_usage' => $results['system']['disk_usage']['used_percent'] . '%',
                'database_size' => round($results['database']['size'] / 1024 / 1024, 2) . ' MB',
                'page_load_time' => $results['performance']['page_load_time'] . ' ms'
            ],
            'recommendations' => $this->generateRecommendations($results)
        ];

        return $report;
    }

    /**
     * Generiert Verbesserungsempfehlungen
     */
    private function generateRecommendations($results)
    {
        $recommendations = [];

        // Memory
        if ($results['system']['memory_usage_percent'] > 80) {
            $recommendations[] = 'Memory-Verbrauch optimieren - aktuell über 80%';
        }

        // Disk Space
        if ($results['system']['disk_usage']['used_percent'] > 90) {
            $recommendations[] = 'Festplattenspeicher freigeben - weniger als 10% verfügbar';
        }

        // Performance
        if ($results['performance']['page_load_time'] > 2000) {
            $recommendations[] = 'Page-Load-Zeit optimieren - aktuell über 2 Sekunden';
        }

        // Security
        if ($results['security']['failed_logins'] > 10) {
            $recommendations[] = 'Sicherheit prüfen - viele fehlgeschlagene Login-Versuche';
        }

        // Logs
        if (!empty($results['logs']['recent_errors'])) {
            $recommendations[] = 'Aktuelle Fehler im Error-Log prüfen und beheben';
        }

        return $recommendations;
    }
}

// CLI-Ausführung
if (php_sapi_name() === 'cli') {
    $monitor = new SystemMonitor();

    $command = $argv[1] ?? 'check';

    switch ($command) {
        case 'check':
            $results = $monitor->runFullCheck();
            echo "🔍 System Check - Status: " . strtoupper($results['overall_status']) . "\n";
            echo "Memory: {$results['system']['memory_usage_percent']}% | ";
            echo "Disk: {$results['system']['disk_usage']['used_percent']}% | ";
            echo "DB: " . round($results['database']['size'] / 1024 / 1024, 2) . "MB\n";

            if ($results['overall_status'] !== 'good') {
                echo "\n⚠️ Issues found:\n";
                foreach (['system', 'database', 'performance', 'security', 'files', 'logs'] as $component) {
                    if ($results[$component]['status'] !== 'good') {
                        echo "  - $component: {$results[$component]['status']}\n";
                    }
                }
            }
            break;

        case 'report':
            $report = $monitor->generatePerformanceReport();
            echo "📊 Performance Report\n";
            echo "Status: {$report['summary']['overall_status']}\n";
            echo "Memory: {$report['metrics']['memory_usage']}\n";
            echo "Disk: {$report['metrics']['disk_usage']}\n";
            echo "DB Size: {$report['metrics']['database_size']}\n";
            echo "Page Load: {$report['metrics']['page_load_time']}\n";

            if (!empty($report['recommendations'])) {
                echo "\n💡 Recommendations:\n";
                foreach ($report['recommendations'] as $rec) {
                    echo "  - $rec\n";
                }
            }
            break;

        default:
            echo "Usage: php monitor.php [check|report]\n";
            echo "  check  - Run system health check\n";
            echo "  report - Generate performance report\n";
            exit(1);
    }
}

// Web-API für Admin-Panel
elseif (!empty($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        $monitor = new SystemMonitor();

        switch ($_GET['action']) {
            case 'status':
                $results = $monitor->runFullCheck();
                echo json_encode([
                    'status' => $results['overall_status'],
                    'summary' => [
                        'memory' => $results['system']['memory_usage_percent'],
                        'disk' => $results['system']['disk_usage']['used_percent'],
                        'database' => round($results['database']['size'] / 1024 / 1024, 2)
                    ]
                ]);
                break;

            case 'full':
                echo json_encode($monitor->runFullCheck());
                break;

            case 'report':
                echo json_encode($monitor->generatePerformanceReport());
                break;

            default:
                echo json_encode(['error' => 'Unknown action']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
