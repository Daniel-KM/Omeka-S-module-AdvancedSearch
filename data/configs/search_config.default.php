<?php declare(strict_types=1);

/**
 * Example of a generic config with the internal adapter and the main form.
 *
 * Some form fields are useless, they are added as an example.
 *
 * When created, it can be modified in the admin board.
 *
 * @var \Laminas\I18n\View\Helper\Translate $translate
 */
return [
    '@context' => null,
    '@id' => null,
    '@type' => 'o:SearchConfig',
    'o:id' => null,
    'o:name' => $translate('Default'),
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
            'fulltext_search' => '',
            'validate_form' => false,
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
                    'label' => $translate('Collection'),
                    'type' => 'Omeka/MultiSelect',
                ],
                [
                    'field' => 'resource_class_id',
                    'label' => $translate('Class'),
                    'type' => 'Omeka/MultiSelectFlat',
                ],
                [
                    'field' => 'resource_template_id',
                    'label' => $translate('Template'),
                    'type' => 'Omeka/Radio',
                ],
                [
                    'field' => 'title',
                    'label' => $translate('Title'),
                    'type' => null,
                ],
                [
                    'field' => 'author',
                    'label' => $translate('Author'),
                    'type' => null,
                ],
                [
                    'field' => 'dcterms:subject',
                    'label' => $translate('Subject'),
                    'type' => null,
                ],
                [
                    'field' => 'advanced',
                    'label' => $translate('Filters'),
                    'type' => $translate('Advanced'),
                    'fields' => [
                        'title' => [
                            'value' => 'title',
                            'label' => $translate('Title'),
                        ],
                        'author' => [
                            'value' => 'author',
                            'label' => $translate('Author'),
                        ],
                        'dcterms:creator' => [
                            'value' => 'dcterms:creator',
                            'label' => $translate('Creator'),
                        ],
                        'dcterms:subject' => [
                            'value' => 'dcterms:subject',
                            'label' => $translate('Subject'),
                        ],
                        'date' => [
                            'value' => 'date',
                            'label' => $translate('Date'),
                        ],
                        'description' => [
                            'value' => 'description',
                            'label' => $translate('Description'),
                        ],
                        'resource_class_id' => [
                            'value' => 'resource_class_id',
                            'label' => $translate('Class'),
                        ],
                    ],
                    'default_number' => '1',
                    'max_number' => '10',
                    'field_joiner' => true,
                    'field_joiner_not' => true,
                    'field_operator' => true,
                    'field_operators' => [
                        'eq' => $translate('is exactly'), // @translate
                        'in' => $translate('contains'), // @translate
                        'sw' => $translate('starts with'), // @translate
                        'ew' => $translate('ends with'), // @translate
                        'ex' => $translate('has any value'), // @translate
                        'res' => $translate('is resource with ID'), // @translate
                    ],
                ],
                [
                    'field' => 'dcterms:date',
                    'label' => $translate('Date range'),
                    'type' => 'DateRange',
                ],
                // Other available filters.
                /*
                [
                    'field' => 'dcterms:created',
                    'label' => $translate('Number'),
                    'type' => 'Number',
                ],
                [
                    'field' => 'dcterms:valid',
                    'label' => $translate('Number'),
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
                    'label' => $translate('Date'),
                    'type' => 'Date',
                ],
                [
                    'field' => 'date_range',
                    'label' => $translate('Date range'),
                    'type' => 'DateRangeStartEnd',
                    'options' => [
                        'from' => 'dcterms:created',
                        'to' => 'dcterms:issued',
                    ],
                ],
                [
                    'field' => 'dcterms:spatial',
                    'label' => $translate('Place'),
                    'type' => 'Spatial',
                ],
                [
                    'field' => 'dcterms:spatial',
                    'label' => $translate('Place'),
                    'type' => 'SpatialBox',
                ],
                */
            ],
            'attribute_form' => false,
            'button_reset' => true,
            'button_submit' => true,
        ],

        'display' => [
            'template' => null,
            'breadcrumbs' => false,
            'search_filters' => 'header',
            'active_facets' => 'none',
            'total_results' => 'header',
            'search_form_simple' => 'none',
            'search_form_quick' => 'none',
            'paginator' => 'header',
            'per_pages' => 'header',
            'sort' => 'header',
            'facets_filters' => 'none',
            'grid_list' => 'header',
            'grid_list_mode' => 'auto',
            'facets' => 'before',
        ],

        'pagination' => [
            'per_pages' => [
                // For translation only.
                10 => $translate('Results by %d'), // @translate
                10 => $translate('Results by 10'), // @translate
                // This is the default for Omeka.
                25 => $translate('Results by 25'), // @translate
                50 => $translate('Results by 50'), // @translate
                100 => $translate('Results by 100'), // @translate
            ],
        ],

        'sort' => [
            'label' => $translate('Sort'),
            'fields' => [
                'dcterms:title asc' => [
                    'name' => 'dcterms:title asc',
                    'label' => $translate('Title'),
                ],
                'dcterms:title desc' => [
                    'name' => 'dcterms:title desc',
                    'label' => $translate('Title (from z to a)'),
                ],
                'dcterms:date asc' => [
                    'name' => 'dcterms:date asc',
                    'label' => $translate('Date'),
                ],
                'dcterms:date desc' => [
                    'name' => 'dcterms:date desc',
                    'label' => $translate('Date (most recent first)'),
                ],
            ],
        ],

        'facet' => [
            'label' => 'Facets', // @translate
            'facets' => [
                'item_set_id' => [
                    'name' => 'item_set_id',
                    'label' => $translate('Collection'),
                ],
                'resource_class_id' => [
                    'name' => 'resource_class_id',
                    'label' => $translate('Classe'),
                ],
                'dcterms:subject' => [
                    'name' => 'dcterms:subject',
                    'label' => $translate('Subject'),
                ],
                'dcterms:type' => [
                    'name' => 'dcterms:type',
                    'label' => $translate('Type'),
                ],
                'author' => [
                    'name' => 'author',
                    'label' => $translate('Author'),
                ],
                'date' => [
                    'name' => 'date',
                    'label' => $translate('Date'),
                ],
                'dcterms:language' => [
                    'name' => 'dcterms:language',
                    'label' => $translate('Language'),
                ],
            ],
            'languages' => [],
            'mode' => 'button',
            'limit' => 10,
            'order' => '',
            'display_list' => 'available',
            'display_submit' => 'above',
            'display_reset' => 'above',
            'label_submit' => $translate('Apply facets'),
            'label_reset' => $translate('Reset facets'),
            'display_active' => true,
            'display_count' => true,
        ],
    ],
    'o:created' => null,
    'o:modified' => null,
];
