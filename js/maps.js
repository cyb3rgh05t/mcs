/**
 * Mobile Car Service - Entfernungsberechnung
 * Simuliert Google Maps API für Entfernungsberechnungen
 */

class MapsService {
  constructor() {
    this.companyAddress = CONFIG.COMPANY_ADDRESS;
    this.cache = new Map(); // Cache für berechnete Entfernungen
    this.loadCachedDistances();
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
      }
    } catch (error) {
      console.warn("Fehler beim Laden des Distance-Cache:", error);
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
      // Cache-Key erstellen
      const cacheKey = this.createCacheKey(customerAddress);

      // Prüfe Cache
      if (this.cache.has(cacheKey)) {
        console.log("Entfernung aus Cache geladen");
        return this.cache.get(cacheKey);
      }

      // Neue Berechnung
      const distance = await this.performDistanceCalculation(customerAddress);

      // In Cache speichern
      this.cache.set(cacheKey, distance);
      this.saveCachedDistances();

      return distance;
    } catch (error) {
      console.error("Fehler bei der Entfernungsberechnung:", error);
      // Fallback: Geschätzte Entfernung basierend auf PLZ
      return this.estimateDistanceByPostalCode(customerAddress.zip);
    }
  }

  /**
   * Cache-Key für Adresse erstellen
   */
  createCacheKey(address) {
    return `${address.street}_${address.zip}_${address.city}`
      .toLowerCase()
      .replace(/\s+/g, "_");
  }

  /**
   * Führt die eigentliche Entfernungsberechnung durch
   * In der Realität würde hier die Google Maps API aufgerufen
   */
  async performDistanceCalculation(customerAddress) {
    // Simuliere API-Aufruf Delay
    await this.delay(1000 + Math.random() * 2000);

    // Simulierte Entfernungsberechnung basierend auf bekannten Städten
    const distance = this.simulateDistanceCalculation(customerAddress);

    // Füge etwas Variation hinzu für Realismus
    const variation = (Math.random() - 0.5) * 2; // ±1 km Variation
    const finalDistance = Math.max(1, distance + variation);

    return {
      distance: Math.round(finalDistance * 10) / 10, // Eine Nachkommastelle
      duration: this.estimateDrivingTime(finalDistance),
      route: this.generateRoute(customerAddress),
      calculatedAt: new Date().toISOString(),
    };
  }

  /**
   * Simuliert Entfernungsberechnung basierend auf bekannten Orten
   */
  simulateDistanceCalculation(customerAddress) {
    const city = customerAddress.city.toLowerCase();
    const zip = customerAddress.zip;

    // Bekannte Entfernungen von Rheine (Firmensitz)
    const knownDistances = {
      // Direkte Nachbarn
      rheine: this.getDistanceByPostalCode(zip, "48431"),
      salzbergen: 8,
      spelle: 12,
      emsdetten: 15,
      greven: 20,
      steinfurt: 18,
      neuenkirchen: 10,
      wettringen: 14,

      // Größere Städte in der Region
      münster: 35,
      osnabrück: 45,
      enschede: 25,
      nordhorn: 55,
      coesfeld: 40,
      ibbenbüren: 30,
      lengerich: 25,
      mettingen: 22,
      hopsten: 20,
      recke: 15,

      // Weitere entfernte Städte
      amsterdam: 180,
      bremen: 150,
      hannover: 160,
      dortmund: 80,
      düsseldorf: 120,
      köln: 150,
      hamburg: 250,
      berlin: 450,
      frankfurt: 280,
      stuttgart: 400,
    };

    // Prüfe direkte Übereinstimmung
    for (const [cityName, distance] of Object.entries(knownDistances)) {
      if (city.includes(cityName) || cityName.includes(city)) {
        return typeof distance === "number" ? distance : distance;
      }
    }

    // Fallback: Schätze basierend auf PLZ
    return this.estimateDistanceByPostalCode(zip);
  }

  /**
   * Schätzt Entfernung basierend auf Postleitzahl
   */
  estimateDistanceByPostalCode(customerZip) {
    const companyZip = this.companyAddress.zip;
    const zipDiff = Math.abs(parseInt(customerZip) - parseInt(companyZip));

    // Grobe Schätzung: Pro 100 PLZ-Punkte ca. 10-15 km
    let estimatedDistance = zipDiff * 0.12;

    // Mindestens 2 km, höchstens 500 km
    estimatedDistance = Math.max(2, Math.min(500, estimatedDistance));

    return estimatedDistance;
  }

  /**
   * Detaillierte Entfernungsberechnung basierend auf PLZ-Bereichen
   */
  getDistanceByPostalCode(customerZip, companyZip = "48431") {
    const customer = parseInt(customerZip);
    const company = parseInt(companyZip);

    // PLZ-Bereiche und ihre ungefähren Entfernungen von Rheine (48431)
    const plzDistances = {
      // 48xxx (lokaler Bereich)
      48400: 5, // Rheine direkt
      48431: 0, // Firmensitz
      48432: 3, // Rheine andere Teile
      48455: 8, // Salzbergen
      48480: 12, // Spelle
      48485: 10, // Neuenkirchen
      48496: 14, // Hopsten

      // 49xxx (Osnabrück Region)
      49000: 45, // Osnabrück
      49082: 42, // Osnabrück
      49124: 38, // Georgsmarienhütte
      49479: 25, // Ibbenbüren
      49525: 22, // Lengerich
      49565: 18, // Bramsche

      // 48xxx (weitere Münsterland)
      48143: 35, // Münster
      48149: 32, // Münster
      48157: 30, // Münster
      48167: 38, // Münster
      48301: 15, // Nottuln
      48317: 20, // Dülmen
      48336: 18, // Sassenberg
      48351: 25, // Everswinkel
      48361: 15, // Beelen
      48369: 12, // Saerbeck
      48477: 15, // Hörstel
      48599: 25, // Gronau

      // 44xxx-47xxx (Ruhrgebiet)
      44000: 90, // Dortmund
      45000: 110, // Essen
      46000: 130, // Düsseldorf
      47000: 140, // Duisburg

      // 50xxx-53xxx (Köln/Bonn)
      50000: 150, // Köln
      51000: 145, // Köln Umgebung
      52000: 90, // Aachen
      53000: 160, // Bonn

      // Niederlande (NL Postcodes simuliert)
      7500: 25, // Enschede
      7600: 30, // Almelo
    };

    // Finde nächste bekannte PLZ
    let closestDistance = 50; // Default
    let minDiff = Infinity;

    for (const [plz, distance] of Object.entries(plzDistances)) {
      const diff = Math.abs(customer - parseInt(plz));
      if (diff < minDiff) {
        minDiff = diff;
        closestDistance = distance;
      }
    }

    // Füge Entfernung basierend auf PLZ-Differenz hinzu
    const plzFactor = Math.min(minDiff / 100, 5); // Max 5km zusätzlich
    return closestDistance + plzFactor;
  }

  /**
   * Schätzt Fahrtzeit basierend auf Entfernung
   */
  estimateDrivingTime(distance) {
    // Durchschnittsgeschwindigkeit variiert je nach Entfernung
    let avgSpeed;

    if (distance <= 10) {
      avgSpeed = 35; // Stadtverkehr
    } else if (distance <= 30) {
      avgSpeed = 50; // Landstraße
    } else if (distance <= 100) {
      avgSpeed = 65; // Schnellstraße/Autobahn
    } else {
      avgSpeed = 80; // Autobahn
    }

    const timeInHours = distance / avgSpeed;
    const timeInMinutes = Math.round(timeInHours * 60);

    return {
      hours: Math.floor(timeInMinutes / 60),
      minutes: timeInMinutes % 60,
      totalMinutes: timeInMinutes,
    };
  }

  /**
   * Generiert eine simulierte Route
   */
  generateRoute(customerAddress) {
    const distance = this.simulateDistanceCalculation(customerAddress);

    return {
      start: this.companyAddress,
      end: customerAddress,
      distance: distance,
      duration: this.estimateDrivingTime(distance),
      steps: this.generateRouteSteps(customerAddress, distance),
    };
  }

  /**
   * Generiert Routenschritte (simuliert)
   */
  generateRouteSteps(customerAddress, totalDistance) {
    const steps = [];
    const city = customerAddress.city.toLowerCase();

    // Grundroute von Rheine
    steps.push({
      instruction: "Starten Sie in Rheine, Industriestraße 15",
      distance: 0,
      duration: 0,
    });

    if (totalDistance > 5) {
      if (city.includes("münster") || city.includes("emsdetten")) {
        steps.push({
          instruction: "Fahren Sie auf die B481 Richtung Münster",
          distance: Math.round(totalDistance * 0.3),
          duration: Math.round(((totalDistance * 0.3) / 50) * 60),
        });
      } else if (city.includes("osnabrück") || city.includes("ibbenbüren")) {
        steps.push({
          instruction: "Fahren Sie auf die A30 Richtung Osnabrück",
          distance: Math.round(totalDistance * 0.4),
          duration: Math.round(((totalDistance * 0.4) / 70) * 60),
        });
      } else if (city.includes("steinfurt") || city.includes("burgsteinfurt")) {
        steps.push({
          instruction: "Fahren Sie auf die B54 Richtung Steinfurt",
          distance: Math.round(totalDistance * 0.6),
          duration: Math.round(((totalDistance * 0.6) / 50) * 60),
        });
      }
    }

    if (totalDistance > 15) {
      steps.push({
        instruction: `Folgen Sie der Beschilderung nach ${customerAddress.city}`,
        distance: Math.round(totalDistance * 0.7),
        duration: Math.round(((totalDistance * 0.7) / 55) * 60),
      });
    }

    steps.push({
      instruction: `Ankunft: ${customerAddress.street}, ${customerAddress.zip} ${customerAddress.city}`,
      distance: totalDistance,
      duration: this.estimateDrivingTime(totalDistance).totalMinutes,
    });

    return steps;
  }

  /**
   * Berechnet Anfahrtskosten basierend auf Entfernung
   */
  calculateTravelCost(distance) {
    const freeDistance = CONFIG.FREE_DISTANCE_KM;
    const costPerKm = CONFIG.TRAVEL_COST_PER_KM;

    if (distance <= freeDistance) {
      return {
        cost: 0,
        freeKm: distance,
        chargeableKm: 0,
        message: `Kostenlose Anfahrt (unter ${freeDistance}km)`,
      };
    }

    const chargeableDistance = distance - freeDistance;
    const cost = chargeableDistance * costPerKm;

    return {
      cost: Math.round(cost * 100) / 100, // 2 Nachkommastellen
      freeKm: freeDistance,
      chargeableKm: Math.round(chargeableDistance * 10) / 10,
      costPerKm: costPerKm,
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

    if (!address.street || address.street.trim().length < 5) {
      errors.push("Straße und Hausnummer sind erforderlich");
    }

    if (!address.zip || !CONFIG.VALIDATION.zip.test(address.zip)) {
      errors.push("Gültige PLZ ist erforderlich");
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
   * Prüft ob die Adresse im Servicebereich liegt
   */
  isInServiceArea(distance) {
    const maxDistance = 100; // 100km maximaler Servicebereich

    return {
      inArea: distance <= maxDistance,
      maxDistance,
      message:
        distance > maxDistance
          ? `Außerhalb unseres Servicebereichs (max. ${maxDistance}km)`
          : "Im Servicebereich",
    };
  }

  /**
   * Hilfsfunktion für Delays
   */
  delay(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  /**
   * Geocoding Simulation (Koordinaten aus Adresse)
   */
  async geocodeAddress(address) {
    // Simulierte Koordinaten für bekannte Städte
    const coordinates = {
      rheine: { lat: 52.2756, lng: 7.4383 },
      münster: { lat: 51.9607, lng: 7.6261 },
      osnabrück: { lat: 52.2799, lng: 8.0472 },
      steinfurt: { lat: 52.1482, lng: 7.3379 },
      emsdetten: { lat: 52.1725, lng: 7.5289 },
      ibbenbüren: { lat: 52.2766, lng: 7.7164 },
      enschede: { lat: 52.2215, lng: 6.8937 },
    };

    const city = address.city.toLowerCase();

    for (const [cityName, coords] of Object.entries(coordinates)) {
      if (city.includes(cityName)) {
        return {
          ...coords,
          address: `${address.street}, ${address.zip} ${address.city}`,
          confidence: 0.9,
        };
      }
    }

    // Fallback: Geschätzte Koordinaten
    return {
      lat: 52.0 + Math.random() * 2,
      lng: 7.0 + Math.random() * 2,
      address: `${address.street}, ${address.zip} ${address.city}`,
      confidence: 0.5,
    };
  }

  /**
   * Cache-Statistiken
   */
  getCacheStats() {
    return {
      cacheSize: this.cache.size,
      cacheKeys: Array.from(this.cache.keys()),
      oldestEntry: this.findOldestCacheEntry(),
      cacheHitRate: this.calculateCacheHitRate(),
    };
  }

  /**
   * Cache leeren
   */
  clearCache() {
    this.cache.clear();
    localStorage.removeItem("mcs_distance_cache");
    console.log("Distance-Cache wurde geleert");
  }

  /**
   * Findet ältesten Cache-Eintrag
   */
  findOldestCacheEntry() {
    let oldest = null;

    for (const [key, value] of this.cache.entries()) {
      if (
        value.calculatedAt &&
        (!oldest || value.calculatedAt < oldest.calculatedAt)
      ) {
        oldest = { key, ...value };
      }
    }

    return oldest;
  }

  /**
   * Berechnet Cache-Hit-Rate (vereinfacht)
   */
  calculateCacheHitRate() {
    // In einer echten Implementation würde man Hits vs. Misses tracken
    return this.cache.size > 0 ? 0.8 : 0; // 80% Hit-Rate als Beispiel
  }
}

// Globale Maps-Service Instanz
window.mapsService = new MapsService();
