<?php
// error.php - Zentrale Error-Page für alle HTTP-Fehler
$error_code = $_GET['code'] ?? '500';
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Log error
if (file_exists('classes/SecurityManager.php')) {
    require_once 'classes/SecurityManager.php';
    SecurityManager::logSecurityEvent('http_error_' . $error_code, [
        'request_uri' => $request_uri,
        'user_agent' => substr($user_agent, 0, 200),
        'referer' => $_SERVER['HTTP_REFERER'] ?? ''
    ]);
}

// Error-Details definieren
$errors = [
    '400' => [
        'title' => 'Ungültige Anfrage',
        'message' => 'Ihre Anfrage konnte nicht verarbeitet werden.',
        'description' => 'Die übermittelten Daten sind ungültig oder unvollständig.',
        'suggestions' => [
            'Überprüfen Sie die eingegebenen Daten',
            'Versuchen Sie es erneut',
            'Kontaktieren Sie uns bei anhaltenden Problemen'
        ]
    ],
    '401' => [
        'title' => 'Nicht berechtigt',
        'message' => 'Sie haben keine Berechtigung für diese Seite.',
        'description' => 'Eine Anmeldung ist erforderlich.',
        'suggestions' => [
            'Melden Sie sich mit gültigen Zugangsdaten an',
            'Kontaktieren Sie den Administrator'
        ]
    ],
    '403' => [
        'title' => 'Zugriff verweigert',
        'message' => 'Der Zugriff auf diese Ressource ist nicht erlaubt.',
        'description' => 'Sie haben nicht die erforderlichen Berechtigungen.',
        'suggestions' => [
            'Überprüfen Sie Ihre Berechtigung',
            'Kontaktieren Sie den Administrator',
            'Kehren Sie zur Startseite zurück'
        ]
    ],
    '404' => [
        'title' => 'Seite nicht gefunden',
        'message' => 'Die angeforderte Seite existiert nicht.',
        'description' => 'Die URL ist möglicherweise veraltet oder falsch geschrieben.',
        'suggestions' => [
            'Überprüfen Sie die URL auf Tippfehler',
            'Nutzen Sie die Navigation zur gewünschten Seite',
            'Kehren Sie zur Startseite zurück'
        ]
    ],
    '500' => [
        'title' => 'Serverfehler',
        'message' => 'Ein interner Serverfehler ist aufgetreten.',
        'description' => 'Unser Team wurde automatisch benachrichtigt.',
        'suggestions' => [
            'Versuchen Sie es in einigen Minuten erneut',
            'Leeren Sie den Browser-Cache',
            'Kontaktieren Sie uns bei anhaltenden Problemen'
        ]
    ]
];

$error = $errors[$error_code] ?? $errors['500'];

