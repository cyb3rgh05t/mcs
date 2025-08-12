@echo off
REM Mobile Car Service - Development Server Starter (Windows)

echo 🚗 Mobile Car Service - Development Server
echo ==========================================

REM PHP Version prüfen
php -v >nul 2>&1
if errorlevel 1 (
    echo ❌ PHP ist nicht installiert oder nicht im PATH
    echo    Download: https://windows.php.net/download/
    pause
    exit /b 1
)

echo 📋 PHP Version:
php -v | findstr /r "^PHP"

REM Verzeichnisse erstellen
echo 📁 Erstelle Verzeichnisse...
if not exist "backend\data" mkdir "backend\data"
if not exist "backend\logs" mkdir "backend\logs"
if not exist "backend\uploads" mkdir "backend\uploads"

echo ✅ Verzeichnisse bereit

REM Server starten
echo.
echo 🚀 Starte PHP Development Server...
echo 📍 URL: http://localhost:8000
echo 🔗 Frontend: http://localhost:8000/index.html
echo 🔗 Backend Setup: http://localhost:8000/backend/setup.php
echo 🔗 API Health: http://localhost:8000/backend/api.php/system/health
echo.
echo 🛑 Server stoppen: Ctrl+C
echo ==========================================

REM Server starten (im Hauptverzeichnis, nicht im backend/)
php -S localhost:8000 -t .