<?php
// views/step3_customer.php - Modernisierte Kundendatenerfassung
// Die config.php wird bereits in index.php geladen, daher keine erneute Einbindung nötig
?>

<h2 class="step-title">Ihre Kontaktdaten</h2>
<p class="step-description">Damit wir Sie erreichen können und die Anfahrt planen, benötigen wir Ihre Daten.</p>

<form method="POST" action="?step=3" class="customer-form" id="customer-form">
    <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
    <?= SecurityManager::generateHoneypot() ?>

    <div class="form-section">
        <div class="form-group">
            <label for="name" class="form-label">Vor- und Nachname *</label>
            <input type="text"
                id="name"
                name="name"
                class="form-input"
                value="<?= htmlspecialchars($_SESSION['booking']['customer']['name'] ?? '') ?>"
                placeholder="Max Mustermann"
                required
                maxlength="100">
        </div>

        <div class="form-group">
            <label for="email" class="form-label">E-Mail-Adresse *</label>
            <input type="email"
                id="email"
                name="email"
                class="form-input"
                value="<?= htmlspecialchars($_SESSION['booking']['customer']['email'] ?? '') ?>"
                placeholder="max@beispiel.de"
                required
                maxlength="255">
        </div>

        <div class="form-group">
            <label for="phone" class="form-label">Telefonnummer *</label>
            <input type="tel"
                id="phone"
                name="phone"
                class="form-input"
                value="<?= htmlspecialchars($_SESSION['booking']['customer']['phone'] ?? '') ?>"
                placeholder="+49 123 456789"
                required
                maxlength="50">
        </div>

        <div class="form-group">
            <label for="address" class="form-label">Adresse für Anfahrt *</label>
            <input type="text"
                id="address"
                name="address"
                class="form-input"
                value="<?= htmlspecialchars($_SESSION['booking']['customer']['address'] ?? '') ?>"
                placeholder="Musterstraße 123, 12345 Musterstadt"
                required
                maxlength="300"
                onblur="calculateDistance()">
            <small class="form-help">
                Vollständige Adresse für die Anfahrtskostenberechnung (max. <?= defined('TRAVEL_ABSOLUTE_MAX_DISTANCE') ? TRAVEL_ABSOLUTE_MAX_DISTANCE : 35 ?> km von <?= defined('BUSINESS_LOCATION') ? BUSINESS_LOCATION : 'Herne' ?>)
            </small>
        </div>

        <div class="form-group">
            <label for="notes" class="form-label">Zusätzliche Hinweise (optional)</label>
            <textarea id="notes"
                name="notes"
                class="form-input"
                rows="3"
                placeholder="z.B. Hinterhof, 2. Stock, besondere Parkplatzsituation..."
                maxlength="500"><?= htmlspecialchars($_SESSION['booking']['customer']['notes'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- Entfernungsanzeige -->
    <div class="distance-info" id="distance-display" style="display: none;">
        <h4>📍 Entfernungsberechnung</h4>
        <div class="distance-grid">
            <div>
                <strong>Ihre Adresse:</strong><br>
                <span id="calculated-address">-</span>
            </div>
            <div>
                <strong>Entfernung:</strong><br>
                <span id="calculated-distance" class="distance-value">Wird berechnet...</span>
            </div>
        </div>
        <div id="distance-error" class="form-error" style="display: none; margin-top: 10px;"></div>
    </div>

    <div class="btn-group">
        <a href="?step=2" class="btn-secondary">Zurück zur Zeitauswahl</a>
        <button type="submit" class="btn-primary" id="continue-btn">Weiter zu den Leistungen</button>
    </div>
</form>

<style>
    .customer-form {
        max-width: 600px;
        margin: 0 auto;
    }

    .form-section {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid var(--clr-surface-a30);
        border-radius: var(--radius-sm);
        padding: 30px;
        margin-bottom: 30px;
        backdrop-filter: blur(10px);
    }

    .form-help {
        display: block;
        color: var(--clr-primary-a50);
        font-size: 12px;
        margin-top: 5px;
    }

    .distance-value {
        font-size: 20px;
        font-weight: 600;
        color: var(--clr-warning);
    }

    .form-input:focus {
        outline: none;
        border-color: var(--clr-primary-a0);
        background: rgba(0, 0, 0, 0.7);
        box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
    }

    .form-input.error {
        border-color: var(--clr-error);
        background: rgba(244, 67, 54, 0.05);
    }

    .form-input.success {
        border-color: var(--clr-success);
        background: rgba(76, 175, 80, 0.05);
    }

    textarea.form-input {
        resize: vertical;
        min-height: 80px;
    }
</style>

<script>
    let isCalculating = false;
    let lastCalculatedAddress = '';

    function calculateDistance() {
        const addressInput = document.getElementById('address');
        const address = addressInput.value.trim();

        if (!address || address === lastCalculatedAddress || isCalculating) {
            return;
        }

        if (address.length < 10) {
            return;
        }

        isCalculating = true;
        lastCalculatedAddress = address;

        const displayDiv = document.getElementById('distance-display');
        const distanceSpan = document.getElementById('calculated-distance');
        const addressSpan = document.getElementById('calculated-address');
        const errorDiv = document.getElementById('distance-error');
        const continueBtn = document.getElementById('continue-btn');

        displayDiv.style.display = 'block';
        distanceSpan.innerHTML = '<span class="loading">Berechne Entfernung...</span>';
        errorDiv.style.display = 'none';
        addressInput.classList.remove('error', 'success');

        // AJAX-Call zur Entfernungsberechnung
        fetch('api/distance.php', { // Deine existierende API!
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    address: address
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const distance = parseFloat(data.distance_km);

                    addressSpan.textContent = address;
                    distanceSpan.innerHTML = `<strong>${distance.toFixed(1)} km</strong>`;

                    // Check max distance based on your config
                    const maxDistanceSmall = <?= defined('TRAVEL_MAX_DISTANCE_SMALL') ? TRAVEL_MAX_DISTANCE_SMALL : 10 ?>;
                    const maxDistanceLarge = <?= defined('TRAVEL_MAX_DISTANCE_LARGE') ? TRAVEL_MAX_DISTANCE_LARGE : 30 ?>;
                    const maxDistance = <?= defined('TRAVEL_ABSOLUTE_MAX_DISTANCE') ? TRAVEL_ABSOLUTE_MAX_DISTANCE : 35 ?>;

                    if (distance > maxDistance) {
                        distanceSpan.innerHTML += ' <span class="text-error">(max. ' + maxDistance + ' km)</span>';
                        errorDiv.textContent = 'Die Adresse liegt außerhalb unseres Servicegebiets (max. ' + maxDistance + ' km).';
                        errorDiv.style.display = 'block';
                        addressInput.classList.add('error');
                        continueBtn.disabled = true;
                    } else {
                        addressInput.classList.add('success');
                        continueBtn.disabled = false;

                        // Zeige Kostenhinweis basierend auf deiner neuen Logik
                        let costHint = '';
                        if (distance <= <?= defined('TRAVEL_FREE_KM') ? TRAVEL_FREE_KM : 10 ?>) {
                            costHint = '<span class="text-success">Erste <?= defined('TRAVEL_FREE_KM') ? TRAVEL_FREE_KM : 10 ?> km kostenlos!</span>';
                        } else {
                            costHint = '<span class="text-info">Anfahrt: ' + (distance - <?= defined('TRAVEL_FREE_KM') ? TRAVEL_FREE_KM : 10 ?>).toFixed(1) + ' km × <?= defined('TRAVEL_COST_PER_KM') ? number_format(TRAVEL_COST_PER_KM, 2, ',', '.') : '2,00' ?> €</span>';
                        }
                        distanceSpan.innerHTML += '<br>' + costHint;

                        if (data.duration) {
                            distanceSpan.innerHTML += '<br><small class="text-muted">Fahrzeit: ' + data.duration + '</small>';
                        }

                        if (data.estimated) {
                            distanceSpan.innerHTML += '<br><small class="text-warning">⚠ Geschätzte Werte</small>';
                        }
                    }
                } else if (data.error) {
                    distanceSpan.innerHTML = '<span class="text-error">Berechnung fehlgeschlagen</span>';
                    errorDiv.textContent = data.error || 'Adresse konnte nicht verifiziert werden.';
                    errorDiv.style.display = 'block';
                    addressInput.classList.add('error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                distanceSpan.innerHTML = '<span class="text-error">Fehler bei der Berechnung</span>';
                errorDiv.textContent = 'Technischer Fehler. Bitte versuchen Sie es später erneut.';
                errorDiv.style.display = 'block';
            })
            .finally(() => {
                isCalculating = false;
            });
    }

    // Form validation
    document.getElementById('customer-form').addEventListener('submit', function(e) {
        const requiredFields = this.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
            } else {
                field.classList.remove('error');
            }
        });

        if (!isValid) {
            e.preventDefault();
            alert('Bitte füllen Sie alle Pflichtfelder aus.');
            return false;
        }

        // Check if distance was calculated
        const addressInput = document.getElementById('address');
        if (addressInput.classList.contains('error')) {
            e.preventDefault();
            alert('Bitte geben Sie eine gültige Adresse innerhalb unseres Servicegebiets ein.');
            return false;
        }
    });

    // Real-time validation
    document.querySelectorAll('.form-input[required]').forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value.trim()) {
                this.classList.remove('error');
                this.classList.add('success');
            } else {
                this.classList.add('error');
                this.classList.remove('success');
            }
        });
    });

    // Email validation
    document.getElementById('email').addEventListener('blur', function() {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (emailRegex.test(this.value)) {
            this.classList.remove('error');
            this.classList.add('success');
        } else if (this.value) {
            this.classList.add('error');
            this.classList.remove('success');
        }
    });

    // Phone validation
    document.getElementById('phone').addEventListener('blur', function() {
        const phoneRegex = /^[\d\s\+\-\(\)]+$/;
        if (phoneRegex.test(this.value) && this.value.length >= 10) {
            this.classList.remove('error');
            this.classList.add('success');
        } else if (this.value) {
            this.classList.add('error');
            this.classList.remove('success');
        }
    });

    // Check for pre-filled address on page load
    document.addEventListener('DOMContentLoaded', function() {
        const addressInput = document.getElementById('address');
        if (addressInput.value && addressInput.value.length > 10) {
            calculateDistance();
        }
    });
</script>