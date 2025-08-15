<?php
// classes/EmailManager.php - Professionelles E-Mail-System

class EmailManager
{
    private $from_email;
    private $from_name;
    private $admin_email;
    private $smtp_config;

    public function __construct()
    {
        $this->from_email = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@mcs-mobile.de';
        $this->from_name = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'MCS Mobile Car Solutions';
        $this->admin_email = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@mcs-mobile.de';

        $this->smtp_config = [
            'host' => defined('SMTP_HOST') ? SMTP_HOST : 'localhost',
            'port' => defined('SMTP_PORT') ? SMTP_PORT : 587,
            'username' => defined('SMTP_USERNAME') ? SMTP_USERNAME : '',
            'password' => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : ''
        ];
    }

    /**
     * Sendet Buchungsbestätigung an Kunden
     */
    public function sendBookingConfirmation($bookingDetails)
    {
        $subject = 'Buchungsbestätigung #' . str_pad($bookingDetails['id'], 6, '0', STR_PAD_LEFT) . ' - ' . $this->from_name;
        $customerMessage = $this->getBookingConfirmationTemplate($bookingDetails);

        // E-Mail an Kunden
        $customerSuccess = $this->sendEmail(
            $bookingDetails['customer_email'],
            $bookingDetails['customer_name'],
            $subject,
            $customerMessage
        );

        // E-Mail an Admin
        $adminSubject = 'Neue Buchung #' . str_pad($bookingDetails['id'], 6, '0', STR_PAD_LEFT);
        $adminMessage = $this->getAdminNotificationTemplate($bookingDetails);

        $adminSuccess = $this->sendEmail(
            $this->admin_email,
            'MCS Admin',
            $adminSubject,
            $adminMessage
        );

        // Logging
        if ($customerSuccess) {
            error_log("Buchungsbestätigung gesendet an: " . $bookingDetails['customer_email']);
        } else {
            error_log("FEHLER: Buchungsbestätigung konnte nicht gesendet werden an: " . $bookingDetails['customer_email']);
        }

        return ['customer' => $customerSuccess, 'admin' => $adminSuccess];
    }

    /**
     * Sendet E-Mail
     */
    private function sendEmail($to_email, $to_name, $subject, $message)
    {
        // Validiere E-Mail-Adresse
        if (!SecurityManager::validateEmail($to_email)) {
            error_log("Ungültige E-Mail-Adresse: $to_email");
            return false;
        }

        // Headers für HTML-E-Mail
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
            'Reply-To: ' . $this->from_email,
            'Return-Path: ' . $this->from_email,
            'X-Mailer: MCS Booking System v1.0',
            'X-Priority: 3',
            'X-MSMail-Priority: Normal'
        ];

