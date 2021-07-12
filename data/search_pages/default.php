<?php declare(strict_types=1);

// Example of a generic page with the internal adapter and the main form.
// When created, it can be modified in the admin board.

// This config is used for new sites too.

return [
    'search' => [
        'default_results' => 'default',
        'default_query' => '',
    ],

    'autosuggest' => [
        'enable' => true,
        'mode' => 'start',
        'limit' => 25,
        'fields' => [],
        'url' => '',
        'url_param_name' => '',
    ],

    'form' => [
        'item_set_filter_type' => 'select',
        'item_set_id_field' => 'item_set_id_field',
        'resource_class_filter_type' => 'select_flat',
        'resource_class_id_field' => 'resource_class_id_field',
        'resource_template_filter_type' => '0',
        'resource_template_id_field' => 'resource_template_id_field',
        'is_public_field' => 'is_public_field',

        'filters' => [
            ['dcterms:title' => 'Title'],
            ['dcterms:subject' => 'Subject'],
            ['dcterms:creator' => 'Creator'],
            ['dcterms:date' => 'Date'],
            ['dcterms:description' => 'Description'],
        ],
        'filters_max_number' => '5',
        'filter_value_joiner' => true,
        'filter_value_type' => true,

        'fields_order' => [
            'q',
            'itemSet',
            'resourceClass',
            'filters',
            'submit',
        ],
    ],

    'sort' => [
        'fields' => [
            ['dcterms:title asc' => 'Title'],
            ['dcterms:title desc' => 'Title (reverted)'],
            ['dcterms:date asc' => 'Date'],
            ['dcterms:date desc' => 'Date (reverted)'],
        ],
    ],

    'facet' => [
        'facets' => [
            ['item_set_id' => 'Collections'],
            ['resource_class_id' => 'Classes'],
            ['dcterms:subject' => 'Subject'],
            ['dcterms:type' => 'Type'],
            ['dcterms:creator' => 'Creator'],
            ['dcterms:date' => 'Date'],
            ['dcterms:language' => 'Language'],
        ],
        'limit' => 10,
        'languages' => [],
        'mode' => 'button',
    ],
];
