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

// Spuštění session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}


// Načtení shop domain mappingu (potřebujeme před detekcí shopu)
$shopsConfig = require __DIR__ . '/config/shops.php';

// Databázové připojení (zatím bez Config, načteme přímo)
$dbConfig = require __DIR__ . '/config/common/database.php';
$appConfig = require __DIR__ . '/config/common/app.php';

// Databázové připojení
Database::connect($dbConfig['database'], $appConfig);
$db = Database::getConnection();


try {
    // Detekce shopu
    $shopRepository = new ShopRepository($db);
    $shopDetector = new ShopDetector($shopRepository, $shopsConfig['domain_mapping']);
    $shopContext = $shopDetector->detectFromRequest();

    // Uložení do globální proměnné
    $GLOBALS['shopContext'] = $shopContext;

} catch (ShopNotFoundException $e) {
    // Vlastní 404 pro neznámou doménu
    http_response_code(404);

    // Zalogovat chybu
    Tracy\Debugger::log($e);
    exit;
}


// === KROK 2: Inicializovat Config pro detekovaný shop ===
Config::init($shopContext->getTextId());

// === KROK 3: Inicializovat Tracy s hierarchickou konfigurací ===
Tracy\Debugger::enable(
    Config::get('app')['debugger']['mode'],
    Config::get('app')['debugger']['logDir']
);

// Debug info v Tracy
if (Config::get('app')['debugger']['mode'] === Tracy\Debugger::Development) {
    Tracy\Debugger::barDump($shopContext, 'ShopContext');
    Tracy\Debugger::barDump(Config::get('app'), 'App Config');
}


// Vytvoření a spuštění aplikace
$app = new Application;
$app->run();