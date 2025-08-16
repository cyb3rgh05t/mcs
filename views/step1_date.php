<?php
// views/step1_date.php - Modernisierte Datumsauswahl mit Kalender
$availableDates = $bookingManager->getAvailableDates(90);
?>

<h2 class="step-title">Wählen Sie Ihr Wunschdatum</h2>
<p class="step-description">Bitte wählen Sie einen verfügbaren Termin aus. Wir sind Montag bis Samstag für Sie da.</p>

<form method="POST" action="?step=1" id="date-form">
    <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
    <?= SecurityManager::generateHoneypot() ?>

    <!-- Kalender Container -->
    <div class="calendar-container">
        <div class="calendar-header">
            <button type="button" class="calendar-nav" id="prev-month">‹</button>
            <div class="calendar-title" id="calendar-title">Januar 2025</div>
            <button type="button" class="calendar-nav" id="next-month">›</button>
        </div>

        <div class="calendar-weekdays">
            <div class="calendar-weekday">Mo</div>
            <div class="calendar-weekday">Di</div>
            <div class="calendar-weekday">Mi</div>
            <div class="calendar-weekday">Do</div>
            <div class="calendar-weekday">Fr</div>
            <div class="calendar-weekday">Sa</div>
            <div class="calendar-weekday">So</div>
        </div>

        <div class="calendar-days" id="calendar-days">
            <!-- Wird durch JavaScript gefüllt -->
        </div>
    </div>

    <!-- Ausgewähltes Datum Anzeige -->
    <div class="summary-section" id="selected-date-display" style="display: none; max-width: 400px; margin: 30px auto;">
        <div class="summary-title">Ihre Auswahl</div>
        <div class="summary-item">
            <span class="summary-label">Gewähltes Datum:</span>
            <span class="summary-value" id="selected-date-value"></span>
        </div>
    </div>

    <input type="hidden" name="selected_date" id="selected_date" value="">

    <div class="btn-group">
        <button type="submit" class="btn-primary" id="continue-btn" disabled>Weiter zur Uhrzeitauswahl</button>
    </div>
</form>

<?php if (empty($availableDates)): ?>
    <div class="no-dates-available">
        <p><strong>😔 Aktuell sind keine Termine verfügbar.</strong></p>
        <p>Bitte kontaktieren Sie uns direkt:</p>
        <p>
            <strong>📞 Telefon:</strong> <?= defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '+49 123 456789' ?><br>
            <strong>📧 E-Mail:</strong> <?= defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : 'info@mcs-mobile.de' ?>
        </p>
    </div>
<?php endif; ?>

<style>
    .calendar-container {
        background: rgba(0, 0, 0, 0.6);
        border: 1px solid var(--clr-surface-a30);
        border-radius: var(--radius-sm);
        padding: 24px;
        margin-bottom: 30px;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
        backdrop-filter: blur(10px);
    }

    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .calendar-title {
        font-size: 20px;
        font-weight: 500;
        color: var(--clr-primary-a0);
    }

    .calendar-weekdays {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
        margin-bottom: 10px;
    }

    .calendar-weekday {
        text-align: center;
        font-size: 12px;
        font-weight: 600;
        color: var(--clr-primary-a40);
        text-transform: uppercase;
        padding: 10px 0;
    }

    .calendar-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 8px;
    }

    .calendar-day {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid var(--clr-surface-a30);
        border-radius: var(--radius-xs);
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        position: relative;
        color: var(--clr-primary-a30);
    }

    .calendar-day:hover:not(.disabled):not(.other-month) {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--clr-primary-a0);
        transform: scale(1.05);
    }

    .calendar-day.available {
        color: var(--clr-primary-a0);
        background: rgba(40, 167, 69, 0.1);
        border-color: rgba(40, 167, 69, 0.3);
    }

    .calendar-day.available::after {
        content: '•';
        position: absolute;
        bottom: 5px;
        color: var(--clr-success);
        font-size: 12px;
    }

    .calendar-day.selected {
        background: var(--clr-success);
        border-color: var(--clr-success);
        color: white;
        font-weight: bold;
        box-shadow: 0 0 20px rgba(76, 175, 80, 0.4);
    }

    .calendar-day.disabled {
        opacity: 0.3;
        cursor: not-allowed;
        color: var(--clr-surface-a50);
    }

    .calendar-day.other-month {
        opacity: 0.2;
        cursor: not-allowed;
    }

    .calendar-day.today {
        border: 2px solid var(--clr-primary-a0);
    }
