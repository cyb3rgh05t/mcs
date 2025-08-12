/**
 * Mobile Car Service - Konfigurationsdatei
 * Alle wichtigen Einstellungen und Konstanten
 */

const CONFIG = {
  // Firmenadresse für Entfernungsberechnung
  COMPANY_ADDRESS: {
    street: "Hüllerstraße 16",
    zip: "44649",
    city: "Herne",
    lat: 51.52562232663754,
    lng: 7.142675835582232,
  },

  // Preisberechnung
  GOOGLE_MAPS_API_KEY: "dein-api-key-hier", // Optional
  FREE_DISTANCE_KM: 10,
  TRAVEL_COST_PER_KM: 1.5,
  MAX_SERVICE_DISTANCE: 300,

  // Geschäftszeiten
  BUSINESS_HOURS: {
    start: 8, // Start um 8:00 Uhr
    end: 18, // Ende um 18:00 Uhr
    interval: 60, // 60 Minuten Termine
    daysInAdvance: 21, // 21 Tage im Voraus buchbar
  },

  // Datenbank Schlüssel für LocalStorage
  STORAGE_KEYS: {
    customers: "mcs_customers",
    bookings: "mcs_bookings",
    bookedSlots: "mcs_booked_slots",
    settings: "mcs_settings",
  },

  // Validation Rules
  VALIDATION: {
    phone: /^[\+]?[0-9\s\-\(\)]{10,}$/,
    email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
    zip: /^[0-9]{5}$/,
    minNameLength: 2,
  },

  // UI Einstellungen
  UI: {
    animationDuration: 500, // Millisekunden
    defaultBackground: "linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%)",
    maxUploadSize: 5 * 1024 * 1024, // 5MB für Hintergrundbild
    supportedImageTypes: ["image/jpeg", "image/png", "image/webp"],
  },

  // Demo-Daten (für Testing)
  DEMO_DATA: {
    sampleBookings: [
      { date: "2025-08-10", time: "10:00" },
      { date: "2025-08-10", time: "14:00" },
      { date: "2025-08-11", time: "09:00" },
      { date: "2025-08-12", time: "11:00" },
      { date: "2025-08-13", time: "15:00" },
    ],
  },

  // Fehlermeldungen
  MESSAGES: {
    errors: {
      invalidEmail: "Bitte geben Sie eine gültige E-Mail-Adresse ein.",
      invalidPhone: "Bitte geben Sie eine gültige Telefonnummer ein.",
      invalidZip: "Bitte geben Sie eine gültige PLZ (5 Stellen) ein.",
      nameRequired: "Vor- und Nachname sind erforderlich.",
      addressRequired: "Vollständige Adresse ist erforderlich.",
      noDateSelected: "Bitte wählen Sie ein Datum aus.",
      noTimeSelected: "Bitte wählen Sie eine Uhrzeit aus.",
      slotUnavailable: "Dieser Termin ist leider nicht mehr verfügbar.",
      uploadTooLarge: "Die Datei ist zu groß. Maximum: 5MB.",
      unsupportedFormat:
        "Unsupported Dateiformat. Unterstützt: JPG, PNG, WebP.",
    },
    success: {
      bookingConfirmed: "Ihre Buchung wurde erfolgreich bestätigt!",
      distanceCalculated: "Entfernung erfolgreich berechnet.",
      backgroundUploaded: "Hintergrundbild wurde aktualisiert.",
    },
    info: {
      noTravelCost: "Keine Anfahrtskosten (unter 10km)",
      calculatingDistance: "Entfernung wird berechnet...",
      loadingTimeSlots: "Verfügbare Zeiten werden geladen...",
    },
  },
};

// Erweiterte Konfiguration für verschiedene Umgebungen
const ENV_CONFIG = {
  development: {
    debug: true,
    apiTimeout: 10000,
    showTestData: true,
  },
  production: {
    debug: false,
    apiTimeout: 5000,
    showTestData: false,
  },
};

// Aktuell Umgebung bestimmen (basierend auf URL)
const ENVIRONMENT =
  window.location.hostname === "localhost" ? "development" : "production";

// Konfiguration zusammenführen
Object.assign(CONFIG, ENV_CONFIG[ENVIRONMENT]);

// Globale Funktionen für Konfigurationszugriff
window.getConfig = (key) => {
  const keys = key.split(".");
  let value = CONFIG;

  for (const k of keys) {
    value = value[k];
    if (value === undefined) break;
  }

  return value;
};

window.updateConfig = (key, value) => {
  const keys = key.split(".");
  let target = CONFIG;

  for (let i = 0; i < keys.length - 1; i++) {
    if (!target[keys[i]]) target[keys[i]] = {};
    target = target[keys[i]];
  }

  target[keys[keys.length - 1]] = value;

  // Speichere geänderte Einstellungen
  localStorage.setItem(CONFIG.STORAGE_KEYS.settings, JSON.stringify(CONFIG));
};

// Konfiguration aus LocalStorage laden (falls vorhanden)
const savedSettings = localStorage.getItem(CONFIG.STORAGE_KEYS.settings);
if (savedSettings) {
  try {
    const parsed = JSON.parse(savedSettings);
    Object.assign(CONFIG, parsed);
  } catch (error) {
    console.warn("Fehler beim Laden der gespeicherten Einstellungen:", error);
  }
}
