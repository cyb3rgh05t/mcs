/**
 * Mobile Car Service - Service-Definitionen
 * Alle verfügbaren Services mit Preisen und Details
 */

const SERVICES = [
  {
    id: 1,
    name: "Basis-Reinigung",
    description: "Außenreinigung, Innenraumreinigung, Staubsaugen",
    detailedDescription:
      "Komplette Außenwäsche mit Shampoo, Innenraumreinigung inklusive Staubsaugen aller Sitze und Fußmatten, Armaturenbrett abwischen.",
    price: 45,
    duration: 60, // Minuten
    icon: "fas fa-spray-can",
    category: "basic",
    popular: false,
    included: [
      "Außenwäsche mit Autoshampoo",
      "Innenraumstaubsaugen",
      "Fußmatten reinigen",
      "Armaturenbrett abwischen",
      "Scheiben innen reinigen",
    ],
    requirements: ["Wasseranschluss in der Nähe", "Stromanschluss (230V)"],
    estimatedTime: "45-75 Min je nach Fahrzeuggröße",
  },
  {
    id: 2,
    name: "Premium-Reinigung",
    description: "Basis + Felgenreinigung, Armaturenpflege, Scheibenpolitur",
    detailedDescription:
      "Alle Leistungen der Basis-Reinigung plus professionelle Felgenreinigung, Armaturenpflege mit hochwertigen Produkten und Scheibenpolitur für kristallklare Sicht.",
    price: 75,
    duration: 90,
    icon: "fas fa-star",
    category: "premium",
    popular: true,
    included: [
      "Alle Basis-Leistungen",
      "Intensive Felgenreinigung",
      "Armaturenpflege mit UV-Schutz",
      "Scheibenpolitur innen & außen",
      "Türrahmen reinigen",
      "Kunststoffpflege außen",
    ],
    requirements: [
      "Wasseranschluss in der Nähe",
      "Stromanschluss (230V)",
      "Schattenplatz empfohlen",
    ],
    estimatedTime: "75-105 Min je nach Fahrzeuggröße",
  },
  {
    id: 3,
    name: "Komplett-Reinigung",
    description: "Premium + Wachsbehandlung, Lederpflege, Motorraumreinigung",
    detailedDescription:
      "Das Komplettpaket für Ihr Fahrzeug. Alle Premium-Leistungen plus Wachsversiegelung, professionelle Lederpflege und schonende Motorraumreinigung.",
    price: 120,
    duration: 150,
    icon: "fas fa-crown",
    category: "luxury",
    popular: false,
    included: [
      "Alle Premium-Leistungen",
      "Hartwachsversiegelung",
      "Lederpflege und -schutz",
      "Motorraumreinigung",
      "Kofferraumreinigung",
      "Chrom-/Edelstahlpolitur",
      "Reifen-Glanzspray",
    ],
    requirements: [
      "Wasseranschluss in der Nähe",
      "Stromanschluss (230V)",
      "Schattenplatz erforderlich",
      "Motor abgekühlt (min. 30 Min)",
    ],
    estimatedTime: "120-180 Min je nach Fahrzeuggröße",
  },
  {
    id: 4,
    name: "Innenraumaufbereitung",
    description: "Komplette Innenraumaufbereitung inkl. Polsterreinigung",
    detailedDescription:
      "Spezialisierte Tiefenreinigung des Innenraums mit Polsterreinigung, Teppichreinigung und Geruchsbeseitigung.",
    price: 65,
    duration: 80,
    icon: "fas fa-chair",
    category: "interior",
    popular: false,
    included: [
      "Tiefenreinigung aller Polster",
      "Teppichreinigung mit Extraktor",
      "Fleckenentfernung",
      "Geruchsneutralisierung",
      "Lederpflege (falls vorhanden)",
      "Alle Ablagefächer reinigen",
      "Himmel-Reinigung",
    ],
    requirements: [
      "Stromanschluss (230V)",
      "Fahrzeug für 2-3h verfügbar",
      "Zugang zu allen Sitzen",
    ],
    estimatedTime: "60-100 Min je nach Verschmutzung",
  },
  {
    id: 5,
    name: "Felgenspezialist",
    description: "Professionelle Felgenreinigung und -versiegelung",
    detailedDescription:
      "Intensive Reinigung und Pflege Ihrer Felgen mit speziellen Reinigungsmitteln und abschließender Versiegelung für langanhaltenden Schutz.",
    price: 35,
    duration: 45,
    icon: "fas fa-circle",
    category: "wheels",
    popular: false,
    included: [
      "Felgen komplett demontieren",
      "Intensive Reinigung mit Spezialmitteln",
      "Bremsstaub entfernen",
      "Felgenbett reinigen",
      "Versiegelung auftragen",
      "Reifen pflegen und glänzend machen",
    ],
    requirements: [
      "Fahrzeug beweglich",
      "Radschlüssel verfügbar",
      "Wagenheber möglich",
    ],
    estimatedTime: "30-60 Min je nach Felgentyp",
  },
  {
    id: 6,
    name: "Wachsversiegelung",
    description: "Hochwertige Wachsversiegelung für langanhaltenden Schutz",
    detailedDescription:
      "Professionelle Hartwachs-Versiegelung die Ihren Lack für Monate vor Umwelteinflüssen schützt und für tiefen Glanz sorgt.",
    price: 85,
    duration: 120,
    icon: "fas fa-shield-alt",
    category: "protection",
    popular: true,
    included: [
      "Lack dekontaminieren",
      "Knete-Behandlung",
      "Premium Hartwachs auftragen",
      "3-Stufen Polierprozess",
      "Kunststoffteile versiegeln",
      "Scheiben mit Regenabweiser",
      "6 Monate Schutzgarantie",
    ],
    requirements: [
      "Fahrzeug muss sauber sein",
      "Schattenplatz erforderlich",
      "Temperatur unter 25°C",
      "Kein Regen für 6h nach Behandlung",
    ],
    estimatedTime: "90-150 Min je nach Lackzustand",
  },
  {
    id: 7,
    name: "Schnell-Service",
    description: "Express-Außenreinigung für zwischendurch",
    detailedDescription:
      "Schnelle aber gründliche Außenreinigung für alle die wenig Zeit haben aber trotzdem ein sauberes Auto möchten.",
    price: 25,
    duration: 30,
    icon: "fas fa-bolt",
    category: "express",
    popular: false,
    included: [
      "Schnelle Außenwäsche",
      "Scheiben reinigen",
      "Felgen abspülen",
      "Trocknen",
      "Spiegel und Lichter putzen",
    ],
    requirements: [
      "Wasseranschluss in der Nähe",
      "Fahrzeug nicht zu stark verschmutzt",
    ],
    estimatedTime: "20-40 Min",
  },
  {
    id: 8,
    name: "Motorreinigung",
    description: "Schonende professionelle Motorraumreinigung",
    detailedDescription:
      "Fachgerechte Reinigung des Motorraums mit speziellen Reinigungsmitteln unter Schutz aller elektronischen Komponenten.",
    price: 40,
    duration: 45,
    icon: "fas fa-cog",
    category: "engine",
    popular: false,
    included: [
      "Elektronik abdecken",
      "Entfetten mit Spezialreiniger",
      "Vorsichtige Dampfreinigung",
      "Kunststoffteile auffrischen",
      "Schutzspray auftragen",
      "Motoröl-Level prüfen",
    ],
    requirements: [
      "Motor abgekühlt (min. 45 Min)",
      "Wasseranschluss",
      "Fahrzeug nach Reinigung 30 Min stehen lassen",
    ],
    estimatedTime: "30-60 Min je nach Verschmutzung",
  },
];

