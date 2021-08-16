<?php declare(strict_types=1);

// Example of a generic config with the internal adapter and the main form.
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
            'dcterms:title' => [
                'name' => 'dcterms:title',
                'label' => 'Title',
            ],
            'dcterms:subject' => [
                'name' => 'dcterms:subject',
                'label' => 'Subject',
            ],
            'dcterms:creator' => [
                'name' => 'dcterms:creator',
                'label' => 'Creator',
            ],
            'dcterms:date' => [
                'name' => 'dcterms:date',
                'label' => 'Date',
            ],
            'dcterms:description' => [
                'name' => 'dcterms:description',
                'label' => 'Description',
            ],
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
            'dcterms:title asc' => [
                'name' => 'dcterms:title asc',
                'label' => 'Title',
            ],
            'dcterms:title desc' => [
                'name' => 'dcterms:title desc',
                'label' => 'Title (from z to a)',
            ],
            'dcterms:date asc' => [
                'name' => 'dcterms:date asc',
                'label' => 'Date',
            ],
            'dcterms:date desc' => [
                'name' => 'dcterms:date desc',
                'label' => 'Date (most recent first)',
            ],
        ],
    ],

    'facet' => [
        'facets' => [
            'item_set_id' => [
                'name' => 'item_set_id',
                'label' => 'Collections',
            ],
            'resource_class_id' => [
                'name' => 'resource_class_id',
                'label' => 'Classes',
            ],
            'dcterms:subject' => [
                'name' => 'dcterms:subject',
                'label' => 'Subject',
            ],
            'dcterms:type' => [
                'name' => 'dcterms:type',
                'label' => 'Type',
            ],
            'dcterms:creator' => [
                'name' => 'dcterms:creator',
                'label' => 'Creator',
            ],
            'dcterms:date' => [
                'name' => 'dcterms:date',
                'label' => 'Date',
            ],
            'dcterms:language' => [
                'name' => 'dcterms:language',
                'label' => 'Language',
            ],
        ],
        'languages' => [],
        'mode' => 'button',
        'limit' => 10,
        'order' => '',
        'display_button' => 'above',
        'display_active' => true,
        'display_count' => true,
    ],
];
