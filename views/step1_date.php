<?php
// views/step1_date.php
$availableDates = $bookingManager->getAvailableDates();
?>

<h2 class="step-title">Wählen Sie Ihr Wunschdatum</h2>
<p class="step-description">Bitte wählen Sie einen verfügbaren Termin aus. Wir sind Montag bis Samstag für Sie da.</p>

<form method="POST" action="?step=1">
    <div class="date-grid">
        <?php foreach ($availableDates as $date):
            $dateObj = new DateTime($date);
            $dayName = $dateObj->format('l');
            $dayNumber = $dateObj->format('d');
            $monthName = $dateObj->format('M');

            // Deutsche Tagesnamen
            $germanDays = [
                'Monday' => 'Montag',
                'Tuesday' => 'Dienstag',
                'Wednesday' => 'Mittwoch',
                'Thursday' => 'Donnerstag',
                'Friday' => 'Freitag',
                'Saturday' => 'Samstag',
                'Sunday' => 'Sonntag'
            ];
            $dayName = $germanDays[$dayName] ?? $dayName;
        ?>
            <div class="date-option" onclick="selectDate('<?= $date ?>', this)">
                <div class="date-day"><?= $dayName ?></div>
                <div class="date-number"><?= $dayNumber ?>. <?= $monthName ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <input type="hidden" name="selected_date" id="selected_date" value="">

    <div class="btn-group">
        <button type="submit" class="btn-primary" id="continue-btn" disabled>Weiter zur Uhrzeitauswahl</button>
    </div>
</form>

<script>
    function selectDate(date, element) {
        // Alle anderen Optionen deselektieren
        document.querySelectorAll('.date-option').forEach(opt => opt.classList.remove('selected'));

        // Aktuelle Option selektieren
        element.classList.add('selected');

        // Hidden field setzen
        document.getElementById('selected_date').value = date;

        // Continue Button aktivieren
        document.getElementById('continue-btn').disabled = false;
    }
</script>