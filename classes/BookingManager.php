<?php
// classes/BookingManager.php - Erweiterte Klasse mit neuer Anfahrtskostenlogik
class BookingManager
{
    private $db;

    public function __construct($database)
    {
        // Wenn Database-Objekt übergeben wird, hole die Connection
        if (is_object($database) && method_exists($database, 'getConnection')) {
            $this->db = $database->getConnection();
        } else {
            // Direkte PDO-Connection
            $this->db = $database;
        }
    }

    /**
     * Hole verfügbare Termine für die nächsten X Tage
     */
    public function getAvailableDates($days = 60)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT date 
                FROM appointments 
                WHERE status = 'available' 
                AND date >= date('now') 
                AND date <= date('now', '+$days days')
                ORDER BY date ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error getting available dates: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Hole verfügbare Zeiten für ein bestimmtes Datum
     */
    public function getAvailableTimesForDate($date, $requiredDuration = 60)
    {
        try {
            $slotsNeeded = ceil($requiredDuration / 60);

            $stmt = $this->db->prepare("
                SELECT id, time 
                FROM appointments 
                WHERE date = ? 
                AND status = 'available' 
                ORDER BY time ASC
            ");
            $stmt->execute([$date]);
            $allSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $availableTimes = [];

            foreach ($allSlots as $index => $slot) {
                $startHour = intval(substr($slot['time'], 0, 2));
                $canBook = true;

                for ($i = 1; $i < $slotsNeeded; $i++) {
                    $nextHour = $startHour + $i;
                    $nextTime = sprintf('%02d:00', $nextHour);

                    $found = false;
                    foreach ($allSlots as $checkSlot) {
                        if ($checkSlot['time'] === $nextTime) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        $canBook = false;
                        break;
                    }
                }

                if ($canBook) {
                    $availableTimes[] = $slot;
                }
            }

            return $availableTimes;
        } catch (PDOException $e) {
            error_log("Error getting available times: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Prüfe ob ein Termin verfügbar ist
     */
    public function isAppointmentAvailable($appointmentId)
    {
        try {
            $stmt = $this->db->prepare("SELECT status FROM appointments WHERE id = ?");
            $stmt->execute([$appointmentId]);
            $status = $stmt->fetchColumn();
            return $status === 'available';
        } catch (PDOException $e) {
            error_log("Error checking appointment availability: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Hole alle aktiven Services
     */
    public function getAllServices()
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM services WHERE active = 1 ORDER BY name ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting services: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Berechne Gesamtdauer basierend auf Service-IDs
     */
    public function calculateTotalDuration($serviceIds)
    {
        if (empty($serviceIds)) {
            return 60;
        }

        try {
            $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
            $stmt = $this->db->prepare("SELECT SUM(duration) FROM services WHERE id IN ($placeholders) AND active = 1");
            $stmt->execute($serviceIds);
            $duration = $stmt->fetchColumn();
            return $duration ?: 60;
        } catch (PDOException $e) {
            error_log("Error calculating duration: " . $e->getMessage());
            return 60;
        }
    }

    /**
     * NEUE METHODE: Berechne Anfahrtskosten mit neuer Logik
     */
    public function calculateTravelCost($distance, $servicesTotal)
    {
        $travelCost = 0;

        // Leistungen unter Mindestbetrag: Anfahrt gratis bis 10km
        if ($servicesTotal < TRAVEL_MIN_SERVICE_AMOUNT) {
            if ($distance <= TRAVEL_MAX_DISTANCE_SMALL) {
                $travelCost = 0; // Gratis
            } else {
                // Über 10km nicht buchbar bei kleiner Summe
                throw new Exception('Entfernung zu groß für diese Leistungssumme');
            }
        }
        // Leistungen über Mindestbetrag: Erste 10km gratis, dann Berechnung
        else {
            if ($distance <= TRAVEL_MAX_DISTANCE_LARGE) {
                if ($distance > TRAVEL_FREE_KM) {
                    $chargeableDistance = $distance - TRAVEL_FREE_KM;
                    $travelCost = $chargeableDistance * TRAVEL_COST_PER_KM;
                }
            } else {
                // Über 30km nicht buchbar
                throw new Exception('Entfernung zu groß für unser Servicegebiet');
            }
        }

        return round($travelCost, 2);
    }

    /**
     * ANGEPASSTE METHODE: Berechne Gesamtpreis mit neuer Logik
     */
    public function calculateTotalPrice($distance, $serviceIds = null)
    {
        $servicePrice = 0;

        if ($serviceIds && is_array($serviceIds)) {
            try {
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

        // Verwende neue Anfahrtskostenberechnung
        try {
            $travelCost = $this->calculateTravelCost($distance, $servicePrice);
        } catch (Exception $e) {
            // Bei Entfernungsproblem: Fehler weitergeben
            throw $e;
        }

        return round($servicePrice + $travelCost, 2);
    }

    /**
     * NEUE METHODE: Validiere ob Buchung möglich ist basierend auf Entfernung und Services
     */
    public function validateBookingDistance($distance, $servicesTotal)
    {
        // Absolute Obergrenze prüfen
        if ($distance > TRAVEL_ABSOLUTE_MAX_DISTANCE) {
            return [
                'valid' => false,
                'message' => 'Ihre Adresse liegt außerhalb unseres Servicegebiets (max. ' . TRAVEL_ABSOLUTE_MAX_DISTANCE . ' km).'
            ];
        }

        // Prüfung basierend auf Leistungssumme
        if ($servicesTotal < TRAVEL_MIN_SERVICE_AMOUNT) {
            if ($distance > TRAVEL_MAX_DISTANCE_SMALL) {
                return [
                    'valid' => false,
                    'message' => 'Bei Leistungen unter ' . number_format(TRAVEL_MIN_SERVICE_AMOUNT, 2) .
                        '€ beträgt die maximale Entfernung ' . TRAVEL_MAX_DISTANCE_SMALL . ' km.'
                ];
            }
        } else {
            if ($distance > TRAVEL_MAX_DISTANCE_LARGE) {
                return [
                    'valid' => false,
                    'message' => 'Bei Leistungen ab ' . number_format(TRAVEL_MIN_SERVICE_AMOUNT, 2) .
                        '€ beträgt die maximale Entfernung ' . TRAVEL_MAX_DISTANCE_LARGE . ' km.'
                ];
            }
        }

        return ['valid' => true];
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

            // Validiere Entfernung mit Services
            $servicesTotal = 0;
            if (!empty($serviceIds)) {
                $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
                $stmt = $this->db->prepare("SELECT SUM(price) FROM services WHERE id IN ($placeholders) AND active = 1");
                $stmt->execute($serviceIds);
                $servicesTotal = $stmt->fetchColumn() ?: 0;
            }

            $validation = $this->validateBookingDistance($distance, $servicesTotal);
            if (!$validation['valid']) {
                throw new Exception($validation['message']);
            }

            // Berechne Gesamtdauer der Services
            $totalDuration = $this->calculateTotalDuration($serviceIds);
            $slotsNeeded = ceil($totalDuration / 60);

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
                 customer_address, distance, total_price, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $appointmentId,
                $customerData['name'],
                $customerData['email'],
                $customerData['phone'],
                $customerData['address'],
                $distance,
                $totalPrice,
                $customerData['notes'] ?? null
            ]);

            $bookingId = $this->db->lastInsertId();

            // Services hinzufügen
            $this->addServicesToBooking($bookingId, $serviceIds);

            $this->db->commit();

            SecurityManager::logSecurityEvent('booking_created', [
                'booking_id' => $bookingId,
                'customer_email' => $customerData['email'],
                'total_price' => $totalPrice,
                'distance' => $distance
            ]);

            return $bookingId;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating booking: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validiere Services
     */
    private function areServicesValid($serviceIds)
    {
        if (empty($serviceIds)) {
            return false;
        }

        try {
            $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM services WHERE id IN ($placeholders) AND active = 1");
            $stmt->execute($serviceIds);
            $count = $stmt->fetchColumn();
            return $count == count($serviceIds);
        } catch (PDOException $e) {
            error_log("Error validating services: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validiere Kundendaten
     */
    private function validateCustomerData($data)
    {
        if (empty($data['name']) || empty($data['email']) || empty($data['phone']) || empty($data['address'])) {
            throw new Exception("Alle Pflichtfelder müssen ausgefüllt sein");
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Ungültige E-Mail-Adresse");
        }

        if (strlen($data['phone']) < 10) {
            throw new Exception("Ungültige Telefonnummer");
        }
    }

    /**
     * Blockiere erforderliche Slots
     */
    private function blockRequiredSlots($date, $startTime, $slotsNeeded, $primaryAppointmentId)
    {
        try {
            $blockedSlots = [$primaryAppointmentId];

            $stmt = $this->db->prepare("UPDATE appointments SET status = 'booked' WHERE id = ?");
            $stmt->execute([$primaryAppointmentId]);

            if ($slotsNeeded > 1) {
                $startHour = intval(substr($startTime, 0, 2));

                for ($i = 1; $i < $slotsNeeded; $i++) {
                    $slotTime = sprintf('%02d:00', $startHour + $i);

                    $stmt = $this->db->prepare("
                        SELECT id FROM appointments 
                        WHERE date = ? AND time = ? AND status = 'available'
                    ");
                    $stmt->execute([$date, $slotTime]);
                    $slotId = $stmt->fetchColumn();

                    if ($slotId) {
                        $stmt = $this->db->prepare("UPDATE appointments SET status = 'booked' WHERE id = ?");
                        $stmt->execute([$slotId]);
                        $blockedSlots[] = $slotId;
                    }
                }
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
     * Hole Buchungen für Admin
     */
    public function getBookingsForAdmin($limit = 100)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT b.*, a.date, a.time,
                    GROUP_CONCAT(s.name, ', ') as services
                FROM bookings b 
                JOIN appointments a ON b.appointment_id = a.id
                LEFT JOIN booking_services bs ON b.id = bs.booking_id
                LEFT JOIN services s ON bs.service_id = s.id
                GROUP BY b.id
                ORDER BY a.date DESC, a.time DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting bookings for admin: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Aktualisiere Buchungsstatus
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

            // Bei Stornierung: Termine wieder freigeben
            if ($status === 'cancelled') {
                $this->releaseAppointmentsForBooking($bookingId);
            }

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error updating booking status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gib Termine bei Stornierung frei
     */
    private function releaseAppointmentsForBooking($bookingId)
    {
        try {
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
                            $stmt = $this->db->prepare("SELECT id FROM appointments WHERE date = ? AND time = ?");
                            $stmt->execute([$date, $time]);

                            if (!$stmt->fetch()) {
                                $stmt = $this->db->prepare("INSERT INTO appointments (date, time, status) VALUES (?, ?, 'available')");
                                $stmt->execute([$date, $time]);
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
}
