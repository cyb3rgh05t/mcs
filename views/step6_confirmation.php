<?php
// views/step6_confirmation.php
$bookingDetails = $bookingManager->getBookingDetails($_SESSION['booking']['id']);
?>

<div class="confirmation-icon">
    <div class="checkmark">✓</div>
    <h2 class="step-title">Buchung bestätigt!</h2>
</div>

<div class="confirmation-message">
    <p>Vielen Dank für Ihre Buchung bei MCS Mobile Car Solutions!</p>
    <p>Wir haben Ihre Buchung erhalten und werden Sie kurz vor dem Termin kontaktieren.</p>
</div>

<div class="booking-number">
    <strong>Buchungsnummer: #<?= str_pad($bookingDetails['id'], 6, '0', STR_PAD_LEFT) ?></strong>
</div>

<div class="summary-section">
    <div class="summary-title">Ihre Buchungsdetails</div>
    <div class="summary-item">
        <span class="summary-label">Termin:</span>
        <span class="summary-value"><?= date('d.m.Y', strtotime($bookingDetails['date'])) ?> um <?= $bookingDetails['time'] ?> Uhr</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Adresse:</span>
        <span class="summary-value"><?= htmlspecialchars($bookingDetails['customer_address']) ?></span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Gesamtpreis:</span>
        <span class="summary-value total-price"><?= number_format($bookingDetails['total_price'], 2) ?> €</span>
    </div>
</div>

<div class="summary-section">
    <div class="summary-title">Gebuchte Leistungen</div>
    <?php foreach ($bookingDetails['services'] as $service): ?>
        <div class="summary-item">
            <span class="summary-label"><?= htmlspecialchars($service['name']) ?></span>
            <span class="summary-value"><?= number_format($service['price'], 2) ?> €</span>
        </div>
    <?php endforeach; ?>
</div>

<div style="background: rgba(255, 107, 53, 0.1); border: 1px solid #ff6b35; border-radius: 10px; padding: 20px; margin: 20px 0; text-align: center;">
    <h3 style="color: #ff6b35; margin-bottom: 10px;">Was passiert als nächstes?</h3>
    <p style="margin-bottom: 10px;">• Sie erhalten eine Bestätigungs-E-Mail mit allen Details</p>
    <p style="margin-bottom: 10px;">• Wir rufen Sie ca. 30 Minuten vor dem Termin an</p>
    <p style="margin-bottom: 0;">• Zahlung erfolgt bequem vor Ort (Bar oder Karte)</p>
</div>

<div class="btn-group">
    <a href="/" class="btn-primary">Zur Startseite</a>
    <a href="?step=1" class="btn-secondary">Neuen Termin buchen</a>
</div>

<?php
// Session nach erfolgreichem Abschluss leeren
unset($_SESSION['booking']);
?>