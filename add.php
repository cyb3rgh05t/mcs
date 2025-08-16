<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entfernungs-API Test</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .result {
            margin-top: 30px;
            padding: 20px;
            border-radius: 10px;
            display: none;
        }

        .result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .result h3 {
            margin-top: 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }

        .info-item {
            padding: 10px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 5px;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 3px;
        }

        .info-value {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .loading {
            display: none;
            text-align: center;
            color: #667eea;
            margin: 20px 0;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .test-addresses {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .test-addresses h4 {
            margin-top: 0;
            color: #666;
        }

        .test-btn {
            display: inline-block;
            padding: 5px 10px;
            margin: 3px;
            background: #e9ecef;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }

        .test-btn:hover {
            background: #dee2e6;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🗺️ Entfernungs-API Test</h1>

        <form id="distanceForm">
            <div class="form-group">
                <label for="address">Adresse eingeben:</label>
                <input type="text" id="address" name="address" placeholder="z.B. Hauptstraße 1, 12345 Musterstadt" required>
            </div>

            <button type="submit" id="submitBtn">Entfernung berechnen</button>
        </form>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Berechne Entfernung...</p>
        </div>

        <div class="result" id="result"></div>

        <div class="test-addresses">
            <h4>Test-Adressen (Klicken zum Einfügen):</h4>
            <span class="test-btn" onclick="setAddress('Bochumer Str. 85, 44623 Herne')">Herne (5km)</span>
            <span class="test-btn" onclick="setAddress('Berliner Platz 7, 44623 Herne')">Herne Zentrum (3km)</span>
            <span class="test-btn" onclick="setAddress('Bahnhofstraße 1, 44787 Bochum')">Bochum (8km)</span>
            <span class="test-btn" onclick="setAddress('Königsallee 1, 40212 Düsseldorf')">Düsseldorf (40km)</span>
            <span class="test-btn" onclick="setAddress('Hauptstraße 1, 45127 Essen')">Essen (15km)</span>
            <span class="test-btn" onclick="setAddress('Domkloster 4, 50667 Köln')">Köln Dom (65km)</span>
        </div>
    </div>

    <script>
        function setAddress(address) {
            document.getElementById('address').value = address;
        }

        document.getElementById('distanceForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const address = document.getElementById('address').value;
            const submitBtn = document.getElementById('submitBtn');
            const loading = document.getElementById('loading');
            const resultDiv = document.getElementById('result');

            // UI State
            submitBtn.disabled = true;
            loading.style.display = 'block';
            resultDiv.style.display = 'none';

            try {
                const response = await fetch('api/distance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        address: address
                    })
                });

                const data = await response.json();

                if (data.success) {
                    const costPerKm = data.config?.cost_per_km || 2;
                    const freeKm = data.config?.free_km || 10;
                    const minAmount = data.config?.min_service_amount || 59.90;
                    const maxSmall = data.config?.max_distance_small || 10;
                    const maxLarge = data.config?.max_distance_large || 30;

                    // Berechne verschiedene Szenarien
                    let scenario1 = {
                        cost: 0,
                        info: ''
                    };
                    let scenario2 = {
                        cost: 0,
                        info: ''
                    };

                    // Szenario 1: Leistungen < 59.90€
                    if (data.distance_km <= maxSmall) {
                        scenario1.cost = 0;
                        scenario1.info = `✅ Buchbar (kostenlos bis ${maxSmall}km)`;
                    } else {
                        scenario1.info = `❌ Nicht buchbar (max. ${maxSmall}km)`;
                    }

                    // Szenario 2: Leistungen >= 59.90€
                    if (data.distance_km <= maxLarge) {
                        if (data.distance_km <= freeKm) {
                            scenario2.cost = 0;
                            scenario2.info = `✅ Kostenlos (erste ${freeKm}km gratis)`;
                        } else {
                            scenario2.cost = (data.distance_km - freeKm) * costPerKm;
                            scenario2.info = `✅ ${(data.distance_km - freeKm).toFixed(1)}km × ${costPerKm}€`;
                        }
                    } else {
                        scenario2.info = `❌ Nicht buchbar (max. ${maxLarge}km)`;
                    }

                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <h3>✅ Erfolgreich berechnet!</h3>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Entfernung</div>
                                <div class="info-value">${data.distance_km.toFixed(1)} km</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Fahrzeit</div>
                                <div class="info-value">${data.duration || 'Unbekannt'}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Methode</div>
                                <div class="info-value">${data.estimated ? '📊 Schätzung' : '🗺️ Google Maps'}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Von</div>
                                <div class="info-value">${data.business_location || 'Herne'}</div>
                            </div>
                        </div>
                        
                        <h4 style="margin-top: 20px;">💰 Anfahrtskosten-Szenarien:</h4>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">
                            <strong>Bei Leistungen unter ${minAmount.toFixed(2)}€:</strong><br>
                            ${scenario1.info}<br>
                            ${scenario1.cost !== undefined ? `<strong>Anfahrt: ${scenario1.cost.toFixed(2)}€</strong>` : ''}
                        </div>
                        
                        <div style="background: #e7f5ff; padding: 15px; border-radius: 8px; margin: 10px 0;">
                            <strong>Bei Leistungen ab ${minAmount.toFixed(2)}€:</strong><br>
                            ${scenario2.info}<br>
                            ${scenario2.cost !== undefined ? `<strong>Anfahrt: ${scenario2.cost.toFixed(2)}€</strong>` : ''}
                        </div>
                        
                        ${data.message ? `<p style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 5px;">ℹ️ ${data.message}</p>` : ''}
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `
                        <h3>❌ Fehler</h3>
                        <p>${data.error || 'Unbekannter Fehler'}</p>
                        ${data.suggestions ? '<h4>Vorschläge:</h4><ul>' + data.suggestions.map(s => `<li>${s}</li>`).join('') + '</ul>' : ''}
                    `;
                }

                resultDiv.style.display = 'block';

            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `
                    <h3>❌ Verbindungsfehler</h3>
                    <p>Konnte keine Verbindung zur API herstellen.</p>
                    <p><small>${error.message}</small></p>
                `;
                resultDiv.style.display = 'block';
            } finally {
                submitBtn.disabled = false;
                loading.style.display = 'none';
            }
        });
    </script>
</body>

</html>