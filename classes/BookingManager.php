<?php
// classes/BookingManager.php - Erweiterte Version mit Dauer-Blockierung

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
     * NEU: Prüft ob genug aufeinanderfolgende Slots frei sind
     */
    public function getAvailableTimesForDate($date, $requiredDuration = 60)
    {
        try {
            if (!$this->isValidDate($date)) {
                return [];
            }

            // Hole alle Slots für den Tag
            $stmt = $this->db->prepare("
                SELECT id, time, status 
                FROM appointments 
                WHERE date = ?
                ORDER BY time
            ");
            $stmt->execute([$date]);
            $allSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $availableTimes = [];

            // Prüfe jeden Slot
            foreach ($allSlots as $index => $slot) {
                if ($slot['status'] !== 'available') {
                    continue;
                }

                // Prüfe ob genug nachfolgende Slots frei sind
                $slotsNeeded = ceil($requiredDuration / 60); // 60 Minuten pro Slot
                $canBook = true;

                for ($i = 0; $i < $slotsNeeded; $i++) {
                    if (
                        !isset($allSlots[$index + $i]) ||
                        $allSlots[$index + $i]['status'] !== 'available'
                    ) {
                        $canBook = false;
                        break;
                    }
                }

                if ($canBook) {
                    $availableTimes[] = [
                        'id' => $slot['id'],
                        'time' => $slot['time']
                    ];
                }
            }

            return $availableTimes;
        } catch (PDOException $e) {
            error_log("Error getting available times: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Berechnet die Gesamtdauer der gewählten Services
     */
    public function calculateTotalDuration($serviceIds)
    {
        if (empty($serviceIds) || !is_array($serviceIds)) {
            return 60; // Standard 60 Minuten
        }

        try {
            $serviceIds = array_filter($serviceIds, 'is_numeric');

            if (empty($serviceIds)) {
                return 60;
            }

            $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
            $stmt = $this->db->prepare("
                SELECT SUM(duration) as total_duration 
                FROM services 
                WHERE id IN ($placeholders) AND active = 1
            ");
            $stmt->execute($serviceIds);

            $totalDuration = $stmt->fetchColumn();
            return $totalDuration ?: 60;
        } catch (PDOException $e) {
            error_log("Error calculating total duration: " . $e->getMessage());
            return 60;
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
            return 15;
        }

        $address = strtolower($address);

        $distance_map = [
            'rheine' => 5,
            'emsdetten' => 12,
            'steinfurt' => 18,
            'ibbenbüren' => 25,
            'greven' => 35,
            'münster' => 45,
            'osnabrück' => 55,
        ];

        foreach ($distance_map as $city => $distance) {
            if (strpos($address, $city) !== false) {
                return $distance + rand(-3, 3);
            }
        }

        if (preg_match('/\b(484|483|485)\d{2}\b/', $address)) {
            return rand(5, 25);
        }

        if (preg_match('/\b(48|49)\d{3}\b/', $address)) {
            return rand(20, 60);
        }

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

        $travelCostPerKm = defined('TRAVEL_COST_PER_KM') ? TRAVEL_COST_PER_KM : 0.50;
        $travelCost = $distance * $travelCostPerKm;

        return round($servicePrice + $travelCost, 2);
    }

    /**
     * Erstellt eine neue Buchung - ERWEITERT mit Mehrfach-Slot-Blockierung
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

            // Berechne Gesamtdauer der Services
            $totalDuration = $this->calculateTotalDuration($serviceIds);
            $slotsNeeded = ceil($totalDuration / 60); // 60 Minuten pro Slot

            // Hole Informationen zum Start-Appointment
            $stmt = $this->db->prepare("SELECT date, time FROM appointments WHERE id = ?");
            $stmt->execute([$appointmentId]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$appointment) {
                throw new Exception("Termin nicht gefunden");
            }

            // Prüfe und blockiere alle benötigten Slots
            $blockedSlots = $this->blockRequiredSlots(
                $appointment['date'],
                $appointment['time'],
                $slotsNeeded,
                $appointmentId
            );

            if (count($blockedSlots) < $slotsNeeded) {
                throw new Exception("Nicht genügend aufeinanderfolgende Termine verfügbar");
            }

            // Buchung erstellen
            $stmt = $this->db->prepare("
                INSERT INTO bookings 
                (appointment_id, customer_name, customer_email, customer_phone, 
                 customer_address, distance, total_price) 
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

            SecurityManager::logSecurityEvent('booking_created', [
                'booking_id' => $bookingId,
                'customer_email' => $customerData['email'],
                'total_price' => $totalPrice,
                'duration_minutes' => $totalDuration,
                'slots_blocked' => count($blockedSlots)
            ]);

            return $bookingId;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Booking creation failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Blockiert alle benötigten Termin-Slots
     */
    /**
     * Blockiert alle benötigten Termin-Slots
     */
    private function blockRequiredSlots($date, $startTime, $slotsNeeded, $primaryAppointmentId)
    {
        $blockedSlots = [];

        try {
            // Konvertiere Start-Zeit zu Stunde
            $startHour = intval(substr($startTime, 0, 2));

            // Blockiere alle benötigten Slots
            for ($i = 0; $i < $slotsNeeded; $i++) {
                $slotTime = sprintf('%02d:00', $startHour + $i);

                // Update Slot auf 'booked' - OHNE updated_at
                $stmt = $this->db->prepare("
                UPDATE appointments 
                SET status = 'booked' 
                WHERE date = ? AND time = ? AND status = 'available'
            ");
                $stmt->execute([$date, $slotTime]);

                if ($stmt->rowCount() > 0) {
                    // Hole die ID des gebuchten Slots
                    $stmt = $this->db->prepare("
                    SELECT id FROM appointments 
                    WHERE date = ? AND time = ?
                ");
                    $stmt->execute([$date, $slotTime]);
                    $slotId = $stmt->fetchColumn();

                    if ($slotId) {
                        $blockedSlots[] = $slotId;
                    }
                }
            }

            // Log die blockierten Slots
            if (!empty($blockedSlots)) {
                error_log("Successfully blocked " . count($blockedSlots) . " slots for date $date starting at $startTime");
            }

            return $blockedSlots;
        } catch (PDOException $e) {
            error_log("Error blocking slots: " . $e->getMessage());
            throw new Exception("Fehler beim Blockieren der Termine");
        }
    }

    /**
     * Fügt Services zu einer Buchung hinzu
     */
    private function addServicesToBooking($bookingId, $serviceIds)
    {
        $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
        $stmt = $this->db->prepare("SELECT id, price FROM services WHERE id IN ($placeholders) AND active = 1");
        $stmt->execute($serviceIds);
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
     * Prüft ob Termin mit genügend Folge-Slots verfügbar ist
     */
    private function isAppointmentAvailable($appointmentId, $slotsNeeded = 1)
    {
        try {
            // Hole Appointment-Details
            $stmt = $this->db->prepare("
                SELECT date, time, status 
                FROM appointments 
                WHERE id = ?
            ");
            $stmt->execute([$appointmentId]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$appointment || $appointment['status'] !== 'available') {
                return false;
            }

            // Wenn nur ein Slot benötigt wird
            if ($slotsNeeded <= 1) {
                return true;
            }

            // Prüfe nachfolgende Slots
            $startHour = intval(substr($appointment['time'], 0, 2));

            for ($i = 1; $i < $slotsNeeded; $i++) {
                $nextTime = sprintf('%02d:00', $startHour + $i);

                $stmt = $this->db->prepare("
                    SELECT COUNT(*) 
                    FROM appointments 
                    WHERE date = ? AND time = ? AND status = 'available'
                ");
                $stmt->execute([$appointment['date'], $nextTime]);

                if ($stmt->fetchColumn() == 0) {
                    return false;
                }
            }

            return true;
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

            // Bei Stornierung: Alle blockierten Termine wieder freigeben
            if ($status === 'cancelled') {
                $this->releaseAllBlockedSlots($bookingId);
            }

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error updating booking status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gibt alle blockierten Termine bei Stornierung frei
     */
    private function releaseAllBlockedSlots($bookingId)
    {
        try {
            // Hole Booking-Details
            $stmt = $this->db->prepare("
                SELECT b.appointment_id, a.date, a.time 
                FROM bookings b 
                JOIN appointments a ON b.appointment_id = a.id 
                WHERE b.id = ?
            ");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                return;
            }

            // Berechne Dauer der gebuchten Services
            $stmt = $this->db->prepare("
                SELECT SUM(s.duration) as total_duration
                FROM booking_services bs
                JOIN services s ON bs.service_id = s.id
                WHERE bs.booking_id = ?
            ");
            $stmt->execute([$bookingId]);
            $totalDuration = $stmt->fetchColumn() ?: 60;
            $slotsToRelease = ceil($totalDuration / 60);

            // Gib alle Slots frei
            $startHour = intval(substr($booking['time'], 0, 2));

            for ($i = 0; $i < $slotsToRelease; $i++) {
                $slotTime = sprintf('%02d:00', $startHour + $i);

                $stmt = $this->db->prepare("
                    UPDATE appointments 
                    SET status = 'available' 
                    WHERE date = ? AND time = ? AND status = 'booked'
                ");
                $stmt->execute([$booking['date'], $slotTime]);
            }

            SecurityManager::logSecurityEvent('booking_cancelled_slots_released', [
                'booking_id' => $bookingId,
                'slots_released' => $slotsToRelease
            ]);
        } catch (PDOException $e) {
            error_log("Error releasing appointment slots: " . $e->getMessage());
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
                       COUNT(bs.service_id) as service_count,
                       SUM(s.duration) as total_duration
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
