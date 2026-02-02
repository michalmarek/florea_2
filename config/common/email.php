<?php declare(strict_types=1);

/**
 * Email Configuration
 *
 * Supports two email providers:
 * - Maileon: For important customer emails (password reset, order confirmation)
 * - SMTP (Nette Mail): For internal/admin notifications
 */

return [
    // Maileon Configuration
    'maileon' => [
        'apiKey' => '2c01c285-502e-4437-9711-4195d26a0d00',
        'baseUrl' => 'https://api.maileon.com/1.0/',

        // Event IDs from Maileon dashboard
        // These are transactional events with pre-configured templates
        'events' => [
            'passwordReset' => 477,
        ],
    ],

    // SMTP Configuration (Nette Mail)
    // Development: MailHog (localhost:1025)
    // Production: Real SMTP server
    'smtp' => [
        'host' => 'localhost',
        'port' => 1025,                     // MailHog port (change for production)
        'username' => "",
        'password' => "",
        'encryption' => null,                   // 'ssl', 'tls', or null
    ],

    // Default sender for all emails
    'from' => [
        'email' => 'obchod@florea.cz',
        'name' => 'Florea.cz',
    ],

    // Logging
    'logging' => [
        'enabled' => true,
        'filename' => 'email.log',
    ],
];