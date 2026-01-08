<?php declare(strict_types=1);

/**
 * Shop domain mapping
 * Mapování domén na shop textId
 */

return [
    'domain_mapping' => [
        // Main shop - Florea
        'florea.cz' => 'florea',
        'www.florea.cz' => 'florea',
        'www.florea2.local' => 'florea',

        // Development
        'localhost' => 'florea',  // Default pro local development

        // Satellite shop - Velké Vence
        'velke-vence.cz' => 'velke-vence',
        'www.velke-vence.cz' => 'velke-vence',
        'www.velke-vence.local' => 'velke-vence',
        'velke-vence.local' => 'velke-vence',

        // Satellite shop - Podlahové vázy
        'podlahove-vazy.cz' => 'podlahove-vazy',
        'www.podlahove-vazy.cz' => 'podlahove-vazy',
        'podlahove-vazy.local' => 'podlahove-vazy',

        // TODO: Přidat dalších ~9 satelitních shopů
    ],
];