// ========================================
// TOAST NOTIFICATION SYSTEM
// ========================================

class ToastManager {
  constructor() {
    this.toasts = [];
    this.container = null;
    this.init();
  }

  init() {
    // Toast-Container erstellen
    this.container = document.createElement("div");
    this.container.className = "toast-container";
    this.container.id = "toast-container";
    document.body.appendChild(this.container);
  }

  /**
   * Toast anzeigen
   * @param {string} message - Nachricht
   * @param {string} type - success, error, warning, info, loading
   * @param {number} duration - Anzeigedauer in ms (0 = dauerhaft)
   * @param {object} options - Zusätzliche Optionen
   */
  show(message, type = "info", duration = 5000, options = {}) {
    const toast = this.createToast(message, type, duration, options);
    this.addToast(toast);
    return toast;
  }

  /**
   * Erfolgs-Toast
   */
  success(message, duration = 4000, options = {}) {
    return this.show(message, "success", duration, options);
  }

  /**
   * Fehler-Toast
   */
  error(message, duration = 6000, options = {}) {
    return this.show(message, "error", duration, options);
  }

  /**
   * Warnung-Toast
   */
  warning(message, duration = 5000, options = {}) {
    return this.show(message, "warning", duration, options);
  }

  /**
   * Info-Toast
   */
  info(message, duration = 4000, options = {}) {
    return this.show(message, "info", duration, options);
  }

  /**
   * Loading-Toast (dauerhaft bis manuell geschlossen)
   */
  loading(message = "Wird geladen...", options = {}) {
    return this.show(message, "loading", 0, {
      ...options,
      showCloseButton: true,
      icon: "fas fa-spinner fa-spin",
    });
  }

