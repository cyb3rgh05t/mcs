<?php
// api/distance.php - Professionelle Entfernungsberechnung
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Error handling
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    // Load configuration
    require_once '../config/config.php';
    require_once '../classes/SecurityManager.php';

    // Rate limiting
    $client_ip = SecurityManager::getClientIP();
    if (!SecurityManager::rateLimitCheck($client_ip, 10, 60)) {
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
        exit;
    }

    // Parse JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }

    $address = trim($input['address'] ?? '');

    if (empty($address)) {
        http_response_code(400);
        echo json_encode(['error' => 'Address is required']);
        exit;
    }

    // Validate and sanitize address
    $address = SecurityManager::validateAddress($address);
    if (!$address) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid address format']);
        exit;
    }

    // Log API usage
    SecurityManager::logSecurityEvent('distance_api_request', [
        'address' => substr($address, 0, 50) . '...',
        'ip' => $client_ip
    ]);

    // Check Google Maps API configuration
    $api_key = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';

    if (empty($api_key) || $api_key === 'YOUR_GOOGLE_MAPS_API_KEY') {
        // Fallback to estimation
        $estimated_distance = estimateDistanceFromAddress($address);

        echo json_encode([
            'success' => true,
            'distance_km' => $estimated_distance,
            'duration' => calculateEstimatedDuration($estimated_distance),
            'travel_cost' => round($estimated_distance * (defined('TRAVEL_COST_PER_KM') ? TRAVEL_COST_PER_KM : 0.50), 2),
            'estimated' => true,
            'method' => 'fallback_estimation',
            'message' => 'Geschätzte Entfernung (Google Maps API nicht konfiguriert)'
        ]);
        exit;
    }

    // Business location (configurable)
    $business_address = defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : 'Herne, Deutschland';

    // Prepare Google Maps API request
    $url = 'https://maps.googleapis.com/maps/api/distancematrix/json?' . http_build_query([
        'origins' => $business_address,
        'destinations' => $address,
        'mode' => 'driving',
        'units' => 'metric',
        'language' => 'de',
        'departure_time' => 'now',
        'traffic_model' => 'best_guess',
        'key' => $api_key
    ]);

    // Create HTTP context with timeout
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'MCS Booking System/1.0',
            'ignore_errors' => true
        ]
    ]);

    // Make API request
    $response = file_get_contents($url, false, $context);

    if ($response === false) {
        throw new Exception('Failed to connect to Google Maps API');
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid response from Google Maps API');
    }

    // Handle API response
    if ($data['status'] !== 'OK') {
        handleGoogleMapsError($data['status'], $address);
        exit;
    }

    $element = $data['rows'][0]['elements'][0] ?? null;

    if (!$element || $element['status'] !== 'OK') {
        handleElementError($element['status'] ?? 'UNKNOWN', $address);
        exit;
    }

    // Extract successful result
    $distance_km = $element['distance']['value'] / 1000;
    $duration = $element['duration']['text'];
    $duration_seconds = $element['duration']['value'];

    // Check for traffic info
    $traffic_duration = null;
    if (isset($element['duration_in_traffic'])) {
        $traffic_duration = $element['duration_in_traffic']['text'];
    }

    $travel_cost = $distance_km * (defined('TRAVEL_COST_PER_KM') ? TRAVEL_COST_PER_KM : 0.50);

    // Return successful result
    echo json_encode([
        'success' => true,
        'distance_km' => round($distance_km, 1),
        'duration' => $duration,
        'duration_seconds' => $duration_seconds,
        'traffic_duration' => $traffic_duration,
        'travel_cost' => round($travel_cost, 2),
        'estimated' => false,
        'method' => 'google_maps_api',
        'business_location' => $business_address
    ]);
} catch (Exception $e) {
    error_log('Distance API Error: ' . $e->getMessage());

    // Fallback on any error
    $address = $input['address'] ?? '';
    $estimated = estimateDistanceFromAddress($address);

    echo json_encode([
        'success' => true,
        'distance_km' => $estimated,
        'duration' => calculateEstimatedDuration($estimated),
        'travel_cost' => round($estimated * (defined('TRAVEL_COST_PER_KM') ? TRAVEL_COST_PER_KM : 0.50), 2),
        'estimated' => true,
        'method' => 'error_fallback',
        'message' => 'Automatische Berechnung nicht verfügbar - Schätzung verwendet'
    ]);
}

/**
 * Handle Google Maps API errors
 */
