<?php
// views/step2_time.php - Uhrzeitauswahl (ohne Service-Abhängigkeit in neuem Flow)
$selectedDate = $_SESSION['booking']['date'] ?? '';

// Im neuen Flow haben wir noch keine Services ausgewählt, also Standard-Dauer
$requiredDuration = 60; // Standard 1 Stunde

// Hole verfügbare Zeiten für das Datum
$availableTimes = $bookingManager->getAvailableTimesForDate($selectedDate, $requiredDuration);
?>

<h2 class="step-title">Wählen Sie Ihre Wunschzeit</h2>
<p class="step-description">Verfügbare Zeiten für den <?= date('d.m.Y', strtotime($selectedDate)) ?></p>

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

        <div class="btn-group">
            <a href="?step=1" class="btn-secondary">Zurück</a>
            <button type="submit" class="btn-primary" id="continue-btn" disabled>Weiter zu Ihren Daten</button>
        </div>
    </form>

    <script>
        // Enable continue button when time is selected
        document.querySelectorAll('input[name="appointment_id"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                document.getElementById('continue-btn').disabled = false;

                // Visual feedback
                document.querySelectorAll('.time-slot').forEach(function(slot) {
                    slot.classList.remove('selected');
                });
                this.closest('.time-slot').classList.add('selected');
            });
        });

        // Check if already selected
        const checkedRadio = document.querySelector('input[name="appointment_id"]:checked');
        if (checkedRadio) {
            document.getElementById('continue-btn').disabled = false;
            checkedRadio.closest('.time-slot').classList.add('selected');
        }
    </script>

<?php else: ?>
    <div class="no-times-available">
        <p>😔 Leider sind für dieses Datum keine Termine verfügbar.</p>
        <p>Bitte wählen Sie ein anderes Datum oder kontaktieren Sie uns direkt.</p>
        <div class="btn-group">
            <a href="?step=1" class="btn-secondary">Zurück zur Datumsauswahl</a>
        </div>
    </div>
<?php endif; ?>

<style>
    .time-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 15px;
        margin: 30px 0;
    }

    .time-slot {
        position: relative;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .time-slot input[type="radio"] {
        position: absolute;
        opacity: 0;
    }

    .time-display {
        background: rgba(30, 30, 30, 0.9);
        border: 2px solid #4e4e4e;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .time-slot:hover .time-display {
        border-color: #ff6b35;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
    }

    .time-slot.selected .time-display,
    .time-slot input[type="radio"]:checked+.time-display {
        background: linear-gradient(135deg, #ff6b35, #ff8c42);
        border-color: #ff6b35;
        color: white;
    }

    .time-value {
        display: block;
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .time-label {
        display: block;
        font-size: 14px;
        opacity: 0.8;
    }

    .no-times-available {
        background: rgba(255, 193, 7, 0.1);
        border: 1px solid #ffc107;
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        color: #ffc107;
    }

    .no-times-available p {
        margin: 15px 0;
        font-size: 18px;
    }

    @media (max-width: 768px) {
        .time-grid {
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        }
    }
</style>