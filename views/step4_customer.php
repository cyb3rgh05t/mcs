<?php
// views/step4_customer.php - Kundendatenerfassung mit Validierung
?>

<h2 class="step-title">Ihre Kontaktdaten</h2>
<p class="step-description">Damit wir Sie erreichen können und die Anfahrt planen, benötigen wir Ihre Daten.</p>

<form method="POST" action="?step=4" class="customer-form" id="customer-form">
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
            placeholder="Musterstraße 123, 12345 Musterstadt" required maxlength="300">
        <small style="color: #ccc; font-size: 14px; margin-top: 5px; display: block;">
            Vollständige Adresse für die Anfahrtskostenberechnung
        </small>
    </div>

    <div class="form-group" style="grid-column: 1 / -1;">
        <label for="notes" class="form-label">Anmerkungen (optional)</label>
        <textarea id="notes" name="notes" class="form-input" rows="3"
            placeholder="Besondere Wünsche, Zufahrthinweise, etc." maxlength="500"></textarea>
    </div>

    <div class="form-group" style="grid-column: 1 / -1;">
        <label class="checkbox-container">
            <input type="checkbox" id="privacy" name="privacy" required>
            <span class="checkmark"></span>
            Ich stimme der <a href="#" target="_blank" style="color: #ffffff;">Datenschutzerklärung</a> zu und bin mit der Verarbeitung meiner Daten einverstanden. *
        </label>
    </div>

    <div class="btn-group" style="grid-column: 1 / -1;">
        <a href="?step=3" class="btn-secondary">Zurück</a>
        <button type="submit" class="btn-primary" id="submit-customer-data">Weiter zur Zusammenfassung</button>
    </div>
</form>

<style>
    .checkbox-container {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        cursor: pointer;
        font-size: 14px;
        line-height: 1.4;
    }

    .checkbox-container input[type="checkbox"] {
        margin: 0;
        width: 18px;
        height: 18px;
        accent-color: #ffffff;
    }

    .form-group .form-input:focus {
        border-color: #ffffff;
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
    }

    .form-input:invalid {
        border-color: #dc3545;
    }

    .form-input:valid {
        border-color: #28a745;
    }
</style>