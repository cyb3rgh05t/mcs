<?php
// classes/EmailManager.php
class EmailManager
{
    private $from_email = 'noreply@mcs-mobile.de';
    private $from_name = 'MCS Mobile Car Solutions';
    private $admin_email = 'admin@mcs-mobile.de';

    public function sendBookingConfirmation($bookingDetails)
    {
        $subject = 'Buchungsbestätigung - MCS Mobile Car Solutions';
        $message = $this->getBookingConfirmationTemplate($bookingDetails);

        // E-Mail an Kunden
        $this->sendEmail(
            $bookingDetails['customer_email'],
            $bookingDetails['customer_name'],
            $subject,
            $message
        );

        // E-Mail an Admin
        $adminSubject = 'Neue Buchung #' . str_pad($bookingDetails['id'], 6, '0', STR_PAD_LEFT);
        $adminMessage = $this->getAdminNotificationTemplate($bookingDetails);

        $this->sendEmail(
            $this->admin_email,
            'MCS Admin',
            $adminSubject,
            $adminMessage
        );
    }

    private function sendEmail($to_email, $to_name, $subject, $message)
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
            'Reply-To: ' . $this->from_email,
            'X-Mailer: PHP/' . phpversion()
        ];

        return mail($to_email, $subject, $message, implode("\r\n", $headers));
    }

    private function getBookingConfirmationTemplate($booking)
    {
        $servicesHtml = '';
        foreach ($booking['services'] as $service) {
            $servicesHtml .= '<tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">' . htmlspecialchars($service['name']) . '</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;">' . number_format($service['price'], 2) . ' €</td>
            </tr>';
        }

        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Buchungsbestätigung</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); color: white; padding: 30px; border-radius: 10px; text-align: center; margin-bottom: 30px;">
                <h1 style="margin: 0; color: #ff6b35;">MCS Mobile Car Solutions</h1>
                <p style="margin: 10px 0 0 0; font-size: 18px;">Buchungsbestätigung</p>
            </div>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h2 style="color: #ff6b35; margin-top: 0;">Hallo ' . htmlspecialchars($booking['customer_name']) . ',</h2>
                <p>vielen Dank für Ihre Buchung! Wir freuen uns darauf, Ihr Fahrzeug zu pflegen.</p>
                
                <div style="background: white; padding: 20px; border-radius: 5px; border-left: 4px solid #ff6b35;">
                    <h3 style="margin-top: 0; color: #ff6b35;">Ihre Buchungsdetails</h3>
                    <p><strong>Buchungsnummer:</strong> #' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT) . '</p>
                    <p><strong>Termin:</strong> ' . date('d.m.Y', strtotime($booking['date'])) . ' um ' . $booking['time'] . ' Uhr</p>
                    <p><strong>Adresse:</strong> ' . htmlspecialchars($booking['customer_address']) . '</p>
                </div>
            </div>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="color: #ff6b35; margin-top: 0;">Gebuchte Leistungen</h3>
                <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 5px; overflow: hidden;">
                    ' . $servicesHtml . '
                    <tr style="background: #ff6b35; color: white;">
                        <td style="padding: 15px; font-weight: bold;">Anfahrt (' . $booking['distance'] . ' km)</td>
                        <td style="padding: 15px; text-align: right; font-weight: bold;">' . number_format($booking['distance'] * 0.50, 2) . ' €</td>
                    </tr>
                    <tr style="background: #ff6b35; color: white;">
                        <td style="padding: 15px; font-weight: bold; font-size: 18px;">Gesamtpreis</td>
                        <td style="padding: 15px; text-align: right; font-weight: bold; font-size: 18px;">' . number_format($booking['total_price'], 2) . ' €</td>
                    </tr>
                </table>
            </div>
            
            <div style="background: #e8f5e8; padding: 20px; border-radius: 8px; border: 1px solid #d4edda;">
                <h3 style="color: #155724; margin-top: 0;">Was passiert als nächstes?</h3>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Wir rufen Sie ca. 30 Minuten vor dem Termin an</li>
                    <li>Unser Team kommt pünktlich zu Ihnen</li>
                    <li>Zahlung erfolgt bequem vor Ort (Bar oder Karte)</li>
                    <li>Sie erhalten eine Rechnung per E-Mail</li>
                </ul>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #666;">
                <p>Bei Fragen erreichen Sie uns unter:</p>
                <p><strong>Telefon:</strong> +49 123 456789 | <strong>E-Mail:</strong> info@mcs-mobile.de</p>
                <p style="font-size: 14px; margin-top: 20px;">
                    MCS Mobile Car Solutions<br>
                    Musterstraße 123, 48431 Rheine<br>
                    www.mcs-mobile.de
                </p>
            </div>
        </body>
        </html>';

        return $template;
    }

    private function getAdminNotificationTemplate($booking)
    {
        $servicesHtml = '';
        foreach ($booking['services'] as $service) {
            $servicesHtml .= '• ' . htmlspecialchars($service['name']) . ' (' . number_format($service['price'], 2) . ' €)<br>';
        }

        return '
        <h2>Neue Buchung eingegangen</h2>
        <p><strong>Buchungsnummer:</strong> #' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT) . '</p>
        <p><strong>Kunde:</strong> ' . htmlspecialchars($booking['customer_name']) . '</p>
        <p><strong>E-Mail:</strong> ' . htmlspecialchars($booking['customer_email']) . '</p>
        <p><strong>Telefon:</strong> ' . htmlspecialchars($booking['customer_phone']) . '</p>
        <p><strong>Adresse:</strong> ' . htmlspecialchars($booking['customer_address']) . '</p>
        <p><strong>Termin:</strong> ' . date('d.m.Y', strtotime($booking['date'])) . ' um ' . $booking['time'] . ' Uhr</p>
        <p><strong>Leistungen:</strong><br>' . $servicesHtml . '</p>
        <p><strong>Gesamtpreis:</strong> ' . number_format($booking['total_price'], 2) . ' €</p>
        <p><a href="http://localhost/admin/?tab=bookings">Zur Buchungsverwaltung</a></p>';
    }
}
