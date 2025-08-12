/**
 * Mobile Car Service - API Client
 * Verbindung zwischen Frontend und Backend
 */

class ApiClient {
  constructor() {
    // Backend-URL automatisch erkennen
    this.baseUrl = this.detectBackendUrl();
    this.apiUrl = `${this.baseUrl}/backend/api.php`;
    this.simpleApiUrl = `${this.baseUrl}/backend/simple-api.php`;

    // Backend verfügbar prüfen
    this.backendAvailable = false;
    this.usingSimpleApi = false;
    this.checkBackendHealth();
  }

  /**
   * Backend-URL automatisch erkennen
   */
  detectBackendUrl() {
    const origin = window.location.origin;
    const pathname = window.location.pathname;

    // PHP Development Server Erkennung (localhost:8000)
    if (origin.includes("localhost:8000")) {
      return origin; // http://localhost:8000
    }

    // Apache/Nginx mit Unterordner
    const basePath = pathname.includes("/mobile-car-service")
      ? "/mobile-car-service"
      : "";

    return `${origin}${basePath}`;
  }

  /**
   * Backend-Gesundheit prüfen
   */
  async checkBackendHealth() {
    // Erst die normale API probieren
    const apiResult = await this.testApiEndpoint(this.apiUrl);
    if (apiResult.success) {
      console.log("✅ Backend verfügbar - Daten werden in SQLite gespeichert");
      this.backendAvailable = true;
      this.usingSimpleApi = false;
      this.initializeBackendMode();
      return;
    }

    // Wenn das nicht funktioniert, die einfache API probieren
    console.log("🔄 Normale API nicht verfügbar, versuche Simple API...");
    const simpleResult = await this.testApiEndpoint(this.simpleApiUrl);
    if (simpleResult.success) {
      console.log("✅ Simple API verfügbar - Verwende Test-Backend");
      this.backendAvailable = true;
      this.usingSimpleApi = true;
      this.apiUrl = this.simpleApiUrl; // Switch to simple API
      this.initializeBackendMode();
      return;
    }

    // Beide APIs funktionieren nicht
    console.log("❌ Kein Backend verfügbar - LocalStorage wird verwendet");
    this.backendAvailable = false;
    this.initializeLocalStorageMode();
  }