// Service-Kategorien für bessere Organisation
const SERVICE_CATEGORIES = {
  basic: {
    name: "Basis-Services",
    description: "Grundlegende Reinigungsleistungen",
    icon: "fas fa-car",
    color: "#4CAF50",
  },
  premium: {
    name: "Premium-Services",
    description: "Erweiterte Reinigung und Pflege",
    icon: "fas fa-star",
    color: "#FF9800",
  },
  luxury: {
    name: "Luxus-Services",
    description: "Komplette Fahrzeugaufbereitung",
    icon: "fas fa-crown",
    color: "#9C27B0",
  },
  interior: {
    name: "Innenraum",
    description: "Spezialisierte Innenraumreinigung",
    icon: "fas fa-chair",
    color: "#2196F3",
  },
  wheels: {
    name: "Felgen & Reifen",
    description: "Felgen- und Reifenpflege",
    icon: "fas fa-circle",
    color: "#607D8B",
  },
  protection: {
    name: "Schutz & Versiegelung",
    description: "Langzeitschutz für Ihr Fahrzeug",
    icon: "fas fa-shield-alt",
    color: "#795548",
  },
  express: {
    name: "Express-Services",
    description: "Schnelle Reinigung für zwischendurch",
    icon: "fas fa-bolt",
    color: "#F44336",
  },
  engine: {
    name: "Motor & Technik",
    description: "Motorraumreinigung und technische Pflege",
    icon: "fas fa-cog",
    color: "#3F51B5",
  },
};

/**
 * Service-Manager Klasse
 * Verwaltet alle Service-bezogenen Funktionen
 */
class ServiceManager {
  constructor() {
    this.services = SERVICES;
    this.categories = SERVICE_CATEGORIES;
  }

  /**
   * Alle Services abrufen
   */
  getAllServices() {
    return [...this.services];
  }

  /**
   * Service anhand ID finden
   */
  getServiceById(id) {
    return this.services.find((service) => service.id === id);
  }

