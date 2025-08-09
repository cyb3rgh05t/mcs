/**
 * Mobile Car Service - Buchungslogik
 * Verwaltet den kompletten Buchungsprozess
 */

class BookingFlow {
  constructor() {
    this.currentStep = 1;
    this.maxStep = 5;
    this.currentBooking = this.resetBooking();
    this.validators = new Map();
    this.distanceTimeout = null;

    this.initializeValidators();
    this.setupEventListeners();
  }

  /**
   * Setzt die Buchungsdaten zurück
   */
  resetBooking() {
    return {
      date: null,
      time: null,
      services: [],
      customer: null,
      distance: 0,
      travelCost: 0,
      totalPrice: 0,
      distanceInfo: null,
    };
  }

  /**
   * Initialisiert Validatoren für jeden Schritt
   */
  initializeValidators() {
    this.validators.set(1, () => this.validateStep1());
    this.validators.set(2, () => this.validateStep2());
    this.validators.set(3, () => this.validateStep3());
    this.validators.set(4, () => this.validateStep4());
  }

  /**
   * Setup Event Listeners
   */
  setupEventListeners() {
    // Form Event Listeners
    document.addEventListener("DOMContentLoaded", () => {
      this.setupFormListeners();
      this.generateCalendar();
      this.generateServices();
    });

    // Custom Events
    window.addEventListener("dataChanged", (e) => {
      this.handleDataChange(e.detail);
    });
  }

  /**
   * Setup Form Listeners
   */
  setupFormListeners() {
    const form = document.getElementById("customer-form");
    if (form) {
      const inputs = form.querySelectorAll("input[required], textarea");
      inputs.forEach((input) => {
        input.addEventListener("blur", () => this.validateCustomerData());
        input.addEventListener("input", () => this.debouncedValidation());
      });
    }
  }

  /**
   * Debounced Validation für bessere Performance
   */
  debouncedValidation() {
    clearTimeout(this.distanceTimeout);
    this.distanceTimeout = setTimeout(() => {
      this.validateCustomerData();
    }, 1000);
  }

  /**
   * Generiert den Kalender
   */
  generateCalendar() {
    const calendar = document.getElementById("calendar");
    if (!calendar) return;

    calendar.innerHTML = "";
    const today = new Date();
    const maxDays = CONFIG.BUSINESS_HOURS.daysInAdvance;

    for (let i = 0; i < maxDays; i++) {
      const date = new Date(today);
      date.setDate(today.getDate() + i);

      const dateString = date.toISOString().split("T")[0];
      const dayName = date.toLocaleDateString("de-DE", { weekday: "short" });
      const dayNumber = date.getDate();
      const monthName = date.toLocaleDateString("de-DE", { month: "short" });
      const isToday = i === 0;

      const dateElement = document.createElement("div");
      dateElement.className = `calendar-day ${isToday ? "today" : ""}`;
      dateElement.innerHTML = `
                <div class="day-name">${dayName}</div>
                <div class="day-number">${dayNumber}</div>
                <div class="month-name">${monthName}</div>
            `;

      // Check if day is weekend (optional: disable weekends)
      const isWeekend = date.getDay() === 0 || date.getDay() === 6;
      if (isWeekend) {
        dateElement.classList.add("weekend");
      }

      dateElement.onclick = () => this.selectDate(dateString, dateElement);
      calendar.appendChild(dateElement);
    }
  }

  /**
   * Datum auswählen
   */
  selectDate(dateString, element) {
    // Vorherige Auswahl entfernen
    document
      .querySelectorAll(".calendar-day.selected")
      .forEach((el) => el.classList.remove("selected"));

    // Neue Auswahl
    element.classList.add("selected");
    this.currentBooking.date = dateString;

    // Zeitslots für das gewählte Datum generieren
    this.generateTimeSlots(dateString);

    // Zeitauswahl anzeigen
    const timeSlots = document.getElementById("time-slots");
    if (timeSlots) {
      timeSlots.style.display = "block";
    }

    // Schritt validieren
    this.validateCurrentStep();
  }

