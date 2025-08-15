<?php
// views/step5_summary.php - Buchungsübersicht vor Bestätigung
$selectedServices = [];
if (!empty($_SESSION['booking']['services'])) {
    $serviceIds = $_SESSION['booking']['services'];
    $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
    $stmt = $database->getConnection()->prepare("SELECT * FROM services WHERE id IN ($placeholders)");
    $stmt->execute($serviceIds);
    $selectedServices = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$appointment = $database->getConnection()->prepare("SELECT * FROM appointments WHERE id = ?");
$appointment->execute([$_SESSION['booking']['appointment_id']]);
$appointmentData = $appointment->fetch(PDO::FETCH_ASSOC);
?>

<h2 class="step-title">Buchungsübersicht</h2>
<p class="step-description">Bitte prüfen Sie Ihre Angaben vor der finalen Buchung.</p>

<div class="summary-section">
    <div class="summary-title">📅 Termindetails</div>
    <div class="summary-item">
        <span class="summary-label">Datum:</span>
        <span class="summary-value"><?= date('d.m.Y', strtotime($appointmentData['date'])) ?></span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Uhrzeit:</span>
        <span class="summary-value"><?= $appointmentData['time'] ?> Uhr</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Wochentag:</span>
        <span class="summary-value"><?= date('l', strtotime($appointmentData['date'])) ?></span>
    </div>
</div>

<div class="summary-section">
    <div class="summary-title">🚗 Gebuchte Leistungen</div>
    <?php
    $servicesTotal = 0;
    foreach ($selectedServices as $service):
        $servicesTotal += $service['price'];
    ?>
        <div class="summary-item">
            <span class="summary-label">
                <?= htmlspecialchars($service['name']) ?>
                <small style="display: block; color: #999; font-size: 12px;">
                    <?= htmlspecialchars($service['description']) ?>
                </small>
            </span>
            <span class="summary-value"><?= number_format($service['price'], 2, ',', '.') ?> €</span>
        </div>
    <?php endforeach; ?>

    <div class="summary-item" style="border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 10px; margin-top: 10px;">
        <span class="summary-label"><strong>Leistungen gesamt:</strong></span>
        <span class="summary-value"><strong><?= number_format($servicesTotal, 2, ',', '.') ?> €</strong></span>
    </div>
</div>

<div class="summary-section">
    <div class="summary-title">👤 Kundendaten</div>
    <div class="summary-item">
        <span class="summary-label">Name:</span>
        <span class="summary-value"><?= htmlspecialchars($_SESSION['booking']['customer']['name']) ?></span>
    </div>
    <div class="summary-item">
        <span class="summary-label">E-Mail:</span>
        <span class="summary-value"><?= htmlspecialchars($_SESSION['booking']['customer']['email']) ?></span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Telefon:</span>
        <span class="summary-value"><?= htmlspecialchars($_SESSION['booking']['customer']['phone']) ?></span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Adresse:</span>
        <span class="summary-value"><?= htmlspecialchars($_SESSION['booking']['customer']['address']) ?></span>
    </div>
</div>

<div class="summary-section">
    <div class="summary-title">💰 Kostenaufstellung</div>
    <div class="summary-item">
        <span class="summary-label">Leistungen:</span>
        <span class="summary-value"><?= number_format($servicesTotal, 2, ',', '.') ?> €</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Anfahrt (<?= $_SESSION['booking']['distance'] ?> km à <?= number_format(defined('TRAVEL_COST_PER_KM') ? TRAVEL_COST_PER_KM : 0.50, 2, ',', '.') ?>€):</span>
        <span class="summary-value"><?= number_format($_SESSION['booking']['distance'] * (defined('TRAVEL_COST_PER_KM') ? TRAVEL_COST_PER_KM : 0.50), 2, ',', '.') ?> €</span>
    </div>
    <div class="summary-item" style="border-top: 3px solid #ff6b35; padding-top: 15px; margin-top: 15px; background: rgba(255, 107, 53, 0.1); padding: 15px; border-radius: 8px;">
        <span class="summary-label" style="font-size: 20px; font-weight: bold;">Gesamtpreis (inkl. Anfahrt):</span>
        <span class="summary-value total-price" style="font-size: 24px;"><?= number_format($_SESSION['booking']['total_price'], 2, ',', '.') ?> €</span>
    </div>
</div>

<div style="background: rgba(255, 107, 53, 0.1); border: 1px solid #ff6b35; border-radius: 10px; padding: 20px; margin: 20px 0;">
    <h3 style="color: #ff6b35; margin-bottom: 10px;">💳 Zahlungsinformationen</h3>
    <p style="margin-bottom: 10px;">• Zahlung erfolgt bequem vor Ort</p>
    <p style="margin-bottom: 10px;">• Wir akzeptieren Bargeld und Kartenzahlung</p>
    <p style="margin-bottom: 0;">• Sie erhalten eine ordnungsgemäße Rechnung</p>
</div>

<form method="POST" action="?step=5" id="confirmation-form">
    <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
    <?= SecurityManager::generateHoneypot() ?>

    <div class="form-group">
        <label class="checkbox-container">
            <input type="checkbox" id="terms" name="terms" required>
            <span class="checkmark"></span>
            Ich bestätige, dass alle Angaben korrekt sind und akzeptiere die <a href="#" target="_blank" style="color: #ff6b35;">AGB</a>. *
        </label>
    </div>

    <div class="btn-group">
        <a href="?step=4" class="btn-secondary">Zurück</a>
        <button type="submit" class="btn-primary" id="final-booking-btn" disabled>
            🎯 Verbindlich buchen
        </button>
    </div>
</form>

<script>
    document.getElementById('terms').addEventListener('change', function() {
        document.getElementById('final-booking-btn').disabled = !this.checked;
    });

    // Bestätigungsdialog vor finaler Buchung
    document.getElementById('confirmation-form').addEventListener('submit', function(e) {
        if (!confirm('Möchten Sie die Buchung wirklich abschließen? Nach der Bestätigung ist die Buchung verbindlich.')) {
            e.preventDefault();
        }
    });
</script>