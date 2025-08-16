<?php
// views/step5_summary.php - Modernisierte Buchungsübersicht
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

// Wochentag
$days = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
$dayOfWeek = $days[date('w', strtotime($appointmentData['date']))];
?>

<h2 class="step-title">Buchungsübersicht</h2>
<p class="step-description">Bitte prüfen Sie Ihre Angaben vor der finalen Buchung.</p>

<div class="summary-container">
    <!-- Termindetails -->
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
            <span class="summary-value"><?= $dayOfWeek ?></span>
        </div>
    </div>

    <!-- Kundendaten -->
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
        <?php if (!empty($_SESSION['booking']['customer']['notes'])): ?>
            <div class="summary-item">
                <span class="summary-label">Hinweise:</span>
                <span class="summary-value"><?= htmlspecialchars($_SESSION['booking']['customer']['notes']) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Anfahrt -->
    <div class="summary-section">
        <div class="summary-title">🚗 Anfahrt</div>
        <div class="summary-item">
            <span class="summary-label">Entfernung:</span>
            <span class="summary-value"><?= number_format($distance, 1, ',', '.') ?> km</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Anfahrtskosten:</span>
            <span class="summary-value">
                <?php if ($travelCost == 0): ?>
                    <span class="text-success">Kostenlos</span>
                <?php else: ?>
                    <?= number_format($travelCost, 2, ',', '.') ?> €
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- Gebuchte Leistungen -->
    <div class="summary-section">
        <div class="summary-title">🔧 Gebuchte Leistungen</div>
        <?php
        $totalDuration = 0;
        foreach ($selectedServices as $service):
            $totalDuration += $service['duration'];
        ?>
            <div class="summary-item">
                <span class="summary-label">
                    <?= htmlspecialchars($service['name']) ?>
                    <small class="text-muted">(<?= $service['duration'] ?> Min.)</small>
                </span>
                <span class="summary-value"><?= number_format($service['price'], 2, ',', '.') ?> €</span>
            </div>
        <?php endforeach; ?>

        <div class="summary-item" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--clr-surface-a30);">
            <span class="summary-label">Gesamtdauer:</span>
            <span class="summary-value">ca. <?= $totalDuration ?> Minuten</span>
        </div>
    </div>

    <!-- Gesamtkosten -->
    <div class="summary-section total-section">
        <div class="summary-title">💰 Gesamtkosten</div>
        <div class="summary-item">
            <span class="summary-label">Leistungen:</span>
            <span class="summary-value"><?= number_format($servicesTotal, 2, ',', '.') ?> €</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Anfahrt:</span>
            <span class="summary-value"><?= number_format($travelCost, 2, ',', '.') ?> €</span>
        </div>
        <div class="summary-item total-row">
            <span class="summary-label">Gesamtpreis:</span>
            <span class="summary-value total-price"><?= number_format($totalPrice, 2, ',', '.') ?> €</span>
        </div>
    </div>

    <!-- Hinweise -->
    <div class="info-box">
        <h4>ℹ️ Wichtige Hinweise</h4>
        <ul>
            <li>Die Zahlung erfolgt nach Abschluss der Arbeiten vor Ort</li>
            <li>Wir akzeptieren Barzahlung und EC-Karte</li>
            <li>Sie erhalten eine Bestätigungsmail mit allen Details</li>
            <li>Stornierungen sind bis 24 Stunden vorher kostenfrei möglich</li>
        </ul>
    </div>
</div>

<form method="POST" action="?step=5">
    <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
    <?= SecurityManager::generateHoneypot() ?>

    <div class="form-group" style="max-width: 600px; margin: 30px auto;">
        <label class="checkbox-label">
            <input type="checkbox" name="accept_terms" id="accept_terms" required>
            <span>Ich habe die Angaben überprüft und akzeptiere die
                <a href="/agb" target="_blank" style="color: var(--clr-primary-a0);">AGB</a> sowie die
                <a href="/datenschutz" target="_blank" style="color: var(--clr-primary-a0);">Datenschutzerklärung</a>
            </span>
        </label>
    </div>

    <div class="btn-group">
        <a href="?step=4" class="btn-secondary">Zurück zu den Leistungen</a>
        <button type="submit" class="btn-primary btn-confirm" id="confirm-btn" disabled>
            Verbindlich buchen
        </button>
    </div>
</form>

<style>
    .summary-container {
        display: grid;
        gap: 20px;
        margin-bottom: 30px;
    }

    .total-section {
        background: rgba(76, 175, 80, 0.05);
        border: 2px solid var(--clr-success);
    }

    .total-row {
        font-size: 20px;
        font-weight: 600;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 2px solid var(--clr-success);
    }

    .info-box {
        background: rgba(33, 150, 243, 0.05);
        border: 1px solid var(--clr-info);
        border-radius: var(--radius-sm);
        padding: 20px;
        backdrop-filter: blur(10px);
    }

    .info-box h4 {
        color: var(--clr-info);
        margin-bottom: 15px;
        font-size: 16px;
    }

    .info-box ul {
        list-style: none;
        padding: 0;
    }

    .info-box li {
        padding: 8px 0;
        padding-left: 20px;
        position: relative;
        color: var(--clr-primary-a30);
    }

    .info-box li::before {
        content: '✓';
        position: absolute;
        left: 0;
        color: var(--clr-info);
    }

    .checkbox-label {
        display: flex;
        align-items: flex-start;
        cursor: pointer;
        color: var(--clr-primary-a30);
        line-height: 1.5;
    }

    .checkbox-label input[type="checkbox"] {
        margin-right: 10px;
        margin-top: 3px;
        cursor: pointer;
        width: 20px;
        height: 20px;
    }

    .btn-confirm {
        background: var(--clr-success);
        font-weight: 600;
        font-size: 16px;
        padding: 14px 30px;
    }

    .btn-confirm:hover:not(:disabled) {
        background: #45a049;
        transform: translateY(-2px);
    }

    .btn-confirm:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .text-success {
        color: var(--clr-success);
    }

    .text-muted {
        color: var(--clr-primary-a50);
        font-size: 12px;
    }

    @media (min-width: 768px) {
        .summary-container {
            grid-template-columns: 1fr 1fr;
        }

        .total-section {
            grid-column: span 2;
        }

        .info-box {
            grid-column: span 2;
        }
    }
</style>

<script>
    // Enable button when terms accepted
    document.getElementById('accept_terms').addEventListener('change', function() {
        document.getElementById('confirm-btn').disabled = !this.checked;
    });

    // Confirmation before booking
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!confirm('Möchten Sie diese Buchung wirklich verbindlich abschließen?')) {
            e.preventDefault();
            return false;
        }

        // Show loading state
        const btn = document.getElementById('confirm-btn');
        btn.classList.add('loading');
        btn.textContent = 'Buchung wird verarbeitet...';
    });
</script>