  /**
   * Generiert Zeitslots für ein Datum
   */
  generateTimeSlots(date) {
    const timeGrid = document.getElementById("time-grid");
    if (!timeGrid) return;

    timeGrid.innerHTML = "";

    const start = CONFIG.BUSINESS_HOURS.start;
    const end = CONFIG.BUSINESS_HOURS.end;
    const interval = CONFIG.BUSINESS_HOURS.interval;

    for (let hour = start; hour < end; hour++) {
      const timeString = `${hour.toString().padStart(2, "0")}:00`;
      const isBooked = db.isSlotBooked(date, timeString);

      const timeElement = document.createElement("div");
      timeElement.className = `time-slot ${isBooked ? "booked" : ""}`;
      timeElement.innerHTML = `
                <div class="time-text">${timeString}</div>
                ${isBooked ? '<div class="booked-text">Belegt</div>' : ""}
            `;

      if (!isBooked) {
        timeElement.onclick = () => this.selectTime(timeString, timeElement);
      }

      timeGrid.appendChild(timeElement);
    }
  }

  /**
   * Uhrzeit auswählen
   */
  selectTime(timeString, element) {
    document
      .querySelectorAll(".time-slot.selected")
      .forEach((el) => el.classList.remove("selected"));

    element.classList.add("selected");
    this.currentBooking.time = timeString;

    this.validateCurrentStep();
  }

  /**
   * Generiert Services
   */
  generateServices() {
    const servicesGrid = document.getElementById("services-grid");
    if (!servicesGrid) return;

    servicesGrid.innerHTML = "";

    const services = serviceManager.getAllServices();

    services.forEach((service) => {
      const serviceElement = document.createElement("div");
      serviceElement.className = "service-card";

      // Popularity indicator
      const popularBadge = service.popular
        ? '<div class="popular-badge"><i class="fas fa-star"></i> Beliebt</div>'
        : "";

      serviceElement.innerHTML = `
                ${popularBadge}
                <div class="service-icon">
                    <i class="${service.icon}"></i>
                </div>
                <div class="service-content">
                    <h4>${service.name}</h4>
                    <p>${service.description}</p>
                    <div class="service-details">
                        <span class="duration"><i class="fas fa-clock"></i> ${service.duration} Min</span>
                        <span class="price">${service.price}€</span>
                    </div>
                </div>
            `;

      serviceElement.onclick = () =>
        this.toggleService(service, serviceElement);
      servicesGrid.appendChild(serviceElement);
    });
  }

  /**
   * Service auswählen/abwählen
   */
  toggleService(service, element) {
    const index = this.currentBooking.services.findIndex(
      (s) => s.id === service.id
    );

    if (index > -1) {
      this.currentBooking.services.splice(index, 1);
      element.classList.remove("selected");
    } else {
      this.currentBooking.services.push(service);
      element.classList.add("selected");
    }

    this.updateServicesSummary();
    this.validateCurrentStep();
  }

  /**
   * Update Services Summary (optional UI enhancement)
   */
  updateServicesSummary() {
    const selected = this.currentBooking.services;
    const totalPrice = selected.reduce((sum, s) => sum + s.price, 0);
    const totalDuration = selected.reduce((sum, s) => sum + s.duration, 0);

    // Optional: Show live summary while selecting services
    console.log(
      `Selected: ${selected.length} services, ${totalPrice}€, ${totalDuration} min`
    );
  }

  /**
   * Validiert Kundendaten
   */
  async validateCustomerData() {
    const form = document.getElementById("customer-form");
    if (!form) return false;

    const formData = new FormData(form);
    const customer = {};

    for (let [key, value] of formData.entries()) {
      customer[key] = value.trim();
    }

    // Basis-Validierung
    const validation = this.validateCustomerForm(customer);

    if (validation.valid) {
      this.currentBooking.customer = customer;

      // Entfernung berechnen
      try {
        await this.calculateDistance(customer);
        this.enableStep3Next();
      } catch (error) {
        console.error("Fehler bei der Entfernungsberechnung:", error);
        this.showDistanceError();
      }
    } else {
      this.disableStep3Next();
      this.hideDistanceInfo();
    }

    return validation.valid;
  }

