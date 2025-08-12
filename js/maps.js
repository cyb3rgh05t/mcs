/**
 * Mobile Car Service - Echte Entfernungsberechnung
 * Nutzt Google Maps API und OpenStreetMap als Fallback
 */

class MapsService {
  constructor() {
    this.companyAddress = {
      street: "Industriestraße 15",
      zip: "48431",
      city: "Rheine",
      coordinates: { lat: 52.2756, lng: 7.4383 },
    };

    this.cache = new Map();
    this.apiKeys = {
      google: CONFIG.GOOGLE_MAPS_API_KEY || null,
      // Weitere API-Keys hier
    };

    this.rateLimits = {
      google: { requests: 0, resetTime: 0, limit: 100 },
      osm: { requests: 0, resetTime: 0, limit: 60 },
    };

    this.loadCachedDistances();
    this.initializeServices();
  }

  /**
   * Initialisiert die verfügbaren Services
   */
  initializeServices() {
    this.availableServices = [];

    // Google Maps prüfen
    if (this.apiKeys.google) {
      this.availableServices.push("google");
      console.log("✅ Google Maps API verfügbar");
    }

    // OpenStreetMap ist immer verfügbar (kostenlos)
    this.availableServices.push("osm");
    console.log("✅ OpenStreetMap API verfügbar");

    if (this.availableServices.length === 0) {
      console.warn(
        "⚠️ Keine Mapping-Services verfügbar - nur PLZ-Schätzung möglich"
      );
    }
  }

  /**
   * Lädt gecachte Entfernungen aus LocalStorage
   */
  loadCachedDistances() {
    try {
      const cached = localStorage.getItem("mcs_distance_cache");
      if (cached) {
        const data = JSON.parse(cached);
        this.cache = new Map(data);
        console.log(`📦 ${this.cache.size} gecachte Entfernungen geladen`);
      }
    } catch (error) {
      console.warn("Fehler beim Laden des Distance-Cache:", error);
      this.cache = new Map();
    }
  }

  /**
   * Speichert Distance-Cache in LocalStorage
   */
  saveCachedDistances() {
    try {
      const data = Array.from(this.cache.entries());
      localStorage.setItem("mcs_distance_cache", JSON.stringify(data));
    } catch (error) {
      console.warn("Fehler beim Speichern des Distance-Cache:", error);
    }
  }

  /**
   * Hauptfunktion zur Entfernungsberechnung
   */
  async calculateDistance(customerAddress) {
    try {
      // Adresse validieren
      const validation = this.validateAddress(customerAddress);
      if (!validation.valid) {
        throw new Error(`Ungültige Adresse: ${validation.errors.join(", ")}`);
      }

      // Cache-Key erstellen
      const cacheKey = this.createCacheKey(customerAddress);

      // Prüfe Cache (max 7 Tage alt)
      if (this.cache.has(cacheKey)) {
        const cached = this.cache.get(cacheKey);
        const cacheAge = Date.now() - cached.timestamp;
        const maxAge = 7 * 24 * 60 * 60 * 1000; // 7 Tage

        if (cacheAge < maxAge) {
          console.log("📦 Entfernung aus Cache geladen");
          return cached.result;
        } else {
          // Cache-Eintrag ist zu alt
          this.cache.delete(cacheKey);
        }
      }

      // Neue Berechnung durchführen
      const result = await this.performDistanceCalculation(customerAddress);

      // Ergebnis validieren
      if (
        !result ||
        typeof result.distance !== "number" ||
        result.distance < 0
      ) {
        throw new Error("Ungültiges Berechnungsergebnis");
      }

      // In Cache speichern
      this.cache.set(cacheKey, {
        result: result,
        timestamp: Date.now(),
        address: customerAddress,
      });
      this.saveCachedDistances();

      return result;
    } catch (error) {
      console.error("Fehler bei der Entfernungsberechnung:", error);

      // Fallback: PLZ-basierte Schätzung als letzter Ausweg
      const fallback = this.createFallbackResult(customerAddress);

      // Warnung an User
      if (window.toast) {
        toast.warning(
          `Entfernung geschätzt: ${fallback.distance}km (${error.message})`
        );
      }

      return fallback;
    }
  }

  /**
   * Cache-Key für Adresse erstellen
   */
  createCacheKey(address) {
    return `${address.street}_${address.zip}_${address.city}`
      .toLowerCase()
      .replace(/[^a-z0-9_]/g, "_")
      .replace(/_{2,}/g, "_");
  }

