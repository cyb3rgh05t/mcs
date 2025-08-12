/**
 * Mobile Car Service - Datenbank-Simulation
 * Verwaltet Kunden, Buchungen und gebuchte Slots
 */

class Database {
  constructor() {
    this.init();
    this.setupEventListeners();
  }

  /**
   * Initialisiert die Datenbank mit Standard-Daten
   */
  init() {
    // Daten aus LocalStorage laden oder initialisieren
    this.customers = this.loadData(CONFIG.STORAGE_KEYS.customers, []);
    this.bookings = this.loadData(CONFIG.STORAGE_KEYS.bookings, []);
    this.bookedSlots = this.loadData(CONFIG.STORAGE_KEYS.bookedSlots, []);

    // Demo-Daten hinzufügen falls leer
    if (this.bookedSlots.length === 0 && CONFIG.showTestData) {
      this.bookedSlots = [...CONFIG.DEMO_DATA.sampleBookings];
      this.saveBookedSlots();
    }
  }

  /**
   * Event Listeners für Storage-Änderungen
   */
  setupEventListeners() {
    window.addEventListener("storage", (e) => {
      if (Object.values(CONFIG.STORAGE_KEYS).includes(e.key)) {
        this.handleStorageChange(e.key, e.newValue);
      }
    });
  }

  getStorageUsage() {
    try {
      let totalSize = 0;

      Object.values(CONFIG.STORAGE_KEYS).forEach((key) => {
        const data = localStorage.getItem(key);
        if (data) {
          totalSize += data.length;
        }
      });

      // Größe in KB umrechnen
      const sizeKB = Math.round((totalSize / 1024) * 100) / 100;

      // Warnung bei hohem Speicherverbrauch
      if (sizeKB > 5000) {
        // > 5MB
        toast.warning(`Speicherverbrauch hoch: ${sizeKB} KB`);
      }

      return `${sizeKB} KB`;
    } catch (error) {
      console.error("Fehler beim Berechnen der Speichernutzung:", error);
      return "Unbekannt";
    }
  }

  /**
   * Behandelt Storage-Änderungen von anderen Tabs
   */
  handleStorageChange(key, newValue) {
    try {
      const data = JSON.parse(newValue);

      switch (key) {
        case CONFIG.STORAGE_KEYS.customers:
          this.customers = data;
          break;
        case CONFIG.STORAGE_KEYS.bookings:
          this.bookings = data;
          break;
        case CONFIG.STORAGE_KEYS.bookedSlots:
          this.bookedSlots = data;
          break;
      }

      this.notifyDataChange(key, data);

      // Optional: Toast bei Datenänderungen von anderen Tabs
      if (CONFIG.debug) {
        toast.info("Daten wurden in anderem Tab geändert");
      }
    } catch (error) {
      console.error("Fehler beim Verarbeiten der Storage-Änderung:", error);
      toast.error("Fehler beim Synchronisieren der Daten");
    }
  }

  /**
   * Benachrichtigt über Datenänderungen
   */
  notifyDataChange(key, data) {
    const event = new CustomEvent("dataChanged", {
      detail: { key, data },
    });
    window.dispatchEvent(event);
  }

  /**
   * Lädt Daten aus LocalStorage
   */
  loadData(key, defaultValue) {
    try {
      const data = localStorage.getItem(key);
      return data ? JSON.parse(data) : defaultValue;
    } catch (error) {
      console.error(`Fehler beim Laden von ${key}:`, error);
      return defaultValue;
    }
  }

  /**
   * Speichert Daten in LocalStorage
   */
  saveData(key, data) {
    try {
      // Eingaben validieren
      if (!key || typeof key !== "string") {
        throw new Error("Ungültiger Storage-Key");
      }

      if (data === undefined || data === null) {
        throw new Error("Keine Daten zum Speichern");
      }

      // JSON serialisieren
      const jsonString = JSON.stringify(data);

      // Speichern
      localStorage.setItem(key, jsonString);

      // Optional: Erfolgs-Log für Debug
      if (CONFIG.debug) {
        console.log(
          `💾 Daten gespeichert: ${key} (${jsonString.length} Zeichen)`
        );
      }
    } catch (error) {
      console.error(`Fehler beim Speichern von ${key}:`, error);

      // Toast nur bei kritischen Fehlern
      if (error.name === "QuotaExceededError") {
        if (window.toast) {
          toast.error("Speicher voll - bitte löschen Sie alte Daten");
        }
      } else {
        if (window.toast) {
          toast.error("Fehler beim Speichern der Daten");
        }
      }

      throw error;
    }
  }