  /**
   * Validiert Kundenformular
   */
  validateCustomerForm(customer) {
    const errors = [];
    const required = [
      "firstName",
      "lastName",
      "email",
      "phone",
      "street",
      "zip",
      "city",
    ];

    // Erforderliche Felder prüfen
    for (const field of required) {
      if (!customer[field] || customer[field].length === 0) {
        errors.push(`${field} ist erforderlich`);
      }
    }

    // E-Mail Validierung
    if (customer.email && !CONFIG.VALIDATION.email.test(customer.email)) {
      errors.push("Ungültige E-Mail-Adresse");
    }

    // Telefon Validierung
    if (customer.phone && !CONFIG.VALIDATION.phone.test(customer.phone)) {
      errors.push("Ungültige Telefonnummer");
    }

    // PLZ Validierung
    if (customer.zip && !CONFIG.VALIDATION.zip.test(customer.zip)) {
      errors.push("Ungültige PLZ");
    }

    return {
      valid: errors.length === 0,
      errors,
    };
  }

  /**
   * Berechnet Entfernung
   */
  async calculateDistance(customer) {
    try {
      // Loading State
      this.showDistanceCalculating();

      const result = await mapsService.calculateDistance(customer);
      const travelCostInfo = mapsService.calculateTravelCost(result.distance);

      this.currentBooking.distance = result.distance;
      this.currentBooking.travelCost = travelCostInfo.cost;
      this.currentBooking.distanceInfo = {
        ...result,
        costInfo: travelCostInfo,
      };

      this.showDistanceInfo(result.distance, travelCostInfo);
    } catch (error) {
      console.error("Fehler bei der Entfernungsberechnung:", error);
      throw error;
    }
  }

  /**
   * Zeigt Entfernungsinfo an
   */
  showDistanceInfo(distance, costInfo) {
    const distanceInfo = document.getElementById("distance-info");
    const distanceKm = document.getElementById("distance-km");
    const travelCost = document.getElementById("travel-cost");

    if (distanceInfo && distanceKm && travelCost) {
      distanceKm.textContent = distance.toFixed(1);
      travelCost.textContent = costInfo.cost.toFixed(2);
      distanceInfo.style.display = "block";
    }
  }

  /**
   * Versteckt Entfernungsinfo
   */
  hideDistanceInfo() {
    const distanceInfo = document.getElementById("distance-info");
    if (distanceInfo) {
      distanceInfo.style.display = "none";
    }
  }

  /**
   * Zeigt Loading State für Entfernungsberechnung
   */
  showDistanceCalculating() {
    const distanceInfo = document.getElementById("distance-info");
    if (distanceInfo) {
      distanceInfo.innerHTML = `
                <div class="distance-card calculating">
                    <i class="fas fa-spinner fa-spin"></i>
                    <div class="distance-details">
                        <h4>Entfernung wird berechnet...</h4>
                        <p>Bitte warten Sie einen Moment</p>
                    </div>
                </div>
            `;
      distanceInfo.style.display = "block";
    }
  }

  /**
   * Zeigt Entfernungsfehler
   */
  showDistanceError() {
    const distanceInfo = document.getElementById("distance-info");
    if (distanceInfo) {
      distanceInfo.innerHTML = `
                <div class="distance-card error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="distance-details">
                        <h4>Entfernung konnte nicht berechnet werden</h4>
                        <p>Bitte überprüfen Sie Ihre Adresse</p>
                    </div>
                </div>
            `;
      distanceInfo.style.display = "block";
    }
  }

  /**
   * Step 3 Next Button aktivieren
   */
  enableStep3Next() {
    const button = document.getElementById("step3-next");
    if (button) {
      button.disabled = false;
    }
  }

  /**
   * Step 3 Next Button deaktivieren
   */
  disableStep3Next() {
    const button = document.getElementById("step3-next");
    if (button) {
      button.disabled = true;
    }
  }

  /**
   * Generiert Buchungsübersicht
   */
  generateBookingSummary() {
    const summary = document.getElementById("booking-summary");
    if (!summary) return;

    const selectedDate = new Date(this.currentBooking.date);
    const formattedDate = selectedDate.toLocaleDateString("de-DE", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    });

    let totalPrice = this.currentBooking.travelCost;
    let totalDuration = 0;

    this.currentBooking.services.forEach((service) => {
      totalPrice += service.price;
      totalDuration += service.duration;
    });

