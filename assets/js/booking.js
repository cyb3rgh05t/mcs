// assets/js/booking.js

document.addEventListener("DOMContentLoaded", function () {
  // Form Validation
  initFormValidation();

  // Google Maps asynchron laden
  loadGoogleMapsAPI();

  // Loading States
  initLoadingStates();

  // Mobile Navigation
  initMobileNav();

  // Auto-save Form Data
  autoSaveFormData();
});

// Google Maps API asynchron laden
function loadGoogleMapsAPI() {
  // API-Key aus PHP holen (falls verfügbar)
  const apiKey = window.GOOGLE_MAPS_API_KEY || null;

  // Nur laden wenn API-Key konfiguriert ist
  if (apiKey && apiKey !== "YOUR_API_KEY" && apiKey !== "") {
    const script = document.createElement("script");
    script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places&loading=async&callback=initGoogleMaps`;
    script.async = true;
    script.defer = true;

    // Error handling für API-Ladung
    script.onerror = function () {
      console.warn("Google Maps API konnte nicht geladen werden");
      showGoogleMapsError();
    };

    document.head.appendChild(script);
  } else {
    console.info(
      "Google Maps API-Key nicht konfiguriert. Adress-Autocomplete ist deaktiviert."
    );
    showAPIKeyInfo();
  }
}

// Callback-Funktion für Google Maps API
window.initGoogleMaps = function () {
  console.log("Google Maps API erfolgreich geladen");
  initAddressAutocomplete();
};

// API-Key Info anzeigen (nur für Entwicklung)
function showAPIKeyInfo() {
  const addressInput = document.getElementById("address");
  if (addressInput && window.location.hostname === "localhost") {
    const infoDiv = document.createElement("div");
    infoDiv.style.cssText =
      "background: rgba(255, 193, 7, 0.1); border: 1px solid #ffc107; color: #856404; padding: 10px; margin-top: 10px; border-radius: 5px; font-size: 14px;";
    infoDiv.innerHTML =
      "<strong>Info:</strong> Google Maps API-Key nicht konfiguriert. Adress-Autocomplete ist deaktiviert.";
    addressInput.parentNode.appendChild(infoDiv);
  }
}

// Google Maps Fehler anzeigen
function showGoogleMapsError() {
  const addressInput = document.getElementById("address");
  if (addressInput) {
    const errorDiv = document.createElement("div");
    errorDiv.style.cssText =
      "background: rgba(220, 53, 69, 0.1); border: 1px solid #dc3545; color: #721c24; padding: 10px; margin-top: 10px; border-radius: 5px; font-size: 14px;";
    errorDiv.innerHTML =
      "<strong>Warnung:</strong> Google Maps konnte nicht geladen werden. Entfernungsberechnung ist eingeschränkt verfügbar.";
    addressInput.parentNode.appendChild(errorDiv);
  }
}

// Form Validation
function initFormValidation() {
  const forms = document.querySelectorAll("form");

  forms.forEach((form) => {
    form.addEventListener("submit", function (e) {
      if (!validateForm(this)) {
        e.preventDefault();
      }
    });

    // Real-time validation
    const inputs = form.querySelectorAll("input[required], textarea[required]");
    inputs.forEach((input) => {
      input.addEventListener("blur", function () {
        validateField(this);
      });

      input.addEventListener("input", function () {
        if (this.classList.contains("error")) {
          validateField(this);
        }
      });
    });
  });
}

function validateForm(form) {
  const requiredFields = form.querySelectorAll(
    "input[required], textarea[required], select[required]"
  );
  let isValid = true;

  requiredFields.forEach((field) => {
    if (!validateField(field)) {
      isValid = false;
    }
  });

  return isValid;
}

function validateField(field) {
  const value = field.value.trim();
  let isValid = true;
  let errorMessage = "";

  // Required validation
  if (field.hasAttribute("required") && !value) {
    isValid = false;
    errorMessage = "Dieses Feld ist erforderlich.";
  }

  // Email validation
  if (field.type === "email" && value) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(value)) {
      isValid = false;
      errorMessage = "Bitte geben Sie eine gültige E-Mail-Adresse ein.";
    }
  }

  // Phone validation
  if (field.type === "tel" && value) {
    const phoneRegex = /^[+]?[\d\s\-\(\)]{10,}$/;
    if (!phoneRegex.test(value)) {
      isValid = false;
      errorMessage = "Bitte geben Sie eine gültige Telefonnummer ein.";
    }
  }

  // Update field styling
  updateFieldValidation(field, isValid, errorMessage);

  return isValid;
}

function updateFieldValidation(field, isValid, errorMessage) {
  const formGroup = field.closest(".form-group");

  // Remove existing error
  field.classList.remove("error");
  const existingError = formGroup.querySelector(".field-error");
  if (existingError) {
    existingError.remove();
  }

  if (!isValid) {
    field.classList.add("error");

    // Add error message
    const errorDiv = document.createElement("div");
    errorDiv.className = "field-error";
    errorDiv.style.cssText =
      "color: #ff6666; font-size: 14px; margin-top: 5px;";
    errorDiv.textContent = errorMessage;
    formGroup.appendChild(errorDiv);
  }
}

// Google Maps Address Autocomplete (optimiert)
function initAddressAutocomplete() {
  const addressInput = document.getElementById("address");

  if (!addressInput) {
    return; // Adressfeld nicht auf dieser Seite
  }

  // Prüfen ob Google Maps verfügbar ist
  if (typeof google === "undefined" || !google.maps || !google.maps.places) {
    console.warn("Google Maps Places API ist nicht verfügbar");
    enableFallbackAddressInput();
    return;
  }

  try {
    const autocomplete = new google.maps.places.Autocomplete(addressInput, {
      types: ["address"],
      componentRestrictions: { country: "DE" },
      fields: ["formatted_address", "geometry"],
    });

    // Event Listener für Autocomplete
    autocomplete.addListener("place_changed", function () {
      const place = autocomplete.getPlace();

      if (!place.formatted_address) {
        console.warn("Keine vollständige Adresse gefunden");
        return;
      }

      addressInput.value = place.formatted_address;

      // Trigger validation
      validateField(addressInput);

      // Entfernung berechnen wenn Geometrie verfügbar
      if (place.geometry && place.geometry.location) {
        calculateDistance(place.geometry.location);
      } else {
        // Fallback: Entfernung über Text-Adresse berechnen
        calculateDistanceByAddress(place.formatted_address);
      }
    });

    // Success Indikator
    const successDiv = document.createElement("div");
    successDiv.style.cssText =
      "color: #28a745; font-size: 12px; margin-top: 5px;";
    successDiv.innerHTML = "✓ Adress-Autocomplete aktiv";
    addressInput.parentNode.appendChild(successDiv);
  } catch (error) {
    console.error(
      "Fehler beim Initialisieren des Address Autocomplete:",
      error
    );
    enableFallbackAddressInput();
  }
}

// Fallback für Adresseingabe ohne Google Maps
function enableFallbackAddressInput() {
  const addressInput = document.getElementById("address");
  if (!addressInput) return;

  // Einfache Adressvalidierung
  addressInput.addEventListener("blur", function () {
    const address = this.value.trim();
    if (address.length > 10) {
      // Fallback-Entfernungsberechnung über Backend
      calculateDistanceByAddress(address);
    }
  });

  // Info für Benutzer
  const infoDiv = document.createElement("div");
  infoDiv.style.cssText = "color: #6c757d; font-size: 12px; margin-top: 5px;";
  infoDiv.innerHTML = "ℹ️ Bitte geben Sie Ihre vollständige Adresse ein";
  addressInput.parentNode.appendChild(infoDiv);
}

// Distance Calculation (verbesserte Version)
function calculateDistance(destination) {
  const businessLocation = { lat: 51.5255222, lng: 7.1401009 }; // Rheine, Deutschland

  if (typeof google === "undefined" || !google.maps) {
    console.warn("Google Maps nicht verfügbar für Entfernungsberechnung");
    return;
  }

  try {
    const service = new google.maps.DistanceMatrixService();

    service.getDistanceMatrix(
      {
        origins: [businessLocation],
        destinations: [destination],
        travelMode: google.maps.TravelMode.DRIVING,
        unitSystem: google.maps.UnitSystem.METRIC,
        avoidHighways: false,
        avoidTolls: false,
      },
      function (response, status) {
        if (status === "OK" && response.rows[0].elements[0].status === "OK") {
          const result = response.rows[0].elements[0];
          const distanceKm = result.distance.value / 1000;
          const duration = result.duration.text;

          updateDistanceInfo(distanceKm, duration);
        } else {
          console.warn("Entfernungsberechnung fehlgeschlagen:", status);
          showDistanceCalculationError();
        }
      }
    );
  } catch (error) {
    console.error("Fehler bei Google Maps Distance Matrix:", error);
    showDistanceCalculationError();
  }
}

// Fallback: Entfernungsberechnung über Backend
function calculateDistanceByAddress(address) {
  if (!address || address.length < 10) {
    return;
  }

  // Loading-Indikator anzeigen
  showDistanceLoading();

  fetch("/api/distance", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ address: address }),
  })
    .then((response) => response.json())
    .then((data) => {
      hideDistanceLoading();

      if (data.success) {
        updateDistanceInfo(data.distance_km, data.duration || "Unbekannt");
      } else {
        console.warn(
          "Backend-Entfernungsberechnung fehlgeschlagen:",
          data.error
        );
        showDistanceEstimate();
      }
    })
    .catch((error) => {
      hideDistanceLoading();
      console.error("Fehler bei Backend-Entfernungsberechnung:", error);
      showDistanceEstimate();
    });
}

// Entfernungsinfo anzeigen (verbessert)
function updateDistanceInfo(distanceKm, duration) {
  const addressGroup = document
    .getElementById("address")
    .closest(".form-group");

  // Alte Info entfernen
  const oldInfo = addressGroup.querySelector(".distance-info");
  if (oldInfo) oldInfo.remove();

  const infoDiv = document.createElement("div");
  infoDiv.className = "distance-info";
  infoDiv.style.cssText = `
        background: rgba(40, 167, 69, 0.1); 
        border: 1px solid #28a745; 
        border-radius: 5px; 
        padding: 15px; 
        margin-top: 10px; 
        font-size: 14px;
        color: #155724;
    `;

  const travelCost = distanceKm * 0.5;
  infoDiv.innerHTML = `
        <div style="display: flex; align-items: center; margin-bottom: 10px;">
            <span style="color: #28a745; margin-right: 8px;">✓</span>
            <strong>Anfahrtsinformation berechnet</strong>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px;">
            <div>Entfernung: <strong>${distanceKm.toFixed(1)} km</strong></div>
            <div>Fahrzeit: <strong>${duration}</strong></div>
            <div style="grid-column: 1 / -1; padding-top: 8px; border-top: 1px solid rgba(40, 167, 69, 0.2);">
                Anfahrtskosten: <strong style="color: #ffffff;">${travelCost.toFixed(
                  2
                )} €</strong>
            </div>
        </div>
    `;

  addressGroup.appendChild(infoDiv);
}

// Loading-Indikator für Entfernungsberechnung
function showDistanceLoading() {
  const addressGroup = document
    .getElementById("address")
    .closest(".form-group");

  const loadingDiv = document.createElement("div");
  loadingDiv.className = "distance-loading";
  loadingDiv.style.cssText = `
        background: rgba(255, 107, 53, 0.1); 
        border: 1px solid #ffffff; 
        border-radius: 5px; 
        padding: 10px; 
        margin-top: 10px; 
        font-size: 14px;
        color: #ffffff;
        text-align: center;
    `;
  loadingDiv.innerHTML = "🔄 Entfernung wird berechnet...";

  addressGroup.appendChild(loadingDiv);
}

function hideDistanceLoading() {
  const loadingDiv = document.querySelector(".distance-loading");
  if (loadingDiv) loadingDiv.remove();
}

// Fehlerbehandlung für Entfernungsberechnung
function showDistanceCalculationError() {
  const addressGroup = document
    .getElementById("address")
    .closest(".form-group");

  const errorDiv = document.createElement("div");
  errorDiv.className = "distance-error";
  errorDiv.style.cssText = `
        background: rgba(220, 53, 69, 0.1); 
        border: 1px solid #dc3545; 
        border-radius: 5px; 
        padding: 10px; 
        margin-top: 10px; 
        font-size: 14px;
        color: #721c24;
    `;
  errorDiv.innerHTML =
    "⚠️ Entfernungsberechnung nicht möglich. Anfahrtskosten werden manuell berechnet.";

  addressGroup.appendChild(errorDiv);
}

// Geschätzte Entfernung anzeigen (Fallback)
function showDistanceEstimate() {
  const addressGroup = document
    .getElementById("address")
    .closest(".form-group");

  const estimateDiv = document.createElement("div");
  estimateDiv.className = "distance-estimate";
  estimateDiv.style.cssText = `
        background: rgba(255, 193, 7, 0.1); 
        border: 1px solid #ffc107; 
        border-radius: 5px; 
        padding: 10px; 
        margin-top: 10px; 
        font-size: 14px;
        color: #856404;
    `;
  estimateDiv.innerHTML = `
        ℹ️ Entfernungsberechnung automatisch nicht verfügbar.<br>
        <small>Die genauen Anfahrtskosten werden bei der Buchungsbestätigung berechnet.</small>
    `;

  addressGroup.appendChild(estimateDiv);
}

// Loading States
function initLoadingStates() {
  const forms = document.querySelectorAll("form");

  forms.forEach((form) => {
    form.addEventListener("submit", function () {
      const submitBtn = this.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.classList.add("loading");
        submitBtn.disabled = true;
        submitBtn.textContent = "Lädt...";
      }
    });
  });
}

// Mobile Navigation
function initMobileNav() {
  const navToggle = document.createElement("button");
  navToggle.className = "nav-toggle";
  navToggle.innerHTML = "☰";
  navToggle.style.cssText = `
        display: none;
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        @media (max-width: 768px) {
            display: block;
        }
    `;

  const header = document.querySelector(".header");
  const nav = document.querySelector(".nav");

  if (header && nav) {
    header.insertBefore(navToggle, nav);

    navToggle.addEventListener("click", function () {
      nav.classList.toggle("mobile-open");
    });
  }
}

// Service Selection Functions (für step3)
function toggleService(serviceId, element) {
  const checkbox = element.querySelector('input[type="checkbox"]');
  const isSelected = checkbox.checked;

  if (isSelected) {
    checkbox.checked = false;
    element.classList.remove("selected");
  } else {
    checkbox.checked = true;
    element.classList.add("selected");
  }

  updateServicesSummary();
}

function updateServicesSummary() {
  const selectedCheckboxes = document.querySelectorAll(
    '.service-card input[type="checkbox"]:checked'
  );
  const summaryEl = document.getElementById("services-summary");
  const continueBtn = document.getElementById("continue-btn");

  if (selectedCheckboxes.length === 0) {
    if (summaryEl) summaryEl.style.display = "none";
    if (continueBtn) continueBtn.disabled = true;
    return;
  }

  if (summaryEl) summaryEl.style.display = "block";
  if (continueBtn) continueBtn.disabled = false;

  // Summary aktualisieren (wird vom PHP-Code gehandhabt)
}

// Date/Time Selection Functions
function selectDate(date, element) {
  // Alle anderen Optionen deselektieren
  document
    .querySelectorAll(".date-option")
    .forEach((opt) => opt.classList.remove("selected"));

  // Aktuelle Option selektieren
  element.classList.add("selected");

  // Hidden field setzen
  const hiddenField = document.getElementById("selected_date");
  if (hiddenField) hiddenField.value = date;

  // Continue Button aktivieren
  const continueBtn = document.getElementById("continue-btn");
  if (continueBtn) continueBtn.disabled = false;
}

function selectTime(appointmentId, time, element) {
  // Alle anderen Optionen deselektieren
  document
    .querySelectorAll(".time-option")
    .forEach((opt) => opt.classList.remove("selected"));

  // Aktuelle Option selektieren
  element.classList.add("selected");

  // Hidden fields setzen
  const appointmentField = document.getElementById("appointment_id");
  const timeField = document.getElementById("selected_time");

  if (appointmentField) appointmentField.value = appointmentId;
  if (timeField) timeField.value = time;

  // Continue Button aktivieren
  const continueBtn = document.getElementById("continue-btn");
  if (continueBtn) continueBtn.disabled = false;
}

// Utility Functions
function formatCurrency(amount) {
  return new Intl.NumberFormat("de-DE", {
    style: "currency",
    currency: "EUR",
  }).format(amount);
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return new Intl.DateTimeFormat("de-DE", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(date);
}

function formatTime(timeString) {
  return timeString + " Uhr";
}

// Smooth Scrolling für bessere UX
function smoothScrollTo(element) {
  element.scrollIntoView({
    behavior: "smooth",
    block: "start",
  });
}

// Auto-save zu Session Storage (für Formular-Daten)
function autoSaveFormData() {
  const forms = document.querySelectorAll("form");

  forms.forEach((form) => {
    const inputs = form.querySelectorAll("input, textarea, select");

    inputs.forEach((input) => {
      // Load saved data
      const savedValue = sessionStorage.getItem(`form_${input.name}`);
      if (savedValue && input.value === "") {
        input.value = savedValue;
      }

      // Save on change
      input.addEventListener("change", function () {
        sessionStorage.setItem(`form_${this.name}`, this.value);
      });
    });
  });
}

// Error Handling
window.addEventListener("error", function (e) {
  console.error("JavaScript Error:", e.error);

  // Optional: Send error to server for logging
  if (typeof fetch !== "undefined") {
    fetch("/api/log-error", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        message: e.message,
        filename: e.filename,
        lineno: e.lineno,
        colno: e.colno,
        stack: e.error ? e.error.stack : null,
      }),
    }).catch(() => {
      // Ignore logging errors
    });
  }
});

// Performance optimization
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// Add debounced search/validation
const debouncedValidation = debounce(validateField, 300);
