<?php
// views/step2_time.php - Modernisierte Uhrzeitauswahl
$selectedDate = $_SESSION['booking']['date'] ?? '';
$requiredDuration = 60; // Standard 1 Stunde
$availableTimes = $bookingManager->getAvailableTimesForDate($selectedDate, $requiredDuration);

// Wochentag ermitteln
$dayNames = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
$dayOfWeek = $dayNames[date('w', strtotime($selectedDate))];
?>

<h2 class="step-title">Wählen Sie Ihre Wunschzeit</h2>
<p class="step-description">
    Verfügbare Zeiten für <strong><?= $dayOfWeek ?>, den <?= date('d.m.Y', strtotime($selectedDate)) ?></strong>
</p>

<?php if (!empty($availableTimes)): ?>
    <form method="POST" action="?step=2" id="time-form">
        <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
        <?= SecurityManager::generateHoneypot() ?>

        <div class="time-grid">
            <?php foreach ($availableTimes as $time): ?>
                <label class="time-slot" for="time_<?= $time['id'] ?>">
                    <input type="radio"
                        name="appointment_id"
                        id="time_<?= $time['id'] ?>"
                        value="<?= $time['id'] ?>"
                        <?= (isset($_SESSION['booking']['appointment_id']) &&
                            $_SESSION['booking']['appointment_id'] == $time['id']) ? 'checked' : '' ?>>
                    <div class="time-display">
                        <span class="time-value"><?= $time['time'] ?></span>
                        <span class="time-label">Uhr</span>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>

        <!-- Ausgewählte Zeit Anzeige -->
        <div class="summary-section" id="selected-time-display" style="display: none; max-width: 400px; margin: 30px auto;">
            <div class="summary-title">Ihre Auswahl</div>
            <div class="summary-item">
                <span class="summary-label">Datum:</span>
                <span class="summary-value"><?= $dayOfWeek ?>, <?= date('d.m.Y', strtotime($selectedDate)) ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Uhrzeit:</span>
                <span class="summary-value" id="selected-time-value">-</span>
            </div>
        </div>

        <div class="btn-group">
            <a href="?step=1" class="btn-secondary">Zurück zur Datumsauswahl</a>
            <button type="submit" class="btn-primary" id="continue-btn" disabled>Weiter zu Ihren Daten</button>
        </div>
    </form>

    <script>
        // Enable continue button when time is selected
        document.querySelectorAll('input[name="appointment_id"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const continueBtn = document.getElementById('continue-btn');
                const selectedDisplay = document.getElementById('selected-time-display');
                const selectedTimeValue = document.getElementById('selected-time-value');

                continueBtn.disabled = false;

                // Visual feedback
                document.querySelectorAll('.time-slot').forEach(function(slot) {
                    slot.classList.remove('selected');
                });
                this.closest('.time-slot').classList.add('selected');

                // Show selected time
                const timeValue = this.closest('.time-slot').querySelector('.time-value').textContent;
                selectedDisplay.style.display = 'block';
                selectedTimeValue.textContent = timeValue + ' Uhr';

                // Smooth scroll to selection
                selectedDisplay.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
            });
        });

        // Check if already selected on page load
        document.addEventListener('DOMContentLoaded', function() {
            const checkedRadio = document.querySelector('input[name="appointment_id"]:checked');
            if (checkedRadio) {
                checkedRadio.dispatchEvent(new Event('change'));
            }
        });

        // Keyboard navigation
        document.querySelectorAll('.time-slot').forEach(function(slot) {
            slot.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                }
            });

            // Make focusable
            slot.setAttribute('tabindex', '0');
        });
    </script>

<?php else: ?>
    <div class="no-times-available">
        <p><strong>😔 Leider sind für diesen Tag keine Termine verfügbar.</strong></p>
        <p>Mögliche Gründe:</p>
        <ul style="text-align: left; display: inline-block;">
            <li>Alle Termine sind bereits gebucht</li>
            <li>Es ist ein Sonn- oder Feiertag</li>
            <li>Der Tag liegt zu weit in der Zukunft</li>
        </ul>
        <p style="margin-top: 20px;">
            <strong>Was können Sie tun?</strong><br>
            Wählen Sie einen anderen Tag oder kontaktieren Sie uns direkt:
        </p>
        <p>
            <strong>📞 Telefon:</strong> <?= defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '+49 123 456789' ?><br>
            <strong>📧 E-Mail:</strong> <?= defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : 'info@mcs-mobile.de' ?>
        </p>

        <div class="btn-group">
            <a href="?step=1" class="btn-secondary">Anderen Tag wählen</a>
        </div>
    </div>
<?php endif; ?>