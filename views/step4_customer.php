<?php
// views/step4_customer.php
?>

<h2 class="step-title">Ihre Kontaktdaten</h2>
<p class="step-description">Damit wir Sie erreichen können und die Anfahrt planen, benötigen wir Ihre Daten.</p>

<form method="POST" action="?step=4" class="customer-form">
    <div class="form-group">
        <label for="name" class="form-label">Vor- und Nachname *</label>
        <input type="text" id="name" name="name" class="form-input"
            value="<?= htmlspecialchars($_SESSION['booking']['customer']['name'] ?? '') ?>"
            placeholder="Max Mustermann" required>
    </div>

    <div class="form-group">
        <label for="email" class="form-label">E-Mail-Adresse *</label>
        <input type="email" id="email" name="email" class="form-input"
            value="<?= htmlspecialchars($_SESSION['booking']['customer']['email'] ?? '') ?>"
            placeholder="max@beispiel.de" required>
    </div>

    <div class="form-group">
        <label for="phone" class="form-label">Telefonnummer *</label>
        <input type="tel" id="phone" name="phone" class="form-input"
            value="<?= htmlspecialchars($_SESSION['booking']['customer']['phone'] ?? '') ?>"
            placeholder="+49 123 456789" required>
    </div>

    <div class="form-group">
        <label for="address" class="form-label">Adresse für Anfahrt *</label>
        <input type="text" id="address" name="address" class="form-input"
            value="<?= htmlspecialchars($_SESSION['booking']['customer']['address'] ?? '') ?>"
            placeholder="Musterstraße 123, 12345 Musterstadt" required>
        <small style="color: #ccc; font-size: 14px; margin-top: 5px; display: block;">
            Vollständige Adresse für die Anfahrtskostenberechnung
        </small>
    </div>

    <div class="form-group" style="grid-column: 1 / -1;">
        <label for="notes" class="form-label">Anmerkungen (optional)</label>
        <textarea id="notes" name="notes" class="form-input" rows="3"
            placeholder="Besondere Wünsche, Zufahrthinweise, etc."></textarea>
    </div>

    <div class="btn-group" style="grid-column: 1 / -1;">
        <a href="?step=3" class="btn-secondary">Zurück</a>
        <button type="submit" class="btn-primary">Weiter zur Zusammenfassung</button>
    </div>
</form>