    this.currentBooking.totalPrice = totalPrice;

    summary.innerHTML = `
            <div class="summary-section">
                <h4><i class="fas fa-calendar"></i> Termindetails</h4>
                <div class="summary-item">
                    <span>Datum:</span>
                    <span>${formattedDate}</span>
                </div>
                <div class="summary-item">
                    <span>Uhrzeit:</span>
                    <span>${this.currentBooking.time} Uhr</span>
                </div>
                <div class="summary-item">
                    <span>Geschätzte Dauer:</span>
                    <span>${totalDuration} Minuten</span>
                </div>
            </div>

            <div class="summary-section">
                <h4><i class="fas fa-user"></i> Kundeninformationen</h4>
                <div class="summary-item">
                    <span>Name:</span>
                    <span>${this.currentBooking.customer.firstName} ${
      this.currentBooking.customer.lastName
    }</span>
                </div>
                <div class="summary-item">
                    <span>E-Mail:</span>
                    <span>${this.currentBooking.customer.email}</span>
                </div>
                <div class="summary-item">
                    <span>Telefon:</span>
                    <span>${this.currentBooking.customer.phone}</span>
                </div>
                <div class="summary-item">
                    <span>Adresse:</span>
                    <span>${this.currentBooking.customer.street}, ${
      this.currentBooking.customer.zip
    } ${this.currentBooking.customer.city}</span>
                </div>
            </div>

            <div class="summary-section">
                <h4><i class="fas fa-tools"></i> Gewählte Services</h4>
                ${this.currentBooking.services
                  .map(
                    (service) => `
                    <div class="summary-item">
                        <span>${service.name} (${service.duration} Min)</span>
                        <span>${service.price}€</span>
                    </div>
                `
                  )
                  .join("")}
            </div>

            <div class="summary-section">
                <h4><i class="fas fa-route"></i> Anfahrt</h4>
                <div class="summary-item">
                    <span>Entfernung:</span>
                    <span>${this.currentBooking.distance.toFixed(1)} km</span>
                </div>
                <div class="summary-item">
                    <span>Anfahrtspauschale:</span>
                    <span>${this.currentBooking.travelCost.toFixed(2)}€</span>
                </div>
            </div>

            <div class="summary-total">
                <div class="total-item">
                    <span>Gesamtpreis:</span>
                    <span>${totalPrice.toFixed(2)}€</span>
                </div>
            </div>
        `;
  }

  /**
   * Navigation zwischen Schritten
   */
  nextStep(stepNumber) {
    if (!this.validateCurrentStep()) {
      this.showValidationErrors();
      return;
    }

    this.currentStep = stepNumber;
    this.showStep(stepNumber);
    this.updateProgress(stepNumber);

    // Spezielle Aktionen für bestimmte Schritte
    if (stepNumber === 4) {
      this.generateBookingSummary();
    }
  }

  /**
   * Vorheriger Schritt
   */
  previousStep(stepNumber) {
    this.currentStep = stepNumber;
    this.showStep(stepNumber);
    this.updateProgress(stepNumber);
  }

  /**
   * Zeigt einen Schritt an
   */
  showStep(stepNumber) {
    // Alle Schritte ausblenden
    document
      .querySelectorAll(".step-content")
      .forEach((step) => step.classList.remove("active"));

    // Gewählten Schritt anzeigen
    const step = document.getElementById(`step${stepNumber}`);
    if (step) {
      step.classList.add("active");
    }
  }

  /**
   * Aktualisiert Progress-Anzeige
   */
  updateProgress(currentStep) {
    document.querySelectorAll(".progress-step").forEach((step, index) => {
      const stepNumber = index + 1;
      step.classList.remove("active", "completed");

      if (stepNumber < currentStep) {
        step.classList.add("completed");
      } else if (stepNumber === currentStep) {
        step.classList.add("active");
      }
    });
  }

  /**
   * Validiert aktuellen Schritt
   */
  validateCurrentStep() {
    const validator = this.validators.get(this.currentStep);
    return validator ? validator() : true;
  }

  /**
   * Validierungsfunktionen für jeden Schritt
   */
  validateStep1() {
    const valid = this.currentBooking.date && this.currentBooking.time;

    // UI Updates
    const nextButton = document.getElementById("step1-next");
    if (nextButton) {
      nextButton.disabled = !valid;
    }

    return valid;
  }

  validateStep2() {
    return this.currentBooking.services.length > 0;
  }

  validateStep3() {
    return this.currentBooking.customer && this.currentBooking.distance > 0;
  }

  validateStep4() {
    return true; // Zusammenfassung erfordert keine weitere Validierung
  }

  /**
   * Zeigt Validierungsfehler
   */
  showValidationErrors() {
    // Implementiere spezifische Fehlermeldungen basierend auf aktuellem Schritt
    console.warn(`Validierung für Schritt ${this.currentStep} fehlgeschlagen`);
  }

  /**
   * Buchung bestätigen
   */
  async confirmBooking() {
    try {
      // Finale Validierung
      if (!this.validateBooking()) {
        throw new Error("Buchungsdaten sind unvollständig");
      }

      // Kunde speichern
      const savedCustomer = db.saveCustomer(this.currentBooking.customer);

      // Buchung vorbereiten
      const booking = {
        customer: savedCustomer,
        date: this.currentBooking.date,
        time: this.currentBooking.time,
        services: this.currentBooking.services,
        distance: this.currentBooking.distance,
        travelCost: this.currentBooking.travelCost,
        totalPrice: this.currentBooking.totalPrice,
        distanceInfo: this.currentBooking.distanceInfo,
      };

      // Buchung speichern
      const savedBooking = db.saveBooking(booking);

      // Buchungsnummer anzeigen
      const bookingNumberElement = document.getElementById("booking-number");
      if (bookingNumberElement) {
        bookingNumberElement.textContent = savedBooking.id;
      }

      // Erfolgsseite anzeigen
      this.nextStep(5);

      // Optional: Analytics oder Tracking
      this.trackBookingSuccess(savedBooking);
    } catch (error) {
      console.error("Fehler bei der Buchungsbestätigung:", error);
      this.showBookingError(error.message);
    }
  }

  /**
   * Vollständige Buchungsvalidierung
   */
  validateBooking() {
    return (
      this.currentBooking.date &&
      this.currentBooking.time &&
      this.currentBooking.services.length > 0 &&
      this.currentBooking.customer &&
      this.currentBooking.distance > 0
    );
  }

  /**
   * Zeigt Buchungsfehler
   */
  showBookingError(message) {
    alert(`Fehler bei der Buchung: ${message}`);
  }

  /**
   * Tracking für erfolgreiche Buchung
   */
  trackBookingSuccess(booking) {
    console.log("Buchung erfolgreich:", booking.id);
    // Hier könnten Analytics Events gesendet werden
  }

  /**
   * Neue Buchung starten
   */
  newBooking() {
    // Reset
    this.currentBooking = this.resetBooking();
    this.currentStep = 1;

    // UI Reset
    document.getElementById("customer-form").reset();
    document
      .querySelectorAll(".selected")
      .forEach((el) => el.classList.remove("selected"));
    document.getElementById("step1-next").disabled = true;
    document.getElementById("step3-next").disabled = true;
    document.getElementById("time-slots").style.display = "none";
    this.hideDistanceInfo();

    // Kalender neu generieren
    this.generateCalendar();

    // Zurück zu Schritt 1
    this.showStep(1);
    this.updateProgress(1);
  }

  /**
   * Behandelt Datenänderungen
   */
  handleDataChange(data) {
    if (data.key === CONFIG.STORAGE_KEYS.bookedSlots) {
      // Kalender aktualisieren wenn sich gebuchte Slots ändern
      this.generateCalendar();
      if (this.currentBooking.date) {
        this.generateTimeSlots(this.currentBooking.date);
      }
    }
  }

  /**
   * Öffentliche API für externe Aufrufe
   */
  getCurrentBooking() {
    return { ...this.currentBooking };
  }

  /**
   * Setzt Buchungsdaten (für Testing oder externe Integration)
   */
  setBookingData(data) {
    Object.assign(this.currentBooking, data);
    this.validateCurrentStep();
  }
}

// Globale BookingFlow Instanz
window.bookingFlow = new BookingFlow();
