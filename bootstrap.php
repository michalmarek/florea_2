<?php
declare(strict_types=1);

// Načtu vendory
require __DIR__ . '/vendor/autoload.php';

use Core\Application;
use Core\Config;
use Core\Database;
use Shop\ShopContext;
use Shop\ShopDetector;
use Shop\ShopRepository;
use Shop\Exception\ShopNotFoundException;

// Načtení konfigurace
Config::load(__DIR__ . '/config/app.php');
Config::load(__DIR__ . '/config/database.php');
Config::loadLocal(__DIR__ . '/config/app.local.php');
Config::loadLocal(__DIR__ . '/config/database.local.php');
Config::load(__DIR__ . '/config/shops.php');

// Spuštění session s custom nastavením
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Inicializace Tracy pro debugging
Tracy\Debugger::enable(
    Config::get('debugger.mode'),
    Config::get('debugger.logDir')
);

// === ShopContext inicializace ===

try {
    // Databázové připojení
    Database::connect();
    $db = Database::getConnection();

    // ShopRepository
    $shopRepository = new ShopRepository($db);

    // ShopDetector
    $shopDetector = new ShopDetector(
        $shopRepository,
        Config::get('domain_mapping')
    );

    // Detekce aktuálního shopu
    $shopContext = $shopDetector->detectFromRequest();

    // Uložení do globální proměnné pro přístup v Application
    $GLOBALS['shopContext'] = $shopContext;

    // Debug info v Tracy (jen v dev módu)
    if (Config::get('debugger.mode') === Tracy\Debugger::Development) {
        Tracy\Debugger::barDump($shopContext, 'ShopContext');
    }

} catch (ShopNotFoundException $e) {
    // Vlastní 404 pro neznámou doménu
    http_response_code(404);

    if (Config::get('debugger.mode') === Tracy\Debugger::Development) {
        // V development módu ukázat detailní chybu
        throw $e;
    } else {
        // V produkci pěkná error stránka
        echo '<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Not Found</title>
    <style>
        body { font-family: system-ui; max-width: 600px; margin: 100px auto; padding: 20px; text-align: center; }
        h1 { color: #dc3545; }
        p { color: #6c757d; }
    </style>
</head>
<body>
    <h1>🛍️ Shop Not Found</h1>
    <p>This domain is not configured in our system.</p>
    <p>Please contact the administrator.</p>
</body>
</html>';

        // Zalogovat chybu
        Tracy\Debugger::log($e);
        exit;
    }
}

// Vytvoření a spuštění aplikace
$app = new Application;
$app->run();