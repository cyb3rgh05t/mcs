<?php
// views/step3_services.php - Leistungsauswahl mit Preisberechnung
$services = $bookingManager->getAllServices();
?>

<h2 class="step-title">Wählen Sie Ihre Leistungen</h2>
<p class="step-description">Welche Services sollen wir für Sie durchführen? Sie können mehrere Leistungen auswählen.</p>

<form method="POST" action="?step=3" id="services-form">
    <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
    <?= SecurityManager::generateHoneypot() ?>

    <div class="services-grid">
        <?php foreach ($services as $service): ?>
            <div class="service-card" onclick="toggleService(<?= $service['id'] ?>, this)" role="button" tabindex="0">
                <input type="checkbox" name="services[]" value="<?= $service['id'] ?>" id="service_<?= $service['id'] ?>" style="display: none;">

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
            <div class="summary-item">
                <span class="summary-label">Zwischensumme:</span>
                <span class="summary-value" id="services-total">0,00 €</span>
            </div>
        </div>
    </div>

    <div class="btn-group">
        <a href="?step=2" class="btn-secondary">Zurück</a>
        <button type="submit" class="btn-primary" id="continue-btn" disabled>Weiter zu den Kundendaten</button>
    </div>
</form>

<script>
    const serviceData = <?= json_encode($services) ?>;
    let selectedServices = [];

    function toggleService(serviceId, element) {
        const checkbox = element.querySelector('input[type="checkbox"]');
        const isSelected = checkbox.checked;

        if (isSelected) {
            checkbox.checked = false;
            element.classList.remove('selected');
            selectedServices = selectedServices.filter(id => id !== serviceId);
        } else {
            checkbox.checked = true;
            element.classList.add('selected');
            selectedServices.push(serviceId);
        }

        updateServicesSummary();
    }

    function updateServicesSummary() {
        const summaryEl = document.getElementById('services-summary');
        const listEl = document.getElementById('selected-services-list');
        const totalEl = document.getElementById('services-total');
        const continueBtn = document.getElementById('continue-btn');

        if (selectedServices.length === 0) {
            summaryEl.style.display = 'none';
            continueBtn.disabled = true;
            return;
        }

        summaryEl.style.display = 'block';
        continueBtn.disabled = false;

        let html = '';
        let total = 0;

        selectedServices.forEach(serviceId => {
            const service = serviceData.find(s => s.id == serviceId);
            if (service) {
                html += `
                <div class="summary-item">
                    <span class="summary-label">${service.name}</span>
                    <span class="summary-value">${parseFloat(service.price).toFixed(2).replace('.', ',')} €</span>
                </div>
            `;
                total += parseFloat(service.price);
            }
        });

        listEl.innerHTML = html;
        totalEl.textContent = total.toFixed(2).replace('.', ',') + ' €';
    }
</script>