function handleGoogleMapsError($status, $address)
{
    $estimated = estimateDistanceFromAddress($address);

    $error_messages = [
        'INVALID_REQUEST' => 'Ungültige Anfrage an Google Maps',
        'MAX_ELEMENTS_EXCEEDED' => 'Zu viele Anfragen an Google Maps',
        'OVER_DAILY_LIMIT' => 'Google Maps Tageslimit überschritten',
        'OVER_QUERY_LIMIT' => 'Google Maps Anfragelimit überschritten',
        'REQUEST_DENIED' => 'Google Maps Anfrage verweigert',
        'UNKNOWN_ERROR' => 'Unbekannter Google Maps Fehler'
    ];

    $message = $error_messages[$status] ?? "Google Maps Fehler: $status";

    echo json_encode([
        'success' => true,
        'distance_km' => $estimated,
        'duration' => calculateEstimatedDuration($estimated),
        'travel_cost' => round($estimated * (defined('TRAVEL_COST_PER_KM') ? TRAVEL_COST_PER_KM : 0.50), 2),
        'estimated' => true,
        'method' => 'google_maps_error_fallback',
        'message' => "$message - Schätzung verwendet",
        'google_error' => $status
    ]);
}

/**
 * Handle element-level errors
 */
function handleElementError($status, $address)
{
    $estimated = estimateDistanceFromAddress($address);

    switch ($status) {
        case 'NOT_FOUND':
            echo json_encode([
                'success' => true,
                'distance_km' => $estimated,
                'duration' => calculateEstimatedDuration($estimated),
                'travel_cost' => round($estimated * (defined('TRAVEL_COST_PER_KM') ? TRAVEL_COST_PER_KM : 0.50), 2),
                'estimated' => true,
                'method' => 'address_not_found_fallback',
                'message' => 'Adresse nicht gefunden - Schätzung verwendet'
            ]);
            break;

        case 'ZERO_RESULTS':
            echo json_encode([
                'error' => 'Keine Route zur angegebenen Adresse gefunden',
                'suggestions' => [
                    'Überprüfen Sie die Schreibweise der Adresse',
                    'Geben Sie eine vollständige Adresse mit PLZ an',
                    'Kontaktieren Sie uns direkt für schwer erreichbare Orte'
                ]
            ]);
            break;

        case 'MAX_ROUTE_LENGTH_EXCEEDED':
            echo json_encode([
                'error' => 'Entfernung zu groß für Anfahrt',
                'message' => 'Die angegebene Adresse liegt außerhalb unseres Servicegebiets'
            ]);
            break;

        default:
            echo json_encode([
                'success' => true,
                'distance_km' => $estimated,
                'duration' => calculateEstimatedDuration($estimated),
                'travel_cost' => round($estimated * (defined('TRAVEL_COST_PER_KM') ? TRAVEL_COST_PER_KM : 0.50), 2),
                'estimated' => true,
                'method' => 'unknown_error_fallback',
                'message' => "Routenberechnung fehlgeschlagen ($status) - Schätzung verwendet"
            ]);
    }
}

/**
 * Intelligent distance estimation based on address
 */
