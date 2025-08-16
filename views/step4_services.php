<?php
// views/step4_services.php - Leistungsauswahl mit Entfernungsvalidierung
$services = $bookingManager->getAllServices();
$customerDistance = $_SESSION['booking']['distance'] ?? 0;
?>

<h2 class="step-title">Wählen Sie Ihre Leistungen</h2>
<p class="step-description">Welche Services sollen wir für Sie durchführen? Sie können mehrere Leistungen auswählen.</p>

<!-- Entfernungs- und Kosteninformation -->
<div style="background: rgba(255, 107, 53, 0.1); border: 1px solid #ff6b35; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
    <h4 style="color: #ff6b35; margin-bottom: 15px;">📍 Ihre Anfahrtsinformationen</h4>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div>
            <strong>Entfernung:</strong> <?= number_format($customerDistance, 1) ?> km<br>
            <strong>Adresse:</strong> <?= htmlspecialchars($_SESSION['booking']['customer']['address'] ?? '') ?>
        </div>
        <div id="travel-cost-info">
            <strong>Anfahrtskosten:</strong> <span id="travel-cost-display">Werden basierend auf Ihrer Auswahl berechnet</span><br>
            <small style="color: #ccc;" id="travel-cost-details">Wählen Sie Leistungen aus, um die Anfahrtskosten zu sehen</small>
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

        <?php if (empty($services)): ?>
            <div class="no-services-available">
                <p>Aktuell sind keine Services verfügbar.</p>
                <p>Bitte kontaktieren Sie uns direkt unter <?= defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '+49 123 456789' ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Preisübersicht -->
    <div class="price-summary" style="background: rgba(30, 30, 30, 0.9); border: 1px solid #4e4e4e; border-radius: 8px; padding: 20px; margin-top: 30px;">
        <h3 style="color: #ff6b35; margin-bottom: 15px;">💰 Kostenübersicht</h3>
        <div class="price-details">
            <div class="price-row" style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <span>Leistungen:</span>
                <span id="services-total">0,00 €</span>
            </div>
            <div class="price-row" style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <span>Anfahrt:</span>
                <span id="travel-total">0,00 €</span>
            </div>
            <div class="price-row" style="display: flex; justify-content: space-between; margin-top: 15px; padding-top: 15px; border-top: 2px solid #ff6b35; font-size: 20px; font-weight: bold;">
                <span>Gesamtpreis:</span>
                <span id="total-price" style="color: #ff6b35;">0,00 €</span>
            </div>
        </div>

        <!-- Warnungen/Hinweise -->
        <div id="distance-validation-message" style="margin-top: 20px; padding: 15px; border-radius: 5px; display: none;">
            <span id="validation-icon"></span>
            <span id="validation-text"></span>
        </div>
    </div>

    <div class="btn-group" style="margin-top: 30px;">
        <a href="?step=3" class="btn-secondary">Zurück</a>
        <button type="submit" class="btn-primary" id="continue-btn" disabled>Weiter zur Zusammenfassung</button>
    </div>
</form>

