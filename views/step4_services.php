<?php
// views/step4_services.php - Modernisierte Leistungsauswahl
$services = $bookingManager->getAllServices();
$customerDistance = $_SESSION['booking']['distance'] ?? 0;
?>

<h2 class="step-title">Wählen Sie Ihre Leistungen</h2>
<p class="step-description">Welche Services sollen wir für Sie durchführen? Sie können mehrere Leistungen auswählen.</p>

<!-- Entfernungs- und Kosteninformation -->
<div class="distance-info">
    <h4>📍 Ihre Anfahrtsinformationen</h4>
    <div class="distance-grid">
        <div>
            <strong>Entfernung:</strong> <?= number_format($customerDistance, 1, ',', '.') ?> km<br>
            <strong>Adresse:</strong><br>
            <small><?= htmlspecialchars($_SESSION['booking']['customer']['address'] ?? '') ?></small>
        </div>
        <div id="travel-cost-info">
            <strong>Anfahrtskosten:</strong>
            <span id="travel-cost-display">Werden berechnet...</span><br>
            <small id="travel-cost-details">Wählen Sie Leistungen aus</small>
        </div>
    </div>
</div>

<form method="POST" action="?step=4" id="services-form">
    <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
    <?= SecurityManager::generateHoneypot() ?>

    <div class="services-grid">
        <?php foreach ($services as $service): ?>
            <div class="service-card"
                data-service-id="<?= $service['id'] ?>"
                data-service-name="<?= htmlspecialchars($service['name']) ?>"
                data-service-price="<?= $service['price'] ?>"
                data-service-duration="<?= $service['duration'] ?>"
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
    </div>

    <!-- Ausgewählte Services Zusammenfassung -->
    <div class="summary-container" id="selected-services-summary" style="display: none;">
        <h3 class="summary-title">🛒 Ihre Auswahl</h3>

        <div class="summary-section">
            <div id="selected-services-list"></div>

            <div class="summary-item" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--clr-surface-a30);">
                <span class="summary-label">Zwischensumme Leistungen:</span>
                <span class="summary-value" id="services-subtotal">0,00 €</span>
            </div>

            <div class="summary-item">
                <span class="summary-label">Anfahrtskosten:</span>
                <span class="summary-value" id="travel-cost-summary">0,00 €</span>
            </div>

            <div class="summary-item total-row">
                <span class="summary-label">Gesamtpreis:</span>
                <span class="summary-value total-price" id="total-price">0,00 €</span>
            </div>
        </div>
    </div>

    <div class="btn-group">
        <a href="?step=3" class="btn-secondary">Zurück zu Kundendaten</a>
        <button type="submit" class="btn-primary" id="continue-btn" disabled>Zur Zusammenfassung</button>
    </div>
</form>

