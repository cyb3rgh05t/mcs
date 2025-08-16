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
    // WICHTIG: Wir verwenden einen eigenen Namespace um Konflikte zu vermeiden
    (function() {
        // Verfügbare Termine von PHP
        const availableDates = <?= json_encode($availableDates) ?>;
        const workingDays = <?= json_encode(WORKING_DAYS) ?>;
        let currentDate = new Date();
        let selectedDate = null;

        const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
            'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
        ];
        const dayNames = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];

        function renderCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();

            document.getElementById('calendar-title').textContent = monthNames[month] + ' ' + year;

            const firstDay = new Date(year, month, 1);
            let startDate = new Date(firstDay);
            let dayOfWeek = firstDay.getDay();

            if (dayOfWeek === 0) {
                startDate.setDate(firstDay.getDate() - 6);
            } else {
                startDate.setDate(firstDay.getDate() - (dayOfWeek - 1));
            }

            const calendarDays = document.getElementById('calendar-days');
            calendarDays.innerHTML = '';

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            for (let i = 0; i < 42; i++) {
                const date = new Date(startDate);
                date.setDate(startDate.getDate() + i);

                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const dateStr = `${year}-${month}-${day}`;

                const dayDiv = document.createElement('div');
                dayDiv.className = 'calendar-day';
                dayDiv.textContent = date.getDate();

                const jsWeekday = date.getDay();
                const isoWeekday = jsWeekday === 0 ? 7 : jsWeekday;

                if (date.getMonth() !== currentDate.getMonth()) {
                    dayDiv.classList.add('other-month');
                } else {
                    if (date.toDateString() === today.toDateString()) {
                        dayDiv.classList.add('today');
                    }

                    if (availableDates.includes(dateStr) && date >= today && workingDays.includes(isoWeekday)) {
                        dayDiv.classList.add('available');
                        dayDiv.setAttribute('data-date', dateStr);
                        dayDiv.setAttribute('data-day-name', dayNames[date.getDay()]);
                        dayDiv.setAttribute('data-formatted', `${String(date.getDate()).padStart(2, '0')}.${String(date.getMonth() + 1).padStart(2, '0')}.${date.getFullYear()}`);
                    } else if (date < today || !workingDays.includes(isoWeekday)) {
                        dayDiv.classList.add('disabled');
                    }
                }

                if (selectedDate === dateStr) {
                    dayDiv.classList.add('selected');
                }

                calendarDays.appendChild(dayDiv);
            }
        }

        // UMBENANNT: calendarSelectDate statt selectDate
        function calendarSelectDate(element, dateStr) {
            if (!element || !element.classList) return;

            if (element.classList.contains('disabled') || element.classList.contains('other-month')) return;

            // Toggle
            if (element.classList.contains('selected')) {
                element.classList.remove('selected');
                selectedDate = null;
                document.getElementById('selected_date').value = '';
                document.getElementById('selected-date-display').style.display = 'none';
                document.getElementById('continue-btn').disabled = true;
                return;
            }

            // Remove all previous selections
            document.querySelectorAll('.calendar-day.selected').forEach(day => {
                day.classList.remove('selected');
            });

            element.classList.add('selected');
            selectedDate = dateStr;

            document.getElementById('selected_date').value = dateStr;

            const displayDiv = document.getElementById('selected-date-display');
            const displayValue = document.getElementById('selected-date-value');

            const dayName = element.getAttribute('data-day-name');
            const formattedDate = element.getAttribute('data-formatted');

            displayDiv.style.display = 'block';
            displayValue.textContent = `${dayName}, ${formattedDate}`;

            document.getElementById('continue-btn').disabled = false;

            displayDiv.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('calendar-days').addEventListener('click', function(e) {
                let target = e.target;

                if (!target.classList.contains('calendar-day')) {
                    target = target.closest('.calendar-day');
                }

                if (target && target.classList.contains('available')) {
                    const dateStr = target.getAttribute('data-date');
                    if (dateStr) {
                        calendarSelectDate(target, dateStr);
                    }
                }
            });

            document.getElementById('prev-month').addEventListener('click', function() {
                currentDate.setMonth(currentDate.getMonth() - 1);
                renderCalendar();
            });

            document.getElementById('next-month').addEventListener('click', function() {
                currentDate.setMonth(currentDate.getMonth() + 1);
                renderCalendar();
            });

            renderCalendar();

            <?php if (isset($_SESSION['booking']['date'])): ?>
                selectedDate = '<?= $_SESSION['booking']['date'] ?>';
                const preselectedDate = new Date('<?= $_SESSION['booking']['date'] ?>T00:00:00');
                currentDate = new Date(preselectedDate.getFullYear(), preselectedDate.getMonth(), 1);
                renderCalendar();
            <?php endif; ?>
        });
    })(); // Sofort ausgeführte Funktion für eigenen Scope
</script>