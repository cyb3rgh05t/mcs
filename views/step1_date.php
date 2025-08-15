<?php
// views/step1_date.php - Datumsauswahl mit Kalender
$availableDates = $bookingManager->getAvailableDates(90); // Hole mehr Termine für mehrere Monate
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
    <div class="selected-date-display" id="selected-date-display" style="display: none;">
        <div class="selected-date-label">Ausgewähltes Datum:</div>
        <div class="selected-date-value" id="selected-date-value"></div>
    </div>

    <input type="hidden" name="selected_date" id="selected_date" value="">

    <div class="btn-group">
        <button type="submit" class="btn-primary" id="continue-btn" disabled>Weiter zur Uhrzeitauswahl</button>
    </div>
</form>

<?php if (empty($availableDates)): ?>
    <div class="no-dates-available">
        <p>Aktuell sind keine Termine verfügbar. Bitte kontaktieren Sie uns direkt.</p>
        <p><strong>Telefon:</strong> <?= defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '+49 123 456789' ?></p>
    </div>
<?php endif; ?>

<style>
    .calendar-container {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 30px;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }

    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .calendar-nav {
        background: rgba(255, 107, 53, 0.2);
        border: 1px solid #ff6b35;
        color: #ff6b35;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        font-size: 24px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .calendar-nav:hover {
        background: #ff6b35;
        color: white;
        transform: scale(1.1);
    }

    .calendar-nav:disabled {
        opacity: 0.3;
        cursor: not-allowed;
    }

    .calendar-title {
        font-size: 20px;
        font-weight: bold;
        color: #ff6b35;
    }

    .calendar-weekdays {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 5px;
        margin-bottom: 10px;
    }

    .calendar-weekday {
        text-align: center;
        font-weight: bold;
        color: #999;
        font-size: 14px;
        padding: 10px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .calendar-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 5px;
    }

    .calendar-day {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        position: relative;
    }

    .calendar-day:hover:not(.disabled):not(.other-month) {
        background: rgba(255, 107, 53, 0.2);
        border-color: #ff6b35;
        transform: scale(1.05);
    }

    .calendar-day.available {
        color: #fff;
        background: rgba(40, 167, 69, 0.1);
        border-color: rgba(40, 167, 69, 0.3);
    }

    .calendar-day.available::after {
        content: '•';
        position: absolute;
        bottom: 5px;
        color: #28a745;
        font-size: 12px;
    }

    .calendar-day.selected {
        background: linear-gradient(45deg, #ff6b35, #ff8c42);
        border-color: #ff6b35;
        color: white;
        font-weight: bold;
        box-shadow: 0 0 15px rgba(255, 107, 53, 0.4);
    }

    .calendar-day.disabled {
        opacity: 0.3;
        cursor: not-allowed;
        color: #666;
    }

    .calendar-day.other-month {
        opacity: 0.2;
        cursor: not-allowed;
    }

    .calendar-day.today {
        border: 2px solid #ff6b35;
    }

    .selected-date-display {
        background: linear-gradient(135deg, rgba(255, 107, 53, 0.1), rgba(255, 140, 66, 0.1));
        border: 1px solid #ff6b35;
        border-radius: 10px;
        padding: 20px;
        margin: 20px auto;
        max-width: 400px;
        text-align: center;
    }

    .selected-date-label {
        color: #999;
        font-size: 14px;
        margin-bottom: 5px;
    }

    .selected-date-value {
        color: #ff6b35;
        font-size: 24px;
        font-weight: bold;
    }

    @media (max-width: 768px) {
        .calendar-container {
            padding: 15px;
        }

        .calendar-day {
            font-size: 14px;
        }
    }
</style>

<script>
    // Verfügbare Termine von PHP
    const availableDates = <?= json_encode($availableDates) ?>;

    // Deutsche Monatsnamen
    const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
        'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
    ];

    const dayNames = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];

    let currentDate = new Date();
    let currentMonth = currentDate.getMonth();
    let currentYear = currentDate.getFullYear();
    let selectedDateValue = null;

    function initCalendar() {
        renderCalendar(currentMonth, currentYear);

        // Event Listener für Navigation
        document.getElementById('prev-month').addEventListener('click', function() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar(currentMonth, currentYear);
        });

        document.getElementById('next-month').addEventListener('click', function() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar(currentMonth, currentYear);
        });
    }

    function renderCalendar(month, year) {
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const prevLastDay = new Date(year, month, 0);
        const prevDays = prevLastDay.getDate();
        const lastDate = lastDay.getDate();
        const day = firstDay.getDay();
        const nextDays = 7 - lastDay.getDay() - 1;

        // Update Titel
        document.getElementById('calendar-title').textContent = monthNames[month] + ' ' + year;

        // Erstelle Kalender-HTML
        let html = '';
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Startday korrigieren (Montag = 0)
        let startDay = day === 0 ? 6 : day - 1;

        // Vorherige Monatstage
        for (let x = startDay; x > 0; x--) {
            html += `<div class="calendar-day other-month">${prevDays - x + 1}</div>`;
        }

        // Aktuelle Monatstage
        for (let i = 1; i <= lastDate; i++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            const dateObj = new Date(year, month, i);
            const isPast = dateObj < today;
            const isToday = dateObj.getTime() === today.getTime();
            const isAvailable = availableDates.includes(dateStr);
            const isSunday = dateObj.getDay() === 0;

            let className = 'calendar-day';

            if (isPast) {
                className += ' disabled';
            } else if (isSunday) {
                className += ' disabled';
            } else if (isAvailable) {
                className += ' available';
            }

            if (isToday) {
                className += ' today';
            }

            if (selectedDateValue === dateStr) {
                className += ' selected';
            }

            const onClick = (isPast || isSunday || !isAvailable) ? '' : `onclick="selectCalendarDate('${dateStr}', this)"`;

            html += `<div class="${className}" data-date="${dateStr}" ${onClick}>${i}</div>`;
        }

        // Nächste Monatstage
        for (let j = 1; j <= nextDays + (7 - nextDays % 7) % 7; j++) {
            html += `<div class="calendar-day other-month">${j}</div>`;
        }

        document.getElementById('calendar-days').innerHTML = html;

        // Navigation buttons aktivieren/deaktivieren
        const minDate = new Date();
        const maxDate = new Date();
        maxDate.setMonth(maxDate.getMonth() + 3);

        document.getElementById('prev-month').disabled =
            new Date(year, month, 1) <= new Date(minDate.getFullYear(), minDate.getMonth(), 1);

        document.getElementById('next-month').disabled =
            new Date(year, month, 1) >= new Date(maxDate.getFullYear(), maxDate.getMonth(), 1);
    }

    function selectCalendarDate(dateStr, element) {
        selectedDateValue = dateStr;

        // Update hidden input
        document.getElementById('selected_date').value = dateStr;

        // Update UI - alle selected entfernen
        document.querySelectorAll('.calendar-day').forEach(day => {
            day.classList.remove('selected');
        });

        // Aktuelle selektieren
        if (element) {
            element.classList.add('selected');
        } else {
            // Falls kein Element übergeben wurde, finde es über data-date
            const dayElement = document.querySelector(`.calendar-day[data-date="${dateStr}"]`);
            if (dayElement) {
                dayElement.classList.add('selected');
            }
        }

        // Zeige ausgewähltes Datum
        const date = new Date(dateStr + 'T00:00:00');
        const dayName = dayNames[date.getDay()];
        const formattedDate = `${dayName}, ${date.getDate()}. ${monthNames[date.getMonth()]} ${date.getFullYear()}`;

        document.getElementById('selected-date-value').textContent = formattedDate;
        document.getElementById('selected-date-display').style.display = 'block';

        // Button aktivieren
        document.getElementById('continue-btn').disabled = false;

        // Smooth scroll
        document.getElementById('selected-date-display').scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }

    // Initialisiere Kalender beim Laden
    document.addEventListener('DOMContentLoaded', function() {
        initCalendar();

        // Wenn bereits ein Datum in der Session ist
        <?php if (isset($_SESSION['booking']['date'])): ?>
            selectCalendarDate('<?= $_SESSION['booking']['date'] ?>', null);
        <?php endif; ?>
    });
</script>