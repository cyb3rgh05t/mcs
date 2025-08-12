<?php

/**
 * Mobile Car Service - Main Router für PHP Development Server
 * Startet im Hauptverzeichnis: php -S localhost:8000 router.php
 */

$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Debug-Info (mit ?debug=1)
if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "Request URI: " . $requestUri . "\n";
    echo "Request Path: " . $requestPath . "\n";
    echo "Document Root: " . __DIR__ . "\n";
    echo "Working Directory: " . getcwd() . "\n";
    echo "</pre>";
    exit;
}

// API-Requests an backend/api.php weiterleiten
if (preg_match('#^/backend/api\.php(/.*)?$#', $requestPath, $matches)) {
    // PATH_INFO für API-Routing setzen
    if (isset($matches[1])) {
        $_SERVER['PATH_INFO'] = $matches[1];
    }

    // API-Datei einbinden
    if (file_exists(__DIR__ . '/backend/api.php')) {
        require_once __DIR__ . '/backend/api.php';
    } else {
        http_response_code(404);
        echo "API-Datei nicht gefunden: " . __DIR__ . '/backend/api.php';
    }
    exit;
}

// Setup-Requests
if ($requestPath === '/backend/setup.php') {
    if (file_exists(__DIR__ . '/backend/setup.php')) {
        require_once __DIR__ . '/backend/setup.php';
    } else {
        http_response_code(404);
        echo "Setup-Datei nicht gefunden: " . __DIR__ . '/backend/setup.php';
    }
    exit;
}

// Static files (CSS, JS, Images)
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$/', $requestPath)) {
    $filePath = __DIR__ . $requestPath;
    if (file_exists($filePath)) {
        // MIME-Type setzen
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (isset($mimeTypes[$extension])) {
            header('Content-Type: ' . $mimeTypes[$extension]);
        }

        readfile($filePath);
        exit;
    }
}

// Index-Request
if ($requestPath === '/' || $requestPath === '/index.html') {
    if (file_exists(__DIR__ . '/index.html')) {
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/index.html');
    } else {
        echo "<!DOCTYPE html>
<html>
<head><title>Mobile Car Service</title></head>
<body>
    <h1>🚗 Mobile Car Service</h1>
    <p>index.html nicht gefunden in: " . __DIR__ . "</p>
    <ul>
        <li><a href='/backend/setup.php'>Backend Setup</a></li>
        <li><a href='/backend/api.php/system/health'>API Health Check</a></li>
    </ul>
</body>
</html>";
    }
    exit;
}

// Andere HTML-Dateien
if (preg_match('/\.html?$/', $requestPath)) {
    $filePath = __DIR__ . $requestPath;
    if (file_exists($filePath)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($filePath);
        exit;
    }
}

// Backend-Dateien schützen (außer erlaubte)
if (preg_match('#^/backend/#', $requestPath)) {
    $allowedBackendPaths = [
        '/backend/setup.php',
        '/backend/api.php'
    ];

    if (!in_array($requestPath, $allowedBackendPaths) && !preg_match('#^/backend/api\.php/#', $requestPath)) {
        http_response_code(403);
        echo "<!DOCTYPE html>
<html>
<head><title>403 Forbidden</title></head>
<body>
    <h1>403 Forbidden</h1>
    <p>Zugriff auf Backend-Datei nicht erlaubt: <code>{$requestPath}</code></p>
    <p><a href='/'>← Zurück zur Startseite</a></p>
</body>
</html>";
        exit;
    }
}

// Normale Datei-Requests
$filePath = __DIR__ . $requestPath;
if (file_exists($filePath) && is_file($filePath)) {
    return false; // Let PHP built-in server handle it
}

// 404 für alles andere
http_response_code(404);
echo "<!DOCTYPE html>
<html>
<head>
    <title>404 - Mobile Car Service</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            text-align: center; 
            padding: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.2);
            max-width: 500px;
        }
        h1 { 
            font-size: 2.5em; 
            margin-bottom: 20px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .links { 
            margin-top: 30px; 
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .links a {
            padding: 12px 24px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            border: 1px solid rgba(255,255,255,0.3);
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .links a:hover { 
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        code {
            background: rgba(0,0,0,0.3);
            padding: 4px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        .debug-info {
            margin-top: 30px;
            font-size: 12px;
            opacity: 0.8;
            background: rgba(0,0,0,0.2);
            padding: 15px;
            border-radius: 10px;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🚗 404</h1>
        <p>Die Seite <code>{$requestPath}</code> wurde nicht gefunden.</p>
        
        <div class='links'>
            <a href='/'>🏠 Startseite</a>
            <a href='/backend/setup.php'>⚙️ Backend Setup</a>
            <a href='/backend/api.php/system/health'>🔍 API Status</a>
        </div>
        
        <div class='debug-info'>
            <strong>Debug Info:</strong><br>
            Document Root: " . __DIR__ . "<br>
            Request Path: {$requestPath}<br>
            File Path: {$filePath}<br>
            File Exists: " . (file_exists($filePath) ? 'Yes' : 'No') . "
        </div>
    </div>
</body>
</html>";
exit;
