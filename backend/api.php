<?php

/**
 * Mobile Car Service - API Router
 * Zentrale API-Endpunkt-Verwaltung
 */

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
require_once __DIR__ . '/utils/Security.php';
require_once __DIR__ . '/controllers/BookingController.php';
require_once __DIR__ . '/controllers/CustomerController.php';
require_once __DIR__ . '/controllers/EmailController.php';
require_once __DIR__ . '/models/Service.php';
require_once __DIR__ . '/utils/MapsService.php';

/**
 * API Router Klasse
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
        $this->handleSecurity();
    }

    /**
     * Routen definieren
     */
    private function setupRoutes()
    {
        // Booking Routes
        $this->addRoute('POST', 'bookings', [$this->controllers['booking'], 'create']);
        $this->addRoute('GET', 'bookings', [$this->controllers['booking'], 'getAll']);
        $this->addRoute('GET', 'bookings/{id}', [$this->controllers['booking'], 'get']);
        $this->addRoute('GET', 'bookings/number/{bookingNumber}', [$this->controllers['booking'], 'getByNumber']);
        $this->addRoute('PUT', 'bookings/{id}', [$this->controllers['booking'], 'update']);
        $this->addRoute('DELETE', 'bookings/{id}', [$this->controllers['booking'], 'delete']);
        $this->addRoute('POST', 'bookings/{id}/cancel', [$this->controllers['booking'], 'cancel']);
        $this->addRoute('GET', 'bookings/today', [$this->controllers['booking'], 'getToday']);
        $this->addRoute('GET', 'bookings/upcoming', [$this->controllers['booking'], 'getUpcoming']);
        $this->addRoute('GET', 'bookings/stats', [$this->controllers['booking'], 'getStats']);
        $this->addRoute('GET', 'bookings/available-slots/{date}', [$this->controllers['booking'], 'getAvailableSlots']);
        $this->addRoute('POST', 'bookings/calculate-distance', [$this->controllers['booking'], 'calculateDistance']);
        $this->addRoute('POST', 'bookings/{id}/send-reminder', [$this->controllers['booking'], 'sendReminder']);
        $this->addRoute('POST', 'bookings/{id}/resend-confirmation', [$this->controllers['booking'], 'resendConfirmation']);

        // Customer Routes
        $this->addRoute('POST', 'customers', [$this->controllers['customer'], 'create']);
        $this->addRoute('GET', 'customers', [$this->controllers['customer'], 'getAll']);
        $this->addRoute('GET', 'customers/{id}', [$this->controllers['customer'], 'get']);
        $this->addRoute('GET', 'customers/email/{email}', [$this->controllers['customer'], 'getByEmail']);
        $this->addRoute('PUT', 'customers/{id}', [$this->controllers['customer'], 'update']);
        $this->addRoute('DELETE', 'customers/{id}', [$this->controllers['customer'], 'delete']);
        $this->addRoute('GET', 'customers/{id}/bookings', [$this->controllers['customer'], 'getBookings']);
        $this->addRoute('GET', 'customers/stats', [$this->controllers['customer'], 'getStats']);
        $this->addRoute('GET', 'customers/duplicates', [$this->controllers['customer'], 'getDuplicates']);
        $this->addRoute('GET', 'customers/export', [$this->controllers['customer'], 'export']);
        $this->addRoute('GET', 'customers/by-city/{city}', [$this->controllers['customer'], 'getByCity']);
        $this->addRoute('POST', 'customers/search', [$this->controllers['customer'], 'search']);

        // Service Routes
        $this->addRoute('GET', 'services', [$this, 'getServices']);
        $this->addRoute('GET', 'services/{id}', [$this, 'getService']);
        $this->addRoute('GET', 'services/category/{category}', [$this, 'getServicesByCategory']);
        $this->addRoute('GET', 'services/popular', [$this, 'getPopularServices']);
        $this->addRoute('GET', 'services/search/{query}', [$this, 'searchServices']);
        $this->addRoute('GET', 'services/combinations', [$this, 'getServiceCombinations']);
        $this->addRoute('GET', 'services/stats', [$this, 'getServiceStats']);

        // Maps/Distance Routes
        $this->addRoute('POST', 'distance/calculate', [$this, 'calculateDistance']);
        $this->addRoute('POST', 'distance/batch', [$this, 'calculateDistanceBatch']);
        $this->addRoute('GET', 'distance/cache-stats', [$this, 'getDistanceCacheStats']);
        $this->addRoute('DELETE', 'distance/cache', [$this, 'clearDistanceCache']);

        // Email Routes
        $this->addRoute('POST', 'email/test', [$this, 'sendTestEmail']);
        $this->addRoute('POST', 'email/contact', [$this, 'sendContactEmail']);

        // System Routes
        $this->addRoute('GET', 'system/health', [$this, 'healthCheck']);
        $this->addRoute('GET', 'system/info', [$this, 'systemInfo']);
        $this->addRoute('GET', 'system/stats', [$this, 'systemStats']);

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
        } catch (SecurityException $e) {
            $this->handleSecurityException($e);
        } catch (ValidationException $e) {
            errorResponse('Validierungsfehler', 400, $e->getErrors());
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
                $params[$paramName] = $this->pathSegments[$i] ?? null;
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

            // Parameter als separate Argumente übergeben
            $args = array_values($params);
            call_user_func_array([$object, $method], $args);
        } else {
            call_user_func($handler, $params);
        }
    }

    /**
     * Security-Behandlung
     */
    private function handleSecurity()
    {
        // Security Headers setzen
        Security::setSecurityHeaders();

        // IP-Blockierung prüfen
        $clientIp = getClientIp();
        if (security()->isIpBlocked($clientIp)) {
            errorResponse('IP-Adresse blockiert', 403);
        }

        // Rate Limiting für API-Calls
        try {
            security()->checkRateLimit($clientIp . '_api', 100, 3600); // 100 Requests pro Stunde
        } catch (SecurityException $e) {
            // Bei Rate Limit Überschreitung IP temporär blockieren
            security()->blockIp($clientIp, 'Rate limit exceeded', 1800); // 30 Min
            errorResponse('Zu viele Anfragen', 429);
        }

        // Session-Sicherheit
        Security::secureSession();
    }

    /**
     * Service-Endpunkte
     */
    public function getServices()
    {
        try {
            $services = $this->controllers['service']->getAll();
            successResponse($services);
        } catch (Exception $e) {
            errorResponse('Fehler beim Abrufen der Services', 500);
        }
    }

    public function getService($id)
    {
        try {
            $service = $this->controllers['service']->findById($id);
            if (!$service) {
                errorResponse('Service nicht gefunden', 404);
            }
            successResponse($service);
        } catch (Exception $e) {
            errorResponse('Fehler beim Abrufen des Service', 500);
        }
    }

    public function getServicesByCategory($category)
    {
        try {
            $services = $this->controllers['service']->getByCategory($category);
            successResponse(['category' => $category, 'services' => $services]);
        } catch (Exception $e) {
            errorResponse('Fehler beim Abrufen der Services', 500);
        }
    }

    public function getPopularServices()
    {
        try {
            $services = $this->controllers['service']->getPopular();
            successResponse($services);
        } catch (Exception $e) {
            errorResponse('Fehler beim Abrufen der beliebten Services', 500);
        }
    }

    public function searchServices($query)
    {
        try {
            $services = $this->controllers['service']->search(urldecode($query));
            successResponse(['query' => $query, 'services' => $services]);
        } catch (Exception $e) {
            errorResponse('Fehler bei der Service-Suche', 500);
        }
    }

    public function getServiceCombinations()
    {
        try {
            $combinations = $this->controllers['service']->getSuggestedCombinations();
            successResponse($combinations);
        } catch (Exception $e) {
            errorResponse('Fehler beim Abrufen der Service-Kombinationen', 500);
        }
    }

    public function getServiceStats()
    {
        try {
            $stats = $this->controllers['service']->getStats();
            successResponse($stats);
        } catch (Exception $e) {
            errorResponse('Fehler beim Abrufen der Service-Statistiken', 500);
        }
    }

    /**
     * Maps/Distance-Endpunkte
     */
    public function calculateDistance()
    {
        try {
            $input = getJsonInput();

            if (empty($input['address'])) {
                errorResponse('Adresse ist erforderlich', 400);
            }

            $result = $this->controllers['maps']->calculateDistance($input['address']);
            $travelCost = $this->controllers['maps']->calculateTravelCost($result['distance']);
            $serviceArea = $this->controllers['maps']->isInServiceArea($result['distance']);

            successResponse([
                'distance' => $result,
                'travel_cost' => $travelCost,
                'service_area' => $serviceArea
            ]);
        } catch (Exception $e) {
            errorResponse('Entfernungsberechnung fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }

    public function calculateDistanceBatch()
    {
        try {
            $input = getJsonInput();

            if (empty($input['addresses']) || !is_array($input['addresses'])) {
                errorResponse('Adressen-Array ist erforderlich', 400);
            }

            if (count($input['addresses']) > 10) {
                errorResponse('Maximal 10 Adressen pro Batch', 400);
            }

            $results = $this->controllers['maps']->calculateDistanceBatch($input['addresses']);
            successResponse($results);
        } catch (Exception $e) {
            errorResponse('Batch-Entfernungsberechnung fehlgeschlagen', 500);
        }
    }

    public function getDistanceCacheStats()
    {
        try {
            $stats = $this->controllers['maps']->getCacheStats();
            successResponse($stats);
        } catch (Exception $e) {
            errorResponse('Fehler beim Abrufen der Cache-Statistiken', 500);
        }
    }

    public function clearDistanceCache()
    {
        try {
            $this->controllers['maps']->clearCache();
            successResponse(null, 'Cache erfolgreich geleert');
        } catch (Exception $e) {
            errorResponse('Fehler beim Leeren des Cache', 500);
        }
    }

    /**
     * E-Mail-Endpunkte
     */
    public function sendTestEmail()
    {
        try {
            $input = getJsonInput();

            if (empty($input['email'])) {
                errorResponse('E-Mail-Adresse ist erforderlich', 400);
            }

            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                errorResponse('Ungültige E-Mail-Adresse', 400);
            }

            $this->controllers['email']->sendTestEmail($input['email']);
            successResponse(null, 'Test-E-Mail erfolgreich gesendet');
        } catch (Exception $e) {
            errorResponse('Test-E-Mail konnte nicht gesendet werden: ' . $e->getMessage(), 500);
        }
    }

    public function sendContactEmail()
    {
        try {
            $input = getJsonInput();

            $required = ['name', 'email', 'subject', 'message'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    errorResponse("Feld '$field' ist erforderlich", 400);
                }
            }

            Security::checkHoneypot('website', $input);

            $this->controllers['email']->sendContactEmail($input);
            successResponse(null, 'Kontaktanfrage erfolgreich gesendet');
        } catch (SecurityException $e) {
            errorResponse($e->getMessage(), $e->getHttpCode());
        } catch (Exception $e) {
            errorResponse('Kontaktanfrage konnte nicht gesendet werden', 500);
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
                'version' => APP_VERSION
            ];

            $overallStatus = 'ok';
            foreach (['database', 'email', 'storage'] as $component) {
                if ($health[$component]['status'] !== 'ok') {
                    $overallStatus = 'warning';
                }
            }

            $health['status'] = $overallStatus;
            $httpCode = $overallStatus === 'ok' ? 200 : 503;

            jsonResponse($health, $httpCode);
        } catch (Exception $e) {
            errorResponse('Health Check fehlgeschlagen', 500);
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
                    'php_version' => PHP_VERSION,
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'upload_max_filesize' => ini_get('upload_max_filesize')
                ],
                'environment' => [
                    'debug' => config('app.debug'),
                    'timezone' => date_default_timezone_get(),
                    'locale' => 'de_DE'
                ]
            ];

            successResponse($info);
        } catch (Exception $e) {
            errorResponse('System-Info konnte nicht abgerufen werden', 500);
        }
    }

    public function systemStats()
    {
        try {
            $stats = [
                'database' => db()->getStats(),
                'services' => $this->controllers['service']->getStats(),
                'cache' => $this->controllers['maps']->getCacheStats(),
                'uptime' => $this->getUptime()
            ];

            successResponse($stats);
        } catch (Exception $e) {
            errorResponse('System-Statistiken konnten nicht abgerufen werden', 500);
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
            errorResponse('Konfiguration konnte nicht abgerufen werden', 500);
        }
    }

    /**
     * Helper-Methoden
     */
    private function getPath()
    {
        $requestUri = $_SERVER['REQUEST_URI'];
        $scriptName = $_SERVER['SCRIPT_NAME'];

        // Basis-Pfad entfernen
        $basePath = dirname($scriptName);
        if ($basePath !== '/') {
            $requestUri = substr($requestUri, strlen($basePath));
        }

        // Query-String entfernen
        if (($pos = strpos($requestUri, '?')) !== false) {
            $requestUri = substr($requestUri, 0, $pos);
        }

        return $requestUri;
    }

    private function checkDatabase()
    {
        try {
            $stats = db()->getStats();
            return ['status' => 'ok', 'stats' => $stats];
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
            $paths = [BASE_PATH, LOGS_PATH, DB_PATH];
            $writableCount = 0;

            foreach ($paths as $path) {
                if (is_dir($path) && is_writable($path)) {
                    $writableCount++;
                } elseif (is_file($path) && is_writable(dirname($path))) {
                    $writableCount++;
                }
            }

            $status = $writableCount === count($paths) ? 'ok' : 'warning';
            return ['status' => $status, 'writable_paths' => $writableCount . '/' . count($paths)];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function getUptime()
    {
        $uptime = file_exists('/proc/uptime') ? file_get_contents('/proc/uptime') : null;
        return $uptime ? floatval(explode(' ', $uptime)[0]) : null;
    }

    private function notFound()
    {
        errorResponse('Endpunkt nicht gefunden', 404);
    }

    private function handleSecurityException(SecurityException $e)
    {
        error_log('Security violation: ' . $e->getMessage() . ' from ' . getClientIp());
        errorResponse($e->getMessage(), $e->getHttpCode());
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
    errorResponse('Kritischer Systemfehler', 500);
}
