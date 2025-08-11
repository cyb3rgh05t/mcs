<?php

/**
 * Mobile Car Service - Maps Service (Backend)
 * Serverseitige Entfernungsberechnung und Geocoding
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';

class MapsService
{
    private $db;
    private $companyAddress;
    private $cache = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->companyAddress = [
            'street' => COMPANY_ADDRESS,
            'lat' => config('business.company_lat', 52.2756),
            'lng' => config('business.company_lng', 7.4383)
        ];
        $this->loadCache();
    }

    /**
     * Hauptfunktion zur Entfernungsberechnung
     */
    public function calculateDistance($customerAddress, $useCache = true)
    {
        try {
            // Input validieren
            $this->validateAddress($customerAddress);

            // Cache-Key erstellen
            $cacheKey = $this->createCacheKey($customerAddress);

            // Cache prüfen
            if ($useCache && isset($this->cache[$cacheKey])) {
                $cachedResult = $this->cache[$cacheKey];

                // Cache-Alter prüfen (24 Stunden)
                if (time() - $cachedResult['calculated_at'] < 86400) {
                    return $cachedResult;
                }
            }

            // Neue Berechnung
            $result = $this->performDistanceCalculation($customerAddress);

            // In Cache speichern
            if ($useCache) {
                $this->cache[$cacheKey] = $result;
                $this->saveCache();
            }

            return $result;
        } catch (Exception $e) {
            error_log('Distance calculation failed: ' . $e->getMessage());

            // Fallback: Geschätzte Entfernung basierend auf PLZ
            return $this->estimateDistanceByPostalCode($customerAddress['zip']);
        }
    }

    /**
     * Anfahrtskosten berechnen
     */
    public function calculateTravelCost($distance)
    {
        $freeDistance = (float)config('business.free_distance_km', FREE_DISTANCE_KM);
        $costPerKm = (float)config('business.travel_cost_per_km', TRAVEL_COST_PER_KM);

        if ($distance <= $freeDistance) {
            return [
                'distance' => $distance,
                'free_distance' => $freeDistance,
                'chargeable_distance' => 0,
                'cost_per_km' => $costPerKm,
                'total_cost' => 0.00,
                'is_free' => true,
                'message' => "Kostenlose Anfahrt (unter {$freeDistance}km)"
            ];
        }

        $chargeableDistance = $distance - $freeDistance;
        $totalCost = $chargeableDistance * $costPerKm;

        return [
            'distance' => $distance,
            'free_distance' => $freeDistance,
            'chargeable_distance' => round($chargeableDistance, 2),
            'cost_per_km' => $costPerKm,
            'total_cost' => round($totalCost, 2),
            'is_free' => false,
            'message' => "{$chargeableDistance}km × {$costPerKm}€ = {$totalCost}€"
        ];
    }

    /**
     * Führt die eigentliche Entfernungsberechnung durch
     */
    private function performDistanceCalculation($customerAddress)
    {
        // Versuche zuerst Google Maps API (falls konfiguriert)
        if (config('maps.google_api_key')) {
            try {
                return $this->calculateWithGoogleMaps($customerAddress);
            } catch (Exception $e) {
                error_log('Google Maps API failed: ' . $e->getMessage());
                // Fallback zur Simulation
            }
        }

        // Simulation basierend auf bekannten Entfernungen
        $distance = $this->simulateDistanceCalculation($customerAddress);

        return [
            'distance' => round($distance, 1),
            'method' => 'simulation',
            'duration_minutes' => $this->estimateDrivingTime($distance),
            'calculated_at' => time(),
            'coordinates' => $this->estimateCoordinates($customerAddress),
            'route_info' => $this->generateSimulatedRoute($customerAddress, $distance)
        ];
    }

    /**
     * Google Maps Distance Matrix API verwenden
     */
    private function calculateWithGoogleMaps($customerAddress)
    {
        $apiKey = config('maps.google_api_key');
        $origin = urlencode($this->companyAddress['street']);
        $destination = urlencode($this->formatAddressString($customerAddress));

        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?" .
            "origins={$origin}&destinations={$destination}&units=metric&key={$apiKey}";

        $response = $this->makeHttpRequest($url);
        $data = json_decode($response, true);

        if ($data['status'] !== 'OK') {
            throw new Exception('Google Maps API error: ' . $data['status']);
        }

        $element = $data['rows'][0]['elements'][0];

        if ($element['status'] !== 'OK') {
            throw new Exception('Route not found: ' . $element['status']);
        }

        $distanceKm = $element['distance']['value'] / 1000;
        $durationMinutes = $element['duration']['value'] / 60;

        return [
            'distance' => round($distanceKm, 1),
            'method' => 'google_maps',
            'duration_minutes' => round($durationMinutes),
            'calculated_at' => time(),
            'raw_response' => $element
        ];
    }

    /**
     * Simulierte Entfernungsberechnung
     */
    private function simulateDistanceCalculation($customerAddress)
    {
        $city = strtolower(trim($customerAddress['city']));
        $zip = trim($customerAddress['zip']);

        // Bekannte Entfernungen von Rheine (Firmensitz)
        $knownDistances = [
            // Direkte Nachbarn (0-15km)
            'rheine' => $this->getDistanceByPostalCode($zip, '48431'),
            'salzbergen' => 8,
            'spelle' => 12,
            'emsdetten' => 15,
            'neuenkirchen' => 10,
            'wettringen' => 14,
            'hopsten' => 20,
            'recke' => 15,

            // Regionale Städte (15-50km)
            'münster' => 35,
            'steinfurt' => 18,
            'greven' => 20,
            'ibbenbüren' => 30,
            'lengerich' => 25,
            'mettingen' => 22,
            'tecklenburg' => 28,
            'ladbergen' => 25,
            'saerbeck' => 12,
            'nordwalde' => 22,

            // Größere Städte (50-100km)
            'osnabrück' => 45,
            'nordhorn' => 55,
            'coesfeld' => 40,
            'borken' => 65,
            'ahaus' => 70,
            'vreden' => 75,
            'stadtlohn' => 80,
            'gronau' => 25,

            // Niederländische Städte
            'enschede' => 25,
            'hengelo' => 35,
            'almelo' => 30,
            'oldenzaal' => 20,
            'deventer' => 85,
            'zwolle' => 95,

            // Weitere deutsche Städte
            'bremen' => 150,
            'hannover' => 160,
            'hamburg' => 250,
            'dortmund' => 80,
            'essen' => 110,
            'düsseldorf' => 120,
            'köln' => 150,
            'berlin' => 450,
            'frankfurt' => 280,
            'stuttgart' => 400,
            'münchen' => 520
        ];

        // Direkte Übereinstimmung suchen
        foreach ($knownDistances as $cityName => $distance) {
            if (strpos($city, $cityName) !== false || strpos($cityName, $city) !== false) {
                return is_numeric($distance) ? $distance : $distance;
            }
        }

        // Fallback: PLZ-basierte Schätzung
        return $this->estimateDistanceByPostalCode($zip);
    }

    /**
     * Entfernung basierend auf PLZ schätzen
     */
    private function estimateDistanceByPostalCode($customerZip)
    {
        $companyZip = 48431; // Rheine
        $customerZipInt = (int)$customerZip;
        $zipDiff = abs($customerZipInt - $companyZip);

        // PLZ-Bereiche und geschätzte Entfernungen
        $plzRanges = [
            // 48xxx - Münsterland (lokal)
            [48000, 48999, function ($diff) {
                return min(5 + ($diff * 0.05), 50);
            }],

            // 49xxx - Osnabrück Region
            [49000, 49999, function ($diff) {
                return 30 + min($diff * 0.08, 70);
            }],

            // 44xxx-47xxx - Ruhrgebiet
            [44000, 47999, function ($diff) {
                return 80 + min($diff * 0.02, 50);
            }],

            // 50xxx-53xxx - Köln/Bonn
            [50000, 53999, function ($diff) {
                return 120 + min($diff * 0.01, 80);
            }],

            // 40xxx-43xxx - Düsseldorf/Wuppertal
            [40000, 43999, function ($diff) {
                return 100 + min($diff * 0.02, 60);
            }],

            // 30xxx-39xxx - Hannover/Göttingen
            [30000, 39999, function ($diff) {
                return 140 + min($diff * 0.01, 100);
            }],

            // 20xxx-29xxx - Hamburg/Bremen
            [20000, 29999, function ($diff) {
                return 200 + min($diff * 0.005, 100);
            }],

            // Niederländische PLZ simulieren (7xxx)
            [7000, 7999, function ($diff) {
                return 20 + min($diff * 0.1, 80);
            }]
        ];

        foreach ($plzRanges as [$min, $max, $calculator]) {
            if ($customerZipInt >= $min && $customerZipInt <= $max) {
                return $calculator($zipDiff);
            }
        }

        // Standard-Fallback für unbekannte Bereiche
        return min(50 + ($zipDiff * 0.01), 500);
    }

    /**
     * Detaillierte PLZ-basierte Berechnung
     */
    private function getDistanceByPostalCode($customerZip, $companyZip = '48431')
    {
        $customer = (int)$customerZip;
        $company = (int)$companyZip;

        // Spezifische PLZ-Entfernungen (bekannte Orte)
        $specificDistances = [
            // Rheine und Umgebung
            48431 => 0,   // Rheine Zentrum
            48432 => 3,   // Rheine Nord
            48429 => 5,   // Rheine Süd
            48455 => 8,   // Salzbergen
            48480 => 12,  // Spelle
            48485 => 10,  // Neuenkirchen
            48496 => 20,  // Hopsten
            48477 => 15,  // Hörstel

            // Münsterland
            48143 => 35,  // Münster Zentrum
            48149 => 32,  // Münster West
            48159 => 38,  // Münster Ost
            48163 => 40,  // Münster Süd
            48167 => 42,  // Münster Nord
            48301 => 25,  // Nottuln
            48317 => 30,  // Dülmen
            48268 => 20,  // Greven
            48565 => 18,  // Steinfurt
            48599 => 25,  // Gronau

            // Osnabrück Region
            49074 => 45,  // Osnabrück Zentrum
            49076 => 43,  // Osnabrück West
            49082 => 47,  // Osnabrück Ost
            49088 => 50,  // Osnabrück Nord
            49124 => 38,  // Georgsmarienhütte
            49479 => 30,  // Ibbenbüren
            49525 => 25,  // Lengerich
            49545 => 22,  // Tecklenburg
            49565 => 40,  // Bramsche

            // Ruhrgebiet (Auswahl)
            44137 => 90,  // Dortmund
            45127 => 110, // Essen
            46045 => 130, // Oberhausen
            47051 => 140, // Duisburg

            // Köln/Düsseldorf
            50667 => 150, // Köln
            40210 => 120, // Düsseldorf

            // Niederlande (geschätzt)
            7500 => 25,   // Enschede (NL)
            7600 => 30,   // Almelo (NL)
            7700 => 35,   // Dedemsvaart (NL)
        ];

        // Direkte Übereinstimmung
        if (isset($specificDistances[$customer])) {
            return $specificDistances[$customer];
        }

        // Nächste bekannte PLZ finden
        $nearestDistance = 100; // Fallback
        $minDiff = PHP_INT_MAX;

        foreach ($specificDistances as $plz => $distance) {
            $diff = abs($customer - $plz);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $nearestDistance = $distance;
            }
        }

        // Entfernung basierend auf PLZ-Differenz anpassen
        $adjustment = min($minDiff / 1000, 20); // Max 20km Anpassung

        return max(1, $nearestDistance + $adjustment);
    }

    /**
     * Fahrtzeit schätzen
     */
    private function estimateDrivingTime($distance)
    {
        // Durchschnittsgeschwindigkeit basierend auf Entfernung
        if ($distance <= 10) {
            $avgSpeed = 35; // Stadtverkehr
        } elseif ($distance <= 30) {
            $avgSpeed = 50; // Landstraße
        } elseif ($distance <= 100) {
            $avgSpeed = 65; // Schnellstraße/Autobahn
        } else {
            $avgSpeed = 80; // Autobahn
        }

        return round(($distance / $avgSpeed) * 60); // Minuten
    }

    /**
     * Koordinaten schätzen
     */
    private function estimateCoordinates($address)
    {
        $city = strtolower(trim($address['city']));

        // Bekannte Koordinaten
        $coordinates = [
            'rheine' => ['lat' => 52.2756, 'lng' => 7.4383],
            'münster' => ['lat' => 51.9607, 'lng' => 7.6261],
            'osnabrück' => ['lat' => 52.2799, 'lng' => 8.0472],
            'steinfurt' => ['lat' => 52.1482, 'lng' => 7.3379],
            'emsdetten' => ['lat' => 52.1725, 'lng' => 7.5289],
            'ibbenbüren' => ['lat' => 52.2766, 'lng' => 7.7164],
            'greven' => ['lat' => 52.0907, 'lng' => 7.6094],
            'lengerich' => ['lat' => 52.1872, 'lng' => 7.8707],
            'enschede' => ['lat' => 52.2215, 'lng' => 6.8937],
            'gronau' => ['lat' => 52.2108, 'lng' => 7.0378]
        ];

        foreach ($coordinates as $cityName => $coords) {
            if (strpos($city, $cityName) !== false || strpos($cityName, $city) !== false) {
                return $coords;
            }
        }

        // Fallback: Geschätzte Koordinaten basierend auf PLZ
        $zip = (int)$address['zip'];
        $baseLat = 52.0;
        $baseLng = 7.0;

        // Grobe PLZ-zu-Koordinaten Zuordnung
        $latOffset = ($zip - 48000) * 0.0001;
        $lngOffset = ($zip - 48000) * 0.0001;

        return [
            'lat' => round($baseLat + $latOffset, 4),
            'lng' => round($baseLng + $lngOffset, 4)
        ];
    }

    /**
     * Simulierte Route generieren
     */
    private function generateSimulatedRoute($customerAddress, $distance)
    {
        $city = strtolower(trim($customerAddress['city']));
        $steps = [];

        // Start
        $steps[] = [
            'instruction' => 'Starten Sie in Rheine, ' . $this->companyAddress['street'],
            'distance' => 0,
            'duration' => 0
        ];

        // Hauptroute basierend auf Zielort
        if ($distance > 5) {
            if (strpos($city, 'münster') !== false) {
                $steps[] = [
                    'instruction' => 'Fahren Sie auf die B481 Richtung Münster',
                    'distance' => round($distance * 0.3),
                    'duration' => round($distance * 0.3 / 50 * 60)
                ];
            } elseif (strpos($city, 'osnabrück') !== false) {
                $steps[] = [
                    'instruction' => 'Fahren Sie auf die A30 Richtung Osnabrück',
                    'distance' => round($distance * 0.4),
                    'duration' => round($distance * 0.4 / 70 * 60)
                ];
            } elseif (strpos($city, 'steinfurt') !== false) {
                $steps[] = [
                    'instruction' => 'Fahren Sie auf die B54 Richtung Steinfurt',
                    'distance' => round($distance * 0.6),
                    'duration' => round($distance * 0.6 / 50 * 60)
                ];
            } else {
                $steps[] = [
                    'instruction' => 'Folgen Sie der Hauptstraße',
                    'distance' => round($distance * 0.5),
                    'duration' => round($distance * 0.5 / 45 * 60)
                ];
            }
        }

        // Ziel
        $steps[] = [
            'instruction' => "Ankunft: {$customerAddress['street']}, {$customerAddress['zip']} {$customerAddress['city']}",
            'distance' => $distance,
            'duration' => $this->estimateDrivingTime($distance)
        ];

        return [
            'steps' => $steps,
            'total_distance' => $distance,
            'total_duration' => $this->estimateDrivingTime($distance),
            'method' => 'simulated'
        ];
    }

    /**
     * HTTP Request ausführen
     */
    private function makeHttpRequest($url, $timeout = 10)
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'user_agent' => 'Mobile Car Service/1.0'
            ]
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception('HTTP request failed');
        }

        return $response;
    }

    /**
     * Adresse validieren
     */
    private function validateAddress($address)
    {
        $required = ['street', 'zip', 'city'];

        foreach ($required as $field) {
            if (empty($address[$field])) {
                throw new Exception("Adressfeld '$field' ist erforderlich");
            }
        }

        // PLZ Format prüfen
        if (!preg_match('/^[0-9]{5}$/', $address['zip'])) {
            throw new Exception('Ungültige PLZ (muss 5 Ziffern haben)');
        }

        return true;
    }

    /**
     * Adresse als String formatieren
     */
    private function formatAddressString($address)
    {
        return trim($address['street'] . ', ' . $address['zip'] . ' ' . $address['city']);
    }

    /**
     * Cache-Key erstellen
     */
    private function createCacheKey($address)
    {
        $key = strtolower($address['street'] . '_' . $address['zip'] . '_' . $address['city']);
        return 'dist_' . md5($key);
    }

    /**
     * Cache laden
     */
    private function loadCache()
    {
        $cacheFile = __DIR__ . '/../data/distance_cache.json';

        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            $this->cache = $data ?: [];
        }
    }

    /**
     * Cache speichern
     */
    private function saveCache()
    {
        $cacheFile = __DIR__ . '/../data/distance_cache.json';
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Alte Cache-Einträge bereinigen (älter als 7 Tage)
        $cutoff = time() - (7 * 24 * 3600);
        foreach ($this->cache as $key => $entry) {
            if (isset($entry['calculated_at']) && $entry['calculated_at'] < $cutoff) {
                unset($this->cache[$key]);
            }
        }

        file_put_contents($cacheFile, json_encode($this->cache, JSON_PRETTY_PRINT));
    }

    /**
     * Servicebereich prüfen
     */
    public function isInServiceArea($distance, $maxDistance = null)
    {
        $maxDistance = $maxDistance ?: config('business.max_service_distance', 100);

        return [
            'in_area' => $distance <= $maxDistance,
            'distance' => $distance,
            'max_distance' => $maxDistance,
            'message' => $distance <= $maxDistance
                ? 'Im Servicebereich'
                : "Außerhalb des Servicebereichs (max. {$maxDistance}km)"
        ];
    }

    /**
     * Batch-Entfernungsberechnung für mehrere Adressen
     */
    public function calculateDistanceBatch($addresses)
    {
        $results = [];

        foreach ($addresses as $index => $address) {
            try {
                $results[$index] = $this->calculateDistance($address);
            } catch (Exception $e) {
                $results[$index] = [
                    'error' => $e->getMessage(),
                    'distance' => null
                ];
            }
        }

        return $results;
    }

    /**
     * Cache-Statistiken
     */
    public function getCacheStats()
    {
        return [
            'cache_size' => count($this->cache),
            'cache_file_size' => $this->getCacheFileSize(),
            'oldest_entry' => $this->getOldestCacheEntry(),
            'newest_entry' => $this->getNewestCacheEntry()
        ];
    }

    /**
     * Cache-Dateigröße
     */
    private function getCacheFileSize()
    {
        $cacheFile = __DIR__ . '/../data/distance_cache.json';
        return file_exists($cacheFile) ? filesize($cacheFile) : 0;
    }

    /**
     * Ältester Cache-Eintrag
     */
    private function getOldestCacheEntry()
    {
        $oldest = null;

        foreach ($this->cache as $entry) {
            if (
                isset($entry['calculated_at']) &&
                ($oldest === null || $entry['calculated_at'] < $oldest)
            ) {
                $oldest = $entry['calculated_at'];
            }
        }

        return $oldest ? date('Y-m-d H:i:s', $oldest) : null;
    }

    /**
     * Neuester Cache-Eintrag
     */
    private function getNewestCacheEntry()
    {
        $newest = null;

        foreach ($this->cache as $entry) {
            if (
                isset($entry['calculated_at']) &&
                ($newest === null || $entry['calculated_at'] > $newest)
            ) {
                $newest = $entry['calculated_at'];
            }
        }

        return $newest ? date('Y-m-d H:i:s', $newest) : null;
    }

    /**
     * Cache leeren
     */
    public function clearCache()
    {
        $this->cache = [];
        $cacheFile = __DIR__ . '/../data/distance_cache.json';

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        return true;
    }
}

// Helper-Funktion
function mapsService()
{
    return new MapsService();
}
