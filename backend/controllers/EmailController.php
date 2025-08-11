<?php

/**
 * Mobile Car Service - Email Controller
 * Verwaltet alle E-Mail-Versendungen
 */

require_once __DIR__ . '/../config.php';

class EmailController
{
    private $smtpSettings;
    private $templates;

    public function __construct()
    {
        $this->smtpSettings = [
            'host' => config('email.host', SMTP_HOST),
            'port' => config('email.port', SMTP_PORT),
            'username' => config('email.username', SMTP_USERNAME),
            'password' => config('email.password', SMTP_PASSWORD),
            'from_email' => config('email.from_email', SMTP_FROM_EMAIL),
            'from_name' => config('email.from_name', SMTP_FROM_NAME)
        ];

        $this->initializeTemplates();
    }

    /**
     * Buchungsbestätigung senden
     */
    public function sendBookingConfirmation($booking)
    {
        try {
            $subject = "Buchungsbestätigung - " . $booking['booking_number'];
            $template = 'booking_confirmation';

            $templateData = [
                'booking' => $booking,
                'customer' => $booking['customer'],
                'services' => $booking['services'],
                'company' => [
                    'name' => COMPANY_NAME,
                    'address' => COMPANY_ADDRESS,
                    'phone' => COMPANY_PHONE,
                    'email' => COMPANY_EMAIL
                ]
            ];

            $htmlContent = $this->renderTemplate($template, $templateData);
            $textContent = $this->generateTextFromHtml($htmlContent);

            return $this->sendEmail(
                $booking['customer']['email'],
                $booking['customer']['full_name'],
                $subject,
                $htmlContent,
                $textContent
            );
        } catch (Exception $e) {
            error_log('Booking confirmation email failed: ' . $e->getMessage());
            throw new Exception('Buchungsbestätigung konnte nicht gesendet werden');
        }
    }

    /**
     * Buchungserinnerung senden
     */
    public function sendBookingReminder($booking)
    {
        try {
            $bookingDate = new DateTime($booking['date'] . ' ' . $booking['time']);
            $subject = "Terminerinnerung - Morgen um " . $booking['time'] . " Uhr";
            $template = 'booking_reminder';

            $templateData = [
                'booking' => $booking,
                'customer' => $booking['customer'],
                'services' => $booking['services'],
                'formatted_date' => $bookingDate->format('l, d.m.Y'),
                'formatted_time' => $bookingDate->format('H:i'),
                'company' => [
                    'name' => COMPANY_NAME,
                    'phone' => COMPANY_PHONE,
                    'email' => COMPANY_EMAIL
                ]
            ];

            $htmlContent = $this->renderTemplate($template, $templateData);
            $textContent = $this->generateTextFromHtml($htmlContent);

            return $this->sendEmail(
                $booking['customer']['email'],
                $booking['customer']['full_name'],
                $subject,
                $htmlContent,
                $textContent
            );
        } catch (Exception $e) {
            error_log('Booking reminder email failed: ' . $e->getMessage());
            throw new Exception('Terminerinnerung konnte nicht gesendet werden');
        }
    }

    /**
     * Status-Update senden
     */
    public function sendBookingStatusUpdate($booking, $newStatus)
    {
        try {
            $statusMessages = [
                'confirmed' => 'bestätigt',
                'in_progress' => 'in Bearbeitung',
                'completed' => 'abgeschlossen',
                'cancelled' => 'storniert'
            ];

            $statusText = $statusMessages[$newStatus] ?? $newStatus;
            $subject = "Status-Update: Ihre Buchung wurde $statusText";
            $template = 'booking_status_update';

            $templateData = [
                'booking' => $booking,
                'customer' => $booking['customer'],
                'new_status' => $newStatus,
                'status_text' => $statusText,
                'company' => [
                    'name' => COMPANY_NAME,
                    'phone' => COMPANY_PHONE,
                    'email' => COMPANY_EMAIL
                ]
            ];

            $htmlContent = $this->renderTemplate($template, $templateData);
            $textContent = $this->generateTextFromHtml($htmlContent);

            return $this->sendEmail(
                $booking['customer']['email'],
                $booking['customer']['full_name'],
                $subject,
                $htmlContent,
                $textContent
            );
        } catch (Exception $e) {
            error_log('Status update email failed: ' . $e->getMessage());
            throw new Exception('Status-Update konnte nicht gesendet werden');
        }
    }

