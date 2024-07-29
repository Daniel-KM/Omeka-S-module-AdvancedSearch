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
    'o:slug' => 'find',
    'o:engine' => ['o:id' => 1],
    'o:form' => 'main',
    'o:settings' => [
        'request' => [
            'default_results' => 'default',
            'default_query' => '',
            // Generally sort, but allows to force page, facets, etc.
            'default_query_post' => '',
            'hidden_query_filters' => [],
            'fulltext_search' => '',
            'validate_form' => false,
        ],

        // To be moved: this is only for the first form filter q.
        'autosuggest' => [
            'suggester' => 1,
            'url' => '',
            'url_param_name' => '',
            'limit' => null,
            'fill_input' => false,
        ],

        // All filters except "advanced" are managed the same via querier:
        // a form is a query configurator.
        'form' => [
            'button_submit' => true,
            'label_submit' => $translate('Search'),
            'button_reset' => false,
            'label_reset' => $translate('Reset fields'),
            'attribute_form' => false,

            'filters' => [
                // Ordered list of specific filters.
                'item_set_id' => [
                    'field' => 'item_set_id',
                    // A end user doesn't knwo "item set", but "collection".
                    'label' => $translate('Collection'), // @ŧranslate
                    'type' => 'MultiSelect',
                ],
                'resource_class_id' => [
                    'field' => 'resource_class_id',
                    'label' => $translate('Class'),
                    'type' => 'MultiSelectFlat',
                ],
                'resource_template_id' => [
                    'field' => 'resource_template_id',
                    'label' => $translate('Template'),
                    'type' => 'Radio',
                ],
                'title' => [
                    'field' => 'title',
                    'label' => $translate('Title'),
                    'type' => null,
                    'options' => [
                        'autosuggest' => true,
                    ],
                ],
                'author' => [
                    'field' => 'author',
                    'label' => $translate('Author'),
                    'type' => 'Select',
                ],
                'dcterms_subject' => [
                    'field' => 'dcterms:subject',
                    'label' => $translate('Subject'),
                    'type' => null,
                    'options' => [
                        'autosuggest' => true,
                    ],
                ],
                'date' => [
                    'field' => 'date',
                    'label' => $translate('Date range'),
                    'type' => 'RangeDouble',
                    'options' => [
                        'first_digits' => true,
                    ],
                    'attributes' => [
                        'min' => 1454,
                        'max' => 2025,
                    ],
                ],
                'advanced' => [
                    'field' => 'advanced',
                    'label' => $translate('Filters'),
                    'type' => 'Advanced',
                ],
                // Other available filters.
                /*
                'dcterms_created' => [
                    'field' => 'dcterms:created',
                    'label' => $translate('Number'),
                    'type' => 'Number',
                ],
                'dcterms_audience' => [
                    'field' => 'dcterms:audience',
                    'type' => 'Checkbox',
                    'options' => [
                        'unchecked_value' => 'no',
                        'checked_value' => 'yes',
                    ],
                ],
                // Not managed currently.
                /*
                'date_range' => [
                    'field' => 'date_range',
                    'label' => $translate('Date range'),
                    'type' => 'DateRangeStartEnd',
                    'options' => [
                        'from' => 'dcterms:created',
                        'to' => 'dcterms:issued',
                    ],
                ],
                'dcterms_spatial' => [
                    'field' => 'dcterms:spatial',
                    'label' => $translate('Place'),
                    'type' => 'Spatial',
                ],
                'dcterms_spatial_2' => [
                    'field' => 'dcterms:spatial',
                    'label' => $translate('Place'),
                    'type' => 'SpatialBox',
                ],
                */
            ],

            // The specific settings for filter Advanced are separated to avoid
            // a complex form.
            'advanced' => [
                'default_number' => 1,
                'max_number' => 10,
                'field_joiner' => true,
                'field_joiner_not' => true,
                'field_operator' => true,
                'field_operators' => [
                    'in' => $translate('contains'), // @translate
                    'eq' => $translate('is exactly'), // @translate
                    'sw' => $translate('starts with'), // @translate
                    'ew' => $translate('ends with'), // @translate
                    'ex' => $translate('has any value'), // @translate
                    'res' => $translate('is resource with ID'), // @translate
                ],
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
            ],
        ],

        'display' => [
            'by_resource_type' => false,
            'template' => null,
            'breadcrumbs' => false,
            'search_filters' => 'header',
            'active_facets' => 'none',
            'total_results' => 'header',
            'search_form_simple' => 'none',
            'search_form_quick' => 'none',
            'paginator' => 'header',
            'per_page' => 'header',
            'sort' => 'header',
            'grid_list' => 'header',
            'grid_list_mode' => 'auto',
            'thumbnail_mode' => 'default',
            'thumbnail_type' => 'medium',
            'allow_html' => false,
            'facets' => 'before',
            'per_page_list' => [
                // For translation only.
                10 => $translate('Results by %d'), // @translate
                10 => $translate('Results by 10'), // @translate
                // This is the default for Omeka.
                25 => $translate('Results by 25'), // @translate
                50 => $translate('Results by 50'), // @translate
                100 => $translate('Results by 100'), // @translate
            ],
            'label_sort' => $translate('Sort'),
            'sort_list' => [
                'relevance desc' => [
                    'name' => 'relevance desc',
                    'label' => $translate('Relevance'), // @translate
                ],
                'relevance asc' => [
                    'name' => 'relevance asc',
                    'label' => $translate('Relevance (inversed)'), // @translate
                ],
                'dcterms:title asc' => [
                    'name' => 'dcterms:title asc',
                    'label' => $translate('Title'), // @translate
                ],
                'dcterms:title desc' => [
                    'name' => 'dcterms:title desc',
                    'label' => $translate('Title (from z to a)'), // @translate
                ],
                'dcterms:date asc' => [
                    'name' => 'dcterms:date asc',
                    'label' => $translate('Date'), // @translate
                ],
                'dcterms:date desc' => [
                    'name' => 'dcterms:date desc',
                    'label' => $translate('Date (most recent first)'), // @translate
                ],
            ],
        ],

        'facet' => [
            'label' => 'Facets',
            'label_no_facets' => $translate('No facets'),
            'mode' => 'button',
            'list' => 'available',
            'display_active' => true,
            'label_active_facets' => $translate('Active facets'),
            'display_submit' => 'above',
            'label_submit' => $translate('Apply facets'),
            'display_reset' => 'above',
            'label_reset' => $translate('Reset facets'),
            // The mode is the always the same, but passed with each facet for simplicity.
            'facets' => [
                'item_set_id' => [
                    'field' => 'item_set_id',
                    'languages' => [],
                    'label' => $translate('Collection'),
                    'type' => 'Checkbox',
                    'order' => 'default',
                    'limit' => 25,
                    'state' => 'static',
                    'more' => 10,
                    'display_count' => false,
                    'mode' => 'button',
                ],
                'resource_class_id' => [
                    'field' => 'resource_class_id',
                    'languages' => [],
                    'label' => $translate('Class'),
                    'type' => 'Checkbox',
                    'order' => 'default',
                    'limit' => 25,
                    'state' => 'static',
                    'more' => 10,
                    'display_count' => false,
                    'mode' => 'button',
                ],
                'date' => [
                    'field' => 'date',
                    'languages' => [],
                    'label' => $translate('Date'),
                    'type' => 'RangeDouble',
                    'state' => 'static',
                    'min' => 1454,
                    'max' => 2025,
                    'mode' => 'button',
                ],
                'dcterms:subject' => [
                    'field' => 'dcterms:subject',
                    'languages' => [],
                    'label' => $translate('Subject'),
                    'type' => 'Checkbox',
                    'order' => 'default',
                    'limit' => 25,
                    'state' => 'static',
                    'more' => 10,
                    'langs' => [],
                    'display_count' => false,
                    'mode' => 'button',
                ],
                'dcterms:type' => [
                    'field' => 'dcterms:type',
                    'languages' => [],
                    'label' => $translate('Type'),
                    'type' => 'Checkbox',
                    'order' => 'default',
                    'limit' => 25,
                    'state' => 'static',
                    'more' => 10,
                    'display_count' => false,
                    'mode' => 'button',
                ],
                'author' => [
                    'field' => 'author',
                    'languages' => [],
                    'label' => $translate('Author'),
                    'type' => 'Checkbox',
                    'order' => 'default',
                    'limit' => 25,
                    'state' => 'static',
                    'more' => 10,
                    'display_count' => false,
                    'mode' => 'button',
                ],
                'dcterms:language' => [
                    'field' => 'dcterms:language',
                    'languages' => [],
                    'label' => $translate('Language'),
                    'type' => 'Checkbox',
                    'order' => 'default',
                    'limit' => 25,
                    'state' => 'static',
                    'more' => 10,
                    'display_count' => false,
                    'mode' => 'button',
                ],
            ],
        ],
    ],
    'o:created' => null,
    'o:modified' => null,
];
