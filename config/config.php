<?php
// Database Configuration
define('DB_PATH', __DIR__ . '/../database/bookings.db');

// Business Configuration
define('BUSINESS_NAME', 'MCS Mobile Car Solutions');
define('BUSINESS_ADDRESS', 'Musterstraße 123, 48431 Rheine');
define('BUSINESS_PHONE', '+49 123 456789');
define('BUSINESS_EMAIL', 'info@mcs-mobile.de');
define('BUSINESS_WEBSITE', 'www.mcs-mobile.de');

// Booking Configuration
define('TRAVEL_COST_PER_KM', 0.50);
define('WORKING_HOURS_START', 8);
define('WORKING_HOURS_END', 17);
define('WORKING_DAYS', [1, 2, 3, 4, 5, 6]); // Monday to Saturday

// Email Configuration
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@mcs-mobile.de');
define('SMTP_FROM_NAME', BUSINESS_NAME);

// Google Maps API
define('GOOGLE_MAPS_API_KEY', 'AIzaSyBbVppnqML9ojgSNrtINJedxSZGR_iDxug');

// Security
define('ADMIN_PASSWORD_HASH', password_hash('admin123', PASSWORD_DEFAULT));
define('SESSION_TIMEOUT', 3600); // 1 hour

// Timezone
date_default_timezone_set('Europe/Berlin');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
