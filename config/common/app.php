<?php

declare(strict_types=1);

/**
 * Konfigurační soubor aplikace
 * Společná konfigurace pro všechny shopy
 */

return [
    // Site info - základní nastavení (může být overridden per shop)
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
        'root' => dirname(__DIR__, 2),
        'config' => dirname(__DIR__, 2) . '/config',
        'lang' => dirname(__DIR__, 2) . '/lang',
        'app' => dirname(__DIR__, 2) . '/src',
        'www' => dirname(__DIR__, 2) . '/www',
        'cache' => dirname(__DIR__, 2) . '/var/cache',
        'log' => dirname(__DIR__, 2) . '/var/log',
        'temp' => dirname(__DIR__, 2) . '/var/temp',
        'templates' => dirname(__DIR__, 2) . '/src/UI',
        'assets' => dirname(__DIR__, 2) . '/www/assets',
    ],

    'assets' => [
        'url' => '/assets',
    ],

    // Tracy debugger
    'debugger' => [
        'mode' => Tracy\Debugger::Development, // nebo Production
        'logDir' => dirname(__DIR__, 2) . '/var/log',
        'email' => null, // pro produkci - email pro zasílání chyb
    ],

    // Latte
    'latte' => [
        'tempDirectory' => dirname(__DIR__, 2) . '/var/cache',
        'autoRefresh' => true, // v produkci false
    ],
];