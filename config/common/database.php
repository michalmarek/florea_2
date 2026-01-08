<?php declare(strict_types=1);

/**
 * Common database configuration
 * Společná databázová konfigurace
 *
 * Pro local override použij config/common/database.local.php
 */

return [
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
];