<?php

class BookingManager
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database->getConnection();
    }

    public function getAvailableDates()
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT date 
            FROM appointments 
            WHERE status = 'available' 
            AND date >= date('now') 
            ORDER BY date
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getAvailableTimesForDate($date)
    {
        $stmt = $this->db->prepare("
            SELECT id, time 
            FROM appointments 
            WHERE date = ? AND status = 'available' 
            ORDER BY time
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllServices()
    {
        $stmt = $this->db->prepare("SELECT * FROM services WHERE active = 1 ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function calculateDistance($customerAddress)
    {
        // Hier würden Sie die Google Maps API integrieren
        // Für Demo-Zwecke geben wir eine zufällige Entfernung zurück
        return rand(5, 50); // km
    }

    public function calculateTotalPrice($serviceIds, $distance)
    {
        $servicePrice = 0;
        if (!empty($serviceIds)) {
            $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
            $stmt = $this->db->prepare("SELECT SUM(price) FROM services WHERE id IN ($placeholders)");
            $stmt->execute($serviceIds);
            $servicePrice = $stmt->fetchColumn();
        }

        // Anfahrtskosten: 0.50€ pro km
        $travelCost = $distance * 0.50;

        return $servicePrice + $travelCost;
    }

    public function createBooking($appointmentId, $customerData, $serviceIds, $distance, $totalPrice)
    {
        try {
            $this->db->beginTransaction();

            // Appointment als gebucht markieren
            $stmt = $this->db->prepare("UPDATE appointments SET status = 'booked' WHERE id = ?");
            $stmt->execute([$appointmentId]);

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
                $stmt = $this->db->prepare("INSERT INTO booking_services (booking_id, service_id) VALUES (?, ?)");
                foreach ($serviceIds as $serviceId) {
                    $stmt->execute([$bookingId, $serviceId]);
                }
            }

            $this->db->commit();
            return $bookingId;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function getBookingDetails($bookingId)
    {
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
                SELECT s.* 
                FROM services s 
                JOIN booking_services bs ON s.id = bs.service_id 
                WHERE bs.booking_id = ?
            ");
            $stmt->execute([$bookingId]);
            $booking['services'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $booking;
    }
}
