<?php
// views/step2_time.php - Uhrzeitauswahl mit Validierung
$selectedDate = $_SESSION['booking']['date'] ?? '';
$availableTimes = $bookingManager->getAvailableTimesForDate($selectedDate);
?>

<h2 class="step-title">Wählen Sie Ihre Wunschzeit</h2>
<p class="step-description">Verfügbare Zeiten für den <?= date('d.m.Y', strtotime($selectedDate)) ?></p>

<form method="POST" action="?step=2" id="time-form">
    <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
    <?= SecurityManager::generateHoneypot() ?>

    <div class="time-grid">
        <?php foreach ($availableTimes as $time): ?>
            <div class="time-option" onclick="selectTime(<?= $time['id'] ?>, '<?= $time['time'] ?>', this)" role="button" tabindex="0">
                <?= $time['time'] ?> Uhr
            </div>
        <?php endforeach; ?>

        <?php if (empty($availableTimes)): ?>
            <div class="no-times-available">
                <p>Für diesen Tag sind keine Termine mehr verfügbar.</p>
                <a href="?step=1" class="btn-secondary">Anderes Datum wählen</a>
            </div>
        <?php endif; ?>
    </div>

    <input type="hidden" name="appointment_id" id="appointment_id" value="">
    <input type="hidden" name="selected_time" id="selected_time" value="">

    <div class="btn-group">
        <a href="?step=1" class="btn-secondary">Zurück</a>
        <button type="submit" class="btn-primary" id="continue-btn" disabled>Weiter zur Leistungsauswahl</button>
    </div>
</form>

<script>
    function selectTime(appointmentId, time, element) {
        // Alle anderen Optionen deselektieren
        document.querySelectorAll('.time-option').forEach(opt => opt.classList.remove('selected'));

        // Aktuelle Option selektieren
        element.classList.add('selected');

        // Hidden fields setzen
        document.getElementById('appointment_id').value = appointmentId;
        document.getElementById('selected_time').value = time;

        // Continue Button aktivieren
        document.getElementById('continue-btn').disabled = false;
    }
</script>