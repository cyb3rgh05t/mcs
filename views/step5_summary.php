<?php
// views/step5_summary.php - Buchungsübersicht mit neuer Anfahrtskostenlogik
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

// Berechne Kosten
$servicesTotal = $_SESSION['booking']['services_total'] ?? 0;
$distance = $_SESSION['booking']['distance'] ?? 0;
$travelCost = $_SESSION['booking']['travel_cost'] ?? 0;
$totalPrice = $_SESSION['booking']['total_price'] ?? 0;
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
        <span class="summary-value">
            <?php
            $days = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
            echo $days[date('w', strtotime($appointmentData['date']))];
            ?>
        </span>
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
    <div class="summary-item">
        <span class="summary-label">Entfernung:</span>
        <span class="summary-value"><?= number_format($distance, 1, ',', '.') ?> km</span>
    </div>
    <?php if (!empty($_SESSION['booking']['customer']['notes'])): ?>
        <div class="summary-item">
            <span class="summary-label">Anmerkungen:</span>
            <span class="summary-value"><?= htmlspecialchars($_SESSION['booking']['customer']['notes']) ?></span>
        </div>
    <?php endif; ?>
</div>

<div class="summary-section">
    <div class="summary-title">🚗 Gebuchte Leistungen</div>
    <?php
    foreach ($selectedServices as $service):
    ?>
        <div class="summary-item">
            <span class="summary-label">
                <?= htmlspecialchars($service['name']) ?>
                <small style="display: block; color: #999; font-size: 12px;">
                    <?= htmlspecialchars($service['description']) ?>
                    <br>Dauer: ca. <?= $service['duration'] ?> Minuten
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
    <div class="summary-title">💰 Kostenaufstellung</div>
    <div class="summary-item">
        <span class="summary-label">Leistungen:</span>
        <span class="summary-value"><?= number_format($servicesTotal, 2, ',', '.') ?> €</span>
    </div>

    <div class="summary-item">
        <span class="summary-label">
            Anfahrt:
            <?php
            // Erkläre die Anfahrtskostenberechnung
            if ($travelCost == 0) {
                if ($servicesTotal < TRAVEL_MIN_SERVICE_AMOUNT) {
                    echo '<small style="display: block; color: #28a745;">Kostenlos bei Leistungen unter ' .
                        number_format(TRAVEL_MIN_SERVICE_AMOUNT, 2, ',', '.') . '€ (bis ' .
                        TRAVEL_MAX_DISTANCE_SMALL . ' km)</small>';
                } else {
                    echo '<small style="display: block; color: #28a745;">Kostenlos (unter ' .
                        TRAVEL_FREE_KM . ' km)</small>';
                }
            } else {
                $chargeableDistance = $distance - TRAVEL_FREE_KM;
                echo '<small style="display: block; color: #999;">' .
                    number_format($distance, 1, ',', '.') . ' km - ' .
                    TRAVEL_FREE_KM . ' km gratis = ' .
                    number_format($chargeableDistance, 1, ',', '.') . ' km × ' .
                    number_format(TRAVEL_COST_PER_KM, 2, ',', '.') . '€</small>';
            }
            ?>
        </span>
        <span class="summary-value"><?= number_format($travelCost, 2, ',', '.') ?> €</span>
    </div>

    <div class="summary-item" style="border-top: 3px solid #ff6b35; padding-top: 15px; margin-top: 15px; background: rgba(255, 107, 53, 0.1); padding: 15px; border-radius: 8px;">
        <span class="summary-label" style="font-size: 20px; font-weight: bold;">Gesamtpreis:</span>
        <span class="summary-value total-price" style="font-size: 24px; color: #ff6b35;"><?= number_format($totalPrice, 2, ',', '.') ?> €</span>
    </div>
</div>

<!-- Zusätzliche Informationen -->
<div style="background: rgba(255, 255, 255, 0.05); border: 1px solid #4e4e4e; border-radius: 8px; padding: 20px; margin: 20px 0;">
    <h3 style="color: #ff6b35; margin-bottom: 15px;">💳 Zahlungsinformationen</h3>
    <ul style="margin: 0; padding-left: 20px; color: #ccc;">
        <li style="margin-bottom: 10px;">Zahlung erfolgt bequem vor Ort</li>
        <li style="margin-bottom: 10px;">Wir akzeptieren Bargeld und Kartenzahlung</li>
        <li style="margin-bottom: 10px;">Sie erhalten eine ordnungsgemäße Rechnung</li>
        <li>Bei Fragen erreichen Sie uns unter <?= defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '+49 123 456789' ?></li>
    </ul>
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

    <div class="btn-group" style="margin-top: 30px;">
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