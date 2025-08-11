<?php

/**
 * Mobile Car Service - Booking Model
 * Verwaltet alle Buchungsoperationen
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/Customer.php';
require_once __DIR__ . '/Service.php';

class Booking
{
    private $db;
    private $validator;
    private $customer;
    private $service;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->validator = new Validator();
        $this->customer = new Customer();
        $this->service = new Service();
    }

    /**
     * Neue Buchung erstellen
     */
    public function create($data)
    {
        // Validierung
        $this->validateBookingData($data);

        try {
            $this->db->beginTransaction();

            // Kunde erstellen/aktualisieren
            $customerData = $data['customer'];
            $customer = $this->customer->create($customerData);

            // Verfügbarkeit prüfen
            if ($this->isSlotBooked($data['date'], $data['time'])) {
                throw new Exception('Dieser Termin ist bereits gebucht');
            }

            // Services validieren
            $services = $this->validateServices($data['services']);

            // Gesamtpreis berechnen
            $serviceTotal = array_sum(array_column($services, 'price'));
            $totalPrice = $serviceTotal + ($data['travel_cost'] ?? 0);

            // Buchungsnummer generieren
            $bookingNumber = $this->generateBookingNumber();

            // Buchung erstellen
            $bookingData = [
                'booking_number' => $bookingNumber,
                'customer_id' => $customer['id'],
                'booking_date' => $data['date'],
                'booking_time' => $data['time'],
                'distance' => $data['distance'] ?? 0,
                'travel_cost' => $data['travel_cost'] ?? 0,
                'total_price' => $totalPrice,
                'status' => 'confirmed',
                'notes' => trim($data['notes'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $bookingId = $this->db->insert('bookings', $bookingData);

            // Services zur Buchung hinzufügen
            foreach ($services as $service) {
                $this->db->insert('booking_services', [
                    'booking_id' => $bookingId,
                    'service_id' => $service['id'],
                    'price' => $service['price']
                ]);
            }

            $this->db->commit();

            return $this->findById($bookingId);
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Booking creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Buchung aktualisieren
     */
    public function update($id, $data)
    {
        // Prüfen ob Buchung existiert
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception('Buchung nicht gefunden');
        }

        try {
            $this->db->beginTransaction();

            $updateData = [];
            $allowedFields = ['booking_date', 'booking_time', 'status', 'notes'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Bei Datum/Zeit-Änderung Verfügbarkeit prüfen
            if (isset($data['booking_date']) || isset($data['booking_time'])) {
                $newDate = $data['booking_date'] ?? $existing['booking_date'];
                $newTime = $data['booking_time'] ?? $existing['booking_time'];

                if ($this->isSlotBooked($newDate, $newTime, $id)) {
                    throw new Exception('Neuer Termin ist bereits gebucht');
                }
            }

            $updateData['updated_at'] = date('Y-m-d H:i:s');

            $this->db->update('bookings', $updateData, 'id = ?', [$id]);

            $this->db->commit();

            return $this->findById($id);
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Booking update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Buchung anhand ID finden
     */
    public function findById($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            return null;
        }

        $sql = "SELECT b.*, 
                       c.first_name, c.last_name, c.email, c.phone, 
                       c.street, c.zip, c.city, c.notes as customer_notes
                FROM bookings b
                JOIN customers c ON b.customer_id = c.id
                WHERE b.id = ?";

        $booking = $this->db->fetchOne($sql, [$id]);

        if ($booking) {
            // Services laden
            $booking['services'] = $this->getBookingServices($id);
            return $this->formatBooking($booking);
        }

        return null;
    }

    /**
     * Buchung anhand Buchungsnummer finden
     */
    public function findByBookingNumber($bookingNumber)
    {
        $sql = "SELECT b.*, 
                       c.first_name, c.last_name, c.email, c.phone, 
                       c.street, c.zip, c.city, c.notes as customer_notes
                FROM bookings b
                JOIN customers c ON b.customer_id = c.id
                WHERE b.booking_number = ?";

        $booking = $this->db->fetchOne($sql, [$bookingNumber]);

        if ($booking) {
            $booking['services'] = $this->getBookingServices($booking['id']);
            return $this->formatBooking($booking);
        }

        return null;
    }

    /**
     * Alle Buchungen abrufen
     */
    public function getAll($limit = 100, $offset = 0, $filters = [])
    {
        $params = [];
        $sql = "SELECT b.*, 
                       c.first_name, c.last_name, c.email, c.phone,
                       COUNT(bs.service_id) as service_count,
                       GROUP_CONCAT(s.name) as service_names
                FROM bookings b
                JOIN customers c ON b.customer_id = c.id
                LEFT JOIN booking_services bs ON b.id = bs.booking_id
                LEFT JOIN services s ON bs.service_id = s.id";

        $where = [];

        // Filter anwenden
        if (!empty($filters['status'])) {
            $where[] = "b.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "b.booking_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "b.booking_date <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['customer_id'])) {
            $where[] = "b.customer_id = ?";
            $params[] = $filters['customer_id'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim($filters['search']) . '%';
            $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR b.booking_number LIKE ?)";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " GROUP BY b.id ORDER BY b.booking_date DESC, b.booking_time DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $bookings = $this->db->fetchAll($sql, $params);

        // Services für jede Buchung laden
        foreach ($bookings as &$booking) {
            $booking['services'] = $this->getBookingServices($booking['id']);
            $booking = $this->formatBooking($booking);
        }

        return $bookings;
    }

    /**
     * Buchungen für einen bestimmten Tag abrufen
     */
    public function getByDate($date)
    {
        $sql = "SELECT b.*, 
                       c.first_name, c.last_name, c.email, c.phone
                FROM bookings b
                JOIN customers c ON b.customer_id = c.id
                WHERE b.booking_date = ? AND b.status != 'cancelled'
                ORDER BY b.booking_time ASC";

        $bookings = $this->db->fetchAll($sql, [$date]);

        foreach ($bookings as &$booking) {
            $booking['services'] = $this->getBookingServices($booking['id']);
            $booking = $this->formatBooking($booking);
        }

        return $bookings;
    }

    /**
     * Verfügbare Zeitslots für ein Datum abrufen
     */
    public function getAvailableSlots($date)
    {
        // Geschäftszeiten
        $startHour = BUSINESS_HOURS_START;
        $endHour = BUSINESS_HOURS_END;

        // Alle möglichen Slots generieren
        $allSlots = [];
        for ($hour = $startHour; $hour < $endHour; $hour++) {
            $allSlots[] = sprintf('%02d:00', $hour);
        }

        // Gebuchte Slots abrufen
        $sql = "SELECT booking_time FROM bookings 
                WHERE booking_date = ? AND status != 'cancelled'";
        $bookedSlots = $this->db->fetchAll($sql, [$date]);
        $bookedTimes = array_column($bookedSlots, 'booking_time');

        // Verfügbare Slots berechnen
        $availableSlots = [];
        foreach ($allSlots as $slot) {
            $availableSlots[] = [
                'time' => $slot,
                'available' => !in_array($slot, $bookedTimes)
            ];
        }

        return $availableSlots;
    }

    /**
     * Prüfen ob ein Slot bereits gebucht ist
     */
    public function isSlotBooked($date, $time, $excludeBookingId = null)
    {
        $params = [$date, $time];
        $sql = "SELECT id FROM bookings 
                WHERE booking_date = ? AND booking_time = ? AND status != 'cancelled'";

        if ($excludeBookingId) {
            $sql .= " AND id != ?";
            $params[] = $excludeBookingId;
        }

        $result = $this->db->fetchOne($sql, $params);
        return $result !== null;
    }

    /**
     * Buchung stornieren
     */
    public function cancel($id, $reason = null)
    {
        $booking = $this->findById($id);
        if (!$booking) {
            throw new Exception('Buchung nicht gefunden');
        }

        if ($booking['status'] === 'cancelled') {
            throw new Exception('Buchung ist bereits storniert');
        }

        // Prüfen ob Buchung in der Vergangenheit liegt
        $bookingDateTime = $booking['booking_date'] . ' ' . $booking['booking_time'];
        if (strtotime($bookingDateTime) < time()) {
            throw new Exception('Vergangene Buchungen können nicht storniert werden');
        }

        try {
            $updateData = [
                'status' => 'cancelled',
                'notes' => ($booking['notes'] ? $booking['notes'] . "\n\n" : '') .
                    "Storniert am " . date('d.m.Y H:i') .
                    ($reason ? " - Grund: " . $reason : ''),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->db->update('bookings', $updateData, 'id = ?', [$id]);

            return $this->findById($id);
        } catch (Exception $e) {
            error_log('Booking cancellation failed: ' . $e->getMessage());
            throw new Exception('Buchung konnte nicht storniert werden');
        }
    }

    /**
     * Buchung löschen (nur für Admins)
     */
    public function delete($id)
    {
        $booking = $this->findById($id);
        if (!$booking) {
            throw new Exception('Buchung nicht gefunden');
        }

        try {
            $this->db->beginTransaction();

            // Services löschen
            $this->db->delete('booking_services', 'booking_id = ?', [$id]);

            // Buchung löschen
            $this->db->delete('bookings', 'id = ?', [$id]);

            $this->db->commit();

            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Booking deletion failed: ' . $e->getMessage());
            throw new Exception('Buchung konnte nicht gelöscht werden');
        }
    }

    /**
     * Services einer Buchung abrufen
     */
    private function getBookingServices($bookingId)
    {
        $sql = "SELECT s.*, bs.price as booking_price
                FROM services s
                JOIN booking_services bs ON s.id = bs.service_id
                WHERE bs.booking_id = ?
                ORDER BY s.name";

        return $this->db->fetchAll($sql, [$bookingId]);
    }

    /**
     * Services validieren
     */
    private function validateServices($serviceIds)
    {
        if (empty($serviceIds)) {
            throw new Exception('Mindestens ein Service muss ausgewählt werden');
        }

        $services = [];
        foreach ($serviceIds as $serviceId) {
            $service = $this->service->findById($serviceId);
            if (!$service) {
                throw new Exception("Service mit ID $serviceId nicht gefunden");
            }
            if (!$service['active']) {
                throw new Exception("Service '{$service['name']}' ist nicht mehr verfügbar");
            }
            $services[] = $service;
        }

        return $services;
    }

    /**
     * Buchungsnummer generieren
     */
    private function generateBookingNumber()
    {
        $prefix = 'MCS';
        $timestamp = date('ymd');
        $random = strtoupper(substr(uniqid(), -4));

        $bookingNumber = $prefix . $timestamp . $random;

        // Prüfen ob Nummer bereits existiert
        if ($this->findByBookingNumber($bookingNumber)) {
            return $this->generateBookingNumber(); // Rekursiv neue Nummer generieren
        }

        return $bookingNumber;
    }

    /**
     * Buchungsdaten validieren
     */
    private function validateBookingData($data)
    {
        $rules = [
            'date' => ['required', 'date'],
            'time' => ['required', 'time'],
            'services' => ['required', 'array'],
            'customer' => ['required', 'array']
        ];

        $errors = $this->validator->validate($data, $rules);

        // Datum-Validierung
        if (isset($data['date'])) {
            $bookingDate = strtotime($data['date']);
            $today = strtotime('today');
            $maxDate = strtotime('+' . BUSINESS_DAYS_ADVANCE . ' days');

            if ($bookingDate < $today) {
                $errors['date'] = 'Buchungsdatum darf nicht in der Vergangenheit liegen';
            } elseif ($bookingDate > $maxDate) {
                $errors['date'] = 'Buchungsdatum liegt zu weit in der Zukunft';
            }

            // Wochenende prüfen (optional)
            $dayOfWeek = date('N', $bookingDate);
            if ($dayOfWeek >= 6) { // Samstag (6) oder Sonntag (7)
                $errors['date'] = 'Buchungen sind nur von Montag bis Freitag möglich';
            }
        }

        // Zeit-Validierung
        if (isset($data['time'])) {
            $hour = (int)substr($data['time'], 0, 2);
            if ($hour < BUSINESS_HOURS_START || $hour >= BUSINESS_HOURS_END) {
                $errors['time'] = 'Uhrzeit liegt außerhalb der Geschäftszeiten';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Buchung formatieren für API-Response
     */
    private function formatBooking($booking)
    {
        if (!$booking) {
            return null;
        }

        return [
            'id' => (int)$booking['id'],
            'booking_number' => $booking['booking_number'],
            'status' => $booking['status'],
            'date' => $booking['booking_date'],
            'time' => $booking['booking_time'],
            'datetime' => $booking['booking_date'] . ' ' . $booking['booking_time'],
            'customer' => [
                'id' => (int)$booking['customer_id'],
                'first_name' => $booking['first_name'],
                'last_name' => $booking['last_name'],
                'full_name' => $booking['first_name'] . ' ' . $booking['last_name'],
                'email' => $booking['email'],
                'phone' => $booking['phone'],
                'address' => [
                    'street' => $booking['street'],
                    'zip' => $booking['zip'],
                    'city' => $booking['city'],
                    'full_address' => $booking['street'] . ', ' . $booking['zip'] . ' ' . $booking['city']
                ]
            ],
            'services' => array_map(function ($service) {
                return [
                    'id' => (int)$service['id'],
                    'name' => $service['name'],
                    'price' => (float)$service['booking_price'],
                    'duration' => (int)$service['duration']
                ];
            }, $booking['services'] ?? []),
            'distance' => (float)($booking['distance'] ?? 0),
            'travel_cost' => (float)($booking['travel_cost'] ?? 0),
            'total_price' => (float)$booking['total_price'],
            'notes' => $booking['notes'],
            'created_at' => $booking['created_at'],
            'updated_at' => $booking['updated_at']
        ];
    }

    /**
     * Buchungs-Statistiken
     */
    public function getStats($dateFrom = null, $dateTo = null)
    {
        $params = [];
        $whereClause = "WHERE status != 'cancelled'";

        if ($dateFrom) {
            $whereClause .= " AND booking_date >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $whereClause .= " AND booking_date <= ?";
            $params[] = $dateTo;
        }

        $stats = [];

        // Gesamtanzahl Buchungen
        $sql = "SELECT COUNT(*) as count FROM bookings $whereClause";
        $result = $this->db->fetchOne($sql, $params);
        $stats['total_bookings'] = $result['count'] ?? 0;

        // Gesamtumsatz
        $sql = "SELECT SUM(total_price) as revenue FROM bookings $whereClause";
        $result = $this->db->fetchOne($sql, $params);
        $stats['total_revenue'] = (float)($result['revenue'] ?? 0);

        // Durchschnittlicher Buchungswert
        $stats['average_booking_value'] = $stats['total_bookings'] > 0 ?
            $stats['total_revenue'] / $stats['total_bookings'] : 0;

        // Buchungen nach Status
        $sql = "SELECT status, COUNT(*) as count FROM bookings 
                WHERE 1=1 " . str_replace('WHERE status !=', 'AND status !=', $whereClause) . "
                GROUP BY status";
        $statusStats = $this->db->fetchAll($sql, $params);
        $stats['by_status'] = [];
        foreach ($statusStats as $status) {
            $stats['by_status'][$status['status']] = $status['count'];
        }

        // Beliebteste Services
        $sql = "SELECT s.name, COUNT(*) as count
                FROM booking_services bs
                JOIN services s ON bs.service_id = s.id
                JOIN bookings b ON bs.booking_id = b.id
                $whereClause
                GROUP BY s.id
                ORDER BY count DESC
                LIMIT 5";
        $stats['popular_services'] = $this->db->fetchAll($sql, $params);

        // Buchungen pro Tag (letzte 30 Tage)
        $sql = "SELECT booking_date, COUNT(*) as count
                FROM bookings
                WHERE booking_date >= DATE('now', '-30 days') AND status != 'cancelled'
                GROUP BY booking_date
                ORDER BY booking_date";
        $stats['bookings_per_day'] = $this->db->fetchAll($sql);

        return $stats;
    }

    /**
     * Heute anstehende Buchungen
     */
    public function getTodaysBookings()
    {
        $today = date('Y-m-d');
        return $this->getByDate($today);
    }

    /**
     * Kommende Buchungen (nächste 7 Tage)
     */
    public function getUpcomingBookings($days = 7)
    {
        $dateFrom = date('Y-m-d');
        $dateTo = date('Y-m-d', strtotime("+$days days"));

        return $this->getAll(100, 0, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => 'confirmed'
        ]);
    }
}

// Helper-Funktion
function booking()
{
    return new Booking();
}