<style>
    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .service-card {
        background: rgba(0, 0, 0, 0.5);
        border: 2px solid var(--clr-surface-a30);
        border-radius: var(--radius-sm);
        padding: 20px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        backdrop-filter: blur(10px);
    }

    .service-card:hover {
        border-color: var(--clr-primary-a50);
        background: rgba(30, 30, 30, 0.6);
        transform: translateY(-2px);
    }

    .service-card.selected {
        border-color: var(--clr-primary-a0);
        background: rgba(255, 107, 53, 0.1);
    }

    .service-checkbox {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 24px;
        height: 24px;
        border: 2px solid var(--clr-surface-a50);
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .service-card.selected .service-checkbox {
        background: var(--clr-primary-a0);
        border-color: var(--clr-primary-a0);
    }

    .service-checkbox .checkmark {
        display: none;
        color: var(--clr-surface-a0);
        font-weight: bold;
    }

    .service-card.selected .service-checkbox .checkmark {
        display: block;
    }

    .service-name {
        font-size: 18px;
        font-weight: 500;
        margin-bottom: 10px;
        color: var(--clr-primary-a0);
        padding-right: 40px;
    }

    .service-description {
        color: var(--clr-primary-a40);
        font-size: 14px;
        line-height: 1.4;
        margin-bottom: 15px;
    }

    .service-meta {
        display: flex;
        justify-content: space-between;
        padding-top: 15px;
        border-top: 1px solid var(--clr-surface-a30);
    }

    .service-price {
        font-size: 20px;
        font-weight: 600;
        color: var(--clr-warning);
    }

    .service-duration {
        color: var(--clr-primary-a50);
        font-size: 14px;
    }

    .distance-info {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid var(--clr-surface-a30);
        border-radius: var(--radius-sm);
        padding: 20px;
        margin-bottom: 30px;
        backdrop-filter: blur(10px);
    }

    .distance-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 15px;
    }

    @media (max-width: 768px) {
        .distance-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    // JavaScript für die Leistungsauswahl mit korrigierter Anfahrtskosten-Logik
    const customerDistance = <?= $customerDistance ?>;
    const travelConfig = window.TRAVEL_CONFIG || {
        costPerKm: <?= TRAVEL_COST_PER_KM ?>,
        freeKm: <?= TRAVEL_FREE_KM ?>,
        minServiceAmount: <?= TRAVEL_MIN_SERVICE_AMOUNT ?>,
        maxDistanceSmall: <?= TRAVEL_MAX_DISTANCE_SMALL ?>,
        maxDistanceLarge: <?= TRAVEL_MAX_DISTANCE_LARGE ?>,
        absoluteMaxDistance: <?= TRAVEL_ABSOLUTE_MAX_DISTANCE ?>
    };

    let selectedServices = [];

    function toggleServiceSelection(card) {
        const checkbox = card.querySelector('input[type="checkbox"]');
        const serviceId = card.dataset.serviceId;
        const serviceName = card.dataset.serviceName;
        const servicePrice = parseFloat(card.dataset.servicePrice);
        const serviceDuration = parseInt(card.dataset.serviceDuration);

        if (card.classList.contains('selected')) {
            card.classList.remove('selected');
            checkbox.checked = false;
            selectedServices = selectedServices.filter(s => s.id !== serviceId);
        } else {
            card.classList.add('selected');
            checkbox.checked = true;
            selectedServices.push({
                id: serviceId,
                name: serviceName,
                price: servicePrice,
                duration: serviceDuration
            });
        }

        updateSummary();
    }

    function updateSummary() {
        const summarySection = document.getElementById('selected-services-summary');
        const continueBtn = document.getElementById('continue-btn');
        const servicesList = document.getElementById('selected-services-list');

        if (selectedServices.length === 0) {
            summarySection.style.display = 'none';
            continueBtn.disabled = true;

            // Update Anfahrtskosten-Info
            document.getElementById('travel-cost-display').textContent = 'Werden berechnet...';
            document.getElementById('travel-cost-details').textContent = 'Wählen Sie Leistungen aus';
            return;
        }

        summarySection.style.display = 'block';
        continueBtn.disabled = false;

        // Services-Liste
        servicesList.innerHTML = selectedServices.map(service => `
            <div class="summary-item">
                <span class="summary-label">${service.name}</span>
                <span class="summary-value">${service.price.toFixed(2).replace('.', ',')} €</span>
            </div>
        `).join('');

        // Berechnungen
        const servicesTotal = selectedServices.reduce((sum, s) => sum + s.price, 0);
        const totalDuration = selectedServices.reduce((sum, s) => sum + s.duration, 0);

        // NEUE ANFAHRTSKOSTEN-LOGIK (korrekt implementiert)
        let travelCost = 0;
        let travelCostDetails = '';

        if (servicesTotal < travelConfig.minServiceAmount) {
            // Unter 59,90€: max 10km, komplett kostenlos
            if (customerDistance <= travelConfig.maxDistanceSmall) {
                travelCost = 0;
                travelCostDetails = `Kostenlos (unter ${travelConfig.minServiceAmount.toFixed(2).replace('.', ',')}€ bis ${travelConfig.maxDistanceSmall}km)`;
            } else {
                // Nicht buchbar
                travelCostDetails = `⚠️ Zu weit! Max. ${travelConfig.maxDistanceSmall}km bei dieser Leistungssumme`;
                document.getElementById('travel-cost-display').innerHTML = '<span style="color: red;">Nicht verfügbar</span>';
                continueBtn.disabled = true;
            }
        } else {
            // Ab 59,90€: max 30km, erste 10km gratis
            if (customerDistance <= travelConfig.maxDistanceLarge) {
                if (customerDistance <= travelConfig.freeKm) {
                    travelCost = 0;
                    travelCostDetails = `Kostenlos (erste ${travelConfig.freeKm}km gratis)`;
                } else {
                    const chargeableKm = customerDistance - travelConfig.freeKm;
                    travelCost = chargeableKm * travelConfig.costPerKm;
                    travelCostDetails = `${chargeableKm.toFixed(1)}km × ${travelConfig.costPerKm.toFixed(2).replace('.', ',')}€/km (erste ${travelConfig.freeKm}km gratis)`;
                }
            } else {
                // Nicht buchbar
                travelCostDetails = `⚠️ Zu weit! Max. ${travelConfig.maxDistanceLarge}km bei dieser Leistungssumme`;
                document.getElementById('travel-cost-display').innerHTML = '<span style="color: red;">Nicht verfügbar</span>';
                continueBtn.disabled = true;
            }
        }

        const totalPrice = servicesTotal + travelCost;

        // Update Display
        document.getElementById('services-subtotal').textContent = servicesTotal.toFixed(2).replace('.', ',') + ' €';
        document.getElementById('travel-cost-summary').textContent = travelCost.toFixed(2).replace('.', ',') + ' €';
        document.getElementById('total-price').textContent = totalPrice.toFixed(2).replace('.', ',') + ' €';

        // Update Anfahrtskosten-Info (nur wenn buchbar)
        if (continueBtn.disabled === false) {
            document.getElementById('travel-cost-display').textContent = travelCost.toFixed(2).replace('.', ',') + ' €';
            document.getElementById('travel-cost-details').textContent = travelCostDetails;
        }
    }

    // Keyboard support
    document.querySelectorAll('.service-card').forEach(card => {
        card.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleServiceSelection(this);
            }
        });
    });

    // Initial check for pre-selected services
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.service-card input:checked').forEach(checkbox => {
            const card = checkbox.closest('.service-card');
            card.classList.add('selected');
            const serviceId = card.dataset.serviceId;
            const serviceName = card.dataset.serviceName;
            const servicePrice = parseFloat(card.dataset.servicePrice);
            const serviceDuration = parseInt(card.dataset.serviceDuration);

            selectedServices.push({
                id: serviceId,
                name: serviceName,
                price: servicePrice,
                duration: serviceDuration
            });
        });
        updateSummary();
    });
</script>