<script>
    // Konfiguration aus PHP
    const travelConfig = {
        distance: <?= $customerDistance ?>,
        costPerKm: <?= TRAVEL_COST_PER_KM ?>,
        freeKm: <?= TRAVEL_FREE_KM ?>,
        minServiceAmount: <?= TRAVEL_MIN_SERVICE_AMOUNT ?>,
        maxDistanceSmall: <?= TRAVEL_MAX_DISTANCE_SMALL ?>,
        maxDistanceLarge: <?= TRAVEL_MAX_DISTANCE_LARGE ?>
    };

    // Service-Auswahl Toggle
    function toggleServiceSelection(card) {
        const checkbox = card.querySelector('input[type="checkbox"]');
        checkbox.checked = !checkbox.checked;
        card.classList.toggle('selected', checkbox.checked);

        updatePriceCalculation();
    }

    // Preisberechnung aktualisieren
    function updatePriceCalculation() {
        const selectedServices = document.querySelectorAll('.service-card.selected');
        let servicesTotal = 0;
        let totalDuration = 0;

        selectedServices.forEach(card => {
            const price = parseFloat(card.dataset.servicePrice);
            const duration = parseInt(card.dataset.serviceDuration);
            servicesTotal += price;
            totalDuration += duration;
        });

        // Anfahrtskosten berechnen
        let travelCost = 0;
        let travelMessage = '';
        let validationMessage = '';
        let validationIcon = '';
        let validationColor = '';
        let canContinue = true;

        if (servicesTotal >= travelConfig.minServiceAmount) {
            // Leistungen >= 59.90€: Normale Berechnung
            if (travelConfig.distance <= travelConfig.maxDistanceLarge) {
                if (travelConfig.distance > travelConfig.freeKm) {
                    const chargeableDistance = travelConfig.distance - travelConfig.freeKm;
                    travelCost = chargeableDistance * travelConfig.costPerKm;
                    travelMessage = `${travelConfig.distance.toFixed(1)} km - ${travelConfig.freeKm} km gratis = ${chargeableDistance.toFixed(1)} km × ${travelConfig.costPerKm.toFixed(2)}€`;
                } else {
                    travelMessage = `Kostenlos (unter ${travelConfig.freeKm} km)`;
                }
                validationIcon = '✅';
                validationMessage = 'Ihre Adresse liegt in unserem Servicegebiet.';
                validationColor = '#28a745';
            } else {
                // Entfernung zu groß
                validationIcon = '❌';
                validationMessage = `Bei Leistungen ab ${travelConfig.minServiceAmount.toFixed(2)}€ beträgt die maximale Entfernung ${travelConfig.maxDistanceLarge} km. Ihre Entfernung: ${travelConfig.distance.toFixed(1)} km.`;
                validationColor = '#dc3545';
                canContinue = false;
            }
        } else if (servicesTotal > 0) {
            // Leistungen < 59.90€: Anfahrt gratis bis 10km
            if (travelConfig.distance <= travelConfig.maxDistanceSmall) {
                travelMessage = 'Kostenlos bei Leistungen unter ' + travelConfig.minServiceAmount.toFixed(2) + '€';
                validationIcon = '✅';
                validationMessage = 'Anfahrt ist bei dieser Leistungssumme kostenlos.';
                validationColor = '#28a745';
            } else {
                // Entfernung zu groß für kleine Leistungssumme
                validationIcon = '⚠️';
                validationMessage = `Bei Leistungen unter ${travelConfig.minServiceAmount.toFixed(2)}€ beträgt die maximale Entfernung ${travelConfig.maxDistanceSmall} km. ` +
                    `Ihre Entfernung: ${travelConfig.distance.toFixed(1)} km. ` +
                    `Bitte wählen Sie zusätzliche Leistungen (Gesamtsumme mind. ${travelConfig.minServiceAmount.toFixed(2)}€).`;
                validationColor = '#ffc107';
                canContinue = false;
            }
        } else {
            // Keine Leistungen ausgewählt
            travelMessage = 'Wählen Sie Leistungen aus';
            canContinue = false;
        }

        // UI aktualisieren
        document.getElementById('services-total').textContent = servicesTotal.toFixed(2).replace('.', ',') + ' €';
        document.getElementById('travel-total').textContent = travelCost.toFixed(2).replace('.', ',') + ' €';
        document.getElementById('total-price').textContent = (servicesTotal + travelCost).toFixed(2).replace('.', ',') + ' €';

        // Anfahrtskosten-Details
        document.getElementById('travel-cost-display').textContent = travelCost.toFixed(2).replace('.', ',') + ' €';
        document.getElementById('travel-cost-details').textContent = travelMessage;

        // Validierungsnachricht
        const validationDiv = document.getElementById('distance-validation-message');
        if (validationMessage) {
            validationDiv.style.display = 'block';
            validationDiv.style.backgroundColor = validationColor + '20';
            validationDiv.style.border = '1px solid ' + validationColor;
            document.getElementById('validation-icon').textContent = validationIcon + ' ';
            document.getElementById('validation-text').textContent = validationMessage;
        } else {
            validationDiv.style.display = 'none';
        }

        // Continue-Button aktivieren/deaktivieren
        document.getElementById('continue-btn').disabled = !canContinue || servicesTotal === 0;
    }

    // Keyboard-Support für Service-Cards
    document.querySelectorAll('.service-card').forEach(card => {
        card.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleServiceSelection(this);
            }
        });
    });

    // Initial Check für vorausgewählte Services
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($_SESSION['booking']['services'])): ?>
            <?php foreach ($_SESSION['booking']['services'] as $serviceId): ?>
                const card = document.querySelector(`[data-service-id="${<?= $serviceId ?>}"]`);
                if (card) {
                    card.classList.add('selected');
                    card.querySelector('input[type="checkbox"]').checked = true;
                }
            <?php endforeach; ?>
            updatePriceCalculation();
        <?php endif; ?>
    });

    // Form-Validierung
    document.getElementById('services-form').addEventListener('submit', function(e) {
        const selectedServices = document.querySelectorAll('.service-card.selected');
        if (selectedServices.length === 0) {
            e.preventDefault();
            alert('Bitte wählen Sie mindestens eine Leistung aus.');
            return false;
        }

        // Prüfe nochmal die Entfernungsvalidierung
        let servicesTotal = 0;
        selectedServices.forEach(card => {
            servicesTotal += parseFloat(card.dataset.servicePrice);
        });

        if (servicesTotal < travelConfig.minServiceAmount && travelConfig.distance > travelConfig.maxDistanceSmall) {
            e.preventDefault();
            alert(`Bei einer Leistungssumme unter ${travelConfig.minServiceAmount.toFixed(2)}€ ist die maximale Entfernung ${travelConfig.maxDistanceSmall} km. ` +
                `Bitte wählen Sie zusätzliche Leistungen oder kontaktieren Sie uns direkt.`);
            return false;
        }

        if (servicesTotal >= travelConfig.minServiceAmount && travelConfig.distance > travelConfig.maxDistanceLarge) {
            e.preventDefault();
            alert(`Die maximale Entfernung für Buchungen beträgt ${travelConfig.maxDistanceLarge} km. ` +
                `Bitte kontaktieren Sie uns direkt für Ihre Anfrage.`);
            return false;
        }
    });
</script>