// Set appropriate HTTP status
http_response_code((int)$error_code);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $error['title'] ?> - MCS Mobile Car Solutions</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .error-container {
            max-width: 600px;
            margin: 100px auto;
            text-align: center;
            padding: 40px 20px;
        }

        .error-code {
            font-size: 120px;
            font-weight: bold;
            color: #ffffff;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            line-height: 1;
        }

        .error-title {
            font-size: 36px;
            color: #fff;
            margin-bottom: 15px;
        }

        .error-message {
            font-size: 18px;
            color: #ccc;
            margin-bottom: 30px;
        }

        .error-description {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #ffffff;
        }

        .error-suggestions {
            background: rgba(255, 107, 53, 0.1);
            border: 1px solid #ffffff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }

        .error-suggestions h3 {
            color: #ffffff;
            margin-bottom: 15px;
            text-align: center;
        }

        .error-suggestions ul {
            list-style: none;
            padding: 0;
        }

        .error-suggestions li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 107, 53, 0.2);
        }

        .error-suggestions li:last-child {
            border-bottom: none;
        }

        .error-suggestions li:before {
            content: "💡";
            margin-right: 10px;
        }

        .error-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .error-actions a {
            background: linear-gradient(45deg, #ffffff, #ff8c42);
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: transform 0.3s ease;
        }

        .error-actions a:hover {
            transform: translateY(-2px);
        }

        .error-actions .secondary {
            background: transparent;
            border: 2px solid #666;
            color: #ccc;
        }

        .error-actions .secondary:hover {
            border-color: #ffffff;
            color: #ffffff;
        }

        .error-details {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #333;
            font-size: 12px;
            color: #666;
        }

        @media (max-width: 768px) {
            .error-code {
                font-size: 80px;
            }

            .error-title {
                font-size: 24px;
            }

            .error-actions {
                flex-direction: column;
                align-items: center;
            }

            .error-actions a {
                width: 200px;
                text-align: center;
            }
        }

        /* Animation */
        @keyframes bounce {

            0%,
            20%,
            50%,
            80%,
            100% {
                transform: translateY(0);
            }

            40% {
                transform: translateY(-10px);
            }

            60% {
                transform: translateY(-5px);
            }
        }

        .error-code {
            animation: bounce 2s infinite;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="error-container">
            <!-- Error Icon/Code -->
            <div class="error-code"><?= $error_code ?></div>

            <!-- Error Info -->
            <h1 class="error-title"><?= htmlspecialchars($error['title']) ?></h1>
            <p class="error-message"><?= htmlspecialchars($error['message']) ?></p>

            <!-- Description -->
            <div class="error-description">
                <p><?= htmlspecialchars($error['description']) ?></p>
            </div>

            <!-- Suggestions -->
            <div class="error-suggestions">
                <h3>Was können Sie tun?</h3>
                <ul>
                    <?php foreach ($error['suggestions'] as $suggestion): ?>
                        <li><?= htmlspecialchars($suggestion) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Action Buttons -->
            <div class="error-actions">
                <a href="/" class="primary">🏠 Zur Startseite</a>
                <a href="javascript:history.back()" class="secondary">⬅️ Zurück</a>
                <?php if ($error_code === '404'): ?>
                    <a href="/?step=1" class="primary">📅 Termin buchen</a>
                <?php endif; ?>
            </div>

            <!-- Contact Info for 500 errors -->
            <?php if ($error_code === '500'): ?>
                <div style="background: rgba(220, 53, 69, 0.1); border: 1px solid #dc3545; border-radius: 10px; padding: 20px; margin-top: 30px; color: #ff6666;">
                    <h3>💬 Kontakt bei Problemen</h3>
                    <p>Falls dieser Fehler wiederholt auftritt, kontaktieren Sie uns:</p>
                    <p>
                        <strong>📞 Telefon:</strong> <?= defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '+49 123 456789' ?><br>
                        <strong>📧 E-Mail:</strong> <?= defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : 'info@mcs-mobile.de' ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Debug Info (nur in Development) -->
            <?php if (($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false): ?>
                <div class="error-details">
                    <h4>🔧 Debug-Informationen</h4>
                    <p><strong>Request URI:</strong> <?= htmlspecialchars($request_uri) ?></p>
                    <p><strong>Time:</strong> <?= date('d.m.Y H:i:s') ?></p>
                    <p><strong>IP:</strong> <?= htmlspecialchars($client_ip) ?></p>
                    <?php if (!empty($_SERVER['HTTP_REFERER'])): ?>
                        <p><strong>Referer:</strong> <?= htmlspecialchars($_SERVER['HTTP_REFERER']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-retry für 500 Fehler nach 10 Sekunden
        <?php if ($error_code === '500'): ?>
            let retryCount = parseInt(sessionStorage.getItem('error_retry_count') || '0');
            if (retryCount < 3) {
                setTimeout(() => {
                    sessionStorage.setItem('error_retry_count', retryCount + 1);
                    if (confirm('Möchten Sie es automatisch erneut versuchen?')) {
                        window.location.reload();
                    }
                }, 10000);
            }
        <?php else: ?>
            sessionStorage.removeItem('error_retry_count');
        <?php endif; ?>

        // Tracking für Error-Pages (optional)
        if (typeof gtag !== 'undefined') {
            gtag('event', 'exception', {
                'description': 'HTTP <?= $error_code ?> Error',
                'fatal': <?= $error_code === '500' ? 'true' : 'false' ?>
            });
        }

        // Console-Info für Entwickler
        console.group('🚨 HTTP <?= $error_code ?> Error');
        console.log('Page:', '<?= htmlspecialchars($request_uri) ?>');
        console.log('Time:', '<?= date('c') ?>');
        console.log('Description:', '<?= htmlspecialchars($error['description']) ?>');
        console.groupEnd();
    </script>
</body>

</html>