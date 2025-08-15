<?php
// views/step3_services.php - Leistungsauswahl mit funktionierender Preisberechnung
$services = $bookingManager->getAllServices();
?>

<h2 class="step-title">Wählen Sie Ihre Leistungen</h2>
<p class="step-description">Welche Services sollen wir für Sie durchführen? Sie können mehrere Leistungen auswählen.</p>

<form method="POST" action="?step=3" id="services-form">
    <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
    <?= SecurityManager::generateHoneypot() ?>

    <div class="services-grid">
        <?php foreach ($services as $service): ?>
            <div class="service-card"
                data-service-id="<?= $service['id'] ?>"
                data-service-name="<?= htmlspecialchars($service['name']) ?>"
                data-service-price="<?= $service['price'] ?>"
                onclick="toggleServiceSelection(this)"
                role="button"
                tabindex="0">

                <input type="checkbox"
                    name="services[]"
                    value="<?= $service['id'] ?>"
                    id="service_<?= $service['id'] ?>"
                    style="display: none;">

                <div class="service-checkbox">
                    <span class="checkmark">✓</span>
                </div>

                <div class="service-content">
                    <div class="service-name"><?= htmlspecialchars($service['name']) ?></div>
                    <div class="service-description"><?= htmlspecialchars($service['description']) ?></div>
                    <div class="service-meta">
                        <div class="service-price"><?= number_format($service['price'], 2, ',', '.') ?> €</div>
                        <div class="service-duration">ca. <?= $service['duration'] ?> Min.</div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($services)): ?>
            <div class="no-services-available">
                <p>Aktuell sind keine Services verfügbar. Bitte kontaktieren Sie uns direkt.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="selected-services-summary" id="services-summary" style="display: none;">
        <div class="summary-section">
            <div class="summary-title">Ausgewählte Leistungen</div>
            <div id="selected-services-list"></div>
            <div class="summary-item" style="border-top: 2px solid rgba(255, 107, 53, 0.3); padding-top: 15px; margin-top: 15px;">
                <span class="summary-label" style="font-weight: bold;">Zwischensumme:</span>
                <span class="summary-value" id="services-total" style="font-weight: bold; color: #ffffff;">0,00 €</span>
            </div>
        </div>
    </div>

    <div class="btn-group">
        <a href="?step=2" class="btn-secondary">Zurück</a>
        <button type="submit" class="btn-primary" id="continue-btn" disabled>Weiter zu den Kundendaten</button>
    </div>
</form>

<style>
    .service-card {
        position: relative;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .service-checkbox {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 24px;
        height: 24px;
        border: 2px solid #666;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .service-checkbox .checkmark {
        opacity: 0;
        color: white;
        font-weight: bold;
        transition: opacity 0.3s ease;
    }

    .service-card.selected {
        border-color: #ffffff;
        background: linear-gradient(45deg, rgba(255, 107, 53, 0.2), rgba(255, 140, 66, 0.2));
    }

    .service-card.selected .service-checkbox {
        background: #ffffff;
        border-color: #ffffff;
    }

    .service-card.selected .service-checkbox .checkmark {
        opacity: 1;
    }

    .selected-services-summary {
        margin-top: 30px;
        animation: fadeIn 0.3s ease;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<script>
    // Neue, einfachere Implementierung
    function toggleServiceSelection(element) {
        const checkbox = element.querySelector('input[type="checkbox"]');
        const isCurrentlySelected = element.classList.contains('selected');

        if (isCurrentlySelected) {
            // Deselektieren
            element.classList.remove('selected');
            checkbox.checked = false;
        } else {
            // Selektieren
            element.classList.add('selected');
            checkbox.checked = true;
        }

        // Update Zusammenfassung
        updateServiceSummary();
    }

    function updateServiceSummary() {
        // Hole alle ausgewählten Service-Cards
        const selectedCards = document.querySelectorAll('.service-card.selected');
        const summaryContainer = document.getElementById('services-summary');
        const listContainer = document.getElementById('selected-services-list');
        const totalElement = document.getElementById('services-total');
        const continueButton = document.getElementById('continue-btn');

        // Debug
        console.log('Anzahl ausgewählte Services:', selectedCards.length);

        if (selectedCards.length === 0) {
            // Keine Services ausgewählt
            summaryContainer.style.display = 'none';
            continueButton.disabled = true;
            return;
        }

        // Services vorhanden - zeige Zusammenfassung
        summaryContainer.style.display = 'block';
        continueButton.disabled = false;

        let htmlContent = '';
        let totalPrice = 0;

        // Durchlaufe alle ausgewählten Services
        selectedCards.forEach(card => {
            const serviceName = card.getAttribute('data-service-name');
            const servicePrice = parseFloat(card.getAttribute('data-service-price'));

            // Debug
            console.log('Service:', serviceName, 'Preis:', servicePrice);

            if (!isNaN(servicePrice)) {
                totalPrice += servicePrice;

                htmlContent += `
                <div class="summary-item">
                    <span class="summary-label">${serviceName}</span>
                    <span class="summary-value">${servicePrice.toFixed(2).replace('.', ',')} €</span>
                </div>
            `;
            }
        });

        // Update DOM
        listContainer.innerHTML = htmlContent;
        totalElement.textContent = totalPrice.toFixed(2).replace('.', ',') + ' €';

        // Debug
        console.log('Gesamtpreis:', totalPrice);

        // Smooth scroll zur Zusammenfassung
        if (summaryContainer.offsetHeight > 0) {
            setTimeout(() => {
                summaryContainer.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
            }, 100);
        }
    }

    // Initialisierung beim Laden der Seite
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Service-Seite geladen');

        // Prüfe ob bereits Services ausgewählt sind (z.B. bei Zurück-Navigation)
        const checkedBoxes = document.querySelectorAll('.service-card input[type="checkbox"]:checked');

        checkedBoxes.forEach(checkbox => {
            const card = checkbox.closest('.service-card');
            if (card && !card.classList.contains('selected')) {
                card.classList.add('selected');
            }
        });

        // Initial update
        updateServiceSummary();

        // Alternative Event-Handler für Tastatur
        document.querySelectorAll('.service-card').forEach(card => {
            card.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleServiceSelection(this);
                }
            });
        });
    });

    // Globale Funktion für Kompatibilität mit booking.js
    window.toggleService = function(serviceId, element) {
        toggleServiceSelection(element);
    };
</script>