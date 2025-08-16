<?php
// api/distance.php - Professionelle Entfernungsberechnung mit Google Maps und Fallback
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

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
        'ip' => SecurityManager::getClientIP()
    ]);

    // Check Google Maps API configuration
    $api_key = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';

    if (empty($api_key) || $api_key === 'YOUR_GOOGLE_MAPS_API_KEY' || strpos($api_key, 'AIza') !== 0) {
        // Fallback to estimation
        $estimated_distance = estimateDistanceFromAddress($address);

        echo json_encode([
            'success' => true,
            'distance_km' => $estimated_distance,
            'duration' => calculateEstimatedDuration($estimated_distance),
            'travel_cost' => calculateTravelCostWithNewLogic($estimated_distance, 0),
            'estimated' => true,
            'method' => 'fallback_estimation',
            'message' => 'Geschätzte Entfernung (Google Maps API nicht konfiguriert)'
        ]);
        exit;
    }

    // Business location from config
    $business_address = defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : 'Hüllerstraße 16, 44649 Herne, Deutschland';

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
    $distance_km = round($element['distance']['value'] / 1000, 1);
    $duration = $element['duration']['text'];
    $duration_seconds = $element['duration']['value'];

    // Check for traffic info
    $traffic_duration = null;
    if (isset($element['duration_in_traffic'])) {
        $traffic_duration = $element['duration_in_traffic']['text'];
    }

    // Calculate travel cost with new logic (will be determined by services later)
    $travel_cost_preview = calculateTravelCostWithNewLogic($distance_km, 0);

    // Return successful result
    echo json_encode([
        'success' => true,
        'distance_km' => $distance_km,
        'duration' => $duration,
        'duration_seconds' => $duration_seconds,
        'traffic_duration' => $traffic_duration,
        'travel_cost' => $travel_cost_preview,
        'estimated' => false,
        'method' => 'google_maps_api',
        'business_location' => $business_address,
        'config' => [
            'free_km' => TRAVEL_FREE_KM,
            'cost_per_km' => TRAVEL_COST_PER_KM,
            'min_service_amount' => TRAVEL_MIN_SERVICE_AMOUNT,
            'max_distance_small' => TRAVEL_MAX_DISTANCE_SMALL,
            'max_distance_large' => TRAVEL_MAX_DISTANCE_LARGE,
            'absolute_max' => TRAVEL_ABSOLUTE_MAX_DISTANCE
        ]
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
        'travel_cost' => calculateTravelCostWithNewLogic($estimated, 0),
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
        'REQUEST_DENIED' => 'Google Maps Anfrage verweigert - API Key prüfen',
        'UNKNOWN_ERROR' => 'Unbekannter Google Maps Fehler'
    ];

    $message = $error_messages[$status] ?? "Google Maps Fehler: $status";

    echo json_encode([
        'success' => true,
        'distance_km' => $estimated,
        'duration' => calculateEstimatedDuration($estimated),
        'travel_cost' => calculateTravelCostWithNewLogic($estimated, 0),
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
                'travel_cost' => calculateTravelCostWithNewLogic($estimated, 0),
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
                'travel_cost' => calculateTravelCostWithNewLogic($estimated, 0),
                'estimated' => true,
                'method' => 'unknown_error_fallback',
                'message' => "Routenberechnung fehlgeschlagen ($status) - Schätzung verwendet"
            ]);
    }
}

/**
 * Intelligent distance estimation based on address for Herne location
 */
function estimateDistanceFromAddress($address)
{
    $address = strtolower($address);

    // Entfernungen von Herne zu verschiedenen Städten
    $distance_map = [
        // Direkte Umgebung Herne
        'herne' => 3,
        'bochum' => 8,
        'gelsenkirchen' => 10,
        'recklinghausen' => 12,
        'castrop-rauxel' => 8,
        'wanne-eickel' => 5,
        'gladbeck' => 12,
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

        // Weitere Ruhrgebietsstädte
        'wuppertal' => 35,
        'solingen' => 40,
        'düsseldorf' => 45,
        'köln' => 80,
    ];

    // Check for known cities
    foreach ($distance_map as $city => $distance) {
        if (strpos($address, $city) !== false) {
            // Add some randomness for realism
            return $distance + rand(-2, 3);
        }
    }

    // PLZ-based estimation for Herne region (446xx, 447xx)
    if (preg_match('/\b(446\d{2}|447\d{2})\b/', $address)) {
        return rand(3, 15); // Herne und direkte Umgebung
    }

    if (preg_match('/\b(44\d{3})\b/', $address)) {
        return rand(10, 30); // Ruhrgebiet
    }

    if (preg_match('/\b(45\d{3}|46\d{3}|47\d{3})\b/', $address)) {
        return rand(15, 50); // Erweitertes Ruhrgebiet
    }

    if (preg_match('/\b(40\d{3}|41\d{3}|42\d{3})\b/', $address)) {
        return rand(30, 60); // Düsseldorf/Wuppertal Region
    }

    // Default for unknown addresses
    return 15;
}

/**
 * Calculate estimated duration based on distance
 */
function calculateEstimatedDuration($distance_km)
{
    if ($distance_km <= 20) {
        $minutes = $distance_km * 2.5; // Stadtverkehr
    } elseif ($distance_km <= 50) {
        $minutes = $distance_km * 1.8; // Landstraße
    } else {
        $minutes = $distance_km * 1.3; // Autobahn
    }

    $minutes = max(10, round($minutes));

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

/**
 * Calculate travel cost with new logic
 */
function calculateTravelCostWithNewLogic($distance_km, $services_total)
{
    // This is just a preview - actual calculation happens with services
    // For API response, we return 0 as services are not yet selected
    return 0;
}