    /**
     * Stornierungsbestätigung senden
     */
    public function sendBookingCancellation($booking, $reason = null)
    {
        try {
            $subject = "Stornierungsbestätigung - " . $booking['booking_number'];
            $template = 'booking_cancellation';

            $templateData = [
                'booking' => $booking,
                'customer' => $booking['customer'],
                'reason' => $reason,
                'company' => [
                    'name' => COMPANY_NAME,
                    'phone' => COMPANY_PHONE,
                    'email' => COMPANY_EMAIL
                ]
            ];

            $htmlContent = $this->renderTemplate($template, $templateData);
            $textContent = $this->generateTextFromHtml($htmlContent);

            return $this->sendEmail(
                $booking['customer']['email'],
                $booking['customer']['full_name'],
                $subject,
                $htmlContent,
                $textContent
            );
        } catch (Exception $e) {
            error_log('Cancellation email failed: ' . $e->getMessage());
            throw new Exception('Stornierungsbestätigung konnte nicht gesendet werden');
        }
    }

    /**
     * Kontakt-E-Mail senden
     */
    public function sendContactEmail($contactData)
    {
        try {
            $subject = "Neue Kontaktanfrage von " . $contactData['name'];
            $template = 'contact_inquiry';

            $templateData = [
                'contact' => $contactData,
                'sent_at' => date('d.m.Y H:i:s')
            ];

            $htmlContent = $this->renderTemplate($template, $templateData);
            $textContent = $this->generateTextFromHtml($htmlContent);

            // An Unternehmen senden
            $result = $this->sendEmail(
                COMPANY_EMAIL,
                COMPANY_NAME,
                $subject,
                $htmlContent,
                $textContent
            );

            // Autoresponder an Kontakt senden
            if ($result) {
                $this->sendContactAutoResponse($contactData);
            }

            return $result;
        } catch (Exception $e) {
            error_log('Contact email failed: ' . $e->getMessage());
            throw new Exception('Kontakt-E-Mail konnte nicht gesendet werden');
        }
    }

    /**
     * Auto-Antwort für Kontaktanfragen
     */
    private function sendContactAutoResponse($contactData)
    {
        try {
            $subject = "Ihre Anfrage bei " . COMPANY_NAME;
            $template = 'contact_autoresponse';

            $templateData = [
                'contact' => $contactData,
                'company' => [
                    'name' => COMPANY_NAME,
                    'phone' => COMPANY_PHONE,
                    'email' => COMPANY_EMAIL
                ]
            ];

            $htmlContent = $this->renderTemplate($template, $templateData);
            $textContent = $this->generateTextFromHtml($htmlContent);

            return $this->sendEmail(
                $contactData['email'],
                $contactData['name'],
                $subject,
                $htmlContent,
                $textContent
            );
        } catch (Exception $e) {
            error_log('Contact autoresponse failed: ' . $e->getMessage());
            // Nicht kritisch, daher nicht weiterwerfen
        }
    }

    /**
     * Newsletter senden
     */
    public function sendNewsletter($recipients, $subject, $content)
    {
        try {
            $successCount = 0;
            $failureCount = 0;

            foreach ($recipients as $recipient) {
                try {
                    $this->sendEmail(
                        $recipient['email'],
                        $recipient['name'],
                        $subject,
                        $content,
                        $this->generateTextFromHtml($content)
                    );
                    $successCount++;
                } catch (Exception $e) {
                    $failureCount++;
                    error_log("Newsletter send failed for {$recipient['email']}: " . $e->getMessage());
                }

                // Kleine Pause zwischen E-Mails
                usleep(100000); // 0.1 Sekunden
            }

            return [
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'total' => count($recipients)
            ];
        } catch (Exception $e) {
            error_log('Newsletter sending failed: ' . $e->getMessage());
            throw new Exception('Newsletter konnte nicht gesendet werden');
        }
    }