  /**
   * API-Endpunkt testen
   */
  async testApiEndpoint(url) {
    try {
      console.log("🔍 Teste API:", url);

      const response = await fetch(`${url}/system/health`, {
        method: "GET",
        headers: { "Content-Type": "application/json" },
      });

      console.log("📡 Response Status:", response.status);

      if (response.ok) {
        const data = await response.json();
        console.log("📊 Response Data:", data);

        const isHealthy =
          data.success && data.data && data.data.status === "ok";

        return {
          success: isHealthy,
          data: data,
          url: url,
        };
      } else {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
    } catch (error) {
      console.log("❌ API-Test fehlgeschlagen für:", url);
      console.log("Fehler:", error.message);
      return {
        success: false,
        error: error.message,
        url: url,
      };
    }
  }

  /**
   * Backend-Modus initialisieren
   */
  initializeBackendMode() {
    // Original Database-Klasse durch API-Version ersetzen
    window.DatabaseBackend = new DatabaseApiAdapter(this);

    // Globale DB-Instanz umstellen
    if (window.db && window.db.constructor.name === "Database") {
      this.migrateToBackend(window.db);
    }

    window.db = window.DatabaseBackend;

    const apiType = this.usingSimpleApi ? "Test-API" : "SQLite";
    const message = `Backend aktiv - Daten werden in ${apiType} gespeichert`;
    this.showBackendStatus(message, "success");
  }

  /**
   * LocalStorage-Modus beibehalten
   */
  initializeLocalStorageMode() {
    // Normale Database-Klasse verwenden
    if (!window.db) {
      window.db = new Database();
    }
    this.showBackendStatus(
      "LocalStorage aktiv - Backend nicht verfügbar",
      "warning"
    );
  }

  /**
   * Daten von LocalStorage zu Backend migrieren
   */
  async migrateToBackend(localDb) {
    try {
      console.log("📦 Migriere Daten von LocalStorage zu Backend...");

      // Existierende Daten laden
      const customers = localDb.customers || [];
      const bookings = localDb.bookings || [];

      let migrated = 0;

      // Kunden migrieren
      for (const customer of customers) {
        try {
          await this.request("POST", "/customers", customer);
          migrated++;
        } catch (error) {
          console.warn("Kunde konnte nicht migriert werden:", customer.email);
        }
      }

      // Buchungen migrieren
      for (const booking of bookings) {
        try {
          await this.request("POST", "/bookings", booking);
          migrated++;
        } catch (error) {
          console.warn(
            "Buchung konnte nicht migriert werden:",
            booking.bookingNumber
          );
        }
      }

      if (migrated > 0) {
        console.log(`✅ ${migrated} Datensätze erfolgreich migriert`);
        toast.success(`${migrated} Datensätze zu Backend migriert`);
      }
    } catch (error) {
      console.error("Migration fehlgeschlagen:", error);
      toast.warning("Datenmigration fehlgeschlagen");
    }
  }

  /**
   * API-Request senden
   */
  async request(method, endpoint, data = null) {
    const url = `${this.apiUrl}${endpoint}`;

    const options = {
      method,
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
    };

    if (data && ["POST", "PUT", "PATCH"].includes(method)) {
      options.body = JSON.stringify(data);
    }

    try {
      const response = await fetch(url, options);
      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.error?.message || `HTTP ${response.status}`);
      }

      return result.data || result;
    } catch (error) {
      console.error(`API ${method} ${endpoint} failed:`, error);
      throw error;
    }
  }

  /**
   * GET Request
   */
  async get(endpoint) {
    return this.request("GET", endpoint);
  }

  /**
   * POST Request
   */
  async post(endpoint, data) {
    return this.request("POST", endpoint, data);
  }

  /**
   * PUT Request
   */
  async put(endpoint, data) {
    return this.request("PUT", endpoint, data);
  }

  /**
   * DELETE Request
   */
  async delete(endpoint) {
    return this.request("DELETE", endpoint);
  }

  /**
   * Backend-Status anzeigen
   */
  showBackendStatus(message, type = "info") {
    // Status-Indikator erstellen
    const indicator = document.createElement("div");
    indicator.className = `backend-status backend-status-${type}`;
    indicator.innerHTML = `
      <div class="status-content">
        <i class="fas fa-${
          type === "success"
            ? "database"
            : type === "warning"
            ? "exclamation-triangle"
            : "info-circle"
        }"></i>
        <span>${message}</span>
        ${
          this.backendAvailable
            ? '<i class="fas fa-check-circle status-ok"></i>'
            : '<i class="fas fa-times-circle status-error"></i>'
        }
      </div>
    `;

    // Styling
    const style = document.createElement("style");
    style.textContent = `
      .backend-status {
        position: fixed;
        top: 80px;
        right: 20px;
        background: rgba(0,0,0,0.9);
        color: white;
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 14px;
        z-index: 10000;
        animation: slideIn 0.3s ease;
        max-width: 300px;
      }
      .backend-status-success { border-left: 4px solid #4ade80; }
      .backend-status-warning { border-left: 4px solid #fbbf24; }
      .backend-status-info { border-left: 4px solid #60a5fa; }
      .status-content {
        display: flex;
        align-items: center;
        gap: 8px;
      }
      .status-ok { color: #4ade80; }
      .status-error { color: #ef4444; }
      @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
    `;

    document.head.appendChild(style);
    document.body.appendChild(indicator);

    // Nach 5 Sekunden ausblenden
    setTimeout(() => {
      indicator.style.animation = "slideIn 0.3s ease reverse";
      setTimeout(() => indicator.remove(), 300);
    }, 5000);
  }
}

