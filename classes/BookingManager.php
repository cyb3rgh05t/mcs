<?php
// classes/BookingManager.php - Zentrale Buchungslogik

class BookingManager
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database->getConnection();
    }

    /**
     * Holt verfügbare Termine (nur Datum)
     */
    public function getAvailableDates($limit = 30)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT date 
                FROM appointments 
                WHERE status = 'available' 
                AND date >= date('now') 
                ORDER BY date
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error getting available dates: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Holt verfügbare Zeiten für ein bestimmtes Datum
     */
    public function getAvailableTimesForDate($date)
    {
        try {
            // Validiere Datum
            if (!$this->isValidDate($date)) {
                return [];
            }

            $stmt = $this->db->prepare("
                SELECT id, time 
                FROM appointments 
                WHERE date = ? AND status = 'available' 
                ORDER BY time
            ");
            $stmt->execute([$date]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting available times: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Holt alle aktiven Services
     */
    public function getAllServices()
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM services WHERE active = 1 ORDER BY price ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting services: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Berechnet Entfernung zur Kundenadresse
     */
    public function calculateDistance($customerAddress)
    {
        $address = SecurityManager::validateAddress($customerAddress);
        if (!$address) {
            return 15; // Standard-Entfernung bei ungültiger Adresse
        }

        // Hier können Sie Google Maps API, Mapbox oder andere Services integrieren
        // Für Demo verwenden wir eine intelligente Schätzung

        $address = strtolower($address);

        // Bekannte Entfernungen von Rheine
        $distance_map = [
            // Lokale Umgebung
            'rheine' => 5,
            'emsdetten' => 12,
            'steinfurt' => 18,
            'ibbenbüren' => 25,
            'greven' => 35,

            // Münsterland
            'münster' => 45,
            'osnabrück' => 55,
            'coesfeld' => 40,
            'warendorf' => 50,

            // NRW
            'düsseldorf' => 120,
            'köln' => 150,
            'dortmund' => 80,
            'essen' => 90,
            'bielefeld' => 70,

            // Niederlande
            'enschede' => 45,
            'amsterdam' => 200,
            'rotterdam' => 180,
        ];

        // Suche nach bekannten Städten
        foreach ($distance_map as $city => $distance) {
            if (strpos($address, $city) !== false) {
                // Kleine Variation hinzufügen
                return $distance + rand(-3, 3);
            }
        }

        // PLZ-basierte Schätzung
        if (preg_match('/\b(484|483|485)\d{2}\b/', $address)) {
            return rand(5, 25); // Lokale Umgebung
        }

        if (preg_match('/\b(48|49)\d{3}\b/', $address)) {
            return rand(20, 60); // Münsterland/Emsland
        }

        if (preg_match('/\b(4)\d{4}\b/', $address)) {
            return rand(40, 120); // NRW
        }

        // Standard für unbekannte Adressen
        return rand(25, 50);
    }

    /**
     * Berechnet Gesamtpreis der Buchung
     */
    public function calculateTotalPrice($serviceIds, $distance)
    {
        $servicePrice = 0;

        if (!empty($serviceIds) && is_array($serviceIds)) {
            try {
                // Validiere Service-IDs
                $serviceIds = array_filter($serviceIds, 'is_numeric');

                if (!empty($serviceIds)) {
                    $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
                    $stmt = $this->db->prepare("SELECT SUM(price) FROM services WHERE id IN ($placeholders) AND active = 1");
                    $stmt->execute($serviceIds);
                    $servicePrice = $stmt->fetchColumn() ?: 0;
                }
            } catch (PDOException $e) {
                error_log("Error calculating service price: " . $e->getMessage());
                $servicePrice = 0;
            }
        }

        // Anfahrtskosten
        $travelCostPerKm = defined('TRAVEL_COST_PER_KM') ? TRAVEL_COST_PER_KM : 0.50;
        $travelCost = $distance * $travelCostPerKm;

        return round($servicePrice + $travelCost, 2);
    }

    /**
     * Erstellt eine neue Buchung
     */
    public function createBooking($appointmentId, $customerData, $serviceIds, $distance, $totalPrice)
    {
        try {
            $this->db->beginTransaction();

            // Validiere Appointment
            if (!$this->isAppointmentAvailable($appointmentId)) {
                throw new Exception("Termin ist nicht mehr verfügbar");
            }

            // Validiere Services
            if (!$this->areServicesValid($serviceIds)) {
                throw new Exception("Ungültige Services ausgewählt");
            }

            // Validiere Kundendaten
            $this->validateCustomerData($customerData);

            // Appointment als gebucht markieren
            $stmt = $this->db->prepare("UPDATE appointments SET status = 'booked' WHERE id = ? AND status = 'available'");
            $stmt->execute([$appointmentId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Termin konnte nicht reserviert werden");
            }

            // Buchung erstellen
            $stmt = $this->db->prepare("
                INSERT INTO bookings 
                (appointment_id, customer_name, customer_email, customer_phone, customer_address, distance, total_price) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $appointmentId,
                $customerData['name'],
                $customerData['email'],
                $customerData['phone'],
                $customerData['address'],
                $distance,
                $totalPrice
            ]);

            $bookingId = $this->db->lastInsertId();

            // Services zur Buchung hinzufügen
            if (!empty($serviceIds)) {
                $this->addServicesToBooking($bookingId, $serviceIds);
            }

            $this->db->commit();

            // Log successful booking
            SecurityManager::logSecurityEvent('booking_created', [
                'booking_id' => $bookingId,
                'customer_email' => $customerData['email'],
                'total_price' => $totalPrice
            ]);

            return $bookingId;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Booking creation failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fügt Services zu einer Buchung hinzu
     */
    private function addServicesToBooking($bookingId, $serviceIds)
    {
        // Hole aktuelle Preise der Services
        $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
        $stmt = $this->db->prepare("SELECT id, price FROM services WHERE id IN ($placeholders) AND active = 1");
        $stmt->execute($serviceIds);
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Füge Services mit aktuellen Preisen hinzu
        $stmt = $this->db->prepare("INSERT INTO booking_services (booking_id, service_id, price_at_booking) VALUES (?, ?, ?)");
        foreach ($services as $service) {
            $stmt->execute([$bookingId, $service['id'], $service['price']]);
        }
    }

    /**
     * Holt Buchungsdetails
     */
    public function getBookingDetails($bookingId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT b.*, a.date, a.time 
                FROM bookings b 
                JOIN appointments a ON b.appointment_id = a.id 
                WHERE b.id = ?
            ");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($booking) {
                // Services laden
                $stmt = $this->db->prepare("
                    SELECT s.name, s.description, bs.price_at_booking as price, s.duration
                    FROM services s 
                    JOIN booking_services bs ON s.id = bs.service_id 
                    WHERE bs.booking_id = ?
                ");
                $stmt->execute([$bookingId]);
                $booking['services'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $booking;
        } catch (PDOException $e) {
            error_log("Error getting booking details: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validiert Kundendaten
     */
    private function validateCustomerData($customerData)
    {
        $required = ['name', 'email', 'phone', 'address'];

        foreach ($required as $field) {
            if (empty($customerData[$field])) {
                throw new Exception("Pflichtfeld fehlt: $field");
            }
        }

        if (!SecurityManager::validateEmail($customerData['email'])) {
            throw new Exception("Ungültige E-Mail-Adresse");
        }

        if (!SecurityManager::validatePhone($customerData['phone'])) {
            throw new Exception("Ungültige Telefonnummer");
        }

        if (!SecurityManager::validateAddress($customerData['address'])) {
            throw new Exception("Ungültige Adresse");
        }
    }

    /**
     * Prüft ob Termin verfügbar ist
     */
    private function isAppointmentAvailable($appointmentId)
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM appointments WHERE id = ? AND status = 'available'");
            $stmt->execute([$appointmentId]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Validiert Service-IDs
     */
    private function areServicesValid($serviceIds)
    {
        if (empty($serviceIds) || !is_array($serviceIds)) {
            return false;
        }

        try {
            $serviceIds = array_filter($serviceIds, 'is_numeric');
            if (empty($serviceIds)) {
                return false;
            }

            $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM services WHERE id IN ($placeholders) AND active = 1");
            $stmt->execute($serviceIds);

            return $stmt->fetchColumn() == count($serviceIds);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Validiert Datum
     */
    private function isValidDate($date)
    {
        if (!$date) return false;

        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            return false;
        }

        // Datum darf nicht in der Vergangenheit liegen
        $today = new DateTime();
        if ($dateObj < $today) {
            return false;
        }

        return true;
    }

    /**
     * Generiert neue Termine
     */
    public function generateTimeSlots($startDate = null, $endDate = null, $daysInFuture = 30)
    {
        try {
            $startDate = $startDate ?: date('Y-m-d', strtotime('+1 day'));
            $endDate = $endDate ?: date('Y-m-d', strtotime("+$daysInFuture days"));

            $working_hours_start = defined('WORKING_HOURS_START') ? WORKING_HOURS_START : 8;
            $working_hours_end = defined('WORKING_HOURS_END') ? WORKING_HOURS_END : 17;
            $working_days = defined('WORKING_DAYS') ? WORKING_DAYS : [1, 2, 3, 4, 5, 6];

            $current = new DateTime($startDate);
            $end = new DateTime($endDate);
            $slots_created = 0;

            while ($current <= $end) {
                $dayOfWeek = $current->format('N');

                if (in_array($dayOfWeek, $working_days)) {
                    for ($hour = $working_hours_start; $hour <= $working_hours_end; $hour++) {
                        $time = sprintf('%02d:00', $hour);
                        $date = $current->format('Y-m-d');

                        try {
                            $stmt = $this->db->prepare("INSERT OR IGNORE INTO appointments (date, time) VALUES (?, ?)");
                            $stmt->execute([$date, $time]);
                            if ($stmt->rowCount() > 0) {
                                $slots_created++;
                            }
                        } catch (PDOException $e) {
                            // Ignoriere Duplikate
                        }
                    }
                }
                $current->modify('+1 day');
            }

            return $slots_created;
        } catch (Exception $e) {
            error_log("Error generating time slots: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Aktualisiert Buchungsstatus
     */
    public function updateBookingStatus($bookingId, $status)
    {
        $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];

        if (!in_array($status, $validStatuses)) {
            throw new Exception("Ungültiger Status");
        }

        try {
            $stmt = $this->db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$status, $bookingId]);

            // Bei Stornierung: Termin wieder freigeben
            if ($status === 'cancelled') {
                $this->releaseAppointment($bookingId);
            }

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error updating booking status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gibt Termin bei Stornierung frei
     */
    private function releaseAppointment($bookingId)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE appointments 
                SET status = 'available' 
                WHERE id = (SELECT appointment_id FROM bookings WHERE id = ?)
            ");
            $stmt->execute([$bookingId]);
        } catch (PDOException $e) {
            error_log("Error releasing appointment: " . $e->getMessage());
        }
    }

    /**
     * Holt Buchungen für Admin
     */
    public function getBookingsForAdmin($limit = 100, $status = null)
    {
        try {
            $sql = "
                SELECT b.*, a.date, a.time, 
                       GROUP_CONCAT(s.name, ', ') as services,
                       COUNT(bs.service_id) as service_count
                FROM bookings b 
                JOIN appointments a ON b.appointment_id = a.id 
                LEFT JOIN booking_services bs ON b.id = bs.booking_id
                LEFT JOIN services s ON bs.service_id = s.id
            ";

            $params = [];
            if ($status) {
                $sql .= " WHERE b.status = ?";
                $params[] = $status;
            }

            $sql .= " GROUP BY b.id ORDER BY a.date DESC, a.time DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting bookings for admin: " . $e->getMessage());
            return [];
        }
    }
}
