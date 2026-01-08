<?php

declare(strict_types=1);

/**
 * Routes - definice všech custom rout
 */

return [
    // === TESTOVACÍ ROUTA - ShopInfo ===
    [
        'pattern' => 'shop-info',  // ← Stejné pro všechny jazyky
        'presenter' => 'ShopInfo',
        'action' => 'default',
    ],

    // Jak objednat
    [
        'patterns' => [
            'cs' => 'jak-objednat',
            'en' => 'how-to-order',
            'de' => 'uber-uns',
        ],
        'presenter' => 'Order',
        'action' => 'default',
    ],

    // Časté dotazy
    [
        'patterns' => [
            'cs' => 'caste-dotazy',
            'en' => 'faq',
            'de' => 'uber-uns',
        ],
        'presenter' => 'Faq',
        'action' => 'default',
    ],

    // O nás
    [
        'patterns' => [
            'cs' => 'o-nas',
            'en' => 'about-us',
            'de' => 'uber-uns',
        ],
        'presenter' => 'AboutUs',
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

    // === Další příklady ===
    // Blog - stejné URL pro všechny jazyky
    // [
    //     'pattern' => 'blog[/<slug>]',
    //     'presenter' => 'Blog',
    //     'action' => 'default',
    // ],

    // Článek s ID a slugem
    // [
    //     'pattern' => 'article/<id \d+>[-<slug>]',
    //     'presenter' => 'Article',
    //     'action' => 'detail',
    // ],

    // Galerie s kategorií
    // [
    //     'pattern' => 'gallery[/<category>]',
    //     'presenter' => 'Gallery',
    //     'action' => 'default',
    // ],

    // [
    //     'patterns' => [
    //         'cs' => 'sluzby[/<slug>]',
    //         'en' => 'services[/<slug>]',
    //     ],
    //     'presenter' => 'Services',
    //     'action' => 'default',
    // ],

    // [
    //     'pattern' => 'user/<username>',
    //     'presenter' => 'User',
    //     'action' => 'profile',
    // ],
];