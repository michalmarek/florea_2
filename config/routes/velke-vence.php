<?php

declare(strict_types=1);

/**
 * Routes for Velke Vence shop
 * Definice rout pro satelitní shop Velké Vence
 */

return [
    // === TESTOVACÍ ROUTA - ShopInfo ===
    [
        'pattern' => 'shop-info',
        'presenter' => 'ShopInfo',
        'action' => 'default',
    ],

    // Kontakty
    [
        'patterns' => [
            'cs' => 'kontakt',
            'en' => 'contact',
            'de' => 'kontakt',
        ],
        'presenter' => 'Contact',
        'action' => 'default',
    ],

    // Produkty
    [
        'patterns' => [
            'cs' => 'produkty/<slug>',
            'en' => 'products/<slug>',
            'de' => 'produkte/<slug>',
        ],
        'presenter' => 'Products',
        'action' => 'detail',
    ],
    [
        'patterns' => [
            'cs' => 'produkty',
            'en' => 'products',
            'de' => 'produkte',
        ],
        'presenter' => 'Products',
        'action' => 'default',
    ],
];