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
            <strong>Entfernung:</strong> <?= number_format($customerDistance, 1) ?> km<br>
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
    <div class="summary-section" id="selected-services-summary" style="display: none;">
        <div class="summary-title">Ihre Auswahl</div>
        <div id="selected-services-list"></div>
        <div class="summary-item" style="margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--clr-surface-a30);">
            <span class="summary-label">Services:</span>
            <span class="summary-value" id="services-subtotal">0,00 €</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Anfahrtskosten:</span>
            <span class="summary-value" id="travel-cost-summary">0,00 €</span>
        </div>
        <div class="summary-item" style="font-size: 20px; font-weight: 600;">
            <span class="summary-label">Gesamtpreis:</span>
            <span class="summary-value total-price" id="total-price">0,00 €</span>
        </div>
    </div>

    <div class="btn-group">
        <a href="?step=3" class="btn-secondary">Zurück</a>
        <button type="submit" class="btn-primary" id="continue-btn" disabled>Weiter zur Zusammenfassung</button>
    </div>
</form>

<script>
    const customerDistance = <?= $customerDistance ?>;
    let selectedServices = [];

    function toggleServiceSelection(card) {
        const checkbox = card.querySelector('input[type="checkbox"]');
        const serviceId = card.dataset.serviceId;
        const serviceName = card.dataset.serviceName;
        const servicePrice = parseFloat(card.dataset.servicePrice);
        const serviceDuration = parseInt(card.dataset.serviceDuration);

        checkbox.checked = !checkbox.checked;
        card.classList.toggle('selected', checkbox.checked);

        if (checkbox.checked) {
            selectedServices.push({
                id: serviceId,
                name: serviceName,
                price: servicePrice,
                duration: serviceDuration
            });
        } else {
            selectedServices = selectedServices.filter(s => s.id !== serviceId);
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

        // Anfahrtskosten-Logik
        let travelCost = 0;
        let travelCostDetails = '';

        if (customerDistance <= 5) {
            travelCost = 0;
            travelCostDetails = 'Kostenlos (bis 5 km)';
        } else if (servicesTotal >= 100 && customerDistance <= 15) {
            travelCost = 0;
            travelCostDetails = 'Kostenlos (ab 100€ bis 15 km)';
        } else if (customerDistance <= 30) {
            travelCost = customerDistance * 1.50;
            travelCostDetails = `${customerDistance.toFixed(1)} km × 1,50 €/km`;
        } else {
            travelCost = 30 * 1.50 + (customerDistance - 30) * 2.00;
            travelCostDetails = `Basis: 45€ + ${(customerDistance - 30).toFixed(1)} km × 2,00 €/km`;
        }

        const totalPrice = servicesTotal + travelCost;

        // Update Display
        document.getElementById('services-subtotal').textContent = servicesTotal.toFixed(2).replace('.', ',') + ' €';
        document.getElementById('travel-cost-summary').textContent = travelCost.toFixed(2).replace('.', ',') + ' €';
        document.getElementById('total-price').textContent = totalPrice.toFixed(2).replace('.', ',') + ' €';

        // Update Anfahrtskosten-Info
        document.getElementById('travel-cost-display').textContent = travelCost.toFixed(2).replace('.', ',') + ' €';
        document.getElementById('travel-cost-details').textContent = travelCostDetails;
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