/**
 * Database-Adapter für Backend-API
 */
class DatabaseApiAdapter {
  constructor(apiClient) {
    this.api = apiClient;
    this.customers = [];
    this.bookings = [];
    this.bookedSlots = [];

    // Initial laden
    this.loadInitialData();
  }

  /**
   * Initiale Daten vom Backend laden
   */
  async loadInitialData() {
    try {
      // Parallel laden für bessere Performance
      const [customers, bookings, services] = await Promise.all([
        this.api.get("/customers").catch(() => []),
        this.api.get("/bookings").catch(() => []),
        this.api.get("/services").catch(() => []),
      ]);

      this.customers = customers || [];
      this.bookings = bookings || [];
      this.services = services || [];

      // Gebuchte Slots aus Buchungen extrahieren
      this.bookedSlots = this.extractBookedSlots(this.bookings);

      console.log("📊 Backend-Daten geladen:", {
        customers: this.customers.length,
        bookings: this.bookings.length,
        services: this.services.length,
      });
    } catch (error) {
      console.error("Fehler beim Laden der Backend-Daten:", error);
    }
  }

  /**
   * Gebuchte Slots aus Buchungen extrahieren
   */
  extractBookedSlots(bookings) {
    return bookings.map((booking) => ({
      date: booking.booking_date || booking.date,
      time: booking.booking_time || booking.time,
      duration: booking.duration || 60,
      bookingId: booking.id || booking.bookingNumber,
    }));
  }

  /**
   * KUNDE SPEICHERN - Hauptmethode die booking.js verwendet
   */
  async saveCustomer(customerData) {
    console.log("💾 DatabaseApiAdapter.saveCustomer aufgerufen:", customerData);

    try {
      // Für Backend-Modus
      if (this.api.backendAvailable) {
        console.log("📡 Verwende Backend für Kunde");
        const customer = await this.api.post("/customers", customerData);

        // Lokal hinzufügen
        this.customers.push(customer);
        return customer;
      } else {
        // LocalStorage-Fallback
        console.log("💾 Verwende LocalStorage-Fallback für Kunde");
        return this.saveCustomerLocal(customerData);
      }
    } catch (error) {
      console.error("❌ Fehler in saveCustomer:", error);
      // Fallback zu LocalStorage
      console.log("🔄 Fallback zu LocalStorage nach Backend-Fehler");
      return this.saveCustomerLocal(customerData);
    }
  }

  /**
   * Kunde lokal speichern (LocalStorage-Fallback)
   */
  saveCustomerLocal(customerData) {
    // Validierung
    const validation = this.validateCustomer(customerData);
    if (!validation.valid) {
      throw new Error("Kundendaten ungültig: " + validation.errors.join(", "));
    }

    // Prüfe ob Kunde bereits existiert
    const existingCustomer = this.findCustomerByEmail(customerData.email);
    if (existingCustomer) {
      // Kunde aktualisieren
      Object.assign(existingCustomer, {
        ...customerData,
        updatedAt: new Date().toISOString(),
      });
      console.log("✅ Kunde aktualisiert:", existingCustomer.email);
      return existingCustomer;
    }

    // Neuen Kunden erstellen
    const customer = {
      id: this.generateCustomerId(),
      firstName: customerData.firstName || "",
      lastName: customerData.lastName || "",
      email: customerData.email || "",
      phone: customerData.phone || "",
      street: customerData.street || "",
      zip: customerData.zip || "",
      city: customerData.city || "",
      notes: customerData.notes || "",
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };

    this.customers.push(customer);

    // LocalStorage speichern falls CONFIG verfügbar
    if (typeof localStorage !== "undefined" && window.CONFIG?.STORAGE_KEYS) {
      localStorage.setItem(
        CONFIG.STORAGE_KEYS.customers,
        JSON.stringify(this.customers)
      );
    }

    console.log("✅ Neuer Kunde erstellt:", customer.email);
    return customer;
  }

