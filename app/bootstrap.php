<?php
declare(strict_types=1);

// nactu vendory
require __DIR__ . '/../vendor/autoload.php';

use Core\Application;
use Core\Config;


// Načtení konfigurace
Config::load(__DIR__ . '/config.php');
Config::loadLocal(__DIR__ . '/config.local.php');


// Spuštění session s custom nastavením (aby Tracy nehlásil chybu)
if (session_status() === PHP_SESSION_NONE) {
    // Nastavíme session options PŘED session_start()
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


// Vytvoření a spuštění aplikace
$app = new Application;
$app->run();
