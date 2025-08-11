<?php

/**
 * Mobile Car Service - Service Model
 * Verwaltet alle Service-Operationen
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../utils/Validator.php';

class Service
{
    private $db;
    private $validator;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->validator = new Validator();
    }

    /**
     * Alle aktiven Services abrufen
     */
    public function getAll($includeInactive = false)
    {
        $sql = "SELECT * FROM services";

        if (!$includeInactive) {
            $sql .= " WHERE active = 1";
        }

        $sql .= " ORDER BY popular DESC, category ASC, price ASC";

        $services = $this->db->fetchAll($sql);

        return array_map([$this, 'formatService'], $services);
    }

    /**
     * Service anhand ID finden
     */
    public function findById($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            return null;
        }

        $sql = "SELECT * FROM services WHERE id = ?";
        $service = $this->db->fetchOne($sql, [$id]);

        return $service ? $this->formatService($service) : null;
    }

    /**
     * Services nach Kategorie abrufen
     */
    public function getByCategory($category)
    {
        $sql = "SELECT * FROM services WHERE category = ? AND active = 1 ORDER BY price ASC";
        $services = $this->db->fetchAll($sql, [$category]);

        return array_map([$this, 'formatService'], $services);
    }

    /**
     * Beliebte Services abrufen
     */
    public function getPopular()
    {
        $sql = "SELECT * FROM services WHERE popular = 1 AND active = 1 ORDER BY price ASC";
        $services = $this->db->fetchAll($sql);

        return array_map([$this, 'formatService'], $services);
    }

    /**
     * Services nach Preis-Range abrufen
     */
    public function getByPriceRange($minPrice = null, $maxPrice = null)
    {
        $params = [];
        $sql = "SELECT * FROM services WHERE active = 1";

        if ($minPrice !== null) {
            $sql .= " AND price >= ?";
            $params[] = $minPrice;
        }

        if ($maxPrice !== null) {
            $sql .= " AND price <= ?";
            $params[] = $maxPrice;
        }

        $sql .= " ORDER BY price ASC";

        $services = $this->db->fetchAll($sql, $params);
        return array_map([$this, 'formatService'], $services);
    }

    /**
     * Service suchen
     */
    public function search($query)
    {
        $searchTerm = '%' . trim($query) . '%';

        $sql = "SELECT * FROM services 
                WHERE active = 1 
                AND (name LIKE ? OR description LIKE ? OR detailed_description LIKE ?)
                ORDER BY 
                    CASE 
                        WHEN name LIKE ? THEN 1
                        WHEN description LIKE ? THEN 2
                        ELSE 3
                    END,
                    popular DESC,
                    price ASC";

        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
        $services = $this->db->fetchAll($sql, $params);

        return array_map([$this, 'formatService'], $services);
    }

    /**
     * Neuen Service erstellen (Admin)
     */
    public function create($data)
    {
        // Validierung
        $this->validateServiceData($data);

        $serviceData = [
            'name' => trim($data['name']),
            'description' => trim($data['description']),
            'detailed_description' => trim($data['detailed_description'] ?? ''),
            'price' => (float)$data['price'],
            'duration' => (int)$data['duration'],
            'category' => trim($data['category']),
            'icon' => trim($data['icon'] ?? 'fas fa-car'),
            'popular' => !empty($data['popular']) ? 1 : 0,
            'active' => !empty($data['active']) ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s')
        ];

        try {
            $serviceId = $this->db->insert('services', $serviceData);
            return $this->findById($serviceId);
        } catch (Exception $e) {
            error_log('Service creation failed: ' . $e->getMessage());
            throw new Exception('Service konnte nicht erstellt werden');
        }
    }

    /**
     * Service aktualisieren (Admin)
     */
    public function update($id, $data)
    {
        // Prüfen ob Service existiert
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception('Service nicht gefunden');
        }

        // Validierung
        $this->validateServiceData($data, $id);

        $updateData = [];
        $allowedFields = ['name', 'description', 'detailed_description', 'price', 'duration', 'category', 'icon', 'popular', 'active'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'price') {
                    $updateData[$field] = (float)$data[$field];
                } elseif ($field === 'duration') {
                    $updateData[$field] = (int)$data[$field];
                } elseif (in_array($field, ['popular', 'active'])) {
                    $updateData[$field] = !empty($data[$field]) ? 1 : 0;
                } else {
                    $updateData[$field] = trim($data[$field]);
                }
            }
        }

        try {
            $this->db->update('services', $updateData, 'id = ?', [$id]);
            return $this->findById($id);
        } catch (Exception $e) {
            error_log('Service update failed: ' . $e->getMessage());
            throw new Exception('Service konnte nicht aktualisiert werden');
        }
    }

    /**
     * Service deaktivieren/aktivieren
     */
    public function toggleActive($id)
    {
        $service = $this->findById($id);
        if (!$service) {
            throw new Exception('Service nicht gefunden');
        }

        $newStatus = $service['active'] ? 0 : 1;

        try {
            $this->db->update('services', ['active' => $newStatus], 'id = ?', [$id]);
            return $this->findById($id);
        } catch (Exception $e) {
            error_log('Service toggle failed: ' . $e->getMessage());
            throw new Exception('Service-Status konnte nicht geändert werden');
        }
    }

    /**
     * Service löschen (Admin)
     */
    public function delete($id)
    {
        $service = $this->findById($id);
        if (!$service) {
            throw new Exception('Service nicht gefunden');
        }

        // Prüfen ob Service in Buchungen verwendet wird
        $bookingCount = $this->getBookingCount($id);
        if ($bookingCount > 0) {
            throw new Exception('Service kann nicht gelöscht werden - wird in Buchungen verwendet');
        }

        try {
            $this->db->delete('services', 'id = ?', [$id]);
            return true;
        } catch (Exception $e) {
            error_log('Service deletion failed: ' . $e->getMessage());
            throw new Exception('Service konnte nicht gelöscht werden');
        }
    }

    /**
     * Anzahl Buchungen für einen Service
     */
    public function getBookingCount($serviceId)
    {
        $sql = "SELECT COUNT(*) as count FROM booking_services WHERE service_id = ?";
        $result = $this->db->fetchOne($sql, [$serviceId]);
        return $result['count'] ?? 0;
    }

    /**
     * Kategorien abrufen
     */
    public function getCategories()
    {
        $sql = "SELECT DISTINCT category FROM services WHERE active = 1 ORDER BY category";
        $categories = $this->db->fetchAll($sql);

        return array_column($categories, 'category');
    }

    /**
     * Service-Kombinationen vorschlagen
     */
    public function getSuggestedCombinations()
    {
        $combinations = [
            [
                'name' => 'Komplett-Paket',
                'description' => 'Perfekte Kombination für die komplette Fahrzeugpflege',
                'services' => [2, 5, 6], // Premium + Felgen + Wachs
                'discount' => 10, // 10% Rabatt
                'popular' => true
            ],
            [
                'name' => 'Express-Kombi',
                'description' => 'Schnell und effektiv für zwischendurch',
                'services' => [7, 5], // Schnell + Felgen
                'discount' => 5, // 5% Rabatt
                'popular' => false
            ],
            [
                'name' => 'Innen & Außen',
                'description' => 'Komplette Reinigung innen und außen',
                'services' => [1, 4], // Basis + Innenraum
                'discount' => 8, // 8% Rabatt
                'popular' => true
            ],
            [
                'name' => 'Premium Plus',
                'description' => 'Erweiterte Premium-Behandlung',
                'services' => [2, 6, 8], // Premium + Wachs + Motor
                'discount' => 12, // 12% Rabatt
                'popular' => false
            ]
        ];

        // Preise und Dauern berechnen
        foreach ($combinations as &$combo) {
            $services = [];
            $totalPrice = 0;
            $totalDuration = 0;

            foreach ($combo['services'] as $serviceId) {
                $service = $this->findById($serviceId);
                if ($service) {
                    $services[] = $service;
                    $totalPrice += $service['price'];
                    $totalDuration += $service['duration'];
                }
            }

            $discountAmount = ($totalPrice * $combo['discount']) / 100;

            $combo['services_data'] = $services;
            $combo['original_price'] = $totalPrice;
            $combo['discount_amount'] = $discountAmount;
            $combo['total_price'] = $totalPrice - $discountAmount;
            $combo['total_duration'] = $totalDuration;
            $combo['savings'] = $discountAmount;
        }

        return $combinations;
    }

    /**
     * Service-Statistiken
     */
    public function getStats()
    {
        $stats = [];

        // Gesamtanzahl Services
        $sql = "SELECT COUNT(*) as count FROM services WHERE active = 1";
        $result = $this->db->fetchOne($sql);
        $stats['total_active'] = $result['count'] ?? 0;

        $sql = "SELECT COUNT(*) as count FROM services WHERE active = 0";
        $result = $this->db->fetchOne($sql);
        $stats['total_inactive'] = $result['count'] ?? 0;

        // Durchschnittspreis
        $sql = "SELECT AVG(price) as avg_price FROM services WHERE active = 1";
        $result = $this->db->fetchOne($sql);
        $stats['average_price'] = (float)($result['avg_price'] ?? 0);

        // Preisspanne
        $sql = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM services WHERE active = 1";
        $result = $this->db->fetchOne($sql);
        $stats['price_range'] = [
            'min' => (float)($result['min_price'] ?? 0),
            'max' => (float)($result['max_price'] ?? 0)
        ];

        // Services nach Kategorie
        $sql = "SELECT category, COUNT(*) as count FROM services WHERE active = 1 GROUP BY category";
        $categoryStats = $this->db->fetchAll($sql);
        $stats['by_category'] = [];
        foreach ($categoryStats as $cat) {
            $stats['by_category'][$cat['category']] = $cat['count'];
        }

        // Beliebteste Services (nach Buchungen)
        $sql = "SELECT s.name, s.price, COUNT(bs.service_id) as booking_count
                FROM services s
                LEFT JOIN booking_services bs ON s.id = bs.service_id
                WHERE s.active = 1
                GROUP BY s.id
                ORDER BY booking_count DESC
                LIMIT 5";
        $stats['most_booked'] = $this->db->fetchAll($sql);

        // Umsatz pro Service
        $sql = "SELECT s.name, s.price, COUNT(bs.service_id) as bookings, 
                       (s.price * COUNT(bs.service_id)) as total_revenue
                FROM services s
                LEFT JOIN booking_services bs ON s.id = bs.service_id
                LEFT JOIN bookings b ON bs.booking_id = b.id
                WHERE s.active = 1 AND (b.status IS NULL OR b.status != 'cancelled')
                GROUP BY s.id
                ORDER BY total_revenue DESC
                LIMIT 5";
        $stats['highest_revenue'] = $this->db->fetchAll($sql);

        return $stats;
    }

    /**
     * Service für API-Response formatieren
     */
    private function formatService($service)
    {
        if (!$service) {
            return null;
        }

        return [
            'id' => (int)$service['id'],
            'name' => $service['name'],
            'description' => $service['description'],
            'detailed_description' => $service['detailed_description'],
            'price' => (float)$service['price'],
            'duration' => (int)$service['duration'],
            'category' => $service['category'],
            'icon' => $service['icon'],
            'popular' => (bool)$service['popular'],
            'active' => (bool)$service['active'],
            'created_at' => $service['created_at'],
            'booking_count' => $this->getBookingCount($service['id'])
        ];
    }

    /**
     * Service-Daten validieren
     */
    private function validateServiceData($data, $excludeId = null)
    {
        $rules = [
            'name' => ['required', 'min:3', 'max:100'],
            'description' => ['required', 'min:10', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration' => ['required', 'integer', 'min:15', 'max:480'], // 15 Min bis 8 Stunden
            'category' => ['required', 'min:3', 'max:50']
        ];

        $errors = $this->validator->validate($data, $rules);

        // Name-Eindeutigkeit prüfen
        if (isset($data['name'])) {
            if ($this->nameExists($data['name'], $excludeId)) {
                $errors['name'] = 'Service-Name wird bereits verwendet';
            }
        }

        // Kategorie-Validierung
        if (isset($data['category'])) {
            $validCategories = ['basic', 'premium', 'luxury', 'interior', 'wheels', 'protection', 'express', 'engine'];
            if (!in_array($data['category'], $validCategories)) {
                $errors['category'] = 'Ungültige Kategorie';
            }
        }

        // Icon-Validierung
        if (isset($data['icon'])) {
            if (!preg_match('/^fas? fa-[\w-]+$/', $data['icon'])) {
                $errors['icon'] = 'Icon muss FontAwesome-Format haben (z.B. fas fa-car)';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Prüfen ob Service-Name bereits existiert
     */
    private function nameExists($name, $excludeId = null)
    {
        $params = [trim($name)];
        $sql = "SELECT id FROM services WHERE name = ?";

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $result = $this->db->fetchOne($sql, $params);
        return $result !== null;
    }

    /**
     * Service-Preise aktualisieren (Bulk)
     */
    public function updatePrices($priceUpdates, $percentage = null)
    {
        if (!is_array($priceUpdates) && $percentage === null) {
            throw new Exception('Preisanpassungen oder Prozentsatz erforderlich');
        }

        try {
            $this->db->beginTransaction();

            if ($percentage !== null) {
                // Alle Preise um Prozentsatz anpassen
                $sql = "UPDATE services SET price = ROUND(price * (1 + ? / 100), 2) WHERE active = 1";
                $this->db->query($sql, [$percentage]);
            } else {
                // Individuelle Preisanpassungen
                foreach ($priceUpdates as $serviceId => $newPrice) {
                    if (is_numeric($newPrice) && $newPrice >= 0) {
                        $this->db->update('services', ['price' => $newPrice], 'id = ?', [$serviceId]);
                    }
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Price update failed: ' . $e->getMessage());
            throw new Exception('Preise konnten nicht aktualisiert werden');
        }
    }

    /**
     * Services exportieren
     */
    public function export($format = 'csv')
    {
        $services = $this->getAll(true); // Inkl. inaktive Services

        if ($format === 'csv') {
            return $this->exportToCsv($services);
        } elseif ($format === 'json') {
            return $this->exportToJson($services);
        }

        throw new Exception('Unbekanntes Export-Format');
    }

    /**
     * CSV-Export
     */
    private function exportToCsv($services)
    {
        $output = fopen('php://temp', 'w');

        // Header
        $headers = ['ID', 'Name', 'Beschreibung', 'Preis', 'Dauer (Min)', 'Kategorie', 'Beliebt', 'Aktiv', 'Buchungen'];
        fputcsv($output, $headers, ';');

        // Daten
        foreach ($services as $service) {
            $row = [
                $service['id'],
                $service['name'],
                $service['description'],
                $service['price'],
                $service['duration'],
                $service['category'],
                $service['popular'] ? 'Ja' : 'Nein',
                $service['active'] ? 'Ja' : 'Nein',
                $service['booking_count']
            ];
            fputcsv($output, $row, ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * JSON-Export
     */
    private function exportToJson($services)
    {
        return json_encode($services, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

// Helper-Funktion
function service()
{
    return new Service();
}
