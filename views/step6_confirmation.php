<?php
// views/step6_confirmation.php - Modernisierte Buchungsbestätigung
$bookingDetails = $bookingManager->getBookingDetails($_SESSION['booking']['id']);

// Wochentag
$days = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
$dayOfWeek = $days[date('w', strtotime($bookingDetails['date']))];
?>

<div class="confirmation-container">
    <!-- Success Animation -->
    <div class="confirmation-icon">
        <div class="success-animation">
            <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none" />
                <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
            </svg>
        </div>
        <h2 class="step-title success-title">Buchung erfolgreich!</h2>
    </div>

    <!-- Success Message -->
    <div class="confirmation-message">
        <p class="main-message">
            <strong>Vielen Dank für Ihre Buchung bei <?= defined('BUSINESS_NAME') ? BUSINESS_NAME : 'MCS Mobile Car Solutions' ?>!</strong>
        </p>
        <p>Wir haben Ihre Buchung erhalten und eine Bestätigungs-E-Mail an Sie gesendet.<br>
            Unser Team wird sich kurz vor dem Termin bei Ihnen melden.</p>
    </div>

    <!-- Booking Number -->
    <div class="booking-number-display">
        <div class="booking-number-label">Ihre Buchungsnummer</div>
        <div class="booking-number-value">#<?= str_pad($bookingDetails['id'], 6, '0', STR_PAD_LEFT) ?></div>
        <div class="booking-number-hint">Bitte notieren Sie sich diese Nummer für Rückfragen</div>
    </div>

    <!-- Booking Details -->
    <div class="confirmation-details">
        <div class="summary-section">
            <div class="summary-title">📋 Ihre Buchungsdetails</div>

            <div class="detail-group">
                <h4>Termin</h4>
                <div class="detail-item">
                    <span class="detail-icon">📅</span>
                    <span><?= $dayOfWeek ?>, <?= date('d.m.Y', strtotime($bookingDetails['date'])) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-icon">🕐</span>
                    <span><?= $bookingDetails['time'] ?> Uhr</span>
                </div>
            </div>

            <div class="detail-group">
                <h4>Adresse</h4>
                <div class="detail-item">
                    <span class="detail-icon">📍</span>
                    <span><?= htmlspecialchars($bookingDetails['customer_address']) ?></span>
                </div>
            </div>

            <div class="detail-group">
                <h4>Kontakt</h4>
                <div class="detail-item">
                    <span class="detail-icon">👤</span>
                    <span><?= htmlspecialchars($bookingDetails['customer_name']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-icon">📞</span>
                    <span><?= htmlspecialchars($bookingDetails['customer_phone']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-icon">📧</span>
                    <span><?= htmlspecialchars($bookingDetails['customer_email']) ?></span>
                </div>
            </div>
        </div>

        <div class="summary-section">
            <div class="summary-title">🔧 Gebuchte Leistungen</div>
            <?php foreach ($bookingDetails['services'] as $service): ?>
                <div class="summary-item">
                    <span class="summary-label">
                        <?= htmlspecialchars($service['name']) ?>
                        <small class="text-muted">(<?= $service['duration'] ?> Min.)</small>
                    </span>
                    <span class="summary-value"><?= number_format($service['price'], 2, ',', '.') ?> €</span>
                </div>
            <?php endforeach; ?>

            <div class="summary-item total-row">
                <span class="summary-label">Gesamtpreis:</span>
                <span class="summary-value total-price"><?= number_format($bookingDetails['total_price'], 2, ',', '.') ?> €</span>
            </div>
        </div>
    </div>

    <!-- Next Steps -->
    <div class="next-steps">
        <h3>🚀 Die nächsten Schritte</h3>
        <div class="steps-timeline">
            <div class="timeline-item active">
                <div class="timeline-icon">✓</div>
                <div class="timeline-content">
                    <strong>Buchung bestätigt</strong>
                    <small>Soeben erhalten</small>
                </div>
            </div>
            <div class="timeline-item">
                <div class="timeline-icon">📧</div>
                <div class="timeline-content">
                    <strong>E-Mail-Bestätigung</strong>
                    <small>In wenigen Minuten</small>
                </div>
            </div>
            <div class="timeline-item">
                <div class="timeline-icon">📞</div>
                <div class="timeline-content">
                    <strong>Anruf vor Termin</strong>
                    <small>30 Min. vorher</small>
                </div>
            </div>
            <div class="timeline-item">
                <div class="timeline-icon">🚗</div>
                <div class="timeline-content">
                    <strong>Service vor Ort</strong>
                    <small><?= date('d.m.Y', strtotime($bookingDetails['date'])) ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="confirmation-actions">
        <button onclick="window.print()" class="btn-secondary">
            🖨️ Seite drucken
        </button>
        <a href="/" class="btn-primary">
            Zur Startseite
        </a>
    </div>

    <!-- Contact Info -->
    <div class="contact-reminder">
        <p>Bei Fragen oder Änderungswünschen kontaktieren Sie uns bitte:</p>
        <div class="contact-methods">
            <a href="tel:<?= defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '+49123456789' ?>" class="contact-method">
                <span class="contact-icon">📞</span>
                <span><?= defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '+49 123 456789' ?></span>
            </a>
            <a href="mailto:<?= defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : 'info@mcs-mobile.de' ?>" class="contact-method">
                <span class="contact-icon">📧</span>
                <span><?= defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : 'info@mcs-mobile.de' ?></span>
            </a>
        </div>
    </div>
</div>

<style>
    .confirmation-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }

    .confirmation-icon {
        text-align: center;
        margin-bottom: 30px;
    }

    .success-animation {
        width: 100px;
        height: 100px;
        margin: 0 auto 20px;
    }

    .checkmark {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        display: block;
        stroke-width: 2;
        stroke: var(--clr-success);
        stroke-miterlimit: 10;
        animation: scale 0.3s ease-in-out 0.9s both;
    }

    .checkmark-circle {
        stroke-dasharray: 166;
        stroke-dashoffset: 166;
        stroke-width: 2;
        stroke-miterlimit: 10;
        stroke: var(--clr-success);
        animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
    }

    .checkmark-check {
        transform-origin: 50% 50%;
        stroke-dasharray: 48;
        stroke-dashoffset: 48;
        animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
    }

    @keyframes stroke {
        100% {
            stroke-dashoffset: 0;
        }
    }

    @keyframes scale {

        0%,
        100% {
            transform: none;
        }

        50% {
            transform: scale3d(1.1, 1.1, 1);
        }
    }

    .success-title {
        color: var(--clr-success);
        margin-bottom: 10px;
    }

    .confirmation-message {
        text-align: center;
        margin-bottom: 40px;
    }

    .main-message {
        font-size: 18px;
        margin-bottom: 15px;
    }

    .confirmation-details {
        display: grid;
        gap: 20px;
        margin-bottom: 40px;
    }

    .detail-group {
        margin-bottom: 20px;
    }

    .detail-group h4 {
        color: var(--clr-primary-a40);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 10px;
    }

    .detail-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
        color: var(--clr-primary-a0);
    }

    .detail-icon {
        font-size: 16px;
    }

    .next-steps {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid var(--clr-surface-a30);
        border-radius: var(--radius-sm);
        padding: 30px;
        margin-bottom: 30px;
        backdrop-filter: blur(10px);
    }

    .next-steps h3 {
        color: var(--clr-primary-a0);
        margin-bottom: 20px;
        font-size: 18px;
    }

    .steps-timeline {
        display: grid;
        gap: 20px;
    }

    .timeline-item {
        display: flex;
        align-items: center;
        gap: 15px;
        opacity: 0.5;
        transition: opacity 0.3s ease;
    }

    .timeline-item.active {
        opacity: 1;
    }

    .timeline-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        border: 2px solid var(--clr-surface-a30);
    }

    .timeline-item.active .timeline-icon {
        background: var(--clr-success);
        border-color: var(--clr-success);
    }

    .timeline-content strong {
        display: block;
        color: var(--clr-primary-a0);
        font-size: 14px;
    }

    .timeline-content small {
        color: var(--clr-primary-a50);
        font-size: 12px;
    }

    .confirmation-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-bottom: 40px;
    }

    .contact-reminder {
        background: rgba(33, 150, 243, 0.05);
        border: 1px solid var(--clr-info);
        border-radius: var(--radius-sm);
        padding: 20px;
        text-align: center;
        backdrop-filter: blur(10px);
    }

    .contact-reminder p {
        margin-bottom: 15px;
        color: var(--clr-primary-a30);
    }

    .contact-methods {
        display: flex;
        gap: 20px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .contact-method {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--clr-surface-a30);
        border-radius: var(--radius-sm);
        color: var(--clr-primary-a0);
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .contact-method:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateY(-2px);
    }

    .contact-icon {
        font-size: 18px;
    }

    @media (min-width: 768px) {
        .confirmation-details {
            grid-template-columns: 1fr 1fr;
        }

        .steps-timeline {
            grid-template-columns: repeat(4, 1fr);
        }
    }

    @media print {

        .confirmation-actions,
        .btn-group {
            display: none;
        }

        body {
            background: white;
        }

        .confirmation-container {
            color: black;
        }
    }
</style>

<script>
    // Confetti effect on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Optional: Add confetti library or custom animation
        console.log('Booking confirmed! ID: <?= $bookingDetails['id'] ?>');

        // Save booking ID to localStorage for reference
        localStorage.setItem('lastBookingId', '<?= $bookingDetails['id'] ?>');

        // Clear booking session after short delay
        setTimeout(() => {
            // Session will be cleared server-side
        }, 3000);
    });

    // Print optimization
    window.addEventListener('beforeprint', function() {
        document.body.classList.add('printing');
    });

    window.addEventListener('afterprint', function() {
        document.body.classList.remove('printing');
    });
</script>