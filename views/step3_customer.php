<?php
// views/step3_customer.php - Kundendatenerfassung mit Entfernungsvalidierung
?>

<h2 class="step-title">Ihre Kontaktdaten</h2>
<p class="step-description">Damit wir Sie erreichen können und die Anfahrt planen, benötigen wir Ihre Daten.</p>

<form method="POST" action="?step=3" class="customer-form" id="customer-form">
    <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
    <?= SecurityManager::generateHoneypot() ?>

    <div class="form-group">
        <label for="name" class="form-label">Vor- und Nachname *</label>
        <input type="text" id="name" name="name" class="form-input"
            value="<?= htmlspecialchars($_SESSION['booking']['customer']['name'] ?? '') ?>"
            placeholder="Max Mustermann" required maxlength="100">
    </div>

    <div class="form-group">
        <label for="email" class="form-label">E-Mail-Adresse *</label>
        <input type="email" id="email" name="email" class="form-input"
            value="<?= htmlspecialchars($_SESSION['booking']['customer']['email'] ?? '') ?>"
            placeholder="max@beispiel.de" required maxlength="255">
    </div>

    <div class="form-group">
        <label for="phone" class="form-label">Telefonnummer *</label>
        <input type="tel" id="phone" name="phone" class="form-input"
            value="<?= htmlspecialchars($_SESSION['booking']['customer']['phone'] ?? '') ?>"
            placeholder="+49 123 456789" required maxlength="50">
    </div>

    <div class="form-group">
        <label for="address" class="form-label">Adresse für Anfahrt *</label>
        <input type="text" id="address" name="address" class="form-input"
            value="<?= htmlspecialchars($_SESSION['booking']['customer']['address'] ?? '') ?>"
            placeholder="Musterstraße 123, 12345 Musterstadt" required maxlength="300"
            onblur="calculateDistance()">
        <small style="color: #ccc; font-size: 14px; margin-top: 5px; display: block;">
            Vollständige Adresse für die Anfahrtskostenberechnung (max. <?= TRAVEL_ABSOLUTE_MAX_DISTANCE ?> km Entfernung)
        </small>

        <!-- Entfernungsanzeige -->
        <div id="distance-info" style="display: none; margin-top: 10px; padding: 15px; background: rgba(255, 107, 53, 0.1); border: 1px solid #ff6b35; border-radius: 8px;">
            <div id="distance-loading" style="display: none;">
                <span style="color: #ff6b35;">⏳ Entfernung wird berechnet...</span>
            </div>
            <div id="distance-result" style="display: none;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span>📍 Entfernung:</span>
                    <strong><span id="distance-value">0</span> km</strong>
                </div>
                <div id="distance-warning" style="display: none; color: #ffc107; margin-top: 10px;">
                    ⚠️ <span id="warning-text"></span>
                </div>
                <div id="distance-error" style="display: none; color: #dc3545; margin-top: 10px;">
                    ❌ <span id="error-text"></span>
                </div>
                <div id="distance-ok" style="display: none; color: #28a745; margin-top: 10px;">
                    ✅ Adresse liegt in unserem Servicegebiet
                </div>
            </div>
        </div>

        <input type="hidden" id="calculated_distance" name="calculated_distance" value="<?= $_SESSION['booking']['distance'] ?? 0 ?>">
    </div>

    <div class="form-group" style="grid-column: 1 / -1;">
        <label for="notes" class="form-label">Anmerkungen (optional)</label>
        <textarea id="notes" name="notes" class="form-input" rows="3"
            placeholder="Besondere Wünsche, Zufahrthinweise, etc." maxlength="500"><?= htmlspecialchars($_SESSION['booking']['customer']['notes'] ?? '') ?></textarea>
    </div>

    <div class="form-group" style="grid-column: 1 / -1;">
        <label class="checkbox-container">
            <input type="checkbox" id="privacy" name="privacy" required>
            <span class="checkmark"></span>
            Ich stimme der <a href="#" target="_blank" style="color: #ff6b35;">Datenschutzerklärung</a> zu und bin mit der Verarbeitung meiner Daten einverstanden. *
        </label>
    </div>

    <!-- Informationsbox über Anfahrtskosten -->
    <div style="background: rgba(30, 30, 30, 0.9); border: 1px solid #4e4e4e; border-radius: 8px; padding: 20px; margin: 20px 0; grid-column: 1 / -1;">
        <h4 style="color: #ff6b35; margin-bottom: 15px;">💡 Information zu Anfahrtskosten</h4>
        <ul style="margin: 0; padding-left: 20px; color: #ccc;">
            <li style="margin-bottom: 8px;">
                <strong>Leistungen unter <?= number_format(TRAVEL_MIN_SERVICE_AMOUNT, 2) ?>€:</strong>
                Kostenlose Anfahrt bis <?= TRAVEL_MAX_DISTANCE_SMALL ?> km
            </li>
            <li style="margin-bottom: 8px;">
                <strong>Leistungen ab <?= number_format(TRAVEL_MIN_SERVICE_AMOUNT, 2) ?>€:</strong>
                Die ersten <?= TRAVEL_FREE_KM ?> km sind kostenlos, danach <?= number_format(TRAVEL_COST_PER_KM, 2) ?>€ pro km (max. <?= TRAVEL_MAX_DISTANCE_LARGE ?> km)
            </li>
            <li>
                <strong>Maximale Entfernung:</strong> <?= TRAVEL_ABSOLUTE_MAX_DISTANCE ?> km
            </li>
        </ul>
    </div>

    <div class="btn-group" style="grid-column: 1 / -1;">
        <a href="?step=2" class="btn-secondary">Zurück</a>
        <button type="submit" class="btn-primary" id="submit-customer-data">Weiter zu den Leistungen</button>
    </div>
