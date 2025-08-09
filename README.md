# 🚗 Mobile Car Service - Buchungssystem

Ein professionelles, responsives Buchungssystem für mobile Fahrzeugpflege-Services mit automatischer Entfernungsberechnung, Preiskalkulation und moderner UI im Stil des originaln MCS-Designs.

![MCS Buchungssystem](https://img.shields.io/badge/Version-1.0.0-blue.svg)
![Status](https://img.shields.io/badge/Status-Production%20Ready-green.svg)
![License](https://img.shields.io/badge/License-MIT-yellow.svg)

## 🎯 Features

### ✅ **Kerntunktionen**

- **4-Schritt Buchungsprozess**: Datum/Zeit → Services → Kundendaten → Bestätigung
- **Automatische Entfernungsberechnung** mit KM-Pauschale
- **Responsive Design** für alle Geräte
- **Dunkles Design** im MCS Corporate Style
- **Hintergrundbild-Upload** für individuelle Anpassung
- **LocalStorage Datenbank** mit vollständiger Persistierung
- **Service-Management** mit 8 vorkonfigurierten Services
- **Echtzeit-Verfügbarkeitsprüfung**

### 🛡️ **Technische Features**

- **Modulare ES6+ Architektur**
- **Error Handling & Recovery**
- **Performance Monitoring**
- **Cache-Management**
- **Offline-Support**
- **Development Tools**

## 🏗️ Projektstruktur

```
mobile-car-service/
├── 📄 index.html              # Hauptdatei
├── 📁 css/
│   └── 📄 styles.css          # Komplette Styles
├── 📁 js/
│   ├── 📄 config.js           # App-Konfiguration
│   ├── 📄 database.js         # Datenbank-Simulation
│   ├── 📄 services.js         # Service-Definitionen
│   ├── 📄 maps.js             # Entfernungsberechnung
│   ├── 📄 booking.js          # Buchungslogik
│   └── 📄 app.js              # Hauptanwendung
├── 📁 assets/
│   └── 📁 backgrounds/        # Hintergrundbilder
└── 📄 README.md               # Diese Dokumentation
```

## 🚀 Installation & Setup

### **Schnellstart (Lokal)**

1. **Repository klonen/herunterladen**

```bash
git clone https://github.com/username/mobile-car-service.git
cd mobile-car-service
```

2. **Dateien in Webserver kopieren** oder **index.html direkt öffnen**

```bash
# Option 1: Lokaler Server (empfohlen)
python -m http.server 8000
# oder
npx serve .

# Option 2: Direkt im Browser
open index.html
```

3. **Fertig!** 🎉 Das System ist sofort einsatzbereit.

### **Produktions-Deployment**

**Für Apache/Nginx:**

1. Dateien in `htdocs`/`www` Ordner kopieren
2. Optional: SSL-Zertifikat konfigurieren
3. Cache-Headers setzen (empfohlen)

**Für Netlify/Vercel:**

1. Repository verbinden
2. Build-Command: `none` (statische Seite)
3. Publish directory: `./`

## ⚙️ Konfiguration

### **Basis-Einstellungen** (`js/config.js`)

```javascript
const CONFIG = {
  // Firmenadresse
  COMPANY_ADDRESS: {
    street: "Ihre Straße 123",
    zip: "12345",
    city: "Ihre Stadt",
    lat: 52.2756, // Ihre Koordinaten
    lng: 7.4383,
  },

  // Preisberechnung
  TRAVEL_COST_PER_KM: 1.5, // Euro pro km
  FREE_DISTANCE_KM: 10, // Kostenlose km

  // Geschäftszeiten
  BUSINESS_HOURS: {
    start: 8, // 8:00 Uhr
    end: 18, // 18:00 Uhr
    interval: 60, // 60 Min Termine
    daysInAdvance: 21, // 21 Tage buchbar
  },
};
```

### **Services anpassen** (`js/services.js`)

```javascript
const SERVICES = [
  {
    id: 1,
    name: "Ihr Service",
    description: "Beschreibung",
    price: 50,
    duration: 60, // Minuten
    icon: "fas fa-car", // FontAwesome Icon
    category: "basic",
    popular: true, // Als beliebt markieren
  },
  // ... weitere Services
];
```

## 📊 Datenbank-Management

### **Daten exportieren**

```javascript
// Im Browser Console
const data = window.MCS_DEBUG.exportData();
console.log(JSON.stringify(data, null, 2));
```

### **Datenbank zurücksetzen**

```javascript
window.MCS_DEBUG.resetDatabase();
```

### **Statistiken abrufen**

```javascript
const stats = window.MCS_DEBUG.getStats();
console.table(stats);
```

## 🎨 Design-Anpassung

### **Farben ändern** (`css/styles.css`)

```css
:root {
  --primary-color: #667eea; /* Haupt-Akzentfarbe */
  --secondary-color: #764ba2; /* Sekundär-Akzentfarbe */
  --background-dark: #1a1a1a; /* Dunkler Hintergrund */
  --text-primary: #ffffff; /* Haupttext */
  --text-secondary: rgba(255, 255, 255, 0.8);
}
```

### **Logo ersetzen**

```html
<!-- In index.html, Zeile ~35 -->
<div class="logo-circle">
  <img src="ihr-logo.png" alt="Logo" style="width: 40px; height: 40px;" />
  <div class="logo-text">IHR</div>
</div>
```

### **Hintergrund programmatisch setzen**

```javascript
// Standard-Hintergrund setzen
document.body.style.backgroundImage = 'url("assets/backgrounds/default.jpg")';
```

## 🧪 Development & Testing

### **Debug-Modus aktivieren**

```javascript
// In config.js
const CONFIG = {
  debug: true, // Aktiviert Logging & Dev-Tools
  showTestData: true, // Zeigt Demo-Buchungen
};
```

### **Development Tools** (Browser Console)

```javascript
// Verfügbare Debug-Befehle
window.MCS_DEBUG.getStats(); // App-Statistiken
window.MCS_DEBUG.simulateBooking(); // Test-Buchung erstellen
window.MCS_DEBUG.resetDatabase(); // Datenbank leeren
window.MCS_DEBUG.clearCache(); // Cache leeren
window.MCS_DEBUG.showAllModules(); // Alle Module anzeigen
```

### **Performance Monitoring**

Das System trackt automatisch:

- Ladezeiten
- Speicherverbrauch
- Cache-Hit-Raten
- Error-Rates

## 🔧 API-Integration

### **Google Maps API** (Optional)

Für echte Entfernungsberechnung Google Maps API integrieren:

```javascript
// In js/maps.js ersetzen:
async performDistanceCalculation(customerAddress) {
    const service = new google.maps.DistanceMatrixService();

    return new Promise((resolve, reject) => {
        service.getDistanceMatrix({
            origins: [this.companyAddress],
            destinations: [customerAddress],
            travelMode: google.maps.TravelMode.DRIVING,
            unitSystem: google.maps.UnitSystem.METRIC
        }, (response, status) => {
            if (status === 'OK') {
                const distance = response.rows[0].elements[0].distance.value / 1000;
                resolve({ distance, /* ... */ });
            } else {
                reject(new Error('Distance calculation failed'));
            }
        });
    });
}
```

### **Backend-Integration**

Für echte Datenbank-Anbindung:

```javascript
// In js/database.js
class Database {
  async saveBooking(bookingData) {
    const response = await fetch("/api/bookings", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(bookingData),
    });

    if (!response.ok) {
      throw new Error("Booking save failed");
    }

    return response.json();
  }
}
```

## 📧 E-Mail Benachrichtigungen

### **E-Mail Service Integration**

```javascript
// Nach erfolgreicher Buchung
async function sendConfirmationEmail(booking) {
  await fetch("/api/send-email", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      to: booking.customer.email,
      template: "booking-confirmation",
      data: booking,
    }),
  });
}
```

### **E-Mail Templates**

Beispiel für Buchungsbestätigung:

```html
<!DOCTYPE html>
<html>
  <head>
    <title>Buchungsbestätigung - MCS</title>
  </head>
  <body>
    <h1>Ihre Buchung bei Mobile Car Service</h1>
    <p>Liebe/r {{customer.firstName}} {{customer.lastName}},</p>

    <p>vielen Dank für Ihre Buchung!</p>

    <h2>Buchungsdetails:</h2>
    <ul>
      <li><strong>Buchungsnummer:</strong> {{booking.id}}</li>
      <li><strong>Datum:</strong> {{booking.date}}</li>
      <li><strong>Uhrzeit:</strong> {{booking.time}}</li>
      <li><strong>Services:</strong> {{#each services}}{{name}}{{/each}}</li>
      <li><strong>Gesamtpreis:</strong> {{totalPrice}}€</li>
    </ul>

    <p>Wir freuen uns auf Ihren Termin!</p>

    <p>Ihr MCS-Team</p>
  </body>
</html>
```

## 🔒 Sicherheit & Datenschutz

### **Datenvalidierung**

- Client-side UND Server-side Validierung
- Input Sanitization
- XSS-Schutz

### **Datenschutz**

- DSGVO-konforme Datenverarbeitung
- Explizite Einwilligung erforderlich
- Recht auf Löschung implementieren

### **Sicherheits-Headers** (Server)

```apache
# .htaccess für Apache
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

## 📱 Mobile Optimierung

### **PWA-Features** (Optional)

`manifest.json` erstellen:

```json
{
  "name": "Mobile Car Service",
  "short_name": "MCS",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#1a1a1a",
  "theme_color": "#667eea",
  "icons": [
    {
      "src": "assets/icon-192.png",
      "sizes": "192x192",
      "type": "image/png"
    }
  ]
}
```

Service Worker für Offline-Support:

```javascript
// sw.js
self.addEventListener("fetch", (event) => {
  if (event.request.url.includes("/api/")) {
    // API-Requests online handhaben
    return;
  }

  // Statische Assets cachen
  event.respondWith(
    caches
      .match(event.request)
      .then((response) => response || fetch(event.request))
  );
});
```

## 🚀 Performance-Optimierung

### **Lazy Loading**

```javascript
// Bilder erst bei Bedarf laden
const images = document.querySelectorAll("img[data-src]");
const imageObserver = new IntersectionObserver((entries, observer) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      const img = entry.target;
      img.src = img.dataset.src;
      observer.unobserve(img);
    }
  });
});
```

### **Code Splitting**

```javascript
// Module erst bei Bedarf laden
async function loadAdminModule() {
  const { AdminPanel } = await import("./js/admin.js");
  return new AdminPanel();
}
```

## 📈 Analytics & Tracking

### **Google Analytics Integration**

```javascript
// Analytics Events senden
function trackBookingStep(step) {
  gtag("event", "booking_step", {
    step_number: step,
    event_category: "booking_flow",
  });
}
```

### **Custom Metrics**

```javascript
// Performance Metriken tracken
function trackPerformance() {
  const loadTime = performance.now();

  // An Analytics senden
  gtag("event", "page_load_time", {
    value: Math.round(loadTime),
    event_category: "performance",
  });
}
```

## 🧪 Testing

### **Manueller Test-Workflow**

1. **Buchungsprozess testen**

   - [ ] Datum auswählen
   - [ ] Zeit auswählen
   - [ ] Services wählen
   - [ ] Kundendaten eingeben
   - [ ] Entfernung wird berechnet
   - [ ] Buchung bestätigen

2. **Responsive Design**

   - [ ] Desktop (1920x1080)
   - [ ] Tablet (768x1024)
   - [ ] Mobile (375x667)

3. **Browser-Kompatibilität**
   - [ ] Chrome/Chromium
   - [ ] Firefox
   - [ ] Safari
   - [ ] Edge

### **Automatisierte Tests** (Optional)

```javascript
// Beispiel Test mit Jest
describe("Booking Flow", () => {
  test("should calculate distance correctly", async () => {
    const address = {
      street: "Teststr. 1",
      zip: "48431",
      city: "Rheine",
    };

    const result = await mapsService.calculateDistance(address);
    expect(result.distance).toBeGreaterThan(0);
  });
});
```

## 🔧 Troubleshooting

### **Häufige Probleme**

**Problem:** Hintergrundbilder werden nicht geladen

```javascript
// Lösung: Dateigröße prüfen
console.log("Max file size:", CONFIG.UI.maxUploadSize / 1024 / 1024, "MB");
```

**Problem:** Entfernungsberechnung funktioniert nicht

```javascript
// Debug: Cache leeren
window.MCS_DEBUG.clearCache();
```

**Problem:** Buchungen werden nicht gespeichert

```javascript
// Debug: LocalStorage prüfen
console.log("Storage available:", typeof Storage !== "undefined");
console.log("Storage usage:", JSON.stringify(localStorage).length, "bytes");
```

### **Debug-Logs aktivieren**

```javascript
// In config.js
const CONFIG = {
  debug: true,
  logLevel: "verbose", // 'error', 'warn', 'info', 'verbose'
};
```

## 🤝 Contributing

1. Fork des Repositories erstellen
2. Feature Branch erstellen (`git checkout -b feature/amazing-feature`)
3. Änderungen committen (`git commit -m 'Add amazing feature'`)
4. Branch pushen (`git push origin feature/amazing-feature`)
5. Pull Request erstellen

## 📄 Lizenz

Dieses Projekt steht unter der MIT-Lizenz. Siehe `LICENSE` Datei für Details.

## 🏢 Support

- **E-Mail:** support@mobile-car-service.de
- **Telefon:** +49 (0) 1234 567890
- **Website:** https://www.mobile-car-service.de

## 🎉 Credits

- **Design:** Basierend auf Mobile Car Service (MCS) Corporate Design
- **Icons:** FontAwesome 6.0
- **Fonts:** Segoe UI, System Fonts
- **Framework:** Vanilla JavaScript ES6+

---

**Made with ❤️ for Mobile Car Service**

_Version 1.0.0 - Aktualisiert: August 2025_
