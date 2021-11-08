<?php declare(strict_types=1);

// Example of a generic config with the internal adapter and the main form.
// When created, it can be modified in the admin board.

// This config is used for new sites too.

return [
    'search' => [
        'default_results' => 'default',
        'default_query' => '',
    ],

    // Specific fields for external search engines.
    // TODO To be moved to Internal and Solr.
    'resource_fields' => [
        'is_public_field' => 'is_public_field',
        'item_set_id_field' => 'item_set_id_field',
        'resource_class_id_field' => 'resource_class_id_field',
        'resource_template_id_field' => 'resource_template_id_field',
        'owner_id_field' => 'owner_id_field',
    ],

    'autosuggest' => [
        'suggester' => 1,
        'url' => '',
        'url_param_name' => '',
    ],

    'form' => [
        // All filters are managed the same via the querier: a form is a query configurator.

        'filters' => [
            // Ordered list of specific filters.
            [
                'field' => 'item_set_id_field',
                'label' => 'Collection',
                'type' => 'Select',
            ],
            [
                'field' => 'resource_class_id_field',
                'label' => 'Class',
                'type' => 'SelectFlat',
            ],
            [
                'field' => 'resource_template_id_field',
                'label' => 'Template',
                'type' => 'Radio',
            ],
            [
                'field' => 'advanced',
                'label' => 'Filters',
                'type' => 'Advanced',
                'fields' => [
                    'dcterms:title' => [
                        'value' => 'dcterms:title',
                        'label' => 'Title',
                    ],
                    'dcterms:subject' => [
                        'value' => 'dcterms:subject',
                        'label' => 'Subject',
                    ],
                    'dcterms:creator' => [
                        'value' => 'dcterms:creator',
                        'label' => 'Creator',
                    ],
                    'dcterms:date' => [
                        'value' => 'dcterms:date',
                        'label' => 'Date',
                    ],
                    'dcterms:description' => [
                        'value' => 'dcterms:description',
                        'label' => 'Description',
                    ],
                    'resource_class_id' => [
                        'value' => 'resource_class_id_field',
                        'label' => 'Class',
                    ],
                ],
                'max_number' => '5',
                'field_joiner' => true,
                'field_joiner_not' => true,
                'field_operator' => true,
            ],
            [
                'field' => 'dcterms:date',
                'label' => 'Date range',
                'type' => 'DateRange',
            ],
            // Other available filters.
            /*
            [
                'field' => 'dcterms:created',
                'label' => 'Number',
                'type' => 'Number',
            ],
            [
                'field' => 'dcterms:valid',
                'label' => 'Number',
                'type' => 'NumberRange',
            ],
            [
                'field' => 'dcterms:provenance',
                'type' => 'Hidden',
                'options' => [
                    'the provenance',
                ],
            ],
            [
                'field' => 'dcterms:audience',
                'type' => 'Checkbox',
                'options' => ['no', 'yes'],
            ],
            */
            // Not managed currently.
            /*
            [
                'field' => 'date',
                'label' => 'Date',
                'type' => 'Date',
            ],
            [
                'field' => 'date_range',
                'label' => 'Date range',
                'type' => 'DateRangeStartEnd',
                'options' => [
                    'from' => 'dcterms:created',
                    'to' => 'dcterms:issued',
                ],
            ],
            [
                'field' => 'dcterms:spatial',
                'label' => 'Place',
                'type' => 'Spatial',
            ],
            [
                'field' => 'dcterms:spatial',
                'label' => 'Place',
                'type' => 'SpatialBox',
            ],
            */
        ],
    ],

    'pagination' => [
        'per_pages' => [
            // For translation only.
            10 => 'Results by %d', // @translate
            10 => 'Results by 10', // @translate
            // This is the default for Omeka.
            25 => 'Results by 25', // @translate
            50 => 'Results by 50', // @translate
            100 => 'Results by 100', // @translate
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
            'item_set_id_field' => [
                'name' => 'item_set_id_field',
                'label' => 'Collections',
            ],
            'resource_class_id_field' => [
                'name' => 'resource_class_id_field',
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
