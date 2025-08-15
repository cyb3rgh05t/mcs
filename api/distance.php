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
    $business_address = defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : 'Rheine, Deutschland';

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
        // Lokale Umgebung (Kreis Steinfurt)
        'rheine' => 3,
        'emsdetten' => 12,
        'steinfurt' => 18,
        'horstmar' => 15,
        'laer' => 20,
        'altenberge' => 25,

        // Emsland
        'lingen' => 35,
        'meppen' => 55,
        'papenburg' => 65,
        'haren' => 45,

        // Münsterland
        'münster' => 45,
        'greven' => 30,
        'ibbenbüren' => 25,
        'lengerich' => 30,
        'tecklenburg' => 35,
        'hopsten' => 20,
        'mettingen' => 30,
        'recke' => 15,
        'westerkappeln' => 35,

        // Osnabrück Region
        'osnabrück' => 55,
        'bramsche' => 45,
        'georgsmarienhütte' => 60,
        'wallenhorst' => 50,

        // Niederrhein
        'wesel' => 85,
        'moers' => 90,
        'krefeld' => 95,
        'viersen' => 100,

        // Ruhrgebiet
        'duisburg' => 95,
        'oberhausen' => 85,
        'essen' => 90,
        'gelsenkirchen' => 80,
        'bochum' => 85,
        'dortmund' => 80,
        'hagen' => 95,
        'wuppertal' => 105,

        // Rheinland
        'düsseldorf' => 120,
        'köln' => 150,
        'bonn' => 160,
        'aachen' => 180,
        'mönchengladbach' => 110,

        // Westfalen
        'bielefeld' => 70,
        'gütersloh' => 60,
        'paderborn' => 90,
        'hamm' => 70,
        'unna' => 80,
        'soest' => 85,
        'arnsberg' => 100,
        'iserlohn' => 105,
        'lüdenscheid' => 110,
        'siegen' => 130,

        // Niedersachsen
        'hannover' => 130,
        'braunschweig' => 180,
        'göttingen' => 200,
        'hildesheim' => 150,
        'salzgitter' => 170,
        'wolfsburg' => 200,
        'celle' => 120,
        'lüneburg' => 150,
        'stade' => 170,
        'bremen' => 150,
        'oldenburg' => 120,
        'wilhelmshaven' => 160,
        'emden' => 140,
        'aurich' => 130,
        'leer' => 120,

        // Niederlande
        'enschede' => 45,
        'hengelo' => 55,
        'almelo' => 60,
        'deventer' => 80,
        'zwolle' => 95,
        'amsterdam' => 200,
        'rotterdam' => 180,
        'den haag' => 190,
        'utrecht' => 170,
        'eindhoven' => 120,
        'tilburg' => 130,
        'breda' => 140,
        'nijmegen' => 100,
        'arnhem' => 110,
        'apeldoorn' => 120,
        'groningen' => 150,

        // Weitere deutsche Großstädte
        'hamburg' => 250,
        'berlin' => 450,
        'münchen' => 550,
        'frankfurt' => 300,
        'stuttgart' => 400,
        'nürnberg' => 400,
        'dresden' => 500,
        'leipzig' => 400,
        'karlsruhe' => 350,
        'mannheim' => 320,
        'augsburg' => 500,
        'wiesbaden' => 300,
        'mönchengladbach' => 110,
        'gelsenkirchen' => 80,
        'braunschweig' => 180,
        'chemnitz' => 500,
        'kiel' => 280,
        'magdeburg' => 350,
        'freiburg' => 450,
        'lübeck' => 270,
        'erfurt' => 350,
        'halle' => 380,
        'rostock' => 320,
        'kassel' => 250,
        'mainz' => 300,
        'saarbrücken' => 350,
        'hamm' => 70,
        'ludwigshafen' => 320,
        'mülheim' => 90,
        'oldenburg' => 120,
        'leverkusen' => 130,
        'solingen' => 110,
        'herne' => 85,
        'neuss' => 120,
        'paderborn' => 90
    ];

    // Suche nach Städtenamen in der Adresse
    foreach ($distance_map as $city => $distance) {
        if (strpos($address, $city) !== false) {
            // Kleine Variation hinzufügen für Realismus
            return $distance + rand(-2, 5);
        }
    }

    // PLZ-basierte Schätzung
    if (preg_match('/\b(484\d{2}|483\d{2})\b/', $address)) {
        return rand(5, 25); // Rheine und direkte Umgebung
    }

    if (preg_match('/\b(485\d{2}|486\d{2}|487\d{2}|488\d{2}|489\d{2})\b/', $address)) {
        return rand(15, 45); // Münsterland/Emsland
    }

    if (preg_match('/\b(48\d{3}|49\d{3})\b/', $address)) {
        return rand(20, 80); // NRW/Niedersachsen allgemein
    }

    if (preg_match('/\b(4\d{4})\b/', $address)) {
        return rand(40, 150); // Deutschland West
    }

    if (preg_match('/\b([1-3]\d{4}|[5-9]\d{4})\b/', $address)) {
        return rand(100, 300); // Andere deutsche PLZ-Bereiche
    }

    // Niederländische PLZ
    if (preg_match('/\b(7\d{3}\s?[A-Z]{2})\b/', $address)) {
        return rand(40, 120); // Niederlande
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
