<?php

/**
 * Mobile Car Service - Customer Controller
 * Verwaltet alle API-Endpunkte für Kunden
 */

require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../utils/Security.php';

class CustomerController
{
    private $customer;

    public function __construct()
    {
        $this->customer = new Customer();
    }

    /**
     * Neuen Kunden erstellen
     * POST /api/customers
     */
    public function create()
    {
        try {
            // Rate Limiting
            security()->checkRateLimit(getClientIp() . '_customer', 20, 3600); // 20 pro Stunde

            $input = getJsonInput();
            $this->validateCustomerInput($input);

            // Security Checks
            Security::checkHoneypot('website', $input);

            $customer = $this->customer->create($input);

            successResponse($customer, 'Kunde erfolgreich erstellt');
        } catch (ValidationException $e) {
            errorResponse('Validierungsfehler', 400, $e->getErrors());
        } catch (SecurityException $e) {
            errorResponse($e->getMessage(), $e->getHttpCode());
        } catch (Exception $e) {
            error_log('Customer creation error: ' . $e->getMessage());
            errorResponse('Kunde konnte nicht erstellt werden: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Kunde abrufen
     * GET /api/customers/{id}
     */
    public function get($id)
    {
        try {
            if (!is_numeric($id)) {
                errorResponse('Ungültige Kunden-ID', 400);
            }

            $customer = $this->customer->findById($id);

            if (!$customer) {
                errorResponse('Kunde nicht gefunden', 404);
            }

            // Buchungen hinzufügen
            $customerData = $this->customer->toArray($customer);
            $customerData['bookings'] = $this->customer->getBookings($id, 10);
            $customerData['booking_count'] = $this->customer->getBookingCount($id);

            successResponse($customerData);
        } catch (Exception $e) {
            error_log('Customer retrieval error: ' . $e->getMessage());
            errorResponse('Fehler beim Abrufen des Kunden', 500);
        }
    }

    /**
     * Kunde anhand E-Mail abrufen
     * GET /api/customers/email/{email}
     */
    public function getByEmail($email)
    {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                errorResponse('Ungültige E-Mail-Adresse', 400);
            }

            $customer = $this->customer->findByEmail($email);

            if (!$customer) {
                errorResponse('Kunde nicht gefunden', 404);
            }

            $customerData = $this->customer->toArray($customer);
            $customerData['bookings'] = $this->customer->getBookings($customer['id'], 5);
            $customerData['booking_count'] = $this->customer->getBookingCount($customer['id']);

            successResponse($customerData);
        } catch (Exception $e) {
            error_log('Customer email lookup error: ' . $e->getMessage());
            errorResponse('Fehler beim Abrufen des Kunden', 500);
        }
    }

    /**
     * Alle Kunden abrufen (mit Suche und Pagination)
     * GET /api/customers
     */
    public function getAll()
    {
        try {
            // Query-Parameter
            $limit = min((int)($_GET['limit'] ?? 50), 100); // Max 100
            $offset = max((int)($_GET['offset'] ?? 0), 0);
            $search = $_GET['search'] ?? null;

            $customers = $this->customer->getAll($limit, $offset, $search);
            $totalCount = $this->customer->getCount($search);

            // Für jeden Kunden Buchungsanzahl hinzufügen
            foreach ($customers as &$customer) {
                $customerArray = $this->customer->toArray($customer);
                $customerArray['booking_count'] = $this->customer->getBookingCount($customer['id']);
                $customer = $customerArray;
            }

            successResponse([
                'customers' => $customers,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $totalCount,
                    'has_more' => ($offset + $limit) < $totalCount
                ],
                'search' => $search
            ]);
        } catch (Exception $e) {
            error_log('Customers retrieval error: ' . $e->getMessage());
            errorResponse('Fehler beim Abrufen der Kunden', 500);
        }
    }