  /**
   * Führt die eigentliche Entfernungsberechnung durch
   */
  async performDistanceCalculation(customerAddress) {
    const errors = [];

    // Versuche Google Maps API zuerst (wenn verfügbar)
    if (this.apiKeys.google && this.availableServices.includes("google")) {
      try {
        if (this.checkRateLimit("google")) {
          const result = await this.calculateWithGoogleMaps(customerAddress);
          return result;
        }
      } catch (error) {
        console.warn("Google Maps API fehlgeschlagen:", error.message);
        errors.push(`Google Maps: ${error.message}`);
      }
    }

    // Fallback: OpenStreetMap
    if (this.availableServices.includes("osm")) {
      try {
        if (this.checkRateLimit("osm")) {
          const result = await this.calculateWithOpenStreetMap(customerAddress);
          return result;
        }
      } catch (error) {
        console.warn("OpenStreetMap API fehlgeschlagen:", error.message);
        errors.push(`OpenStreetMap: ${error.message}`);
      }
    }

    // Alle APIs fehlgeschlagen
    throw new Error(`Alle APIs fehlgeschlagen: ${errors.join("; ")}`);
  }

  /**
   * Google Maps Distance Matrix API
   */
  async calculateWithGoogleMaps(customerAddress) {
    const origin = `${this.companyAddress.street}, ${this.companyAddress.zip} ${this.companyAddress.city}`;
    const destination = `${customerAddress.street}, ${customerAddress.zip} ${customerAddress.city}`;

    const url =
      `https://maps.googleapis.com/maps/api/distancematrix/json?` +
      `origins=${encodeURIComponent(origin)}&` +
      `destinations=${encodeURIComponent(destination)}&` +
      `units=metric&` +
      `mode=driving&` +
      `language=de&` +
      `key=${this.apiKeys.google}`;

    this.incrementRateLimit("google");

    const response = await fetch(url);

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();

    if (data.status !== "OK") {
      throw new Error(
        `API Status: ${data.status} - ${
          data.error_message || "Unbekannter Fehler"
        }`
      );
    }

    const element = data.rows[0]?.elements[0];

    if (!element || element.status !== "OK") {
      throw new Error(
        `Route nicht gefunden: ${element?.status || "Unbekannter Fehler"}`
      );
    }

    const distanceKm = element.distance.value / 1000;
    const durationMinutes = Math.round(element.duration.value / 60);

    return {
      distance: Math.round(distanceKm * 10) / 10,
      duration: durationMinutes,
      method: "google_maps",
      raw: {
        distance_text: element.distance.text,
        duration_text: element.duration.text,
        traffic: element.duration_in_traffic || null,
      },
      coordinates: {
        origin: this.companyAddress.coordinates,
        destination: await this.geocodeWithGoogle(customerAddress),
      },
      timestamp: Date.now(),
    };
  }

