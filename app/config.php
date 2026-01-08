<?php

declare(strict_types=1);

/**
 * Konfigurační soubor aplikace
 */

return [
    // Site info
    'site' => [
        'name' => 'VUK objekt',
        'url' => 'https://www.vuk.cz',
        'email' => 'obchod@vuk.cz',
    ],

    // Jazykové nastavení
    'languages' => [
        'default' => 'cs',
        'supported' => ['cs', 'en', 'de'],
    ],

    // Cesty
    'paths' => [
        'app' => dirname(__DIR__) . '/app',
        'www' => dirname(__DIR__) . '/www',
        'temp' => dirname(__DIR__) . '/temp',
        'log' => __DIR__ . '/log',
        'routes' => dirname(__DIR__) . '/app/routes.php',
        'templates' => dirname(__DIR__) . '/app/templates',
        'assets' => dirname(__DIR__) . '/www/assets',
    ],

    'assets' => [
        'url' => '/assets',
    ],

    // Databáze (Nette Database Explorer)
    'database' => [
        'dsn' => 'mysql:host=localhost;dbname=florea;charset=utf8mb4',
        'user' => 'root',
        'password' => 'root',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],

    // Tracy debugger
    'debugger' => [
        'mode' => Tracy\Debugger::Development, // nebo Production
        'logDir' => __DIR__ . '/log',
        'email' => null, // pro produkci - email pro zasílání chyb
    ],

    // Latte
    'latte' => [
        'tempDirectory' => __DIR__ . '/../temp/cache',
        'autoRefresh' => true, // v produkci false
    ],
];