        // Versuche E-Mail zu senden
        try {
            // Für bessere Kompatibilität nutzen wir erstmal die mail() Funktion
            $success = mail($to_email, $subject, $message, implode("\r\n", $headers));

            if (!$success) {
                // Fallback: Versuche mit anderen Parametern
                $success = mail($to_email, $subject, $message, implode("\n", $headers));
            }

            return $success;
        } catch (Exception $e) {
            error_log("E-Mail Fehler: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Template für Kundenbestätigung
     */
    private function getBookingConfirmationTemplate($booking)
    {
        $bookingNumber = str_pad($booking['id'], 6, '0', STR_PAD_LEFT);
        $businessName = defined('BUSINESS_NAME') ? BUSINESS_NAME : 'MCS Mobile Car Solutions';
        $businessPhone = defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '+49 123 456789';
        $businessEmail = defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : 'info@mcs-mobile.de';
        $businessAddress = defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : 'Hüllerstraße 16, 44649 Herne';

        // Services HTML generieren
        $servicesHtml = '';
        $servicesTotal = 0;

        if (isset($booking['services']) && is_array($booking['services'])) {
            foreach ($booking['services'] as $service) {
                $servicesTotal += $service['price'];
                $servicesHtml .= '
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #eee; font-size: 14px;">' . htmlspecialchars($service['name']) . '</td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold; font-size: 14px;">' . number_format($service['price'], 2, ',', '.') . ' €</td>
                    </tr>';
            }
        }

        $travelCost = ($booking['distance'] ?? 0) * (defined('TRAVEL_COST_PER_KM') ? TRAVEL_COST_PER_KM : 0.50);

        $template = '
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Buchungsbestätigung</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4;">
            
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); color: white; padding: 30px 20px; text-align: center;">
                <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: bold;">' . $businessName . '</h1>
                <p style="margin: 10px 0 0 0; font-size: 18px; color: #ccc;">Buchungsbestätigung</p>
            </div>
            
            <!-- Main Content -->
            <div style="max-width: 600px; margin: 0 auto; background: white; padding: 0;">
                
                <!-- Greeting -->
                <div style="padding: 30px 30px 20px 30px;">
                    <h2 style="color: #ffffff; margin: 0 0 15px 0; font-size: 24px;">Hallo ' . htmlspecialchars($booking['customer_name']) . ',</h2>
                    <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6;">
                        vielen Dank für Ihre Buchung! Wir freuen uns darauf, Ihr Fahrzeug zu pflegen und Ihnen den besten Service zu bieten.
                    </p>
                </div>
                
                <!-- Booking Details -->
                <div style="margin: 0 30px 30px 30px; background: #f8f9fa; padding: 25px; border-radius: 8px; border-left: 4px solid #ffffff;">
                    <h3 style="margin: 0 0 20px 0; color: #ffffff; font-size: 20px;">📅 Ihre Buchungsdetails</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px 0; font-weight: bold; width: 30%;">Buchungsnummer:</td>
                            <td style="padding: 8px 0; color: #ffffff; font-weight: bold;">#' . $bookingNumber . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: bold;">Termin:</td>
                            <td style="padding: 8px 0;">' . date('d.m.Y', strtotime($booking['date'])) . ' um ' . $booking['time'] . ' Uhr</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: bold;">Adresse:</td>
                            <td style="padding: 8px 0;">' . htmlspecialchars($booking['customer_address']) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: bold;">Telefon:</td>
                            <td style="padding: 8px 0;">' . htmlspecialchars($booking['customer_phone']) . '</td>
                        </tr>
                    </table>
                </div>
                
                <!-- Services -->
                <div style="margin: 0 30px 30px 30px;">
                    <h3 style="color: #ffffff; margin: 0 0 20px 0; font-size: 20px;">🚗 Gebuchte Leistungen</h3>
                    <div style="background: white; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
                        <table style="width: 100%; border-collapse: collapse;">
                            ' . $servicesHtml . '
                            <tr style="background: #f8f9fa;">
                                <td style="padding: 15px 12px; font-weight: bold; font-size: 14px;">Anfahrt (' . number_format($booking['distance'] ?? 0, 1) . ' km à ' . number_format(defined('TRAVEL_COST_PER_KM') ? TRAVEL_COST_PER_KM : 0.50, 2) . '€)</td>
                                <td style="padding: 15px 12px; text-align: right; font-weight: bold; font-size: 14px;">' . number_format($travelCost, 2, ',', '.') . ' €</td>
                            </tr>
                            <tr style="background: #ffffff; color: white;">
                                <td style="padding: 18px 12px; font-weight: bold; font-size: 18px;">Gesamtpreis</td>
                                <td style="padding: 18px 12px; text-align: right; font-weight: bold; font-size: 20px;">' . number_format($booking['total_price'], 2, ',', '.') . ' €</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Next Steps -->
                <div style="margin: 0 30px 30px 30px; background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%); padding: 25px; border-radius: 8px; border: 1px solid #c3e6cb;">
                    <h3 style="color: #155724; margin: 0 0 15px 0; font-size: 18px;">✅ Was passiert als nächstes?</h3>
                    <ul style="margin: 0; padding-left: 20px; color: #155724;">
                        <li style="margin-bottom: 8px;">Wir rufen Sie ca. 30 Minuten vor dem Termin an</li>
                        <li style="margin-bottom: 8px;">Unser Team kommt pünktlich zu Ihrem Fahrzeug</li>
                        <li style="margin-bottom: 8px;">Zahlung erfolgt bequem vor Ort (Bar oder Karte)</li>
                        <li style="margin-bottom: 0;">Sie erhalten eine Rechnung per E-Mail</li>
                    </ul>
                </div>
                
                <!-- Contact -->
                <div style="margin: 0 30px 30px 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">
                    <h3 style="color: #ffffff; margin: 0 0 15px 0;">📞 Bei Fragen erreichen Sie uns:</h3>
                    <p style="margin: 5px 0; font-size: 16px;"><strong>Telefon:</strong> ' . $businessPhone . '</p>
                    <p style="margin: 5px 0; font-size: 16px;"><strong>E-Mail:</strong> ' . $businessEmail . '</p>
                </div>
                
            </div>
            
            <!-- Footer -->
            <div style="background: #2d2d2d; color: #ccc; padding: 30px 20px; text-align: center; font-size: 14px;">
                <p style="margin: 0 0 10px 0; font-weight: bold; color: #ffffff;">' . $businessName . '</p>
                <p style="margin: 0 0 5px 0;">' . $businessAddress . '</p>
                <p style="margin: 0;">Vielen Dank für Ihr Vertrauen!</p>
            </div>
            
        </body>
        </html>';

        return $template;
    }

    /**
     * Template für Admin-Benachrichtigung
     */
    private function getAdminNotificationTemplate($booking)
    {
        $bookingNumber = str_pad($booking['id'], 6, '0', STR_PAD_LEFT);

        $servicesHtml = '';
        if (isset($booking['services']) && is_array($booking['services'])) {
            foreach ($booking['services'] as $service) {
                $servicesHtml .= '• ' . htmlspecialchars($service['name']) . ' (' . number_format($service['price'], 2) . ' €)<br>';
            }
        }

        return '
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <title>Neue Buchung</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <h2 style="color: #ffffff;">🎉 Neue Buchung eingegangen!</h2>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>Buchungsdetails:</h3>
                <p><strong>Buchungsnummer:</strong> #' . $bookingNumber . '</p>
                <p><strong>Kunde:</strong> ' . htmlspecialchars($booking['customer_name']) . '</p>
                <p><strong>E-Mail:</strong> ' . htmlspecialchars($booking['customer_email']) . '</p>
                <p><strong>Telefon:</strong> ' . htmlspecialchars($booking['customer_phone']) . '</p>
                <p><strong>Adresse:</strong> ' . htmlspecialchars($booking['customer_address']) . '</p>
                <p><strong>Termin:</strong> ' . date('d.m.Y', strtotime($booking['date'])) . ' um ' . $booking['time'] . ' Uhr</p>
                <p><strong>Entfernung:</strong> ' . number_format($booking['distance'] ?? 0, 1) . ' km</p>
            </div>
            
            <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>Gebuchte Leistungen:</h3>
                ' . $servicesHtml . '
                <hr>
                <p><strong style="color: #ffffff; font-size: 18px;">Gesamtpreis: ' . number_format($booking['total_price'], 2) . ' €</strong></p>
            </div>
            
            <div style="background: #d1ecf1; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>Nächste Schritte:</h3>
                <ul>
                    <li>Buchung im Admin-Panel bestätigen</li>
                    <li>Termin in den Kalender eintragen</li>
                    <li>Kunde 30 Min. vor Termin anrufen</li>
                </ul>
                <p><a href="' . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : 'http://localhost') . '/admin/?tab=bookings" style="background: #ffffff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🔧 Zur Buchungsverwaltung</a></p>
            </div>
            
            <p style="color: #666; font-size: 12px;">Diese E-Mail wurde automatisch vom MCS Buchungssystem generiert.</p>
        </body>
        </html>';
    }

    /**
     * Sendet Terminerinnerung
     */
    public function sendReminder($bookingDetails, $hours_before = 24)
    {
        $subject = "Terminerinnerung - Morgen um " . $bookingDetails['time'] . " Uhr";
        $message = $this->getReminderTemplate($bookingDetails, $hours_before);

        return $this->sendEmail(
            $bookingDetails['customer_email'],
            $bookingDetails['customer_name'],
            $subject,
            $message
        );
    }

    /**
     * Template für Terminerinnerung
     */
    private function getReminderTemplate($booking, $hours_before)
    {
        $businessName = defined('BUSINESS_NAME') ? BUSINESS_NAME : 'MCS Mobile Car Solutions';
        $businessPhone = defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '+49 123 456789';

        return '
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <title>Terminerinnerung</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <h2 style="color: #ffffff;">⏰ Terminerinnerung</h2>
            
            <p>Hallo ' . htmlspecialchars($booking['customer_name']) . ',</p>
            
            <p>wir möchten Sie daran erinnern, dass Ihr Termin bei ' . $businessName . ' in ' . $hours_before . ' Stunden stattfindet:</p>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #ffffff;">
                <p><strong>Termin:</strong> ' . date('d.m.Y', strtotime($booking['date'])) . ' um ' . $booking['time'] . ' Uhr</p>
                <p><strong>Adresse:</strong> ' . htmlspecialchars($booking['customer_address']) . '</p>
                <p><strong>Buchungsnummer:</strong> #' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT) . '</p>
            </div>
            
            <p>Bitte stellen Sie sicher, dass Ihr Fahrzeug zugänglich ist und Sie erreichbar sind.</p>
            
            <p>Bei Fragen erreichen Sie uns unter: ' . $businessPhone . '</p>
            
            <p>Vielen Dank!<br>Ihr ' . $businessName . ' Team</p>
        </body>
        </html>';
    }

    /**
     * Test-E-Mail senden
     */
    public function sendTestEmail($to_email)
    {
        $subject = "Test-E-Mail - MCS Buchungssystem";
        $message = '
        <!DOCTYPE html>
        <html>
        <head><title>Test</title></head>
        <body style="font-family: Arial, sans-serif;">
            <h2 style="color: #ffffff;">✅ E-Mail-System funktioniert!</h2>
            <p>Dies ist eine Test-E-Mail vom MCS Buchungssystem.</p>
            <p><strong>Zeitstempel:</strong> ' . date('d.m.Y H:i:s') . '</p>
            <p>Wenn Sie diese E-Mail erhalten, ist das E-Mail-System korrekt konfiguriert.</p>
        </body>
        </html>';

        return $this->sendEmail($to_email, 'Test User', $subject, $message);
    }
}
