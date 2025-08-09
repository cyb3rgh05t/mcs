/**
 * Mobile Car Service - Hauptanwendung
 * Koordiniert alle Module und verwaltet die App
 */

class MobileCarServiceApp {
  constructor() {
    this.isInitialized = false;
    this.modules = new Map();
    this.eventBus = new EventTarget();

    this.init();
  }

  /**
   * Initialisiert die Anwendung
   */
  async init() {
    try {
      console.log("🚗 Mobile Car Service wird gestartet...");

      // Warte auf DOM Ready
      if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => this.start());
      } else {
        await this.start();
      }
    } catch (error) {
      console.error("❌ Fehler beim Initialisieren der App:", error);
      this.handleInitError(error);
    }
  }

  /**
   * Startet die Anwendung
   */
  async start() {
    try {
      // Module registrieren
      this.registerModules();

      // Event Listeners setup
      this.setupEventListeners();

      // Background Manager initialisieren
      this.initBackgroundManager();

      // Performance Monitoring
      this.setupPerformanceMonitoring();

      // Error Handling
      this.setupErrorHandling();

      // App als initialisiert markieren
      this.isInitialized = true;

      // Success Event
      this.emit("appReady", { timestamp: new Date().toISOString() });

      console.log("✅ Mobile Car Service erfolgreich gestartet!");

      // Development Helper
      if (CONFIG.debug) {
        this.setupDevTools();
      }
    } catch (error) {
      console.error("❌ Fehler beim Starten der App:", error);
      throw error;
    }
  }

  /**
   * Registriert alle Module
   */
  registerModules() {
    this.modules.set("database", window.db);
    this.modules.set("serviceManager", window.serviceManager);
    this.modules.set("mapsService", window.mapsService);
    this.modules.set("bookingFlow", window.bookingFlow);

    console.log(`📦 ${this.modules.size} Module registriert`);
  }

  /**
   * Setup Event Listeners
   */
  setupEventListeners() {
    // Window Events
    window.addEventListener("beforeunload", (e) => this.handleBeforeUnload(e));
    window.addEventListener("online", () => this.handleOnlineStatus(true));
    window.addEventListener("offline", () => this.handleOnlineStatus(false));

    // Custom App Events
    this.eventBus.addEventListener("bookingComplete", (e) =>
      this.handleBookingComplete(e)
    );
    this.eventBus.addEventListener("error", (e) => this.handleError(e));

    // Storage Events
    window.addEventListener("storage", (e) => this.handleStorageChange(e));

    console.log("📡 Event Listeners konfiguriert");
  }

  /**
   * Initialisiert Background Manager
   */
  initBackgroundManager() {
    this.backgroundManager = {
      uploadBackground: () => {
        const input = document.getElementById("background-upload");
        if (input) {
          input.click();
        }
      },

      setupUploadHandler: () => {
        const input = document.getElementById("background-upload");
        if (input) {
          input.addEventListener("change", (e) =>
            this.handleBackgroundUpload(e)
          );
        }
      },

      resetBackground: () => {
        document.body.style.backgroundImage = "";
        document.body.style.backgroundSize = "";
        document.body.style.backgroundPosition = "";
        document.body.style.backgroundAttachment = "";
        localStorage.removeItem("mcs_background");
      },

      loadSavedBackground: () => {
        const saved = localStorage.getItem("mcs_background");
        if (saved) {
          document.body.style.backgroundImage = saved;
          document.body.style.backgroundSize = "cover";
          document.body.style.backgroundPosition = "center";
          document.body.style.backgroundAttachment = "fixed";
        }
      },
    };

    // Global verfügbar machen
    window.backgroundManager = this.backgroundManager;

    // Setup Upload Handler
    this.backgroundManager.setupUploadHandler();

    // Gespeicherten Hintergrund laden
    this.backgroundManager.loadSavedBackground();

    console.log("🖼️ Background Manager initialisiert");
  }

  /**
   * Background Upload Handler
   */
  handleBackgroundUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Validierung
    if (file.size > CONFIG.UI.maxUploadSize) {
      this.showNotification("error", CONFIG.MESSAGES.errors.uploadTooLarge);
      return;
    }

    if (!CONFIG.UI.supportedImageTypes.includes(file.type)) {
      this.showNotification("error", CONFIG.MESSAGES.errors.unsupportedFormat);
      return;
    }

    // Upload verarbeiten
    const reader = new FileReader();
    reader.onload = (e) => {
      const backgroundUrl = `linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url(${e.target.result})`;

      document.body.style.backgroundImage = backgroundUrl;
      document.body.style.backgroundSize = "cover";
      document.body.style.backgroundPosition = "center";
      document.body.style.backgroundAttachment = "fixed";

      // Speichern
      localStorage.setItem("mcs_background", backgroundUrl);

      this.showNotification(
        "success",
        CONFIG.MESSAGES.success.backgroundUploaded
      );
    };

    reader.onerror = () => {
      this.showNotification("error", "Fehler beim Laden des Bildes");
    };

    reader.readAsDataURL(file);
  }

  /**
   * Setup Performance Monitoring
   */
  setupPerformanceMonitoring() {
    if ("performance" in window) {
      // Load Time messen
      window.addEventListener("load", () => {
        const loadTime = performance.now();
        console.log(`⏱️ App Load Time: ${loadTime.toFixed(2)}ms`);

        if (CONFIG.debug) {
          this.logPerformanceMetrics();
        }
      });
    }
  }

  /**
   * Performance Metriken loggen
   */
  logPerformanceMetrics() {
    const metrics = {
      loadTime: performance.now(),
      navigation: performance.getEntriesByType("navigation")[0],
      memory: performance.memory
        ? {
            used: Math.round(performance.memory.usedJSHeapSize / 1024 / 1024),
            total: Math.round(performance.memory.totalJSHeapSize / 1024 / 1024),
            limit: Math.round(performance.memory.jsHeapSizeLimit / 1024 / 1024),
          }
        : null,
    };

    console.table(metrics);
  }

  /**
   * Setup Error Handling
   */
  setupErrorHandling() {
    // Globale Error Handler
    window.addEventListener("error", (e) => {
      console.error("🚨 Global Error:", e.error);
      this.handleError({
        type: "javascript",
        message: e.message,
        filename: e.filename,
        lineno: e.lineno,
        colno: e.colno,
        error: e.error,
      });
    });

    // Promise Rejection Handler
    window.addEventListener("unhandledrejection", (e) => {
      console.error("🚨 Unhandled Promise Rejection:", e.reason);
      this.handleError({
        type: "promise",
        reason: e.reason,
      });
    });
  }

  /**
   * Event Emitter
   */
  emit(eventName, data) {
    const event = new CustomEvent(eventName, { detail: data });
    this.eventBus.dispatchEvent(event);

    if (CONFIG.debug) {
      console.log(`📢 Event emitted: ${eventName}`, data);
    }
  }

  /**
   * Event Listener
   */
  on(eventName, callback) {
    this.eventBus.addEventListener(eventName, callback);
  }

  /**
   * Event Handler für Before Unload
   */
  handleBeforeUnload(event) {
    // Prüfe ob ungespeicherte Änderungen vorhanden sind
    if (this.hasUnsavedChanges()) {
      event.preventDefault();
      event.returnValue =
        "Sie haben ungespeicherte Änderungen. Möchten Sie die Seite wirklich verlassen?";
    }
  }

  /**
   * Prüft auf ungespeicherte Änderungen
   */
  hasUnsavedChanges() {
    const booking = window.bookingFlow?.getCurrentBooking();
    return (
      booking &&
      (booking.date || booking.services.length > 0 || booking.customer)
    );
  }

  /**
   * Online/Offline Status Handler
   */
  handleOnlineStatus(isOnline) {
    const message = isOnline
      ? "Internetverbindung wiederhergestellt"
      : "Keine Internetverbindung";

    this.showNotification(isOnline ? "info" : "warning", message);

    // Optional: Offline-Modus aktivieren
    if (!isOnline) {
      this.enableOfflineMode();
    }
  }

  /**
   * Offline-Modus
   */
  enableOfflineMode() {
    console.log("📴 Offline-Modus aktiviert");
    // Hier könnten Offline-spezifische Features implementiert werden
  }

  /**
   * Storage Change Handler
   */
  handleStorageChange(event) {
    if (event.key && event.key.startsWith("mcs_")) {
      console.log(`💾 Storage changed: ${event.key}`);
      this.emit("storageChanged", { key: event.key, newValue: event.newValue });
    }
  }

  /**
   * Booking Complete Handler
   */
  handleBookingComplete(event) {
    const booking = event.detail;
    console.log("🎉 Buchung abgeschlossen:", booking.id);

    // Optional: Analytics, Notifications, etc.
    this.trackEvent("booking_completed", {
      booking_id: booking.id,
      total_price: booking.totalPrice,
      services_count: booking.services.length,
    });
  }

  /**
   * Error Handler
   */
  handleError(error) {
    console.error("🚨 App Error:", error);

    // Error zu externem Service senden (optional)
    if (!CONFIG.debug) {
      this.reportError(error);
    }

    // User Notification
    this.showNotification(
      "error",
      "Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut."
    );
  }

  /**
   * Init Error Handler
   */
  handleInitError(error) {
    document.body.innerHTML = `
            <div style="
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                background: #1a1a1a;
                color: white;
                font-family: Arial, sans-serif;
                text-align: center;
            ">
                <div>
                    <h1>⚠️ Fehler beim Laden</h1>
                    <p>Die Anwendung konnte nicht gestartet werden.</p>
                    <button onclick="location.reload()" style="
                        background: #fff;
                        color: #000;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 5px;
                        cursor: pointer;
                        margin-top: 20px;
                    ">Seite neu laden</button>
                </div>
            </div>
        `;
  }

  /**
   * Zeigt Benachrichtigungen
   */
  showNotification(type, message, duration = 5000) {
    // Einfache Notification Implementation
    const notification = document.createElement("div");
    notification.className = `notification ${type}`;
    notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${
              type === "error"
                ? "#f44336"
                : type === "success"
                ? "#4caf50"
                : "#2196f3"
            };
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease-out;
        `;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Auto-remove
    setTimeout(() => {
      notification.style.animation = "slideOut 0.3s ease-in";
      setTimeout(() => notification.remove(), 300);
    }, duration);
  }

  /**
   * Event Tracking (für Analytics)
   */
  trackEvent(eventName, data) {
    if (CONFIG.debug) {
      console.log(`📊 Track Event: ${eventName}`, data);
    }

    // Hier könnte Google Analytics, Mixpanel, etc. integriert werden
  }

  /**
   * Error Reporting (für externe Services)
   */
  reportError(error) {
    // Hier könnte Sentry, LogRocket, etc. integriert werden
    console.log("📤 Error reported:", error);
  }

  /**
   * Development Tools
   */
  setupDevTools() {
    // Globale Debug-Funktionen
    window.MCS_DEBUG = {
      app: this,
      config: CONFIG,
      modules: this.modules,

      // Hilfsfunktionen
      resetDatabase: () => window.db.resetDatabase(),
      exportData: () => window.db.exportData(),
      clearCache: () => window.mapsService.clearCache(),
      getStats: () => window.db.getStats(),

      // Performance
      performanceMetrics: () => this.logPerformanceMetrics(),

      // Testing
      simulateBooking: () => this.simulateTestBooking(),

      // UI Helpers
      resetBackground: () => this.backgroundManager.resetBackground(),
      showAllModules: () => console.table(Array.from(this.modules.entries())),
    };

    console.log("🛠️ Development Tools verfügbar unter: window.MCS_DEBUG");
    console.log("Beispiel: MCS_DEBUG.getStats()");
  }

  /**
   * Simuliert eine Test-Buchung (Development)
   */
  simulateTestBooking() {
    if (!CONFIG.debug) return;

    const testBooking = {
      date: "2025-08-15",
      time: "10:00",
      services: [
        window.serviceManager.getServiceById(1),
        window.serviceManager.getServiceById(2),
      ],
      customer: {
        firstName: "Max",
        lastName: "Mustermann",
        email: "max@example.com",
        phone: "0123456789",
        street: "Teststraße 123",
        zip: "48431",
        city: "Rheine",
      },
    };

    window.bookingFlow.setBookingData(testBooking);
    console.log("🧪 Test-Buchung simuliert");
  }

  /**
   * App-Status abrufen
   */
  getStatus() {
    return {
      initialized: this.isInitialized,
      modules: Array.from(this.modules.keys()),
      performance: {
        loadTime: performance.now(),
        memory: performance.memory
          ? Math.round(performance.memory.usedJSHeapSize / 1024 / 1024)
          : null,
      },
      data: {
        customers: window.db?.customers.length || 0,
        bookings: window.db?.bookings.length || 0,
        cacheSize: window.mapsService?.getCacheStats().cacheSize || 0,
      },
    };
  }

  /**
   * App herunterfahren
   */
  shutdown() {
    console.log("🛑 App wird heruntergefahren...");

    // Cleanup
    this.modules.clear();

    // Events entfernen
    this.eventBus = new EventTarget();

    this.isInitialized = false;

    console.log("✅ App heruntergefahren");
  }
}

// CSS für Notifications
const notificationStyles = document.createElement("style");
notificationStyles.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .notification {
        font-weight: 500;
        border-left: 4px solid rgba(255,255,255,0.3);
    }
`;
document.head.appendChild(notificationStyles);

// App initialisieren
const app = new MobileCarServiceApp();

// Global verfügbar machen
window.MCS_APP = app;

// Export für Modules (falls verwendet)
if (typeof module !== "undefined" && module.exports) {
  module.exports = MobileCarServiceApp;
}