function estimateDistanceFromAddress($address)
{
    $address = strtolower($address);

    // Entfernungen von Rheine zu verschiedenen Städten/Regionen
    $distance_map = [
        // Direkte Umgebung (Ruhrgebiet)
        'herne' => 3,
        'bochum' => 8,
        'gelsenkirchen' => 10,
        'recklinghausen' => 12,
        'castrop-rauxel' => 8,
        'wanne-eickel' => 5,
        'gladbeck' => 12,  // KORRIGIERT: vorher 120km
        'bottrop' => 15,
        'herten' => 10,

        // Nahes Ruhrgebiet
        'essen' => 20,
        'dortmund' => 20,
        'witten' => 12,
        'oberhausen' => 22,
        'duisburg' => 28,
        'mülheim' => 25,
        'hamm' => 35,
        'unna' => 30,

        // Weitere Ruhrgebietsstädte
        'wuppertal' => 35,
        'solingen' => 40,
        'remscheid' => 45,
        'hagen' => 30,
        'iserlohn' => 45,
        'lüdenscheid' => 50,

        // Düsseldorf & Umgebung
        'düsseldorf' => 45,
        'neuss' => 40,
        'krefeld' => 35,
        'mönchengladbach' => 45,
        'viersen' => 50,

        // Köln & Umgebung  
        'köln' => 80,
        'leverkusen' => 55,
        'bergisch gladbach' => 65,
        'bonn' => 100,
        'troisdorf' => 90,

        // Niederrhein
        'wesel' => 40,
        'moers' => 30,
        'dinslaken' => 25,
        'voerde' => 35,
        'kleve' => 70,

        // Münsterland (KORRIGIERT)
        'münster' => 65,
        'rheine' => 85,  // KORRIGIERT: Rheine ist 85km von Herne
        'emsdetten' => 95,
        'steinfurt' => 100,
        'coesfeld' => 45,
        'borken' => 50,
        'ahaus' => 65,
        'gronau' => 75,

        // Sauerland
        'arnsberg' => 50,
        'meschede' => 70,
        'sundern' => 60,
        'brilon' => 85,
        'winterberg' => 100,

        // Ostwestfalen
        'bielefeld' => 110,
        'gütersloh' => 100,
        'paderborn' => 120,
        'detmold' => 130,
        'minden' => 120,
        'herford' => 115,

        // Bergisches Land
        'siegen' => 90,
        'gummersbach' => 70,
        'attendorn' => 75,

        // Aachen Region
        'aachen' => 120,
        'düren' => 90,
        'eschweiler' => 110,
        'stolberg' => 115,

        // Weitere NRW Städte
        'koblenz' => 150,
        'trier' => 200,
        'mainz' => 180,
        'wiesbaden' => 180,
        'frankfurt' => 200,

        // Niedersachsen
        'osnabrück' => 130,
        'hannover' => 200,
        'braunschweig' => 250,
        'göttingen' => 200,
        'oldenburg' => 180,
        'bremen' => 200,

        // Große Städte Deutschland
        'hamburg' => 320,
        'berlin' => 500,
        'münchen' => 600,
        'stuttgart' => 400,
        'nürnberg' => 400,
        'dresden' => 500,
        'leipzig' => 400,
        'karlsruhe' => 350,
        'mannheim' => 320,
        'augsburg' => 550,

        // Niederlande (von Herne aus)
        'venlo' => 60,
        'roermond' => 70,
        'eindhoven' => 100,
        'maastricht' => 120,
        'nijmegen' => 90,
        'arnhem' => 85,
        'utrecht' => 150,
        'amsterdam' => 180,
        'rotterdam' => 160,
        'den haag' => 170,
        'groningen' => 220,
        'enschede' => 100,
    ];

    // PLZ-basierte Schätzung (angepasst für Herne als Zentrum)
    if (preg_match('/\b(446\d{2}|447\d{2})\b/', $address)) {
        return rand(3, 15); // Herne und direkte Umgebung
    }

    if (preg_match('/\b(44\d{3})\b/', $address)) {
        return rand(10, 30); // Ruhrgebiet
    }

    if (preg_match('/\b(45\d{3}|46\d{3}|47\d{3})\b/', $address)) {
        return rand(15, 50); // Erweitertes Ruhrgebiet/Niederrhein
    }

    if (preg_match('/\b(40\d{3}|41\d{3}|42\d{3})\b/', $address)) {
        return rand(30, 60); // Düsseldorf/Wuppertal Region
    }

    if (preg_match('/\b(48\d{3}|49\d{3})\b/', $address)) {
        return rand(50, 120); // Münsterland/Osnabrück
    }

    if (preg_match('/\b(50\d{3}|51\d{3}|52\d{3}|53\d{3})\b/', $address)) {
        return rand(60, 150); // Köln/Bonn/Aachen Region
    }

    if (preg_match('/\b(5[4-9]\d{3})\b/', $address)) {
        return rand(70, 200); // Südliches NRW/Rheinland-Pfalz
    }

    // Standard für unbekannte Adressen
    return 35;
}

/**
 * Calculate estimated duration based on distance
 */
function calculateEstimatedDuration($distance_km)
{
    if ($distance_km <= 20) {
        $minutes = $distance_km * 2; // Stadtverkehr
    } elseif ($distance_km <= 50) {
        $minutes = $distance_km * 1.5; // Landstraße
    } else {
        $minutes = $distance_km * 1.2; // Autobahn
    }

    $minutes = max(10, round($minutes)); // Minimum 10 Minuten

    if ($minutes < 60) {
        return $minutes . ' Min.';
    } else {
        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;
        if ($remaining_minutes > 0) {
            return $hours . ' Std. ' . $remaining_minutes . ' Min.';
        } else {
            return $hours . ' Std.';
        }
    }
}