  /**
   * Toast erstellen
   */
  createToast(message, type, duration, options) {
    const toastId =
      "toast_" + Date.now() + Math.random().toString(36).substr(2, 9);

    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;
    toast.id = toastId;

    // Icon bestimmen
    const icons = {
      success: "fas fa-check-circle",
      error: "fas fa-exclamation-circle",
      warning: "fas fa-exclamation-triangle",
      info: "fas fa-info-circle",
      loading: "fas fa-spinner fa-spin",
    };

    const icon = options.icon || icons[type] || icons.info;
    const showCloseButton = options.showCloseButton !== false;
    const showProgress = duration > 0 && options.showProgress !== false;

    toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-icon">
                    <i class="${icon}"></i>
                </div>
                <div class="toast-message">${message}</div>
                ${
                  showCloseButton
                    ? '<button class="toast-close"><i class="fas fa-times"></i></button>'
                    : ""
                }
            </div>
            ${
              showProgress
                ? '<div class="toast-progress"><div class="toast-progress-bar"></div></div>'
                : ""
            }
        `;

    // Event Listeners
    if (showCloseButton) {
      const closeBtn = toast.querySelector(".toast-close");
      closeBtn.addEventListener("click", () => this.remove(toastId));
    }

    // Click-to-dismiss (außer bei loading)
    if (type !== "loading") {
      toast.addEventListener("click", () => this.remove(toastId));
    }

    // Auto-remove nach duration
    if (duration > 0) {
      // Progress-Animation
      if (showProgress) {
        const progressBar = toast.querySelector(".toast-progress-bar");
        setTimeout(() => {
          if (progressBar) {
            progressBar.style.animation = `toast-progress ${duration}ms linear`;
          }
        }, 100);
      }

      setTimeout(() => this.remove(toastId), duration);
    }

    return {
      id: toastId,
      element: toast,
      type: type,
      message: message,
      duration: duration,
    };
  }

  /**
   * Toast zum Container hinzufügen
   */
  addToast(toast) {
    this.toasts.push(toast);
    this.container.appendChild(toast.element);

    // Animation
    setTimeout(() => {
      toast.element.classList.add("toast-show");
    }, 10);

    // Maximal 5 Toasts gleichzeitig
    if (this.toasts.length > 5) {
      this.remove(this.toasts[0].id);
    }
  }

  /**
   * Toast entfernen
   */
  remove(toastId) {
    const toastIndex = this.toasts.findIndex((t) => t.id === toastId);
    if (toastIndex === -1) return;

    const toast = this.toasts[toastIndex];

    // Ausblend-Animation
    toast.element.classList.remove("toast-show");
    toast.element.classList.add("toast-hide");

    setTimeout(() => {
      if (toast.element.parentNode) {
        toast.element.parentNode.removeChild(toast.element);
      }
      this.toasts.splice(toastIndex, 1);
    }, 300);
  }

  /**
   * Alle Toasts entfernen
   */
  clear() {
    this.toasts.forEach((toast) => this.remove(toast.id));
  }

  /**
   * Loading-Toast updaten
   */
  updateLoading(toastId, newMessage) {
    const toast = this.toasts.find((t) => t.id === toastId);
    if (toast) {
      const messageEl = toast.element.querySelector(".toast-message");
      if (messageEl) {
        messageEl.textContent = newMessage;
      }
    }
  }

  /**
   * Loading-Toast zu Success umwandeln
   */
  loadingToSuccess(toastId, message = "Erfolgreich abgeschlossen!") {
    const toast = this.toasts.find((t) => t.id === toastId);
    if (toast) {
      this.remove(toastId);
      this.success(message);
    }
  }

  /**
   * Loading-Toast zu Error umwandeln
   */
  loadingToError(toastId, message = "Ein Fehler ist aufgetreten") {
    const toast = this.toasts.find((t) => t.id === toastId);
    if (toast) {
      this.remove(toastId);
      this.error(message);
    }
  }
}

// ========================================
// MOBILE CAR SERVICE APP
// ========================================

class MobileCarServiceApp {
  constructor() {
    this.isInitialized = false;
    this.modules = new Map();
    this.eventBus = new EventTarget();
    this.toastManager = null;

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

      // Toast Manager initialisieren
      this.toastManager = new ToastManager();

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

      // Toast zum App-Start
      this.toastManager.success("🚗 Mobile Car Service erfolgreich gestartet!");

      console.log("✅ Mobile Car Service erfolgreich gestartet!");

      // Development Helper
      if (CONFIG.debug) {
        this.setupDevTools();
      }
    } catch (error) {
      console.error("❌ Fehler beim Starten der App:", error);
      if (this.toastManager) {
        this.toastManager.error("Fehler beim Starten der Anwendung");
      }
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
        this.toastManager.info("Hintergrundbild zurückgesetzt");
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

    // Validierung mit Toast-Feedback
    if (file.size > CONFIG.UI.maxUploadSize) {
      this.toastManager.error("Datei ist zu groß (max. 5MB)");
      return;
    }

    if (!CONFIG.UI.supportedImageTypes.includes(file.type)) {
      this.toastManager.error("Unsupported Dateiformat");
      return;
    }

    // Loading-Toast
    const loadingToast = this.toastManager.loading(
      "Hintergrundbild wird hochgeladen..."
    );

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

      // Loading zu Success
      this.toastManager.loadingToSuccess(
        loadingToast.id,
        "Hintergrundbild erfolgreich geändert"
      );
    };

    reader.onerror = () => {
      this.toastManager.loadingToError(
        loadingToast.id,
        "Fehler beim Laden des Bildes"
      );
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
      data: {
        customers: window.db?.customers.length || 0,
        bookings: window.db?.bookings.length || 0,
        cacheSize: window.mapsService?.getCacheStats().cacheSize || 0,
      },
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

  // ========================================
  // TOAST INTEGRATION METHODEN
  // ========================================

  /**
   * Toast anzeigen (neue Hauptmethode)
   */
  showToast(message, type = "info", duration = 5000, options = {}) {
    return this.toastManager.show(message, type, duration, options);
  }

  /**
   * Erfolgs-Toast
   */
  successToast(message, duration = 4000) {
    return this.toastManager.success(message, duration);
  }

  /**
   * Fehler-Toast
   */
  errorToast(message, duration = 6000) {
    return this.toastManager.error(message, duration);
  }

  /**
   * Warnung-Toast
   */
  warningToast(message, duration = 5000) {
    return this.toastManager.warning(message, duration);
  }

  /**
   * Info-Toast
   */
  infoToast(message, duration = 4000) {
    return this.toastManager.info(message, duration);
  }

  /**
   * Loading-Toast
   */
  loadingToast(message = "Wird geladen...") {
    return this.toastManager.loading(message);
  }

  /**
   * Die bestehende showNotification Methode erweitern mit Toast-System
   */
  showNotification(type, message, duration = 5000) {
    return this.toastManager.show(message, type, duration);
  }

  // ========================================
  // EVENT HANDLER (mit Toast-Integration)
  // ========================================

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
   * Online/Offline Status Handler mit Toast-Feedback
   */
  handleOnlineStatus(isOnline) {
    if (isOnline) {
      this.toastManager.success("Internetverbindung wiederhergestellt");
    } else {
      this.toastManager.warning("Keine Internetverbindung", 0); // Dauerhaft anzeigen
    }

    if (!isOnline) {
      this.enableOfflineMode();
    }
  }

  /**
   * Offline-Modus
   */
  enableOfflineMode() {
    console.log("📴 Offline-Modus aktiviert");
    this.toastManager.info("Offline-Modus aktiviert");
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
   * Booking Complete Handler mit Toast-Feedback
   */
  handleBookingComplete(event) {
    const booking = event.detail;
    console.log("🎉 Buchung abgeschlossen:", booking.id);

    // Erfolgs-Toast mit Buchungsnummer
    this.toastManager.success(
      `🎉 Buchung ${booking.id} erfolgreich erstellt!`,
      6000
    );

    // Analytics Event
    this.trackEvent("booking_completed", {
      booking_id: booking.id,
      total_price: booking.totalPrice,
      services_count: booking.services.length,
    });
  }

  /**
   * Error Handler mit Toast-Integration
   */
  handleError(error) {
    console.error("🚨 App Error:", error);

    // Error zu externem Service senden (optional)
    if (!CONFIG.debug) {
      this.reportError(error);
    }

    // User Notification mit Toast
    this.toastManager.error(
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
      toastManager: this.toastManager,

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

      // Toast Testing
      testToasts: () => {
        toast.success("Test Success Toast");
        setTimeout(() => toast.error("Test Error Toast"), 1000);
        setTimeout(() => toast.warning("Test Warning Toast"), 2000);
        setTimeout(() => toast.info("Test Info Toast"), 3000);
        setTimeout(() => {
          const loading = toast.loading("Test Loading Toast");
          setTimeout(() => {
            this.toastManager.loadingToSuccess(loading.id, "Test erfolgreich!");
          }, 3000);
        }, 4000);
      },
    };

    console.log("🛠️ Development Tools verfügbar unter: window.MCS_DEBUG");
    console.log("Toast-Tests: MCS_DEBUG.testToasts()");
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
    this.toastManager.info("🧪 Test-Buchung simuliert");
    console.log("🧪 Test-Buchung simuliert");
  }

  /**
   * App-Status abrufen
   */
  getStatus() {
    return {
      initialized: this.isInitialized,
      modules: Array.from(this.modules.keys()),
      toastManager: !!this.toastManager,
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

    // Toast alle schließen
    if (this.toastManager) {
      this.toastManager.clear();
    }

    // Cleanup
    this.modules.clear();

    // Events entfernen
    this.eventBus = new EventTarget();

    this.isInitialized = false;

    console.log("✅ App heruntergefahren");
  }
}

// ========================================
// CSS FÜR NOTIFICATIONS (Legacy Support)
// ========================================
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

// ========================================
// APP INITIALISIERUNG
// ========================================

// App initialisieren
const app = new MobileCarServiceApp();

// Global verfügbar machen
window.MCS_APP = app;

// ========================================
// GLOBALE TOAST-FUNKTIONEN
// ========================================
window.toast = {
  show: (message, type = "info", duration = 5000, options = {}) =>
    window.MCS_APP?.toastManager?.show(message, type, duration, options),

  success: (message, duration = 4000) =>
    window.MCS_APP?.toastManager?.success(message, duration),

  error: (message, duration = 6000) =>
    window.MCS_APP?.toastManager?.error(message, duration),

  warning: (message, duration = 5000) =>
    window.MCS_APP?.toastManager?.warning(message, duration),

  info: (message, duration = 4000) =>
    window.MCS_APP?.toastManager?.info(message, duration),

  loading: (message = "Wird geladen...") =>
    window.MCS_APP?.toastManager?.loading(message),

  // Zusätzliche Hilfsfunktionen
  clear: () => window.MCS_APP?.toastManager?.clear(),

  updateLoading: (id, message) =>
    window.MCS_APP?.toastManager?.updateLoading(id, message),

  loadingToSuccess: (id, message) =>
    window.MCS_APP?.toastManager?.loadingToSuccess(id, message),

  loadingToError: (id, message) =>
    window.MCS_APP?.toastManager?.loadingToError(id, message),
};

// ========================================
// MODULE EXPORT (falls verwendet)
// ========================================
if (typeof module !== "undefined" && module.exports) {
  module.exports = MobileCarServiceApp;
}