</form>

<script>
    // Entfernungsberechnung
    function calculateDistance() {
        const addressInput = document.getElementById('address');
        const address = addressInput.value.trim();

        if (address.length < 10) return;

        // UI-Elemente
        const distanceInfo = document.getElementById('distance-info');
        const distanceLoading = document.getElementById('distance-loading');
        const distanceResult = document.getElementById('distance-result');
        const distanceValue = document.getElementById('distance-value');
        const distanceWarning = document.getElementById('distance-warning');
        const distanceError = document.getElementById('distance-error');
        const distanceOk = document.getElementById('distance-ok');
        const warningText = document.getElementById('warning-text');
        const errorText = document.getElementById('error-text');
        const calculatedDistance = document.getElementById('calculated_distance');
        const submitButton = document.getElementById('submit-customer-data');

        // Reset UI
        distanceInfo.style.display = 'block';
        distanceLoading.style.display = 'block';
        distanceResult.style.display = 'none';
        distanceWarning.style.display = 'none';
        distanceError.style.display = 'none';
        distanceOk.style.display = 'none';

        // Google Maps API verfügbar?
        if (window.GOOGLE_MAPS_API_KEY && window.google && window.google.maps) {
            const service = new google.maps.DistanceMatrixService();
            const businessLocation = '<?= BUSINESS_ADDRESS ?>';

            service.getDistanceMatrix({
                origins: [businessLocation],
                destinations: [address],
                travelMode: google.maps.TravelMode.DRIVING,
                unitSystem: google.maps.UnitSystem.METRIC,
                avoidHighways: false,
                avoidTolls: false
            }, function(response, status) {
                distanceLoading.style.display = 'none';
                distanceResult.style.display = 'block';

                if (status === 'OK' && response.rows[0].elements[0].status === 'OK') {
                    const result = response.rows[0].elements[0];
                    const distanceKm = Math.round(result.distance.value / 1000 * 10) / 10;

                    distanceValue.textContent = distanceKm;
                    calculatedDistance.value = distanceKm;

                    // Validierung
                    if (distanceKm > window.TRAVEL_CONFIG.absoluteMaxDistance) {
                        distanceError.style.display = 'block';
                        errorText.textContent = `Ihre Adresse liegt außerhalb unseres Servicegebiets (max. ${window.TRAVEL_CONFIG.absoluteMaxDistance} km).`;
                        submitButton.disabled = true;
                    } else if (distanceKm > window.TRAVEL_CONFIG.maxDistanceLarge) {
                        distanceWarning.style.display = 'block';
                        warningText.textContent = `Diese Entfernung liegt an der Grenze unseres Servicegebiets. Bitte kontaktieren Sie uns zur Bestätigung.`;
                        submitButton.disabled = false;
                    } else {
                        distanceOk.style.display = 'block';
                        submitButton.disabled = false;
                    }
                } else {
                    // Fallback bei Fehler
                    fallbackDistanceCalculation(address);
                }
            });
        } else {
            // Fallback ohne Google Maps
            fallbackDistanceCalculation(address);
        }
    }

    // Fallback-Berechnung über Backend
    function fallbackDistanceCalculation(address) {
        fetch('/api/distance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN
                },
                body: JSON.stringify({
                    address: address
                })
            })
            .then(response => response.json())
            .then(data => {
                const distanceLoading = document.getElementById('distance-loading');
                const distanceResult = document.getElementById('distance-result');
                const distanceValue = document.getElementById('distance-value');
                const distanceWarning = document.getElementById('distance-warning');
                const distanceError = document.getElementById('distance-error');
                const distanceOk = document.getElementById('distance-ok');
                const warningText = document.getElementById('warning-text');
                const errorText = document.getElementById('error-text');
                const calculatedDistance = document.getElementById('calculated_distance');
                const submitButton = document.getElementById('submit-customer-data');

                distanceLoading.style.display = 'none';
                distanceResult.style.display = 'block';

                if (data.success) {
                    const distanceKm = data.distance_km;
                    distanceValue.textContent = distanceKm;
                    calculatedDistance.value = distanceKm;

                    if (data.estimated) {
                        distanceWarning.style.display = 'block';
                        warningText.textContent = 'Geschätzte Entfernung. Die genaue Entfernung wird bei der Buchungsbestätigung ermittelt.';
                    }

                    // Validierung
                    if (distanceKm > window.TRAVEL_CONFIG.absoluteMaxDistance) {
                        distanceError.style.display = 'block';
                        errorText.textContent = `Ihre Adresse liegt außerhalb unseres Servicegebiets (max. ${window.TRAVEL_CONFIG.absoluteMaxDistance} km).`;
                        submitButton.disabled = true;
                    } else {
                        distanceOk.style.display = 'block';
                        submitButton.disabled = false;
                    }
                } else {
                    // Bei Fehler: Schätzung verwenden
                    const estimatedDistance = 15; // Standard-Schätzung
                    distanceValue.textContent = estimatedDistance;
                    calculatedDistance.value = estimatedDistance;
                    distanceWarning.style.display = 'block';
                    warningText.textContent = 'Entfernung konnte nicht automatisch berechnet werden. Wir verwenden eine Schätzung.';
                    submitButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Fehler bei Entfernungsberechnung:', error);
                // Fallback: Erlaube Fortfahren mit Schätzung
                document.getElementById('distance-loading').style.display = 'none';
                document.getElementById('distance-result').style.display = 'block';
                document.getElementById('distance-value').textContent = '15';
                document.getElementById('calculated_distance').value = 15;
                document.getElementById('distance-warning').style.display = 'block';
                document.getElementById('warning-text').textContent = 'Entfernung wird manuell berechnet.';
                document.getElementById('submit-customer-data').disabled = false;
            });
    }

    // Auto-Berechnung bei vorhandener Adresse
    document.addEventListener('DOMContentLoaded', function() {
        const addressInput = document.getElementById('address');
        if (addressInput && addressInput.value.length > 10) {
            calculateDistance();
        }
    });

    // Form-Validierung
    document.getElementById('customer-form').addEventListener('submit', function(e) {
        const distance = parseFloat(document.getElementById('calculated_distance').value);
        if (distance > window.TRAVEL_CONFIG.absoluteMaxDistance) {
            e.preventDefault();
            alert('Ihre Adresse liegt außerhalb unseres Servicegebiets.');
            return false;
        }
    });
</script>