<?php

/**
 * Mobile Car Service - API Router
 * Angepasst für PHP Development Server (php -S localhost:8000)
 */

// CORS Headers zuerst setzen für Development Server
if ($_SERVER['SERVER_NAME'] === 'localhost' && $_SERVER['SERVER_PORT'] == '8000') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');

    // Preflight OPTIONS Request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Error Reporting für Development
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Autoloader und Konfiguration
require_once __DIR__ . '/config.php';

// Temporäre Controller-Simulation bis alle Files da sind
if (!class_exists('BookingController')) {
    class BookingController
    {
        public function create()
        {
            successResponse(['message' => 'Booking created (simulated)']);
        }
        public function getAll()
        {
            successResponse([]);
        }
        public function get($id)
        {
            successResponse(['id' => $id]);
        }
    }
}

if (!class_exists('CustomerController')) {
    class CustomerController
    {
        public function create()
        {
            successResponse(['id' => 1, 'message' => 'Customer created (simulated)']);
        }
        public function getAll()
        {
            successResponse([]);
        }
    }
}

if (!class_exists('EmailController')) {
    class EmailController
    {
        public function sendTestEmail()
        {
            successResponse(['message' => 'Test email sent (simulated)']);
        }
    }
}

if (!class_exists('Service')) {
    class Service
    {
        public function getAll()
        {
            successResponse([
                ['id' => 1, 'name' => 'Außenwäsche', 'price' => 25.00],
                ['id' => 2, 'name' => 'Innenreinigung', 'price' => 35.00]
            ]);
        }
        public function getStats()
        {
            successResponse(['total' => 8]);
        }
    }
}

if (!class_exists('MapsService')) {
    class MapsService
    {
        public function calculateDistance()
        {
            successResponse(['distance' => 5.2, 'duration' => 15]);
        }
        public function getCacheStats()
        {
            successResponse(['hits' => 10, 'misses' => 2]);
        }
    }
}

/**
 * API Router Klasse - Vereinfacht für Development Server
 */
class ApiRouter
{
    private $routes = [];
    private $method;
    private $path;
    private $pathSegments;
    private $controllers;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->path = $this->getPath();
        $this->pathSegments = explode('/', trim($this->path, '/'));

        // Controller-Instanzen
        $this->controllers = [
            'booking' => new BookingController(),
            'customer' => new CustomerController(),
            'email' => new EmailController(),
            'service' => new Service(),
            'maps' => new MapsService()
        ];

