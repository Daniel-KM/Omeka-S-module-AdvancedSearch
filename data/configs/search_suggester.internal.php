<?php declare(strict_types=1);

/**
 * Generic config for the internal suggester (sql).
 *
 * When created, it can be modified in the admin board.
 *
 * @var array
 */
return [
    'mode_index' => 'start',
    'mode_search' => 'start',
    'limit' => 25,
    'length' => 50,
    'fields' => [],
    'excluded_fields' => [
        'dcterms:tableOfContents',
        'bibo:content',
        'extracttext:extracted_text',
    ],
];
