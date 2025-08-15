<?php
// views/step5_summary.php
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
    <div class="summary-title">Termin</div>
    <div class="summary-item">
        <span class="summary-label">Datum:</span>
        <span class="summary-value"><?= date('d.m.Y', strtotime($appointmentData['date'])) ?></span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Uhrzeit:</span>
        <span class="summary-value"><?= $appointmentData['time'] ?> Uhr</span>
    </div>
</div>

<div class="summary-section">
    <div class="summary-title">Leistungen</div>
    <?php
    $servicesTotal = 0;
    foreach ($selectedServices as $service):
        $servicesTotal += $service['price'];
    ?>
        <div class="summary-item">
            <span class="summary-label"><?= htmlspecialchars($service['name']) ?></span>
            <span class="summary-value"><?= number_format($service['price'], 2) ?> €</span>
        </div>
    <?php endforeach; ?>
</div>

<div class="summary-section">
    <div class="summary-title">Kundendaten</div>
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
    <div class="summary-title">Kostenaufstellung</div>
    <div class="summary-item">
        <span class="summary-label">Leistungen:</span>
        <span class="summary-value"><?= number_format($servicesTotal, 2) ?> €</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Anfahrt (<?= $_SESSION['booking']['distance'] ?> km à 0,50€):</span>
        <span class="summary-value"><?= number_format($_SESSION['booking']['distance'] * 0.50, 2) ?> €</span>
    </div>
    <div class="summary-item" style="border-top: 2px solid #ff6b35; padding-top: 15px; margin-top: 15px;">
        <span class="summary-label" style="font-size: 18px; font-weight: bold;">Gesamtpreis:</span>
        <span class="summary-value total-price"><?= number_format($_SESSION['booking']['total_price'], 2) ?> €</span>
    </div>
</div>

<form method="POST" action="?step=5">
    <div class="btn-group">
        <a href="?step=4" class="btn-secondary">Zurück</a>
        <button type="submit" class="btn-primary">Verbindlich buchen</button>
    </div>
</form>