    /**
     * E-Mail senden (Hauptfunktion)
     */
    private function sendEmail($to, $toName, $subject, $htmlBody, $textBody = null)
    {
        try {
            // Für lokale Entwicklung: E-Mails in Datei speichern
            if ($this->isLocalEnvironment()) {
                return $this->saveEmailToFile($to, $toName, $subject, $htmlBody);
            }

            // PHPMailer verwenden (falls verfügbar)
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return $this->sendWithPHPMailer($to, $toName, $subject, $htmlBody, $textBody);
            }

            // PHP mail() Funktion als Fallback
            return $this->sendWithPhpMail($to, $toName, $subject, $htmlBody, $textBody);
        } catch (Exception $e) {
            error_log('Email sending failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * E-Mail mit PHPMailer senden
     */
    private function sendWithPHPMailer($to, $toName, $subject, $htmlBody, $textBody)
    {
        // PHPMailer-Implementation würde hier stehen
        // Für Demonstrationszwecke verwenden wir die einfache Version
        return $this->sendWithPhpMail($to, $toName, $subject, $htmlBody, $textBody);
    }

    /**
     * E-Mail mit PHP mail() senden
     */
    private function sendWithPhpMail($to, $toName, $subject, $htmlBody, $textBody)
    {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $this->smtpSettings['from_name'] . ' <' . $this->smtpSettings['from_email'] . '>';
        $headers[] = 'Reply-To: ' . $this->smtpSettings['from_email'];
        $headers[] = 'X-Mailer: Mobile Car Service/1.0';

        $success = mail($to, $subject, $htmlBody, implode("\r\n", $headers));

        if (!$success) {
            throw new Exception('E-Mail konnte nicht gesendet werden');
        }

        return true;
    }

    /**
     * E-Mail in Datei speichern (für lokale Entwicklung)
     */
    private function saveEmailToFile($to, $toName, $subject, $htmlBody)
    {
        $emailDir = LOGS_PATH . 'emails/';
        if (!is_dir($emailDir)) {
            mkdir($emailDir, 0755, true);
        }

        $filename = date('Y-m-d_H-i-s') . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $to) . '.html';
        $filepath = $emailDir . $filename;

        $emailContent = "<!DOCTYPE html>\n";
        $emailContent .= "<html><head><meta charset='UTF-8'></head><body>\n";
        $emailContent .= "<div style='background: #f0f0f0; padding: 20px; margin-bottom: 20px;'>\n";
        $emailContent .= "<strong>An:</strong> $toName &lt;$to&gt;<br>\n";
        $emailContent .= "<strong>Betreff:</strong> $subject<br>\n";
        $emailContent .= "<strong>Gesendet:</strong> " . date('d.m.Y H:i:s') . "\n";
        $emailContent .= "</div>\n";
        $emailContent .= $htmlBody;
        $emailContent .= "\n</body></html>";

        file_put_contents($filepath, $emailContent);

        error_log("Email saved to file: $filepath");
        return true;
    }

    /**
     * Template rendern
     */
    private function renderTemplate($templateName, $data)
    {
        if (!isset($this->templates[$templateName])) {
            throw new Exception("Template '$templateName' nicht gefunden");
        }

        $template = $this->templates[$templateName];

        // Platzhalter ersetzen
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $placeholder = "{{" . $key . "." . $subKey . "}}";
                    $template = str_replace($placeholder, $this->escapeHtml($subValue), $template);
                }
            } else {
                $placeholder = "{{" . $key . "}}";
                $template = str_replace($placeholder, $this->escapeHtml($value), $template);
            }
        }

        // Spezielle Platzhalter
        $template = str_replace('{{current_year}}', date('Y'), $template);
        $template = str_replace('{{current_date}}', date('d.m.Y'), $template);
        $template = str_replace('{{app_url}}', APP_URL, $template);

        return $template;
    }

    /**
     * Text aus HTML generieren
     */
    private function generateTextFromHtml($html)
    {
        // HTML-Tags entfernen
        $text = strip_tags($html);

        // Mehrfache Leerzeichen und Zeilenumbrüche bereinigen
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);

        // HTML-Entities dekodieren
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        return trim($text);
    }

    /**
     * HTML escapen
     */
    private function escapeHtml($value)
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Lokale Umgebung prüfen
     */
    private function isLocalEnvironment()
    {
        return config('app.debug', false) ||
            $_SERVER['SERVER_NAME'] === 'localhost' ||
            $_SERVER['SERVER_NAME'] === '127.0.0.1';
    }

    /**
     * E-Mail-Templates initialisieren
     */
    private function initializeTemplates()
    {
        $this->templates = [
            'booking_confirmation' => $this->getBookingConfirmationTemplate(),
            'booking_reminder' => $this->getBookingReminderTemplate(),
            'booking_status_update' => $this->getBookingStatusUpdateTemplate(),
            'booking_cancellation' => $this->getBookingCancellationTemplate(),
            'contact_inquiry' => $this->getContactInquiryTemplate(),
            'contact_autoresponse' => $this->getContactAutoResponseTemplate()
        ];
    }

    /**
     * Buchungsbestätigungs-Template
     */
    private function getBookingConfirmationTemplate()
    {
        return '
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buchungsbestätigung</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
        .booking-info { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .detail-row:last-child { border-bottom: none; }
        .total { font-weight: bold; font-size: 1.2em; color: #2c3e50; }
        .footer { text-align: center; color: #666; font-size: 0.9em; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{company.name}}</h1>
        <h2>Buchungsbestätigung</h2>
    </div>
    
    <div class="content">
        <p>Liebe/r {{customer.first_name}} {{customer.last_name}},</p>
        
        <p>vielen Dank für Ihre Buchung! Wir freuen uns, Sie bald bedienen zu dürfen.</p>
        
        <div class="booking-info">
            <h3>Ihre Buchungsdetails:</h3>
            
            <div class="detail-row">
                <span><strong>Buchungsnummer:</strong></span>
                <span>{{booking.booking_number}}</span>
            </div>
            
            <div class="detail-row">
                <span><strong>Datum:</strong></span>
                <span>{{booking.date}}</span>
            </div>
            
            <div class="detail-row">
                <span><strong>Uhrzeit:</strong></span>
                <span>{{booking.time}} Uhr</span>
            </div>
            
            <div class="detail-row">
                <span><strong>Adresse:</strong></span>
                <span>{{customer.address.full_address}}</span>
            </div>
            
            <div class="detail-row">
                <span><strong>Entfernung:</strong></span>
                <span>{{booking.distance}} km</span>
            </div>
            
            <div class="detail-row">
                <span><strong>Anfahrtskosten:</strong></span>
                <span>{{booking.travel_cost}} €</span>
            </div>
            
            <div class="detail-row total">
                <span>Gesamtpreis:</span>
                <span>{{booking.total_price}} €</span>
            </div>
        </div>
        
        <h3>Gewählte Services:</h3>
        <ul>
            {{#each services}}
            <li>{{name}} - {{price}} € ({{duration}} Min)</li>
            {{/each}}
        </ul>
        
        <p><strong>Wichtige Hinweise:</strong></p>
        <ul>
            <li>Bitte stellen Sie sicher, dass Ihr Fahrzeug zugänglich ist</li>
            <li>Bei Wetterunsicherheit kontaktieren Sie uns bitte</li>
            <li>Stornierungen sind bis 24h vor dem Termin kostenlos möglich</li>
        </ul>
        
        <p>Bei Fragen erreichen Sie uns unter:</p>
        <ul>
            <li>Telefon: {{company.phone}}</li>
            <li>E-Mail: {{company.email}}</li>
        </ul>
        
        <p>Mit freundlichen Grüßen,<br>
        Ihr {{company.name}} Team</p>
    </div>
    
    <div class="footer">
        <p>{{company.name}} | {{company.address}}<br>
        Diese E-Mail wurde automatisch generiert.</p>
    </div>
</body>
</html>';
    }

    /**
     * Weitere Templates...
     */
    private function getBookingReminderTemplate()
    {
        return '
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Terminerinnerung</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #e74c3c; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
        .reminder-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .footer { text-align: center; color: #666; font-size: 0.9em; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{company.name}}</h1>
        <h2>🚗 Terminerinnerung</h2>
    </div>
    
    <div class="content">
        <p>Liebe/r {{customer.first_name}} {{customer.last_name}},</p>
        
        <div class="reminder-box">
            <h3>⏰ Ihr Termin ist morgen!</h3>
            <p><strong>Datum:</strong> {{formatted_date}}<br>
            <strong>Uhrzeit:</strong> {{formatted_time}} Uhr<br>
            <strong>Buchungsnummer:</strong> {{booking.booking_number}}</p>
        </div>
        
        <p>Wir freuen uns auf Ihren Termin morgen und werden pünktlich bei Ihnen sein.</p>
        
        <p><strong>Bitte beachten Sie:</strong></p>
        <ul>
            <li>Stellen Sie sicher, dass Ihr Fahrzeug zugänglich ist</li>
            <li>Entfernen Sie persönliche Gegenstände aus dem Fahrzeug</li>
            <li>Bei Änderungen kontaktieren Sie uns bitte rechtzeitig</li>
        </ul>
        
        <p>Bei Fragen erreichen Sie uns unter {{company.phone}}.</p>
        
        <p>Mit freundlichen Grüßen,<br>
        Ihr {{company.name}} Team</p>
    </div>
    
    <div class="footer">
        <p>{{company.name}}<br>
        Telefon: {{company.phone}} | E-Mail: {{company.email}}</p>
    </div>
</body>
</html>';
    }

    // Weitere Template-Methoden würden hier folgen...
    private function getBookingStatusUpdateTemplate()
    {
        return '<!-- Template für Status-Updates -->';
    }
    private function getBookingCancellationTemplate()
    {
        return '<!-- Template für Stornierungen -->';
    }
    private function getContactInquiryTemplate()
    {
        return '<!-- Template für Kontaktanfragen -->';
    }
    private function getContactAutoResponseTemplate()
    {
        return '<!-- Template für Auto-Antworten -->';
    }

    /**
     * Test-E-Mail senden
     */
    public function sendTestEmail($to)
    {
        try {
            $subject = "Test-E-Mail von " . COMPANY_NAME;
            $content = "<h1>Test erfolgreich!</h1><p>Diese E-Mail wurde erfolgreich von Ihrem Mobile Car Service System gesendet.</p><p>Gesendet am: " . date('d.m.Y H:i:s') . "</p>";

            return $this->sendEmail($to, 'Test', $subject, $content);
        } catch (Exception $e) {
            error_log('Test email failed: ' . $e->getMessage());
            throw new Exception('Test-E-Mail konnte nicht gesendet werden');
        }
    }
}

// Helper-Funktion
function emailController()
{
    return new EmailController();
}