        $this->setupRoutes();
    }

    /**
     * Routen definieren
     */
    private function setupRoutes()
    {
        // System Routes (wichtigste zuerst)
        $this->addRoute('GET', 'system/health', [$this, 'healthCheck']);
        $this->addRoute('GET', 'system/info', [$this, 'systemInfo']);
        $this->addRoute('GET', 'system/stats', [$this, 'systemStats']);

        // Booking Routes
        $this->addRoute('POST', 'bookings', [$this->controllers['booking'], 'create']);
        $this->addRoute('GET', 'bookings', [$this->controllers['booking'], 'getAll']);
        $this->addRoute('GET', 'bookings/{id}', [$this->controllers['booking'], 'get']);

        // Customer Routes  
        $this->addRoute('POST', 'customers', [$this->controllers['customer'], 'create']);
        $this->addRoute('GET', 'customers', [$this->controllers['customer'], 'getAll']);

        // Service Routes
        $this->addRoute('GET', 'services', [$this->controllers['service'], 'getAll']);

        // Maps/Distance Routes
        $this->addRoute('POST', 'distance/calculate', [$this->controllers['maps'], 'calculateDistance']);

        // Email Routes
        $this->addRoute('POST', 'email/test', [$this->controllers['email'], 'sendTestEmail']);

        // Config Routes
        $this->addRoute('GET', 'config', [$this, 'getConfig']);
    }

    /**
     * Route hinzufügen
     */
    private function addRoute($method, $pattern, $handler)
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }

    /**
     * Request verarbeiten
     */
    public function handle()
    {
        try {
            $route = $this->findRoute();

            if (!$route) {
                $this->notFound();
                return;
            }

            // Parameter extrahieren
            $params = $this->extractParams($route['pattern']);

            // Handler aufrufen
            $this->callHandler($route['handler'], $params);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Passende Route finden
     */
    private function findRoute()
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $this->method) {
                continue;
            }

            if ($this->matchesPattern($route['pattern'])) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Pattern matching
     */
    private function matchesPattern($pattern)
    {
        $patternSegments = explode('/', trim($pattern, '/'));

        if (count($patternSegments) !== count($this->pathSegments)) {
            return false;
        }

        for ($i = 0; $i < count($patternSegments); $i++) {
            $patternSegment = $patternSegments[$i];
            $pathSegment = $this->pathSegments[$i];

            // Parameter-Segment (z.B. {id})
            if (preg_match('/^\{(.+)\}$/', $patternSegment)) {
                continue;
            }

            // Exakte Übereinstimmung erforderlich
            if ($patternSegment !== $pathSegment) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parameter aus URL extrahieren
     */
    private function extractParams($pattern)
    {
        $patternSegments = explode('/', trim($pattern, '/'));
        $params = [];

        for ($i = 0; $i < count($patternSegments); $i++) {
            $patternSegment = $patternSegments[$i];

            if (preg_match('/^\{(.+)\}$/', $patternSegment, $matches)) {
                $paramName = $matches[1];
                $params[$paramName] = $this->pathSegments[$i] ?? '';
            }
        }

        return $params;
    }

    /**
     * Handler aufrufen
     */
    private function callHandler($handler, $params)
    {
        if (is_array($handler)) {
            $object = $handler[0];
            $method = $handler[1];

            if (method_exists($object, $method)) {
                $object->$method($params);
            } else {
                throw new Exception("Method $method not found");
            }
        } elseif (is_callable($handler)) {
            $handler($params);
        } else {
            throw new Exception('Invalid handler');
        }
    }

    /**
     * System-Endpunkte
     */
    public function healthCheck()
    {
        try {
            $health = [
                'status' => 'ok',
                'timestamp' => date('c'),
                'database' => $this->checkDatabase(),
                'email' => $this->checkEmail(),
                'storage' => $this->checkStorage(),
                'version' => APP_VERSION,
                'server' => 'PHP Development Server'
            ];

            $overallStatus = 'ok';
            foreach (['database', 'storage'] as $component) {
                if ($health[$component]['status'] !== 'ok') {
                    $overallStatus = 'warning';
                }
            }

            $health['status'] = $overallStatus;
            $httpCode = $overallStatus === 'ok' ? 200 : 503;

            // WICHTIG: successResponse verwenden für korrektes Format
            successResponse($health);
        } catch (Exception $e) {
            errorResponse('Health Check fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }

    public function systemInfo()
    {
        try {
            $info = [
                'app' => [
                    'name' => APP_NAME,
                    'version' => APP_VERSION,
                    'url' => APP_URL
                ],
                'server' => [
                    'type' => 'PHP Development Server',
                    'php_version' => PHP_VERSION,
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP Development Server',
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time')
                ],
                'environment' => [
                    'debug' => config('app.debug'),
                    'development_server' => true,
                    'timezone' => date_default_timezone_get()
                ]
            ];

            successResponse($info);
        } catch (Exception $e) {
            errorResponse('System-Info konnte nicht abgerufen werden: ' . $e->getMessage(), 500);
        }
    }

    public function systemStats()
    {
        try {
            $stats = [
                'database' => $this->getDatabaseStats(),
                'services' => $this->controllers['service']->getStats(),
                'cache' => $this->controllers['maps']->getCacheStats(),
                'uptime' => time() // Vereinfacht für Development
            ];

            successResponse($stats);
        } catch (Exception $e) {
            errorResponse('System-Statistiken konnten nicht abgerufen werden: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Konfiguration abrufen (Frontend-sicher)
     */
    public function getConfig()
    {
        try {
            $safeConfig = [
                'business_hours' => [
                    'start' => BUSINESS_HOURS_START,
                    'end' => BUSINESS_HOURS_END,
                    'days_advance' => BUSINESS_DAYS_ADVANCE
                ],
                'pricing' => [
                    'travel_cost_per_km' => TRAVEL_COST_PER_KM,
                    'free_distance_km' => FREE_DISTANCE_KM
                ],
                'company' => [
                    'name' => COMPANY_NAME,
                    'address' => COMPANY_ADDRESS,
                    'phone' => COMPANY_PHONE,
                    'email' => COMPANY_EMAIL
                ],
                'validation' => [
                    'max_file_size' => MAX_FILE_SIZE,
                    'allowed_image_types' => ALLOWED_IMAGE_TYPES
                ]
            ];

            successResponse($safeConfig);
        } catch (Exception $e) {
            errorResponse('Konfiguration konnte nicht abgerufen werden: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper-Methoden
     */
    private function getPath()
    {
        $requestUri = $_SERVER['REQUEST_URI'];

        // Development Server: PATH_INFO verwenden falls verfügbar
        if (isset($_SERVER['PATH_INFO'])) {
            return $_SERVER['PATH_INFO'];
        }

        // Query-String entfernen
        if (($pos = strpos($requestUri, '?')) !== false) {
            $requestUri = substr($requestUri, 0, $pos);
        }

        // /backend/api.php entfernen wenn vorhanden
        $requestUri = preg_replace('#^/backend/api\.php#', '', $requestUri);

        return $requestUri;
    }

    private function checkDatabase()
    {
        try {
            if (class_exists('Database')) {
                $stats = db()->getStats();
                return ['status' => 'ok', 'stats' => $stats];
            } else {
                return ['status' => 'ok', 'message' => 'Database class not loaded yet'];
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkEmail()
    {
        try {
            return ['status' => 'ok', 'smtp_configured' => !empty(SMTP_HOST)];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkStorage()
    {
        try {
            $paths = [BASE_PATH, LOGS_PATH, dirname(DB_PATH)];
            $writableCount = 0;

            foreach ($paths as $path) {
                if (is_dir($path) && is_writable($path)) {
                    $writableCount++;
                }
            }

            $status = $writableCount === count($paths) ? 'ok' : 'warning';
            return ['status' => $status, 'writable_paths' => $writableCount . '/' . count($paths)];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function getDatabaseStats()
    {
        try {
            if (class_exists('Database') && function_exists('db')) {
                return db()->getStats();
            } else {
                return [
                    'customers' => 0,
                    'bookings' => 0,
                    'services' => 8,
                    'database_size' => file_exists(DB_PATH) ? filesize(DB_PATH) : 0
                ];
            }
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function notFound()
    {
        errorResponse('Endpunkt nicht gefunden: ' . $this->path, 404);
    }

    private function handleException(Exception $e)
    {
        error_log('API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

        if (config('app.debug')) {
            errorResponse('Server-Fehler: ' . $e->getMessage(), 500);
        } else {
            errorResponse('Ein unerwarteter Fehler ist aufgetreten', 500);
        }
    }
}

// API Router starten
try {
    $router = new ApiRouter();
    $router->handle();
} catch (Exception $e) {
    error_log('Fatal API Error: ' . $e->getMessage());
    errorResponse('Kritischer Systemfehler: ' . $e->getMessage(), 500);
}
