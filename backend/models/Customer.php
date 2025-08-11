<?php

/**
 * Mobile Car Service - Customer Model
 * Verwaltet alle Kundenoperationen
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../utils/Validator.php';

class Customer
{
    private $db;
    private $validator;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->validator = new Validator();
    }

    /**
     * Neuen Kunden erstellen
     */
    public function create($data)
    {
        // Validierung
        $this->validateCustomerData($data);

        // Prüfen ob E-Mail bereits existiert
        if ($this->emailExists($data['email'])) {
            // Bestehenden Kunden aktualisieren
            $existing = $this->findByEmail($data['email']);
            return $this->update($existing['id'], $data);
        }

        // Daten vorbereiten
        $customerData = [
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => trim($data['phone']),
            'street' => trim($data['street']),
            'zip' => trim($data['zip']),
            'city' => trim($data['city']),
            'notes' => trim($data['notes'] ?? ''),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        try {
            $customerId = $this->db->insert('customers', $customerData);
            return $this->findById($customerId);
        } catch (Exception $e) {
            error_log('Customer creation failed: ' . $e->getMessage());
            throw new Exception('Kunde konnte nicht erstellt werden');
        }
    }

    /**
     * Kunden aktualisieren
     */
    public function update($id, $data)
    {
        // Validierung
        $this->validateCustomerData($data, $id);

        // Prüfen ob Kunde existiert
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception('Kunde nicht gefunden');
        }

        // Prüfen ob E-Mail bereits von anderem Kunden verwendet wird
        if (isset($data['email']) && $data['email'] !== $existing['email']) {
            $emailUser = $this->findByEmail($data['email']);
            if ($emailUser && $emailUser['id'] != $id) {
                throw new Exception('E-Mail-Adresse wird bereits verwendet');
            }
        }

        // Daten vorbereiten
        $updateData = [];
        $allowedFields = ['first_name', 'last_name', 'email', 'phone', 'street', 'zip', 'city', 'notes'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'email') {
                    $updateData[$field] = strtolower(trim($data[$field]));
                } else {
                    $updateData[$field] = trim($data[$field]);
                }
            }
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        try {
            $this->db->update('customers', $updateData, 'id = ?', [$id]);
            return $this->findById($id);
        } catch (Exception $e) {
            error_log('Customer update failed: ' . $e->getMessage());
            throw new Exception('Kunde konnte nicht aktualisiert werden');
        }
    }

    /**
     * Kunde anhand ID finden
     */
    public function findById($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            return null;
        }

        $sql = "SELECT * FROM customers WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Kunde anhand E-Mail finden
     */
    public function findByEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $sql = "SELECT * FROM customers WHERE email = ?";
        return $this->db->fetchOne($sql, [strtolower(trim($email))]);
    }

    /**
     * Alle Kunden abrufen
     */
    public function getAll($limit = 100, $offset = 0, $search = null)
    {
        $params = [];
        $sql = "SELECT * FROM customers";

        if ($search) {
            $searchTerm = '%' . trim($search) . '%';
            $sql .= " WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR city LIKE ?";
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Kundenanzahl abrufen
     */
    public function getCount($search = null)
    {
        $params = [];
        $sql = "SELECT COUNT(*) as count FROM customers";

        if ($search) {
            $searchTerm = '%' . trim($search) . '%';
            $sql .= " WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR city LIKE ?";
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }

        $result = $this->db->fetchOne($sql, $params);
        return $result['count'] ?? 0;
    }

    /**
     * Kunde löschen
     */
    public function delete($id)
    {
        // Prüfen ob Kunde existiert
        $customer = $this->findById($id);
        if (!$customer) {
            throw new Exception('Kunde nicht gefunden');
        }

        // Prüfen ob Kunde Buchungen hat
        $bookingCount = $this->getBookingCount($id);
        if ($bookingCount > 0) {
            throw new Exception('Kunde kann nicht gelöscht werden - es existieren noch Buchungen');
        }

        try {
            $this->db->delete('customers', 'id = ?', [$id]);
            return true;
        } catch (Exception $e) {
            error_log('Customer deletion failed: ' . $e->getMessage());
            throw new Exception('Kunde konnte nicht gelöscht werden');
        }
    }

    /**
     * Buchungsanzahl eines Kunden abrufen
     */
    public function getBookingCount($customerId)
    {
        $sql = "SELECT COUNT(*) as count FROM bookings WHERE customer_id = ?";
        $result = $this->db->fetchOne($sql, [$customerId]);
        return $result['count'] ?? 0;
    }

    /**
     * Buchungen eines Kunden abrufen
     */
    public function getBookings($customerId, $limit = 50)
    {
        $sql = "SELECT b.*, 
                       GROUP_CONCAT(s.name) as service_names,
                       COUNT(bs.service_id) as service_count
                FROM bookings b
                LEFT JOIN booking_services bs ON b.id = bs.booking_id
                LEFT JOIN services s ON bs.service_id = s.id
                WHERE b.customer_id = ?
                GROUP BY b.id
                ORDER BY b.booking_date DESC, b.booking_time DESC
                LIMIT ?";

        return $this->db->fetchAll($sql, [$customerId, $limit]);
    }

    /**
     * E-Mail-Existenz prüfen
     */
    public function emailExists($email, $excludeId = null)
    {
        $params = [strtolower(trim($email))];
        $sql = "SELECT id FROM customers WHERE email = ?";

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $result = $this->db->fetchOne($sql, $params);
        return $result !== null;
    }

    /**
     * Kundendaten validieren
     */
    private function validateCustomerData($data, $excludeId = null)
    {
        $rules = [
            'first_name' => ['required', 'min:2', 'max:50'],
            'last_name' => ['required', 'min:2', 'max:50'],
            'email' => ['required', 'email', 'max:100'],
            'phone' => ['required', 'min:10', 'max:20'],
            'street' => ['required', 'min:5', 'max:100'],
            'zip' => ['required', 'regex:/^[0-9]{5}$/'],
            'city' => ['required', 'min:2', 'max:50'],
            'notes' => ['max:500']
        ];

        $errors = $this->validator->validate($data, $rules);

        // Zusätzliche Validierungen
        if (isset($data['phone'])) {
            if (!preg_match('/^[\+]?[0-9\s\-\(\)]{10,}$/', $data['phone'])) {
                $errors['phone'] = 'Ungültiges Telefonnummer-Format';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Kunde zu Array konvertieren (für API-Response)
     */
    public function toArray($customer)
    {
        if (!$customer) {
            return null;
        }

        return [
            'id' => (int)$customer['id'],
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
            'full_name' => $customer['first_name'] . ' ' . $customer['last_name'],
            'email' => $customer['email'],
            'phone' => $customer['phone'],
            'address' => [
                'street' => $customer['street'],
                'zip' => $customer['zip'],
                'city' => $customer['city'],
                'full_address' => $customer['street'] . ', ' . $customer['zip'] . ' ' . $customer['city']
            ],
            'notes' => $customer['notes'],
            'created_at' => $customer['created_at'],
            'updated_at' => $customer['updated_at']
        ];
    }

    /**
     * Kunden-Statistiken
     */
    public function getStats()
    {
        $stats = [];

        // Gesamtanzahl Kunden
        $stats['total_customers'] = $this->getCount();

        // Neue Kunden heute
        $sql = "SELECT COUNT(*) as count FROM customers WHERE DATE(created_at) = DATE('now')";
        $result = $this->db->fetchOne($sql);
        $stats['new_today'] = $result['count'] ?? 0;

        // Neue Kunden diese Woche
        $sql = "SELECT COUNT(*) as count FROM customers WHERE created_at >= DATE('now', '-7 days')";
        $result = $this->db->fetchOne($sql);
        $stats['new_this_week'] = $result['count'] ?? 0;

        // Kunden mit den meisten Buchungen
        $sql = "SELECT c.first_name, c.last_name, c.email, COUNT(b.id) as booking_count
                FROM customers c
                LEFT JOIN bookings b ON c.id = b.customer_id
                GROUP BY c.id
                HAVING booking_count > 0
                ORDER BY booking_count DESC
                LIMIT 5";
        $stats['top_customers'] = $this->db->fetchAll($sql);

        // Städte-Verteilung
        $sql = "SELECT city, COUNT(*) as count
                FROM customers
                GROUP BY city
                ORDER BY count DESC
                LIMIT 10";
        $stats['cities'] = $this->db->fetchAll($sql);

        return $stats;
    }

    /**
     * Duplikate finden (ähnliche Namen/Adressen)
     */
    public function findDuplicates()
    {
        // Nach ähnlichen Namen suchen
        $sql = "SELECT c1.id as id1, c1.first_name as name1, c1.last_name as lastname1, c1.email as email1,
                       c2.id as id2, c2.first_name as name2, c2.last_name as lastname2, c2.email as email2
                FROM customers c1
                JOIN customers c2 ON c1.id < c2.id
                WHERE (c1.first_name = c2.first_name AND c1.last_name = c2.last_name)
                   OR (c1.street = c2.street AND c1.zip = c2.zip)";

        return $this->db->fetchAll($sql);
    }

    /**
     * Kundendaten exportieren
     */
    public function export($format = 'csv')
    {
        $customers = $this->getAll(1000); // Max 1000 Kunden

        if ($format === 'csv') {
            return $this->exportToCsv($customers);
        } elseif ($format === 'json') {
            return $this->exportToJson($customers);
        }

        throw new Exception('Unbekanntes Export-Format');
    }

    /**
     * CSV-Export
     */
    private function exportToCsv($customers)
    {
        $output = fopen('php://temp', 'w');

        // Header
        $headers = ['ID', 'Vorname', 'Nachname', 'E-Mail', 'Telefon', 'Straße', 'PLZ', 'Stadt', 'Erstellt'];
        fputcsv($output, $headers, ';');

        // Daten
        foreach ($customers as $customer) {
            $row = [
                $customer['id'],
                $customer['first_name'],
                $customer['last_name'],
                $customer['email'],
                $customer['phone'],
                $customer['street'],
                $customer['zip'],
                $customer['city'],
                $customer['created_at']
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
    private function exportToJson($customers)
    {
        $exportData = [];

        foreach ($customers as $customer) {
            $exportData[] = $this->toArray($customer);
        }

        return json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

/**
 * ValidationException für Validierungsfehler
 */
class ValidationException extends Exception
{
    private $errors;

    public function __construct($errors, $message = 'Validierungsfehler')
    {
        $this->errors = $errors;
        parent::__construct($message);
    }

    public function getErrors()
    {
        return $this->errors;
    }
}

// Helper-Funktion für einfachen Zugriff
function customer()
{
    return new Customer();
}
