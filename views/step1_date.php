<?php
// views/step1_date.php - Datumsauswahl mit Sicherheit
$availableDates = $bookingManager->getAvailableDates();
?>

<h2 class="step-title">Wählen Sie Ihr Wunschdatum</h2>
<p class="step-description">Bitte wählen Sie einen verfügbaren Termin aus. Wir sind Montag bis Samstag für Sie da.</p>

<form method="POST" action="?step=1" id="date-form">
    <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
    <?= SecurityManager::generateHoneypot() ?>

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

            $germanMonths = [
                'Jan' => 'Jan',
                'Feb' => 'Feb',
                'Mar' => 'Mär',
                'Apr' => 'Apr',
                'May' => 'Mai',
                'Jun' => 'Jun',
                'Jul' => 'Jul',
                'Aug' => 'Aug',
                'Sep' => 'Sep',
                'Oct' => 'Okt',
                'Nov' => 'Nov',
                'Dec' => 'Dez'
            ];
            $monthName = $germanMonths[$monthName] ?? $monthName;
        ?>
            <div class="date-option" onclick="selectDate('<?= $date ?>', this)" role="button" tabindex="0">
                <div class="date-day"><?= $dayName ?></div>
                <div class="date-number"><?= $dayNumber ?>. <?= $monthName ?></div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($availableDates)): ?>
            <div class="no-dates-available">
                <p>Aktuell sind keine Termine verfügbar. Bitte kontaktieren Sie uns direkt.</p>
                <p><strong>Telefon:</strong> <?= defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '+49 123 456789' ?></p>
            </div>
        <?php endif; ?>
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