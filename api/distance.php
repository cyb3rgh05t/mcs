<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$address = $input['address'] ?? '';

if (empty($address)) {
    echo json_encode(['error' => 'Address is required']);
    exit;
}

// Google Maps API Key - In Produktion aus Umgebungsvariable laden
$api_key = 'AIzaSyBbVppnqML9ojgSNrtINJedxSZGR_iDxug';
$business_address = 'Rheine, Deutschland'; // Ihr Geschäftsstandort

$url = 'https://maps.googleapis.com/maps/api/distancematrix/json?' . http_build_query([
    'origins' => $business_address,
    'destinations' => $address,
    'mode' => 'driving',
    'units' => 'metric',
    'key' => $api_key
]);

$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['status'] === 'OK' && $data['rows'][0]['elements'][0]['status'] === 'OK') {
    $distance = $data['rows'][0]['elements'][0]['distance']['value'] / 1000; // Convert to km
    $duration = $data['rows'][0]['elements'][0]['duration']['text'];

    $travel_cost = $distance * 0.50; // 0.50€ per km

    echo json_encode([
        'success' => true,
        'distance_km' => round($distance, 1),
        'duration' => $duration,
        'travel_cost' => round($travel_cost, 2)
    ]);
} else {
    echo json_encode([
        'error' => 'Could not calculate distance',
        'google_response' => $data
    ]);
}