    /**
     * Kunde aktualisieren
     * PUT /api/customers/{id}
     */
    public function update($id)
    {
        try {
            if (!is_numeric($id)) {
                errorResponse('Ungültige Kunden-ID', 400);
            }

            $input = getJsonInput();

            // CSRF-Schutz
            if (isset($input['csrf_token'])) {
                if (!Security::validateCsrfToken($input['csrf_token'])) {
                    throw new SecurityException('Invalid CSRF token', 403);
                }
            }

            $this->validateCustomerInput($input, $id);

            $customer = $this->customer->update($id, $input);

            if (!$customer) {
                errorResponse('Kunde nicht gefunden', 404);
            }

            successResponse($this->customer->toArray($customer), 'Kunde erfolgreich aktualisiert');
        } catch (ValidationException $e) {
            errorResponse('Validierungsfehler', 400, $e->getErrors());
        } catch (SecurityException $e) {
            errorResponse($e->getMessage(), $e->getHttpCode());
        } catch (Exception $e) {
            error_log('Customer update error: ' . $e->getMessage());
            errorResponse('Kunde konnte nicht aktualisiert werden: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Kunde löschen
     * DELETE /api/customers/{id}
     */
    public function delete($id)
    {
        try {
            if (!is_numeric($id)) {
                errorResponse('Ungültige Kunden-ID', 400);
            }

            // Prüfen ob Kunde Buchungen hat
            $bookingCount = $this->customer->getBookingCount($id);
            if ($bookingCount > 0) {
                errorResponse('Kunde kann nicht gelöscht werden - es existieren noch Buchungen', 400);
            }

            $result = $this->customer->delete($id);

            if (!$result) {
                errorResponse('Kunde nicht gefunden', 404);
            }

            successResponse(null, 'Kunde erfolgreich gelöscht');
        } catch (Exception $e) {
            error_log('Customer deletion error: ' . $e->getMessage());
            errorResponse('Kunde konnte nicht gelöscht werden: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Buchungen eines Kunden abrufen
     * GET /api/customers/{id}/bookings
     */
    public function getBookings($id)
    {
        try {
            if (!is_numeric($id)) {
                errorResponse('Ungültige Kunden-ID', 400);
            }

            $customer = $this->customer->findById($id);
            if (!$customer) {
                errorResponse('Kunde nicht gefunden', 404);
            }

            $limit = min((int)($_GET['limit'] ?? 50), 100);
            $bookings = $this->customer->getBookings($id, $limit);

            successResponse([
                'customer_id' => (int)$id,
                'customer_name' => $customer['first_name'] . ' ' . $customer['last_name'],
                'bookings' => $bookings,
                'total_bookings' => $this->customer->getBookingCount($id)
            ]);
        } catch (Exception $e) {
            error_log('Customer bookings error: ' . $e->getMessage());
            errorResponse('Fehler beim Abrufen der Kundenbuchungen', 500);
        }
    }

    /**
     * Kunden-Statistiken abrufen
     * GET /api/customers/stats
     */
    public function getStats()
    {
        try {
            $stats = $this->customer->getStats();

            successResponse([
                'stats' => $stats,
                'generated_at' => date('c')
            ]);
        } catch (Exception $e) {
            error_log('Customer stats error: ' . $e->getMessage());
            errorResponse('Fehler beim Abrufen der Kunden-Statistiken', 500);
        }
    }

    /**
     * Duplikate finden
     * GET /api/customers/duplicates
     */
    public function getDuplicates()
    {
        try {
            $duplicates = $this->customer->findDuplicates();

            successResponse([
                'duplicates' => $duplicates,
                'count' => count($duplicates)
            ]);
        } catch (Exception $e) {
            error_log('Customer duplicates error: ' . $e->getMessage());
            errorResponse('Fehler beim Suchen nach Duplikaten', 500);
        }
    }

    /**
     * Kunden exportieren
     * GET /api/customers/export
     */
    public function export()
    {
        try {
            $format = $_GET['format'] ?? 'csv';

            if (!in_array($format, ['csv', 'json'])) {
                errorResponse('Ungültiges Export-Format', 400);
            }

            $data = $this->customer->export($format);

            // Headers für Download setzen
            if ($format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="kunden_' . date('Y-m-d') . '.csv"');
            } else {
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="kunden_' . date('Y-m-d') . '.json"');
            }

            echo $data;
            exit;
        } catch (Exception $e) {
            error_log('Customer export error: ' . $e->getMessage());
            errorResponse('Fehler beim Exportieren der Kunden', 500);
        }
    }

    /**
     * Kunden nach Stadt abrufen
     * GET /api/customers/by-city/{city}
     */
    public function getByCity($city)
    {
        try {
            if (empty($city) || strlen($city) < 2) {
                errorResponse('Ungültiger Stadtname', 400);
            }

            $customers = $this->customer->getAll(100, 0, $city);

            // Nach Stadt filtern
            $filteredCustomers = array_filter($customers, function ($customer) use ($city) {
                return stripos($customer['city'], $city) !== false;
            });

            successResponse([
                'city' => $city,
                'customers' => array_values($filteredCustomers),
                'count' => count($filteredCustomers)
            ]);
        } catch (Exception $e) {
            error_log('Customers by city error: ' . $e->getMessage());
            errorResponse('Fehler beim Abrufen der Kunden nach Stadt', 500);
        }
    }

    /**
     * Kunden suchen
     * POST /api/customers/search
     */
    public function search()
    {
        try {
            $input = getJsonInput();

            if (empty($input['query'])) {
                errorResponse('Suchbegriff ist erforderlich', 400);
            }

            $query = trim($input['query']);
            $limit = min((int)($input['limit'] ?? 20), 50);

            if (strlen($query) < 2) {
                errorResponse('Suchbegriff muss mindestens 2 Zeichen haben', 400);
            }

            $customers = $this->customer->getAll($limit, 0, $query);

            // Für jeden Kunden zusätzliche Infos
            foreach ($customers as &$customer) {
                $customerArray = $this->customer->toArray($customer);
                $customerArray['booking_count'] = $this->customer->getBookingCount($customer['id']);
                $customer = $customerArray;
            }

            successResponse([
                'query' => $query,
                'customers' => $customers,
                'count' => count($customers)
            ]);
        } catch (Exception $e) {
            error_log('Customer search error: ' . $e->getMessage());
            errorResponse('Fehler bei der Kundensuche', 500);
        }
    }

    /**
     * Validiert Kundeneingaben
     */
    private function validateCustomerInput($input, $excludeId = null)
    {
        $rules = [
            'first_name' => ['required', 'min:2', 'max:50', 'alpha'],
            'last_name' => ['required', 'min:2', 'max:50', 'alpha'],
            'email' => ['required', 'email', 'max:100'],
            'phone' => ['required', 'phone', 'min:10', 'max:20'],
            'street' => ['required', 'min:5', 'max:100'],
            'zip' => ['required', 'zip'],
            'city' => ['required', 'min:2', 'max:50', 'alpha'],
            'notes' => ['max:500']
        ];

        $customMessages = [
            'first_name.required' => 'Vorname ist erforderlich',
            'first_name.alpha' => 'Vorname darf nur Buchstaben enthalten',
            'last_name.required' => 'Nachname ist erforderlich',
            'last_name.alpha' => 'Nachname darf nur Buchstaben enthalten',
            'email.required' => 'E-Mail-Adresse ist erforderlich',
            'email.email' => 'Ungültige E-Mail-Adresse',
            'phone.required' => 'Telefonnummer ist erforderlich',
            'phone.phone' => 'Ungültige Telefonnummer',
            'street.required' => 'Straße und Hausnummer sind erforderlich',
            'zip.required' => 'PLZ ist erforderlich',
            'zip.zip' => 'Ungültige PLZ (5 Ziffern erforderlich)',
            'city.required' => 'Stadt ist erforderlich',
            'city.alpha' => 'Stadt darf nur Buchstaben enthalten'
        ];

        $errors = (new Validator())->validate($input, $rules, $customMessages);

        // E-Mail-Eindeutigkeit prüfen (außer bei Update des gleichen Kunden)
        if (isset($input['email'])) {
            $existingCustomer = $this->customer->findByEmail($input['email']);
            if ($existingCustomer && (!$excludeId || $existingCustomer['id'] != $excludeId)) {
                $errors['email'] = 'E-Mail-Adresse wird bereits verwendet';
            }
        }

        // Zusätzliche Sicherheitsprüfungen
        foreach (['first_name', 'last_name', 'street', 'city'] as $field) {
            if (isset($input[$field])) {
                $issues = Security::validateInput($input[$field], 'general');
                if (!empty($issues)) {
                    $errors[$field] = 'Feld enthält ungültige Zeichen';
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}

// Helper-Funktion
function customerController()
{
    return new CustomerController();
}