  /**
   * Speichert einen neuen Kunden
   */
  saveCustomer(customerData) {
    try {
      console.log("💾 Speichere Kunde:", customerData);

      // Validierung mit verbessertem Error-Handling
      const validation = this.validateCustomer(customerData);

      if (!validation.valid) {
        const errorMessage =
          validation.errors && Array.isArray(validation.errors)
            ? validation.errors.join(", ")
            : "Unbekannte Validierungsfehler";

        console.error("Validierungsfehler:", validation.errors);
        throw new Error("Ungültige Kundendaten: " + errorMessage);
      }

      // Neue ID generieren
      const id = this.generateCustomerId();

      // Kunde erstellen mit sicheren Standardwerten
      const customer = {
        id: id,
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

      // Zu Array hinzufügen
      this.customers.push(customer);

      // In LocalStorage speichern
      this.saveData(CONFIG.STORAGE_KEYS.customers, this.customers);

      console.log("✅ Kunde erfolgreich gespeichert:", customer.id);
      return customer;
    } catch (error) {
      console.error("Fehler beim Speichern des Kunden:", error);
      // Error wird in booking.js gehandelt
      throw error;
    }
  }

  /**
   * Validiert Kundendaten
   */
  validateCustomer(customerData) {
    try {
      const errors = [];

      // Eingabedaten prüfen
      if (!customerData || typeof customerData !== "object") {
        return {
          valid: false,
          errors: ["Keine Kundendaten übergeben"],
        };
      }

      // Erforderliche Felder prüfen
      const requiredFields = [
        { field: "firstName", name: "Vorname" },
        { field: "lastName", name: "Nachname" },
        { field: "email", name: "E-Mail" },
        { field: "phone", name: "Telefon" },
        { field: "street", name: "Straße" },
        { field: "zip", name: "PLZ" },
        { field: "city", name: "Stadt" },
      ];

      for (const { field, name } of requiredFields) {
        const value = customerData[field];
        if (!value || typeof value !== "string" || value.trim().length === 0) {
          errors.push(`${name} ist erforderlich`);
        }
      }

      // E-Mail Validierung
      if (customerData.email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(customerData.email.trim())) {
          errors.push("Ungültige E-Mail-Adresse");
        }
      }

      // Telefon Validierung
      if (customerData.phone) {
        const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
        if (!phoneRegex.test(customerData.phone.trim())) {
          errors.push("Ungültige Telefonnummer");
        }
      }

      // PLZ Validierung
      if (customerData.zip) {
        const zipRegex = /^[0-9]{5}$/;
        if (!zipRegex.test(customerData.zip.trim())) {
          errors.push("PLZ muss 5 Ziffern haben");
        }
      }

      return {
        valid: errors.length === 0,
        errors: errors,
      };
    } catch (error) {
      console.error("Fehler bei der Kundenvalidierung:", error);
      return {
        valid: false,
        errors: ["Validierung fehlgeschlagen"],
      };
    }
  }

  /**
   * Findet Kunde anhand E-Mail
   */
  findCustomerByEmail(email) {
    return this.customers.find(
      (customer) => customer.email.toLowerCase() === email.toLowerCase()
    );
  }

  /**
   * Speichert eine neue Buchung
   */
  saveBooking(bookingData) {
    try {
      // Validierung
      if (!this.validateBooking(bookingData)) {
        toast.error("Buchungsdaten sind unvollständig");
        throw new Error("Ungültige Buchungsdaten");
      }

      // Prüfe ob Slot noch frei ist
      if (this.isSlotBooked(bookingData.date, bookingData.time)) {
        toast.error("Dieser Termin ist bereits gebucht");
        throw new Error("Slot bereits gebucht");
      }

      // Neue Buchung erstellen
      const booking = {
        ...bookingData,
        id: this.generateBookingNumber(),
        status: "confirmed",
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
      };

      // Buchung speichern
      this.bookings.push(booking);
      this.saveData(CONFIG.STORAGE_KEYS.bookings, this.bookings);

      // Slot als gebucht markieren
      this.bookedSlots.push({
        date: booking.date,
        time: booking.time,
        bookingId: booking.id,
      });
      this.saveBookedSlots();

      // Erfolg wird in booking.js gehandelt, hier nur bei Fehlern
      console.log("✅ Buchung erfolgreich gespeichert:", booking.id);

      return booking;
    } catch (error) {
      console.error("Fehler beim Speichern der Buchung:", error);
      // Error wird in booking.js gehandelt
      throw error;
    }
  }