  /**
   * Services nach Kategorie filtern
   */
  getServicesByCategory(category) {
    return this.services.filter((service) => service.category === category);
  }

  /**
   * Beliebte Services abrufen
   */
  getPopularServices() {
    return this.services.filter((service) => service.popular);
  }

  /**
   * Services nach Preis sortieren
   */
  getServicesByPrice(ascending = true) {
    return [...this.services].sort((a, b) => {
      return ascending ? a.price - b.price : b.price - a.price;
    });
  }

  /**
   * Services nach Dauer sortieren
   */
  getServicesByDuration(ascending = true) {
    return [...this.services].sort((a, b) => {
      return ascending ? a.duration - b.duration : b.duration - a.duration;
    });
  }

  /**
   * Service-Kombinationen vorschlagen
   */
  getSuggestedCombinations() {
    return [
      {
        name: "Komplett-Paket",
        services: [2, 5, 6], // Premium + Felgen + Wachs
        discount: 10, // 10% Rabatt
        totalPrice: this.calculateCombinationPrice([2, 5, 6], 10),
        description: "Perfekte Kombination für die komplette Fahrzeugpflege",
      },
      {
        name: "Express-Kombi",
        services: [7, 5], // Schnell + Felgen
        discount: 5, // 5% Rabatt
        totalPrice: this.calculateCombinationPrice([7, 5], 5),
        description: "Schnell und effektiv für zwischendurch",
      },
      {
        name: "Innen & Außen",
        services: [1, 4], // Basis + Innenraum
        discount: 8, // 8% Rabatt
        totalPrice: this.calculateCombinationPrice([1, 4], 8),
        description: "Komplette Reinigung innen und außen",
      },
    ];
  }

  /**
   * Preis für Service-Kombination berechnen
   */
  calculateCombinationPrice(serviceIds, discountPercent = 0) {
    const totalPrice = serviceIds.reduce((sum, id) => {
      const service = this.getServiceById(id);
      return sum + (service ? service.price : 0);
    }, 0);

    const discount = (totalPrice * discountPercent) / 100;
    return Math.round((totalPrice - discount) * 100) / 100;
  }

  /**
   * Gesamtdauer für Service-Kombination berechnen
   */
  calculateCombinationDuration(serviceIds) {
    return serviceIds.reduce((sum, id) => {
      const service = this.getServiceById(id);
      return sum + (service ? service.duration : 0);
    }, 0);
  }

  /**
   * Services nach Suchbegriff filtern
   */
  searchServices(query) {
    const searchTerm = query.toLowerCase();

    return this.services.filter(
      (service) =>
        service.name.toLowerCase().includes(searchTerm) ||
        service.description.toLowerCase().includes(searchTerm) ||
        service.detailedDescription.toLowerCase().includes(searchTerm) ||
        service.included.some((item) => item.toLowerCase().includes(searchTerm))
    );
  }

  /**
   * Service-Statistiken
   */
  getServiceStats() {
    const stats = {
      totalServices: this.services.length,
      averagePrice:
        this.services.reduce((sum, s) => sum + s.price, 0) /
        this.services.length,
      averageDuration:
        this.services.reduce((sum, s) => sum + s.duration, 0) /
        this.services.length,
      priceRange: {
        min: Math.min(...this.services.map((s) => s.price)),
        max: Math.max(...this.services.map((s) => s.price)),
      },
      categoryCount: {},
    };

    // Anzahl Services pro Kategorie
    this.services.forEach((service) => {
      stats.categoryCount[service.category] =
        (stats.categoryCount[service.category] || 0) + 1;
    });

    return stats;
  }

  /**
   * Validiert Service-Auswahl
   */
  validateServiceSelection(serviceIds) {
    const errors = [];
    const services = serviceIds
      .map((id) => this.getServiceById(id))
      .filter(Boolean);

    if (services.length === 0) {
      errors.push("Mindestens ein Service muss ausgewählt werden");
    }

    // Prüfe auf Konflikte (z.B. Express + Komplett)
    const hasExpress = services.some((s) => s.category === "express");
    const hasLuxury = services.some((s) => s.category === "luxury");

    if (hasExpress && hasLuxury) {
      errors.push("Express- und Luxus-Services können nicht kombiniert werden");
    }

    // Prüfe Gesamtdauer
    const totalDuration = this.calculateCombinationDuration(serviceIds);
    if (totalDuration > 240) {
      // 4 Stunden Maximum
      errors.push("Gesamtdauer darf 4 Stunden nicht überschreiten");
    }

    return {
      valid: errors.length === 0,
      errors,
      services,
      totalDuration,
      totalPrice: services.reduce((sum, s) => sum + s.price, 0),
    };
  }
}

// Globale Service-Manager Instanz
window.serviceManager = new ServiceManager();
