<?php

declare(strict_types=1);

/**
 * Routes for Florea shop
 * Definice rout pro hlavní shop Florea
 */

return [
    // === TESTOVACÍ ROUTA - ShopInfo ===
    [
        'pattern' => 'shop-info',  // ← Stejné pro všechny jazyky
        'presenter' => 'ShopInfo',
        'action' => 'default',
    ],

    // === PRODUKTY ===
    [
        'patterns' => [
            'cs' => 'produkt/<id>',
        ],
        'presenter' => 'Product',
        'action' => 'detail',
    ],

    // === KATEGORIE ===
    [
        'patterns' => [
            'cs' => 'produkty/<slug>',
        ],
        'presenter' => 'Category',
        'action' => 'default',
    ],

    // Kontakty
    [
        'patterns' => [
            'cs' => 'kontakt',
        ],
        'presenter' => 'Contact',
        'action' => 'default',
    ],

    // === AUTH (Přihlášení, Odhlášení, Registrace) ===
    [
        'patterns' => [
            'cs' => 'prihlaseni',
        ],
        'presenter' => 'Auth',
        'action' => 'login',
    ],
    [
        'patterns' => [
            'cs' => 'odhlaseni',
        ],
        'presenter' => 'Auth',
        'action' => 'logout',
    ],
    [
        'patterns' => [
            'cs' => 'registrace',
        ],
        'presenter' => 'Auth',
        'action' => 'register',
    ],
    [
        'patterns' => [
            'cs' => 'ztracene-heslo',
        ],
        'presenter' => 'Auth',
        'action' => 'forgotPassword',
    ],
    [
        'patterns' => [
            'cs' => 'reset-hesla',
        ],
        'presenter' => 'Auth',
        'action' => 'resetPassword',
    ],

    // === ACCOUNT (Můj účet) ===
    [
        'patterns' => [
            'cs' => 'muj-ucet',
        ],
        'presenter' => 'Account',
        'action' => 'profile',
    ],
    [
        'patterns' => [
            'cs' => 'muj-ucet/upravit',
        ],
        'presenter' => 'Account',
        'action' => 'edit',
    ],
    [
        'patterns' => [
            'cs' => 'muj-ucet/zmenit-heslo',
        ],
        'presenter' => 'Account',
        'action' => 'changePassword',
    ],
    [
        'patterns' => [
            'cs' => 'muj-ucet/adresy',
        ],
        'presenter' => 'Account',
        'action' => 'addresses',
    ],
    [
        'patterns' => [
            'cs' => 'muj-ucet/adresy/nastavit-vychozi/<id>',
        ],
        'presenter' => 'Account',
        'action' => 'setDefaultAddress',
    ],
    [
        'patterns' => [
            'cs' => 'muj-ucet/adresy/smazat/<id>',
        ],
        'presenter' => 'Account',
        'action' => 'deleteAddress',
    ],
    [
        'patterns' => [
            'cs' => 'muj-ucet/adresy/upravit/<id>',
        ],
        'presenter' => 'Account',
        'action' => 'editAddress',
    ],
    [
        'patterns' => [
            'cs' => 'muj-ucet/test-email',
        ],
        'presenter' => 'Account',
        'action' => 'testEmail',
    ],
    [
        'patterns' => [
            'cs' => 'muj-ucet/test-maileon',
        ],
        'presenter' => 'Account',
        'action' => 'testMaileon',
    ],
];