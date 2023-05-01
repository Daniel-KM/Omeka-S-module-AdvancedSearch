<?php declare(strict_types=1);

/**
 * Example of a generic config with the internal adapter and the main form.
 *
 * Some form fields are useless, they are added as an example.
 *
 * When created, it can be modified in the admin board.
 */
return [
    '@context' => null,
    '@id' => null,
    '@type' => 'o:SearchConfig',
    'o:id' => null,
    'o:name' => 'Default',
    'o:path' => 'find',
    'o:engine' => ['o:id' => 1],
    'o:form' => 'main',
    'o:settings' => [
        'search' => [
            'default_results' => 'default',
            'default_query' => '',
            // Generally sort, but allows to force page, facets, etc.
            'default_query_post' => '',
            'hidden_query_filters' => '',
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
                    'field' => 'item_set_id',
                    'label' => 'Collection',
                    'type' => 'Omeka/MultiSelect',
                ],
                [
                    'field' => 'resource_class_id',
                    'label' => 'Class',
                    'type' => 'Omeka/MultiSelectFlat',
                ],
                [
                    'field' => 'resource_template_id',
                    'label' => 'Template',
                    'type' => 'Omeka/Radio',
                ],
                [
                    'field' => 'title',
                    'label' => 'Title',
                    'type' => null,
                ],
                [
                    'field' => 'author',
                    'label' => 'Author',
                    'type' => null,
                ],
                [
                    'field' => 'dcterms:subject',
                    'label' => 'Subject',
                    'type' => null,
                ],
                [
                    'field' => 'advanced',
                    'label' => 'Filters',
                    'type' => 'Advanced',
                    'fields' => [
                        'title' => [
                            'value' => 'title',
                            'label' => 'Title',
                        ],
                        'author' => [
                            'value' => 'author',
                            'label' => 'Author',
                        ],
                        'dcterms:creator' => [
                            'value' => 'dcterms:creator',
                            'label' => 'Creator',
                        ],
                        'dcterms:subject' => [
                            'value' => 'dcterms:subject',
                            'label' => 'Subject',
                        ],
                        'date' => [
                            'value' => 'date',
                            'label' => 'Date',
                        ],
                        'description' => [
                            'value' => 'description',
                            'label' => 'Description',
                        ],
                        'resource_class_id' => [
                            'value' => 'resource_class_id',
                            'label' => 'Class',
                        ],
                    ],
                    'max_number' => '5',
                    'field_joiner' => true,
                    'field_joiner_not' => true,
                    'field_operator' => true,
                    'field_operators' => [
                        'eq' => 'is exactly', // @translate
                        'in' => 'contains', // @translate
                        'sw' => 'starts with', // @translate
                        'ew' => 'ends with', // @translate
                        'ex' => 'has any value', // @translate
                        'res' => 'is resource with ID', // @translate
                    ],
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

        'display' => [
            'search_filters' => 'header',
            'active_facets' => 'none',
            'total_results' => 'header',
            'paginator' => 'header',
            'per_pages' => 'header',
            'sort' => 'header',
            'grid_list' => 'header',
            'grid_list_mode' => 'auto',
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
            'label' => 'Facets', // @translate
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
                'author' => [
                    'name' => 'author',
                    'label' => 'Author',
                ],
                'date' => [
                    'name' => 'date',
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
            'display_list' => 'available',
            'display_button' => 'above',
            'display_active' => true,
            'display_count' => true,
        ],
    ],
    'o:created' => null,
    'o:modified' => null,
];