  /**
   * BUCHUNG SPEICHERN - Hauptmethode die booking.js verwendet
   */
  async saveBooking(bookingData) {
    console.log("💾 DatabaseApiAdapter.saveBooking aufgerufen:", bookingData);

    try {
      // Für Backend-Modus
      if (this.api.backendAvailable) {
        console.log("📡 Verwende Backend für Buchung");
        const booking = await this.api.post("/bookings", bookingData);

        // Lokal hinzufügen
        this.bookings.push(booking);
        this.updateBookedSlots(booking);
        return booking;
      } else {
        // LocalStorage-Fallback
        console.log("💾 Verwende LocalStorage-Fallback für Buchung");
        return this.saveBookingLocal(bookingData);
      }
    } catch (error) {
      console.error("❌ Fehler in saveBooking:", error);
      // Fallback zu LocalStorage
      console.log("🔄 Fallback zu LocalStorage nach Backend-Fehler");
      return this.saveBookingLocal(bookingData);
    }
  }

  /**
   * Buchung lokal speichern (LocalStorage-Fallback)
   */
  saveBookingLocal(bookingData) {
    // Validierung
    if (!this.validateBooking(bookingData)) {
      throw new Error("Ungültige Buchungsdaten");
    }

    if (this.isSlotBooked(bookingData.date, bookingData.time)) {
      throw new Error("Slot bereits gebucht");
    }

    // Buchung erstellen
    const booking = {
      id: this.generateBookingNumber(),
      bookingNumber: this.generateBookingNumber(),
      date: bookingData.date,
      time: bookingData.time,
      services: bookingData.services || [],
      customer: bookingData.customer,
      distance: bookingData.distance || 0,
      travelCost: bookingData.travelCost || 0,
      totalPrice: bookingData.totalPrice || 0,
      status: "confirmed",
      notes: bookingData.notes || "",
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };

    this.bookings.push(booking);
    this.updateBookedSlots(booking);

    // LocalStorage speichern
    if (typeof localStorage !== "undefined" && window.CONFIG?.STORAGE_KEYS) {
      localStorage.setItem(
        CONFIG.STORAGE_KEYS.bookings,
        JSON.stringify(this.bookings)
      );
      localStorage.setItem(
        CONFIG.STORAGE_KEYS.bookedSlots,
        JSON.stringify(this.bookedSlots)
      );
    }

    console.log("✅ Buchung lokal gespeichert:", booking.id);
    return booking;
  }

  /**
   * Gebuchte Slots aktualisieren
   */
  updateBookedSlots(booking) {
    this.bookedSlots.push({
      date: booking.date || booking.booking_date,
      time: booking.time || booking.booking_time,
      duration: booking.duration || 60,
      bookingId: booking.id || booking.bookingNumber,
    });
  }

  /**
   * LEGACY-METHODEN - für Kompatibilität mit der originalen Database-Klasse
   */

  // Alias für saveCustomer (für Backend-Kompatibilität)
  async addCustomer(customerData) {
    return this.saveCustomer(customerData);
  }

  // Alias für saveBooking (für Backend-Kompatibilität)
  async addBooking(bookingData) {
    return this.saveBooking(bookingData);
  }

  /**
   * Verfügbarkeit prüfen
   */
  isSlotAvailable(date, time) {
    return !this.bookedSlots.some(
      (slot) => slot.date === date && slot.time === time
    );
  }

  /**
   * Prüft ob ein Slot bereits gebucht ist (Hauptmethode)
   */
  isSlotBooked(date, time) {
    return this.bookedSlots.some(
      (slot) => slot.date === date && slot.time === time
    );
  }

  /**
   * Gibt alle gebuchten Slots für ein Datum zurück
   */
  getBookedSlotsForDate(date) {
    return this.bookedSlots
      .filter((slot) => slot.date === date)
      .map((slot) => slot.time);
  }

  /**
   * Kunde per E-Mail finden
   */
  findCustomerByEmail(email) {
    return this.customers.find(
      (customer) => customer.email.toLowerCase() === email.toLowerCase()
    );
  }