</style>

<script>
    // Verfügbare Termine von PHP
    const availableDates = <?= json_encode($availableDates) ?>;
    const workingDays = <?= json_encode(WORKING_DAYS) ?>; // NEU: Arbeitstage aus Config
    let currentDate = new Date();
    let selectedDate = null;

    const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
        'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
    ];
    const dayNames = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];

    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        // Update title
        document.getElementById('calendar-title').textContent = monthNames[month] + ' ' + year;

        // First day of month
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);

        // Start from Monday
        let startDate = new Date(firstDay);
        const dayOfWeek = firstDay.getDay();
        const daysToSubtract = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
        startDate.setDate(startDate.getDate() - daysToSubtract);

        const calendarDays = document.getElementById('calendar-days');
        calendarDays.innerHTML = '';

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Generate 42 days (6 weeks)
        for (let i = 0; i < 42; i++) {
            const date = new Date(startDate);
            date.setDate(startDate.getDate() + i);

            const dayDiv = document.createElement('div');
            dayDiv.className = 'calendar-day';
            dayDiv.textContent = date.getDate();

            const dateStr = date.toISOString().split('T')[0];

            // NEU: Konvertiere JavaScript Wochentag zu ISO Format
            const jsWeekday = date.getDay(); // 0=So, 1=Mo, 2=Di, 3=Mi, 4=Do, 5=Fr, 6=Sa
            const isoWeekday = jsWeekday === 0 ? 7 : jsWeekday; // 1=Mo, 2=Di, 3=Mi, 4=Do, 5=Fr, 6=Sa, 7=So

            // Check if date is in current month
            if (date.getMonth() !== month) {
                dayDiv.classList.add('other-month');
            } else {
                // Check if today
                if (date.toDateString() === today.toDateString()) {
                    dayDiv.classList.add('today');
                }

                // Check if available
                if (availableDates.includes(dateStr)) {
                    dayDiv.classList.add('available');
                    dayDiv.addEventListener('click', function() {
                        selectDate(date, dateStr, this);
                    });
                    // KORRIGIERT: Prüfe ob vergangen ODER kein Arbeitstag
                } else if (date < today || !workingDays.includes(isoWeekday)) {
                    dayDiv.classList.add('disabled');
                }
            }

            // Check if selected
            if (selectedDate === dateStr) {
                dayDiv.classList.add('selected');
            }

            calendarDays.appendChild(dayDiv);
        }
    }

    function selectDate(date, dateStr, element) {
        // Remove previous selection
        document.querySelectorAll('.calendar-day').forEach(day => {
            day.classList.remove('selected');
        });

        // Add selection
        element.classList.add('selected');
        selectedDate = dateStr;

        // Update hidden input
        document.getElementById('selected_date').value = dateStr;

        // Update display
        const displayDiv = document.getElementById('selected-date-display');
        const displayValue = document.getElementById('selected-date-value');

        const dayName = dayNames[date.getDay()];
        const formattedDate = date.toLocaleDateString('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });

        displayDiv.style.display = 'block';
        displayValue.textContent = `${dayName}, ${formattedDate}`;

        // Enable continue button
        document.getElementById('continue-btn').disabled = false;

        // Smooth scroll
        displayDiv.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }

    // Navigation
    document.getElementById('prev-month').addEventListener('click', function() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    });

    document.getElementById('next-month').addEventListener('click', function() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    });

    // Initial render
    renderCalendar();

    // Check for pre-selected date
    <?php if (isset($_SESSION['booking']['date'])): ?>
        selectedDate = '<?= $_SESSION['booking']['date'] ?>';
        const preselectedDate = new Date('<?= $_SESSION['booking']['date'] ?>');
        currentDate = new Date(preselectedDate.getFullYear(), preselectedDate.getMonth(), 1);
        renderCalendar();
    <?php endif; ?>
</script>