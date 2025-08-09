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
    } catch (error) {
      console.error("Fehler beim Verarbeiten der Storage-Änderung:", error);
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
      localStorage.setItem(key, JSON.stringify(data));
      return true;
    } catch (error) {
      console.error(`Fehler beim Speichern von ${key}:`, error);
      return false;
    }
  }

  /**
   * Speichert einen neuen Kunden
   */
  saveCustomer(customerData) {
    try {
      // Validierung
      if (!this.validateCustomer(customerData)) {
        throw new Error("Ungültige Kundendaten");
      }

      // Neue Kunden-ID generieren
      const customer = {
        ...customerData,
        id: this.generateId(),
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
      };

      // Prüfen ob Kunde bereits existiert (E-Mail)
      const existingCustomer = this.findCustomerByEmail(customer.email);
      if (existingCustomer) {
        // Kundendaten aktualisieren
        const index = this.customers.findIndex(
          (c) => c.id === existingCustomer.id
        );
        customer.id = existingCustomer.id;
        customer.createdAt = existingCustomer.createdAt;
        this.customers[index] = customer;
      } else {
        // Neuen Kunden hinzufügen
        this.customers.push(customer);
      }

      this.saveData(CONFIG.STORAGE_KEYS.customers, this.customers);
      return customer;
    } catch (error) {
      console.error("Fehler beim Speichern des Kunden:", error);
      throw error;
    }
  }

  /**
   * Validiert Kundendaten
   */
  validateCustomer(customer) {
    const required = [
      "firstName",
      "lastName",
      "email",
      "phone",
      "street",
      "zip",
      "city",
    ];

    // Prüfe erforderliche Felder
    for (const field of required) {
      if (!customer[field] || customer[field].trim().length === 0) {
        console.error(`Feld ${field} ist erforderlich`);
        return false;
      }
    }

    // E-Mail Validierung
    if (!CONFIG.VALIDATION.email.test(customer.email)) {
      console.error("Ungültige E-Mail-Adresse");
      return false;
    }

    // Telefon Validierung
    if (!CONFIG.VALIDATION.phone.test(customer.phone)) {
      console.error("Ungültige Telefonnummer");
      return false;
    }

    // PLZ Validierung
    if (!CONFIG.VALIDATION.zip.test(customer.zip)) {
      console.error("Ungültige PLZ");
      return false;
    }

    // Namen-Länge
    if (
      customer.firstName.length < CONFIG.VALIDATION.minNameLength ||
      customer.lastName.length < CONFIG.VALIDATION.minNameLength
    ) {
      console.error("Vor- und Nachname müssen mindestens 2 Zeichen lang sein");
      return false;
    }

    return true;
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
        throw new Error("Ungültige Buchungsdaten");
      }

      // Prüfe ob Slot noch verfügbar ist
      if (this.isSlotBooked(bookingData.date, bookingData.time)) {
        throw new Error("Dieser Termin ist bereits gebucht");
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

      return booking;
    } catch (error) {
      console.error("Fehler beim Speichern der Buchung:", error);
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
    const totalBookings = this.bookings.length;
    const confirmedBookings = this.bookings.filter(
      (b) => b.status === "confirmed"
    ).length;
    const cancelledBookings = this.bookings.filter(
      (b) => b.status === "cancelled"
    ).length;
    const totalCustomers = this.customers.length;

    const totalRevenue = this.bookings
      .filter((b) => b.status === "confirmed")
      .reduce((sum, b) => sum + (b.totalPrice || 0), 0);

    return {
      totalBookings,
      confirmedBookings,
      cancelledBookings,
      totalCustomers,
      totalRevenue,
      averageBookingValue: totalRevenue / (confirmedBookings || 1),
    };
  }

  /**
   * Datenbank exportieren
   */
  exportData() {
    return {
      customers: this.customers,
      bookings: this.bookings,
      bookedSlots: this.bookedSlots,
      exportedAt: new Date().toISOString(),
    };
  }

  /**
   * Datenbank importieren
   */
  importData(data) {
    try {
      if (data.customers) this.customers = data.customers;
      if (data.bookings) this.bookings = data.bookings;
      if (data.bookedSlots) this.bookedSlots = data.bookedSlots;

      // Alle Daten speichern
      this.saveData(CONFIG.STORAGE_KEYS.customers, this.customers);
      this.saveData(CONFIG.STORAGE_KEYS.bookings, this.bookings);
      this.saveBookedSlots();

      return true;
    } catch (error) {
      console.error("Fehler beim Importieren der Daten:", error);
      return false;
    }
  }

  /**
   * Datenbank zurücksetzen
   */
  resetDatabase() {
    this.customers = [];
    this.bookings = [];
    this.bookedSlots = CONFIG.showTestData
      ? [...CONFIG.DEMO_DATA.sampleBookings]
      : [];

    this.saveData(CONFIG.STORAGE_KEYS.customers, this.customers);
    this.saveData(CONFIG.STORAGE_KEYS.bookings, this.bookings);
    this.saveBookedSlots();

    console.log("Datenbank wurde zurückgesetzt");
  }
}

// Globale Datenbankinstanz
window.db = new Database();