  /**
   * Validiert Buchungsdaten
   */
  validateBooking(booking) {
    // Erforderliche Felder prüfen
    if (!booking.date || !booking.time || !booking.customer) {
      console.error("Datum, Zeit und Kundendaten sind erforderlich");
      return false;
    }

    // Datum in der Zukunft
    const bookingDate = new Date(booking.date + "T" + booking.time);
    if (bookingDate <= new Date()) {
      console.error("Buchungsdatum muss in der Zukunft liegen");
      return false;
    }

    // Services vorhanden
    if (!booking.services || booking.services.length === 0) {
      console.error("Mindestens ein Service muss gewählt werden");
      return false;
    }

    return true;
  }

  /**
   * Prüft ob ein Slot bereits gebucht ist
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
   * Generiert eine eindeutige ID
   */
  generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
  }

  generateCustomerId() {
    try {
      // Höchste vorhandene ID finden
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
    } catch (error) {
      console.error("Fehler bei der ID-Generierung:", error);
      // Fallback: Timestamp-basierte ID
      return Date.now();
    }
  }

  /**
   * Generiert eine Buchungsnummer
   */
  generateBookingNumber() {
    const timestamp = new Date().getTime().toString().slice(-6);
    const random = Math.random().toString(36).substr(2, 3).toUpperCase();
    return `MCS${timestamp}${random}`;
  }

  /**
   * Speichert gebuchte Slots
   */
  saveBookedSlots() {
    this.saveData(CONFIG.STORAGE_KEYS.bookedSlots, this.bookedSlots);
  }

  /**
   * Holt eine Buchung anhand der ID
   */
  getBookingById(id) {
    return this.bookings.find((booking) => booking.id === id);
  }

  /**
   * Holt alle Buchungen eines Kunden
   */
  getBookingsByCustomer(customerId) {
    return this.bookings.filter(
      (booking) => booking.customer.id === customerId
    );
  }

  /**
   * Aktualisiert den Status einer Buchung
   */
  updateBookingStatus(bookingId, status) {
    const booking = this.getBookingById(bookingId);
    if (booking) {
      booking.status = status;
      booking.updatedAt = new Date().toISOString();
      this.saveData(CONFIG.STORAGE_KEYS.bookings, this.bookings);
      return booking;
    }
    return null;
  }

  /**
   * Storniert eine Buchung
   */
  cancelBooking(bookingId) {
    const booking = this.getBookingById(bookingId);
    if (booking) {
      // Status auf storniert setzen
      booking.status = "cancelled";
      booking.cancelledAt = new Date().toISOString();

      // Slot wieder freigeben
      this.bookedSlots = this.bookedSlots.filter(
        (slot) => !(slot.date === booking.date && slot.time === booking.time)
      );

      this.saveData(CONFIG.STORAGE_KEYS.bookings, this.bookings);
      this.saveBookedSlots();

      return booking;
    }
    return null;
  }

  /**
   * Statistiken abrufen
   */
  getStats() {
    try {
      const stats = {
        customers: this.customers.length,
        bookings: this.bookings.length,
        bookedSlots: this.bookedSlots.length,
        storageUsed: this.getStorageUsage(),
        lastBooking:
          this.bookings.length > 0
            ? this.bookings[this.bookings.length - 1].createdAt
            : null,
      };

      // Optional: Stats als Toast anzeigen (nur bei manueller Abfrage)
      if (CONFIG.debug) {
        toast.info(`📊 ${stats.customers} Kunden, ${stats.bookings} Buchungen`);
      }

      console.table(stats);
      return stats;
    } catch (error) {
      console.error("Fehler beim Abrufen der Statistiken:", error);
      toast.error("Fehler beim Laden der Statistiken");
      return null;
    }
  }

  /**
   * Datenbank exportieren
   */
  exportData() {
    try {
      const loadingToast = toast.loading("Daten werden exportiert...");

      // Alle Daten sammeln
      const exportData = {
        customers: this.customers,
        bookings: this.bookings,
        bookedSlots: this.bookedSlots,
        exportDate: new Date().toISOString(),
        version: CONFIG.version || "1.0.0",
      };

      // JSON erstellen
      const jsonString = JSON.stringify(exportData, null, 2);

      // Download erstellen
      const blob = new Blob([jsonString], { type: "application/json" });
      const url = URL.createObjectURL(blob);

      const a = document.createElement("a");
      a.href = url;
      a.download = `mcs_backup_${new Date().toISOString().split("T")[0]}.json`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);

      URL.revokeObjectURL(url);

      // Success
      toast.loadingToSuccess(loadingToast.id, "Daten erfolgreich exportiert");

      return exportData;
    } catch (error) {
      console.error("Fehler beim Exportieren der Daten:", error);
      toast.error("Fehler beim Exportieren der Daten");
      throw error;
    }
  }

  /**
   * Datenbank importieren
   */
  importData(file) {
    return new Promise((resolve, reject) => {
      const loadingToast = toast.loading("Daten werden importiert...");

      const reader = new FileReader();

      reader.onload = (e) => {
        try {
          const importData = JSON.parse(e.target.result);

          // Validierung der Import-Daten
          if (
            !importData.customers ||
            !importData.bookings ||
            !importData.bookedSlots
          ) {
            throw new Error("Ungültiges Backup-Format");
          }

          // Bestätigung
          const confirmImport = confirm(
            `Import von ${importData.customers.length} Kunden und ${importData.bookings.length} Buchungen? Aktuelle Daten werden überschrieben.`
          );

          if (!confirmImport) {
            toast.loadingToError(loadingToast.id, "Import abgebrochen");
            resolve(false);
            return;
          }

          // Daten importieren
          this.customers = importData.customers;
          this.bookings = importData.bookings;
          this.bookedSlots = importData.bookedSlots;

          // Speichern
          this.saveData(CONFIG.STORAGE_KEYS.customers, this.customers);
          this.saveData(CONFIG.STORAGE_KEYS.bookings, this.bookings);
          this.saveData(CONFIG.STORAGE_KEYS.bookedSlots, this.bookedSlots);

          // Success
          toast.loadingToSuccess(
            loadingToast.id,
            `Import erfolgreich: ${importData.customers.length} Kunden, ${importData.bookings.length} Buchungen`
          );

          resolve(true);
        } catch (error) {
          console.error("Fehler beim Importieren:", error);
          toast.loadingToError(
            loadingToast.id,
            "Fehler beim Importieren der Daten"
          );
          reject(error);
        }
      };

      reader.onerror = () => {
        toast.loadingToError(loadingToast.id, "Fehler beim Lesen der Datei");
        reject(new Error("Fehler beim Lesen der Datei"));
      };

      reader.readAsText(file);
    });
  }

  /**
   * Datenbank zurücksetzen
   */
  resetDatabase() {
    try {
      // Warnung anzeigen
      const confirmReset = confirm(
        "Sind Sie sicher, dass Sie alle Daten löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden."
      );

      if (!confirmReset) {
        toast.info("Reset abgebrochen");
        return false;
      }

      // Loading-Toast
      const loadingToast = toast.loading("Datenbank wird zurückgesetzt...");

      // Daten löschen
      this.customers = [];
      this.bookings = [];
      this.bookedSlots = [];

      // LocalStorage leeren
      Object.values(CONFIG.STORAGE_KEYS).forEach((key) => {
        localStorage.removeItem(key);
      });

      // Demo-Daten wieder hinzufügen falls konfiguriert
      if (CONFIG.showTestData) {
        this.bookedSlots = [...CONFIG.DEMO_DATA.sampleBookings];
        this.saveBookedSlots();
      }

      // Success
      toast.loadingToSuccess(
        loadingToast.id,
        "Datenbank erfolgreich zurückgesetzt"
      );

      console.log("🗑️ Datenbank zurückgesetzt");
      return true;
    } catch (error) {
      console.error("Fehler beim Zurücksetzen der Datenbank:", error);
      toast.error("Fehler beim Zurücksetzen der Datenbank");
      return false;
    }
  }
}

// Globale Datenbankinstanz
window.db = new Database();
