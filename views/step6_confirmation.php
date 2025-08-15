<?php
// views/step6_confirmation.php - Bestätigung nach erfolgreicher Buchung
$bookingDetails = $bookingManager->getBookingDetails($_SESSION['booking']['id']);
?>

<div class="confirmation-icon">
    <div class="checkmark">🎉</div>
    <h2 class="step-title" style="color: #28a745;">Buchung erfolgreich!</h2>
</div>

<div class="confirmation-message">
    <p style="font-size: 18px; text-align: center; margin-bottom: 20px;">
        <strong>Vielen Dank für Ihre Buchung bei <?= defined('BUSINESS_NAME') ? BUSINESS_NAME : 'MCS Mobile Car Solutions' ?>!</strong>
    </p>
    <p style="text-align: center;">
        Wir haben Ihre Buchung erhalten und eine Bestätigungs-E-Mail an Sie gesendet.<br>
        Unser Team wird sich kurz vor dem Termin bei Ihnen melden.
    </p>
</div>

<div style="background: rgba(30, 30, 30, 0.9); color: white; padding: 20px; border-radius: 6px; display: inline-block;">
    <div style="font-size: 14px; opacity: 0.9;">Ihre Buchungsnummer</div>
    <div style="font-size: 28px; font-weight: bold; margin: 5px 0;">#<?= str_pad($bookingDetails['id'], 6, '0', STR_PAD_LEFT) ?></div>
    <div style="font-size: 12px; opacity: 0.8;">Bitte notieren Sie sich diese Nummer</div>
</div>

<div class="summary-section">
    <div class="summary-title">📋 Ihre Buchungsdetails</div>
    <div class="summary-item">
        <span class="summary-label">Termin:</span>
        <span class="summary-value"><?= date('d.m.Y', strtotime($bookingDetails['date'])) ?> um <?= $bookingDetails['time'] ?> Uhr</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Adresse:</span>
        <span class="summary-value"><?= htmlspecialchars($bookingDetails['customer_address']) ?></span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Kontakt:</span>
        <span class="summary-value"><?= htmlspecialchars($bookingDetails['customer_phone']) ?></span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Gesamtpreis:</span>
        <span class="summary-value total-price"><?= number_format($bookingDetails['total_price'], 2, ',', '.') ?> €</span>
    </div>
</div>

<div class="summary-section">
    <div class="summary-title">🚗 Gebuchte Leistungen</div>
    <?php foreach ($bookingDetails['services'] as $service): ?>
        <div class="summary-item">
            <span class="summary-label"><?= htmlspecialchars($service['name']) ?></span>
            <span class="summary-value"><?= number_format($service['price'], 2, ',', '.') ?> €</span>
        </div>
    <?php endforeach; ?>
</div>

<div style="background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%); border: 1px solid #c3e6cb; border-radius: 15px; padding: 25px; margin: 30px 0; text-align: center;">
    <h3 style="color: #155724; margin-bottom: 15px;">✅ Was passiert als nächstes?</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
        <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
            <div style="font-weight: bold; color: #155724;">1. Bestätigungs-E-Mail</div>
            <div style="font-size: 14px; color: #666;">Sie erhalten eine E-Mail mit allen Details</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
            <div style="font-weight: bold; color: #155724;">2. Anruf vor Termin</div>
            <div style="font-size: 14px; color: #666;">Wir rufen Sie 30 Min. vorher an</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
            <div style="font-weight: bold; color: #155724;">3. Service vor Ort</div>
            <div style="font-size: 14px; color: #666;">Professionelle Fahrzeugpflege</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
            <div style="font-weight: bold; color: #155724;">4. Zahlung & Rechnung</div>
            <div style="font-size: 14px; color: #666;">Bequem vor Ort bezahlen</div>
        </div>
    </div>
</div>

<div style="background: rgba(255, 255, 255, 0.05); border: 1px solid #4e4e4e; border-radius: 4px; padding: 20px; margin: 20px 0; text-align: center;">
    <h3 style="color: #ffffff; margin-bottom: 15px;">📞 Haben Sie Fragen?</h3>
    <p style="margin-bottom: 10px;">
        <strong>Telefon:</strong>
        <a href="tel:<?= defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '+49123456789' ?>" style="color: #ffffff; text-decoration: none;">
            <?= defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '+49 123 456789' ?>
        </a>
    </p>
    <p style="margin-bottom: 10px;">
        <strong>E-Mail:</strong>
        <a href="mailto:<?= defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : 'info@mcs-mobile.de' ?>" style="color: #ffffff; text-decoration: none;">
            <?= defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : 'info@mcs-mobile.de' ?>
        </a>
    </p>
    <p style="margin-bottom: 0;">
        <strong>Buchungsnummer bei Rückfragen:</strong> #<?= str_pad($bookingDetails['id'], 6, '0', STR_PAD_LEFT) ?>
    </p>
</div>

<div class="btn-group" style="margin-top: 40px;">
    <a href="/" class="btn-primary" style="background: #28a745;">🏠 Zur Startseite</a>
    <a href="?step=1" class="btn-secondary">📅 Neuen Termin buchen</a>
</div>

<script>
    // Session nach erfolgreichem Abschluss leeren (wird vom PHP gemacht)
    console.log('✅ Buchung erfolgreich abgeschlossen!');

    // Optional: Tracking/Analytics Code hier einfügen
    // gtag('event', 'booking_completed', { booking_id: '<?= $bookingDetails['id'] ?>' });
</script>

<?php
// Session nach erfolgreichem Abschluss leeren
unset($_SESSION['booking']);
?>