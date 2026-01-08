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
        'config' => dirname(__DIR__) . '/config',
        'app' => dirname(__DIR__) . '/src',
        'www' => dirname(__DIR__) . '/www',
        'cache' => dirname(__DIR__) . '/var/cache',
        'log' => dirname(__DIR__) . '/var/log',
        'temp' => dirname(__DIR__) . '/var/temp',
        'routes' => __DIR__ . '/routes.php',
        'templates' => dirname(__DIR__) . '/src/UI',
        'assets' => dirname(__DIR__) . '/www/assets',
    ],

    'assets' => [
        'url' => '/assets',
    ],

    // Tracy debugger
    'debugger' => [
        'mode' => Tracy\Debugger::Development, // nebo Production
        'logDir' => dirname(__DIR__) . '/var/log',
        'email' => null, // pro produkci - email pro zasílání chyb
    ],

    // Latte
    'latte' => [
        'tempDirectory' => dirname(__DIR__) . '/var/cache',
        'autoRefresh' => true, // v produkci false
    ],
];