<?php

/**
 * Mobile Car Service - Booking Controller
 * Verwaltet alle API-Endpunkte für Buchungen
 */

require_once __DIR__ . '/../models/Booking.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/Service.php';
require_once __DIR__ . '/../utils/MapsService.php';
require_once __DIR__ . '/../utils/Security.php';
require_once __DIR__ . '/EmailController.php';

class BookingController
{
    private $booking;
    private $customer;
    private $service;
    private $mapsService;
    private $emailController;

    public function __construct()
    {
        $this->booking = new Booking();
        $this->customer = new Customer();
        $this->service = new Service();
        $this->mapsService = new MapsService();
        $this->emailController = new EmailController();
    }

    /**
     * Neue Buchung erstellen
     * POST /api/bookings
     */
    public function create()
    {
        try {
            // Rate Limiting
            security()->checkRateLimit(getClientIp() . '_booking', 10, 3600); // 10 Buchungen pro Stunde

            // Input validieren
            $input = getJsonInput();
            $this->validateBookingInput($input);

            // Security Checks
            Security::checkHoneypot('website', $input);
            Security::checkSubmissionTime(3, 3600);

            // Entfernung berechnen
            if (isset($input['customer'])) {
                $distanceResult = $this->mapsService->calculateDistance($input['customer']);
                $travelCostResult = $this->mapsService->calculateTravelCost($distanceResult['distance']);

                // Servicebereich prüfen
                $serviceArea = $this->mapsService->isInServiceArea($distanceResult['distance']);
                if (!$serviceArea['in_area']) {
                    throw new Exception($serviceArea['message']);
                }

                $input['distance'] = $distanceResult['distance'];
                $input['travel_cost'] = $travelCostResult['total_cost'];
                $input['distance_info'] = $distanceResult;
                $input['travel_cost_info'] = $travelCostResult;
            }

            // Buchung erstellen
            $booking = $this->booking->create($input);

            // E-Mail-Benachrichtigung senden
            try {
                $this->emailController->sendBookingConfirmation($booking);
            } catch (Exception $e) {
                error_log('Email sending failed: ' . $e->getMessage());
                // E-Mail-Fehler nicht an User weiterleiten
            }

            // Success Response
            successResponse($booking, 'Buchung erfolgreich erstellt');
        } catch (ValidationException $e) {
            errorResponse('Validierungsfehler', 400, $e->getErrors());
        } catch (SecurityException $e) {
            errorResponse($e->getMessage(), $e->getHttpCode());
        } catch (Exception $e) {
            error_log('Booking creation error: ' . $e->getMessage());
            errorResponse('Buchung konnte nicht erstellt werden: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Buchung abrufen
     * GET /api/bookings/{id}
     */
    public function get($id)
    {
        try {
            $booking = $this->booking->findById($id);

            if (!$booking) {
                errorResponse('Buchung nicht gefunden', 404);
            }

            successResponse($booking);
        } catch (Exception $e) {
            error_log('Booking retrieval error: ' . $e->getMessage());
            errorResponse('Fehler beim Abrufen der Buchung', 500);
        }
    }

    /**
     * Buchung anhand Buchungsnummer abrufen
     * GET /api/bookings/number/{bookingNumber}
     */
    public function getByNumber($bookingNumber)
    {
        try {
            $booking = $this->booking->findByBookingNumber($bookingNumber);

            if (!$booking) {
                errorResponse('Buchung nicht gefunden', 404);
            }

            successResponse($booking);
        } catch (Exception $e) {
            error_log('Booking retrieval error: ' . $e->getMessage());
            errorResponse('Fehler beim Abrufen der Buchung', 500);
        }
    }

    /**
     * Alle Buchungen abrufen (mit Filtern)
     * GET /api/bookings
     */
    public function getAll()
    {
        try {
            // Query-Parameter
            $limit = min((int)($_GET['limit'] ?? 50), 100); // Max 100
            $offset = max((int)($_GET['offset'] ?? 0), 0);

            // Filter
            $filters = [];
            if (!empty($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (!empty($_GET['date_from'])) {
                $filters['date_from'] = $_GET['date_from'];
            }
            if (!empty($_GET['date_to'])) {
                $filters['date_to'] = $_GET['date_to'];
            }
            if (!empty($_GET['customer_id'])) {
                $filters['customer_id'] = (int)$_GET['customer_id'];
            }
            if (!empty($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }

            $bookings = $this->booking->getAll($limit, $offset, $filters);

            successResponse([
                'bookings' => $bookings,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => count($bookings) // Vereinfacht für Demo
                ]
            ]);
        } catch (Exception $e) {
            error_log('Bookings retrieval error: ' . $e->getMessage());
            errorResponse('Fehler beim Abrufen der Buchungen', 500);
        }
    }

    /**
     * Verfügbare Zeitslots für ein Datum abrufen
     * GET /api/bookings/available-slots/{date}
     */
    public function getAvailableSlots($date)
    {
        try {
            // Datum validieren
            if (!Validator::isValidDate($date)) {
                errorResponse('Ungültiges Datum', 400);
            }

            // Datum darf nicht in der Vergangenheit liegen
            if (strtotime($date) < strtotime('today')) {
                errorResponse('Datum liegt in der Vergangenheit', 400);
            }

            // Max. Tage im Voraus prüfen
            $maxDate = strtotime('+' . BUSINESS_DAYS_ADVANCE . ' days');
            if (strtotime($date) > $maxDate) {
                errorResponse('Datum liegt zu weit in der Zukunft', 400);
            }

            $slots = $this->booking->getAvailableSlots($date);

            successResponse([
                'date' => $date,
                'slots' => $slots,
                'business_hours' => [
                    'start' => BUSINESS_HOURS_START,
                    'end' => BUSINESS_HOURS_END
                ]
            ]);
        } catch (Exception $e) {
            error_log('Available slots error: ' . $e->getMessage());
            errorResponse('Fehler beim Abrufen der verfügbaren Termine', 500);
        }
    }

    /**
     * Buchung aktualisieren
     * PUT /api/bookings/{id}
     */
    public function update($id)
    {
        try {
            // CSRF-Schutz
            $input = getJsonInput();
            if (isset($input['csrf_token'])) {
                if (!Security::validateCsrfToken($input['csrf_token'])) {
                    throw new SecurityException('Invalid CSRF token', 403);
                }
            }

            $booking = $this->booking->update($id, $input);

            if (!$booking) {
                errorResponse('Buchung nicht gefunden', 404);
            }

            // Bei Status-Änderungen E-Mail senden
            if (isset($input['status'])) {
                try {
                    $this->emailController->sendBookingStatusUpdate($booking, $input['status']);
                } catch (Exception $e) {
                    error_log('Status email failed: ' . $e->getMessage());
                }
            }

            successResponse($booking, 'Buchung erfolgreich aktualisiert');
        } catch (SecurityException $e) {
            errorResponse($e->getMessage(), $e->getHttpCode());
        } catch (Exception $e) {
            error_log('Booking update error: ' . $e->getMessage());
            errorResponse('Buchung konnte nicht aktualisiert werden', 500);
        }
    }

    /**
     * Buchung stornieren
     * POST /api/bookings/{id}/cancel
     */
    public function cancel($id)
    {
        try {
            $input = getJsonInput();
            $reason = $input['reason'] ?? null;

            $booking = $this->booking->cancel($id, $reason);

            if (!$booking) {
                errorResponse('Buchung nicht gefunden', 404);
            }

            // Stornierungsbestätigung per E-Mail
            try {
                $this->emailController->sendBookingCancellation($booking, $reason);
            } catch (Exception $e) {
                error_log('Cancellation email failed: ' . $e->getMessage());
            }

            successResponse($booking, 'Buchung erfolgreich storniert');
        } catch (Exception $e) {
            error_log('Booking cancellation error: ' . $e->getMessage());
            errorResponse('Buchung konnte nicht storniert werden: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Buchung löschen (Admin)
     * DELETE /api/bookings/{id}
     */
    public function delete($id)
    {
        try {
            // Admin-Berechtigung prüfen (vereinfacht)
            // In echter Anwendung würde hier JWT/Session geprüft

            $result = $this->booking->delete($id);

            if (!$result) {
                errorResponse('Buchung nicht gefunden', 404);
            }

            successResponse(null, 'Buchung erfolgreich gelöscht');
        } catch (Exception $e) {
            error_log('Booking deletion error: ' . $e->getMessage());
            errorResponse('Buchung konnte nicht gelöscht werden: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Buchungen für heute abrufen
     * GET /api/bookings/today
     */
    public function getToday()
    {
        try {
            $bookings = $this->booking->getTodaysBookings();

            successResponse([
                'date' => date('Y-m-d'),
                'bookings' => $bookings,
                'count' => count($bookings)
            ]);
        } catch (Exception $e) {
            error_log('Today bookings error: ' . $e->getMessage());
            errorResponse('Fehler beim Abrufen der heutigen Buchungen', 500);
        }
    }

    /**
     * Kommende Buchungen abrufen
     * GET /api/bookings/upcoming
     */
    public function getUpcoming()
    {
        try {
            $days = min((int)($_GET['days'] ?? 7), 30); // Max 30 Tage
            $bookings = $this->booking->getUpcomingBookings($days);

            successResponse([
                'period_days' => $days,
                'date_from' => date('Y-m-d'),
                'date_to' => date('Y-m-d', strtotime("+$days days")),
                'bookings' => $bookings,
                'count' => count($bookings)
            ]);
        } catch (Exception $e) {
            error_log('Upcoming bookings error: ' . $e->getMessage());
            errorResponse('Fehler beim Abrufen der kommenden Buchungen', 500);
        }
    }

    /**
     * Buchungs-Statistiken abrufen
     * GET /api/bookings/stats
     */
    public function getStats()
    {
        try {
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;

            // Datum-Validierung
            if ($dateFrom && !Validator::isValidDate($dateFrom)) {
                errorResponse('Ungültiges Start-Datum', 400);
            }
            if ($dateTo && !Validator::isValidDate($dateTo)) {
                errorResponse('Ungültiges End-Datum', 400);
            }

            $stats = $this->booking->getStats($dateFrom, $dateTo);

            successResponse([
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            error_log('Booking stats error: ' . $e->getMessage());
            errorResponse('Fehler beim Abrufen der Statistiken', 500);
        }
    }

    /**
     * Entfernung berechnen
     * POST /api/bookings/calculate-distance
     */
    public function calculateDistance()
    {
        try {
            $input = getJsonInput();

            // Adresse validieren
            if (empty($input['address'])) {
                errorResponse('Adresse ist erforderlich', 400);
            }

            $address = $input['address'];

            // Entfernung berechnen
            $distanceResult = $this->mapsService->calculateDistance($address);
            $travelCostResult = $this->mapsService->calculateTravelCost($distanceResult['distance']);
            $serviceAreaResult = $this->mapsService->isInServiceArea($distanceResult['distance']);

            successResponse([
                'address' => $address,
                'distance' => $distanceResult,
                'travel_cost' => $travelCostResult,
                'service_area' => $serviceAreaResult
            ]);
        } catch (Exception $e) {
            error_log('Distance calculation error: ' . $e->getMessage());
            errorResponse('Entfernung konnte nicht berechnet werden: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Buchungserinnerung senden
     * POST /api/bookings/{id}/send-reminder
     */
    public function sendReminder($id)
    {
        try {
            $booking = $this->booking->findById($id);

            if (!$booking) {
                errorResponse('Buchung nicht gefunden', 404);
            }

            // Nur für bestätigte Buchungen in der Zukunft
            if ($booking['status'] !== 'confirmed') {
                errorResponse('Erinnerung nur für bestätigte Buchungen möglich', 400);
            }

            $bookingDateTime = strtotime($booking['datetime']);
            if ($bookingDateTime <= time()) {
                errorResponse('Erinnerung nur für zukünftige Buchungen möglich', 400);
            }

            // Erinnerung senden
            $this->emailController->sendBookingReminder($booking);

            successResponse(null, 'Erinnerung erfolgreich gesendet');
        } catch (Exception $e) {
            error_log('Booking reminder error: ' . $e->getMessage());
            errorResponse('Erinnerung konnte nicht gesendet werden', 500);
        }
    }

    /**
     * Buchungsbestätigung erneut senden
     * POST /api/bookings/{id}/resend-confirmation
     */
    public function resendConfirmation($id)
    {
        try {
            $booking = $this->booking->findById($id);

            if (!$booking) {
                errorResponse('Buchung nicht gefunden', 404);
            }

            $this->emailController->sendBookingConfirmation($booking);

            successResponse(null, 'Bestätigung erfolgreich gesendet');
        } catch (Exception $e) {
            error_log('Booking confirmation resend error: ' . $e->getMessage());
            errorResponse('Bestätigung konnte nicht gesendet werden', 500);
        }
    }

    /**
     * Validiert Buchungseingaben
     */
    private function validateBookingInput($input)
    {
        $rules = [
            'date' => ['required', 'date'],
            'time' => ['required', 'time'],
            'services' => ['required', 'array'],
            'customer' => ['required', 'array']
        ];

        $errors = (new Validator())->validate($input, $rules);

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // Services validieren
        if (empty($input['services'])) {
            throw new ValidationException(['services' => 'Mindestens ein Service muss ausgewählt werden']);
        }

        foreach ($input['services'] as $serviceId) {
            if (!is_numeric($serviceId)) {
                throw new ValidationException(['services' => 'Ungültige Service-ID']);
            }
        }

        // Kundendaten validieren
        $customerRules = [
            'customer.first_name' => ['required', 'min:2', 'max:50'],
            'customer.last_name' => ['required', 'min:2', 'max:50'],
            'customer.email' => ['required', 'email'],
            'customer.phone' => ['required', 'phone'],
            'customer.street' => ['required', 'min:5'],
            'customer.zip' => ['required', 'zip'],
            'customer.city' => ['required', 'min:2']
        ];

        $customerData = [];
        foreach ($input['customer'] as $key => $value) {
            $customerData["customer.$key"] = $value;
        }

        $customerErrors = (new Validator())->validate($customerData, $customerRules);
        if (!empty($customerErrors)) {
            throw new ValidationException($customerErrors);
        }

        // Zusätzliche Business-Validierung
        $this->validateBusinessRules($input);
    }

    /**
     * Business-Regeln validieren
     */
    private function validateBusinessRules($input)
    {
        // Datum in der Zukunft
        $bookingDate = strtotime($input['date']);
        if ($bookingDate < strtotime('today')) {
            throw new ValidationException(['date' => 'Buchungsdatum darf nicht in der Vergangenheit liegen']);
        }

        // Maximaler Buchungszeitraum
        $maxDate = strtotime('+' . BUSINESS_DAYS_ADVANCE . ' days');
        if ($bookingDate > $maxDate) {
            throw new ValidationException(['date' => 'Buchungsdatum liegt zu weit in der Zukunft']);
        }

        // Geschäftszeiten
        $hour = (int)substr($input['time'], 0, 2);
        if ($hour < BUSINESS_HOURS_START || $hour >= BUSINESS_HOURS_END) {
            throw new ValidationException(['time' => 'Uhrzeit liegt außerhalb der Geschäftszeiten']);
        }

        // Wochenende prüfen (optional)
        $dayOfWeek = date('N', $bookingDate);
        if ($dayOfWeek >= 6) { // Samstag (6) oder Sonntag (7)
            throw new ValidationException(['date' => 'Buchungen sind nur von Montag bis Freitag möglich']);
        }

        // Verfügbarkeit prüfen
        if ($this->booking->isSlotBooked($input['date'], $input['time'])) {
            throw new ValidationException(['time' => 'Dieser Termin ist bereits gebucht']);
        }

        // Services existieren und sind aktiv
        foreach ($input['services'] as $serviceId) {
            $service = $this->service->findById($serviceId);
            if (!$service) {
                throw new ValidationException(['services' => "Service mit ID $serviceId nicht gefunden"]);
            }
            if (!$service['active']) {
                throw new ValidationException(['services' => "Service '{$service['name']}' ist nicht verfügbar"]);
            }
        }
    }
}

// Helper-Funktion
function bookingController()
{
    return new BookingController();
}