  /**
   * Kunden-Validierung
   */
  validateCustomer(customerData) {
    const errors = [];

    // Basis-Validierung
    if (!customerData.firstName?.trim())
      errors.push("Vorname ist erforderlich");
    if (!customerData.lastName?.trim())
      errors.push("Nachname ist erforderlich");
    if (!customerData.email?.trim()) errors.push("E-Mail ist erforderlich");
    if (!customerData.phone?.trim()) errors.push("Telefon ist erforderlich");
    if (!customerData.street?.trim()) errors.push("Straße ist erforderlich");
    if (!customerData.zip?.trim()) errors.push("PLZ ist erforderlich");
    if (!customerData.city?.trim()) errors.push("Stadt ist erforderlich");

    // E-Mail Validierung
    if (customerData.email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(customerData.email.trim())) {
        errors.push("Ungültige E-Mail-Adresse");
      }
    }

    return {
      valid: errors.length === 0,
      errors: errors,
    };
  }

  /**
   * Buchungs-Validierung
   */
  validateBooking(booking) {
    // Erforderliche Felder prüfen
    if (!booking.date || !booking.time || !booking.customer) {
      return false;
    }

    // Datum in der Zukunft
    const bookingDate = new Date(booking.date + "T" + booking.time);
    if (bookingDate <= new Date()) {
      return false;
    }

    // Services vorhanden
    if (!booking.services || booking.services.length === 0) {
      return false;
    }

    return true;
  }

  /**
   * ID-Generierung
   */
  generateCustomerId() {
    let maxId = 0;
    if (this.customers && Array.isArray(this.customers)) {
      for (const customer of this.customers) {
        if (
          customer.id &&
          typeof customer.id === "number" &&
          customer.id > maxId
        ) {
          maxId = customer.id;
        }
      }
    }
    return maxId + 1;
  }

  generateBookingNumber() {
    const timestamp = new Date().getTime().toString().slice(-6);
    const random = Math.random().toString(36).substr(2, 3).toUpperCase();
    return `MCS${timestamp}${random}`;
  }

  generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
  }

  /**
   * Services abrufen
   */
  async getServices() {
    if (!this.services || this.services.length === 0) {
      try {
        this.services = await this.api.get("/services");
      } catch (error) {
        console.error("Fehler beim Laden der Services:", error);
        return [];
      }
    }
    return this.services;
  }

  /**
   * Entfernung berechnen
   */
  async calculateDistance(customerAddress) {
    try {
      return await this.api.post("/distance/calculate", {
        address: customerAddress,
      });
    } catch (error) {
      console.error("Fehler bei Entfernungsberechnung:", error);
      // Fallback zu Frontend-Berechnung
      if (window.mapsService) {
        return await window.mapsService.calculateDistance(customerAddress);
      }
      throw error;
    }
  }

  /**
   * Daten exportieren (für Kompatibilität)
   */
  exportData() {
    return {
      customers: this.customers,
      bookings: this.bookings,
      bookedSlots: this.bookedSlots,
      timestamp: new Date().toISOString(),
      source: "backend-api",
    };
  }

  /**
   * Statistiken abrufen
   */
  async getStats() {
    try {
      return await this.api.get("/system/stats");
    } catch (error) {
      return {
        customers: this.customers.length,
        bookings: this.bookings.length,
        bookedSlots: this.bookedSlots.length,
      };
    }
  }

  /**
   * Weitere LocalStorage-kompatible Methoden
   */
  saveData(key, data) {
    if (typeof localStorage !== "undefined") {
      localStorage.setItem(key, JSON.stringify(data));
    }
  }

  loadData(key, defaultValue) {
    if (typeof localStorage !== "undefined") {
      const data = localStorage.getItem(key);
      return data ? JSON.parse(data) : defaultValue;
    }
    return defaultValue;
  }
}

// API-Client global verfügbar machen
window.apiClient = new ApiClient();
