<?php declare(strict_types=1);

/**
 * Generic config for the internal engine (sql).
 *
 * When created, it can be modified in the admin board.
 *
 * @var array
 */
return [
    '@context' => null,
    '@id' => null,
    '@type' => 'o:SearchEngine',
    'o:id' => null,
    'o:name' => 'Internal (sql)',
    'o:adapter' => 'internal',
    'o:settings' => [
        'resource_types' => [
            'items',
        ],
        'adapter' => [
        ],
    ],
    'o:created' => null,
    'o:modified' => null,
];