  /**
   * OpenStreetMap + OSRM für Routing
   */
  async calculateWithOpenStreetMap(customerAddress) {
    // 1. Geocoding für Zieladresse
    const destinationCoords = await this.geocodeWithOSM(customerAddress);

    // 2. Routing mit OSRM
    const origin = this.companyAddress.coordinates;
    const destination = destinationCoords;

    const routeUrl =
      `https://router.project-osrm.org/route/v1/driving/` +
      `${origin.lng},${origin.lat};${destination.lng},${destination.lat}` +
      `?overview=false&steps=false&geometries=geojson`;

    this.incrementRateLimit("osm");

    const response = await fetch(routeUrl);

    if (!response.ok) {
      throw new Error(`OSRM HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();

    if (data.code !== "Ok") {
      throw new Error(
        `OSRM Error: ${data.code} - ${data.message || "Route nicht gefunden"}`
      );
    }

    const route = data.routes[0];
    const distanceKm = route.distance / 1000;
    const durationMinutes = Math.round(route.duration / 60);

    return {
      distance: Math.round(distanceKm * 10) / 10,
      duration: durationMinutes,
      method: "openstreetmap",
      raw: {
        distance_text: `${distanceKm.toFixed(1)} km`,
        duration_text: `${durationMinutes} Min`,
        confidence: destinationCoords.confidence || 0.8,
      },
      coordinates: {
        origin: origin,
        destination: destination,
      },
      timestamp: Date.now(),
    };
  }

  /**
   * Geocoding mit Google Maps
   */
  async geocodeWithGoogle(address) {
    const addressString = `${address.street}, ${address.zip} ${address.city}`;
    const url =
      `https://maps.googleapis.com/maps/api/geocode/json?` +
      `address=${encodeURIComponent(addressString)}&` +
      `language=de&` +
      `key=${this.apiKeys.google}`;

    const response = await fetch(url);
    const data = await response.json();

    if (data.status !== "OK" || !data.results[0]) {
      throw new Error(`Geocoding fehlgeschlagen: ${data.status}`);
    }

    const location = data.results[0].geometry.location;
    return {
      lat: location.lat,
      lng: location.lng,
      confidence: 0.9,
      formatted_address: data.results[0].formatted_address,
    };
  }

  /**
   * Geocoding mit OpenStreetMap Nominatim
   */
  async geocodeWithOSM(address) {
    const addressString = `${address.street}, ${address.zip} ${address.city}, Deutschland`;
    const url =
      `https://nominatim.openstreetmap.org/search?` +
      `q=${encodeURIComponent(addressString)}&` +
      `format=json&` +
      `limit=1&` +
      `countrycodes=de&` +
      `addressdetails=1`;

    const response = await fetch(url, {
      headers: {
        "User-Agent": "Mobile-Car-Service/1.0",
      },
    });

    if (!response.ok) {
      throw new Error(`Nominatim HTTP ${response.status}`);
    }

    const data = await response.json();

    if (!data || data.length === 0) {
      throw new Error("Adresse nicht gefunden");
    }

    const result = data[0];

    // Confidence basierend auf Übereinstimmung
    let confidence = 0.5;
    if (result.address) {
      if (result.address.postcode === address.zip) confidence += 0.2;
      if (
        result.address.city === address.city ||
        result.address.town === address.city
      )
        confidence += 0.2;
      if (result.address.house_number) confidence += 0.1;
    }

    return {
      lat: parseFloat(result.lat),
      lng: parseFloat(result.lon),
      confidence: confidence,
      formatted_address: result.display_name,
    };
  }

  /**
   * Rate Limiting prüfen
   */
  checkRateLimit(service) {
    const limit = this.rateLimits[service];
    const now = Date.now();

    // Reset alle 60 Minuten
    if (now - limit.resetTime > 60 * 60 * 1000) {
      limit.requests = 0;
      limit.resetTime = now;
    }

    if (limit.requests >= limit.limit) {
      console.warn(`Rate limit erreicht für ${service}`);
      return false;
    }

    return true;
  }

  /**
   * Rate Limit Counter erhöhen
   */
  incrementRateLimit(service) {
    this.rateLimits[service].requests++;
  }

  /**
   * Berechnet Anfahrtskosten basierend auf Entfernung
   */
  calculateTravelCost(distance) {
    const freeDistance = CONFIG.FREE_DISTANCE_KM || 10;
    const costPerKm = CONFIG.TRAVEL_COST_PER_KM || 1.5;

    if (distance <= freeDistance) {
      return {
        distance: distance,
        freeDistance: freeDistance,
        chargeableDistance: 0,
        costPerKm: costPerKm,
        cost: 0,
        isFree: true,
        message: `Kostenlose Anfahrt (unter ${freeDistance}km)`,
      };
    }

    const chargeableDistance = distance - freeDistance;
    const cost = chargeableDistance * costPerKm;

    return {
      distance: distance,
      freeDistance: freeDistance,
      chargeableDistance: Math.round(chargeableDistance * 10) / 10,
      costPerKm: costPerKm,
      cost: Math.round(cost * 100) / 100,
      isFree: false,
      message: `${chargeableDistance.toFixed(1)}km × ${costPerKm.toFixed(
        2
      )}€ = ${cost.toFixed(2)}€`,
    };
  }

  /**
   * Validiert eine Kundenadresse
   */
  validateAddress(address) {
    const errors = [];

    if (!address) {
      errors.push("Adresse ist erforderlich");
      return { valid: false, errors };
    }

    if (!address.street || address.street.trim().length < 3) {
      errors.push("Straße und Hausnummer sind erforderlich");
    }

    if (!address.zip || !/^[0-9]{5}$/.test(address.zip)) {
      errors.push("Gültige 5-stellige PLZ ist erforderlich");
    }

    if (!address.city || address.city.trim().length < 2) {
      errors.push("Stadt ist erforderlich");
    }

    return {
      valid: errors.length === 0,
      errors,
    };
  }

  /**
   * Erstellt Fallback-Result für PLZ-Schätzung
   */
  createFallbackResult(customerAddress) {
    const distance = this.estimateDistanceByPostalCode(customerAddress.zip);
    const duration = Math.round(distance * 1.5); // ~1.5 Min pro km

    return {
      distance: distance,
      duration: duration,
      method: "postal_code_estimate",
      raw: {
        distance_text: `~${distance} km`,
        duration_text: `~${duration} Min`,
        confidence: 0.3,
      },
      coordinates: {
        origin: this.companyAddress.coordinates,
        destination: this.estimateCoordinates(customerAddress),
      },
      isEstimate: true,
      timestamp: Date.now(),
    };
  }

  /**
   * PLZ-basierte Entfernungsschätzung (als Fallback)
   */
  estimateDistanceByPostalCode(zip) {
    const companyZip = parseInt(this.companyAddress.zip);
    const customerZip = parseInt(zip);

    // Bekannte PLZ-Bereiche und ihre ungefähren Entfernungen
    const knownZipRanges = [
      { min: 48400, max: 48499, baseDistance: 10 }, // Rheine-Umgebung
      { min: 49400, max: 49499, baseDistance: 25 }, // Osnabrück-Bereich
      { min: 48100, max: 48399, baseDistance: 35 }, // Münster-Bereich
      { min: 26000, max: 26999, baseDistance: 150 }, // Oldenburg
      { min: 30000, max: 39999, baseDistance: 160 }, // Hannover-Bereich
      { min: 44000, max: 44999, baseDistance: 80 }, // Dortmund
      { min: 40000, max: 49999, baseDistance: 100 }, // NRW-West
      { min: 50000, max: 59999, baseDistance: 120 }, // Köln-Bereich
    ];

    // Finde passenden Bereich
    for (const range of knownZipRanges) {
      if (customerZip >= range.min && customerZip <= range.max) {
        // Feintuning basierend auf PLZ-Differenz
        const zipDiff = Math.abs(customerZip - companyZip);
        const adjustment = Math.min(zipDiff / 1000, 10); // Max 10km Adjustment
        return Math.round((range.baseDistance + adjustment) * 10) / 10;
      }
    }

    // Fallback: Grobe Schätzung basierend auf PLZ-Differenz
    const zipDiff = Math.abs(customerZip - companyZip);
    return Math.min(Math.round(zipDiff / 10), 200); // Max 200km
  }

  /**
   * Schätzt Koordinaten basierend auf PLZ
   */
  estimateCoordinates(address) {
    const zip = parseInt(address.zip);

    // Bekannte PLZ-Koordinaten (Zentren)
    const zipCoordinates = {
      48431: { lat: 52.2756, lng: 7.4383 }, // Rheine
      48429: { lat: 52.2856, lng: 7.4283 }, // Rheine-Nord
      48465: { lat: 52.1856, lng: 7.3683 }, // Neuenkirchen
      49477: { lat: 52.2756, lng: 7.7383 }, // Ibbenbüren
      48149: { lat: 51.9607, lng: 7.6261 }, // Münster
    };

    if (zipCoordinates[zip]) {
      return { ...zipCoordinates[zip], confidence: 0.7 };
    }

    // Grobe Schätzung basierend auf PLZ-Bereich
    const lat = 52.0 + (zip % 1000) / 1000;
    const lng = 7.0 + (zip % 100) / 100;

    return { lat, lng, confidence: 0.3 };
  }

  /**
   * Prüft ob die Adresse im Servicebereich liegt
   */
  isInServiceArea(distance) {
    const maxDistance = CONFIG.MAX_SERVICE_DISTANCE || 100;

    return {
      inArea: distance <= maxDistance,
      maxDistance,
      distance,
      message:
        distance > maxDistance
          ? `Außerhalb unseres Servicebereichs (max. ${maxDistance}km)`
          : "Im Servicebereich",
    };
  }

  /**
   * Cache-Statistiken
   */
  getCacheStats() {
    return {
      cacheSize: this.cache.size,
      availableServices: this.availableServices,
      rateLimits: this.rateLimits,
      lastCleared: localStorage.getItem("mcs_cache_last_clear") || "Nie",
    };
  }

  /**
   * Cache leeren
   */
  clearCache() {
    this.cache.clear();
    localStorage.removeItem("mcs_distance_cache");
    localStorage.setItem("mcs_cache_last_clear", new Date().toISOString());

    if (window.toast) {
      toast.success(`Cache geleert: Alle Entfernungen werden neu berechnet`);
    }

    console.log("🗑️ Distance-Cache wurde geleert");
  }

  /**
   * Service-Status prüfen
   */
  async checkServiceHealth() {
    const status = {
      google: false,
      osm: false,
      timestamp: Date.now(),
    };

    // Google Maps testen
    if (this.apiKeys.google) {
      try {
        const testUrl = `https://maps.googleapis.com/maps/api/geocode/json?address=Rheine&key=${this.apiKeys.google}`;
        const response = await fetch(testUrl);
        status.google = response.ok;
      } catch (error) {
        status.google = false;
      }
    }

    // OpenStreetMap testen
    try {
      const testUrl =
        "https://nominatim.openstreetmap.org/search?q=Rheine&format=json&limit=1";
      const response = await fetch(testUrl);
      status.osm = response.ok;
    } catch (error) {
      status.osm = false;
    }

    return status;
  }
}

// Globale Maps-Service Instanz
window.mapsService = new MapsService();
