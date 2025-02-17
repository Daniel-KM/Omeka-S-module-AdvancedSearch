<?php declare(strict_types=1);

/**
 * Example of a generic config with the internal adapter and the main form.
 *
 * Some form fields are useless, they are added as an example.
 *
 * For the aggregated fields:
 * Properties are grouped according to standard Dublin Core refinements.
 * Accrual and instructionnal are grouped together according to rare usage.
 * Some special groups are added for common usage.
 * The bibo terms are appended according to sub property rules.
 * Some bibo terms are not included.
 * @see https://github.com/structureddynamics/Bibliographic-Ontology-BIBO/blob/master/bibo.owl
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
    'o:search_engine' => ['o:id' => 1],
    'o:form_adapter' => 'main',
    'o:settings' => [
        'request' => [
            'default_results' => 'default',
            'default_query' => '',
            // Generally sort, but allows to force page, facets, etc.
            'default_query_post' => '',
            'hidden_query_filters' => [],
            'validate_form' => false,
        ],

        'index' => [
            'aliases' => [
                'title' => [
                    'name' => 'title',
                    'label' => 'Title',
                    'fields' => [
                        'dcterms:title',
                        'dcterms:alternative',
                        'bibo:shortTitle',
                    ],
                ],
                // Aggregate creators and contributors.
                'author' => [
                    'name' => 'author',
                    'label' => 'Author',
                    'fields' => [
                        'dcterms:creator',
                        'dcterms:contributor',
                        'bibo:authorList',
                        'bibo:contributorList',
                        'bibo:director',
                        'bibo:editor',
                        'bibo:editorList',
                        'bibo:interviewee',
                        'bibo:interviewer',
                        'bibo:organizer',
                        'bibo:performer',
                        'bibo:producer',
                        'bibo:recipient',
                        'bibo:translator',
                    ],
                ],
                'creator' => [
                    'name' => 'creator',
                    'label' => 'Creator',
                    'fields' => [
                        'dcterms:creator',
                    ],
                ],
                'contributor' => [
                    'name' => 'contributor',
                    'label' => 'Contributor',
                    'fields' => [
                        'dcterms:contributor',
                        // Note: according to bibo, any people is contributor.
                        'bibo:authorList',
                        'bibo:contributorList',
                        'bibo:director',
                        'bibo:editor',
                        'bibo:editorList',
                        'bibo:interviewee',
                        'bibo:interviewer',
                        'bibo:organizer',
                        'bibo:performer',
                        'bibo:producer',
                        'bibo:recipient',
                        'bibo:translator',
                    ],
                ],
                'subject' => [
                    'name' => 'subject',
                    'label' => 'Subject',
                    'fields' => [
                        'dcterms:subject',
                    ],
                ],
                'description' => [
                    'name' => 'description',
                    'label' => 'Description',
                    'fields' => [
                        'dcterms:description',
                        'dcterms:abstract',
                        'dcterms:tableOfContents',
                        'bibo:abstract',
                        'bibo:shortDescription',
                    ],
                ],
                'publisher' => [
                    'name' => 'publisher',
                    'label' => 'Publisher',
                    'fields' => [
                        'dcterms:publisher',
                        'bibo:distributor',
                        'bibo:issuer',
                    ],
                ],
                'date' => [
                    'name' => 'date',
                    'label' => 'Date',
                    'fields' => [
                        'dcterms:date',
                        'dcterms:available',
                        'dcterms:created',
                        'dcterms:issued',
                        'dcterms:modified',
                        'dcterms:valid',
                        'dcterms:dateAccepted',
                        'dcterms:dateCopyrighted',
                        'dcterms:dateSubmitted',
                        'bibo:argued',
                    ],
                ],
                'type' => [
                    'name' => 'type',
                    'label' => 'Type',
                    'fields' => [
                        'dcterms:type',
                    ],
                ],
                'format' => [
                    'name' => 'format',
                    'label' => 'Format',
                    'fields' => [
                        'dcterms:format',
                        'dcterms:extent',
                        'dcterms:medium',
                    ],
                ],
                'identifier' => [
                    'name' => 'identifier',
                    'label' => 'Identifier',
                    'fields' => [
                        'dcterms:identifier',
                        'dcterms:bibliographicCitation',
                        'bibo:asin',
                        'bibo:coden',
                        'bibo:doi',
                        'bibo:eanucc13',
                        'bibo:eissn',
                        'bibo:gtin14',
                        'bibo:handle',
                        'bibo:identifier',
                        'bibo:isbn',
                        'bibo:isbn10',
                        'bibo:isbn13',
                        'bibo:issn',
                        'bibo:oclcnum',
                        'bibo:pmid',
                        'bibo:sici',
                        'bibo:upc',
                        'bibo:uri',
                    ],
                ],
                // May be a relation.
                'source' => [
                    'name' => 'source',
                    'label' => 'Source',
                    'fields' => [
                        'dcterms:source',
                    ],
                ],
                'provenance' => [
                    'name' => 'provenance',
                    'label' => 'Provenance',
                    'fields' => [
                        'dcterms:provenance',
                    ],
                ],
                'language' => [
                    'name' => 'language',
                    'label' => 'Language',
                    'fields' => [
                        'dcterms:language',
                    ],
                ],
                'relation' => [
                    'name' => 'relation',
                    'label' => 'Relation',
                    'fields' => [
                        'dcterms:relation',
                        'dcterms:isVersionOf',
                        'dcterms:hasVersion',
                        'dcterms:isReplacedBy',
                        'dcterms:replaces',
                        'dcterms:isRequiredBy',
                        'dcterms:requires',
                        'dcterms:isPartOf',
                        'dcterms:hasPart',
                        'dcterms:isReferencedBy',
                        'dcterms:references',
                        'dcterms:isFormatOf',
                        'dcterms:hasFormat',
                        'dcterms:conformsTo',
                        'bibo:annotates',
                        'bibo:citedBy',
                        'bibo:cites',
                        'bibo:reproducedIn',
                        'bibo:reviewOf',
                        'bibo:transcriptOf',
                        'bibo:translationOf',
                    ],
                ],
                'coverage' => [
                    'name' => 'coverage',
                    'label' => 'Coverage',
                    'fields' => [
                        'dcterms:coverage',
                        'dcterms:spatial',
                        'dcterms:temporal',
                    ],
                ],
                'rights' => [
                    'name' => 'rights',
                    'label' => 'Rights',
                    'fields' => [
                        'dcterms:rights',
                        'dcterms:accessRights',
                        'dcterms:license',
                    ],
                ],
                'audience' => [
                    'name' => 'audience',
                    'label' => 'Audience',
                    'fields' => [
                        'dcterms:audience',
                        'dcterms:mediator',
                        'dcterms:educationLevel',
                    ],
                ],
                'rights_holder' => [
                    'name' => 'rights_holder',
                    'label' => 'Rights holder',
                    'fields' => [
                        'dcterms:rightsHolder',
                        'bibo:owner',
                    ],
                ],

                // These groups don't follow rdf rules.

                // Group similar and rarely used unrefined data.
                'accrual_and_instructional' => [
                    'name' => 'accrual_and_instructional',
                    'label' => 'Accrual and instructional metadata',
                    'fields' => [
                        'dcterms:accrualMethod',
                        'dcterms:accrualPeriodicity',
                        'dcterms:accrualPolicy',
                        'dcterms:instructionalMethod',
                    ],
                ],
                'bibliographic_data' => [
                    'name' => 'bibliographic_data',
                    'label' => 'Bibliographic data',
                    'fields' => [
                        'bibo:chapter',
                        'bibo:edition',
                        'bibo:issue',
                        'bibo:locator',
                        'bibo:numPages',
                        'bibo:numVolumes',
                        'bibo:number',
                        'bibo:pageEnd',
                        'bibo:pageStart',
                        'bibo:pages',
                        'bibo:section',
                        'bibo:volume',
                    ],
                ],
                'full_text' => [
                    'name' => 'full_text',
                    'label' => 'Full text',
                    'fields' => [
                        'bibo:content',
                        'extracttext:extracted_text',
                    ],
                ],
            ],
            'query_args' => [
                /**
                 * Default options for filters are: join = and, type = eq.
                 * The keys and values are the filter ones: join, field, type, except, datatype.
                 * @see \AdvancedSearch\Stdlib\SearchResources::buildFilterQuery()
                 */
                /*
                'author' => [
                    'join' => 'and',
                    'type' => 'res',
                ],
                */
            ],
        ],

        // TODO To be moved in form.
        // This is only the first form filter q and nothing really specific, once auto-indexing for filters will be implemented.
        'q' => [
            'label' => $translate('Search'),
            'suggester' => 1,
            'suggest_url' => '',
            'suggest_url_param_name' => '',
            'suggest_limit' => null,
            'suggest_fill_input' => false,
            'remove_diacritics' => false,
            'default_search_partial_word' => false,
        ],

        // All filters except "advanced" are managed the same via querier:
        // a form is a query configurator.
        'form' => [
            'button_submit' => true,
            'label_submit' => $translate('Search'),
            'button_reset' => false,
            'label_reset' => $translate('Reset fields'),
            'attribute_form' => false,

            'rft' => null,

            'filters' => [
                // Ordered list of specific filters.
                'item_set_id' => [
                    'field' => 'item_set_id',
                    // A end user doesn't know "item set", but "collection".
                    'label' => $translate('Collection'), // @ŧranslate
                    'type' => 'MultiSelect',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                ],
                'resource_class_id' => [
                    'field' => 'resource_class_id',
                    'label' => $translate('Class'),
                    'type' => 'MultiSelectFlat',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                ],
                'resource_template_id' => [
                    'field' => 'resource_template_id',
                    'label' => $translate('Template'),
                    'type' => 'Radio',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                ],
                'title' => [
                    'field' => 'title',
                    'label' => $translate('Title'),
                    'type' => null,
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                    'options' => [
                        'autosuggest' => true,
                    ],
                ],
                'author' => [
                    'field' => 'author',
                    'label' => $translate('Author'),
                    'type' => 'Select',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                ],
                'dcterms_subject' => [
                    'field' => 'dcterms:subject',
                    'label' => $translate('Subject'),
                    'type' => null,
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                    'options' => [
                        'autosuggest' => true,
                    ],
                ],
                'date' => [
                    'field' => 'date',
                    'label' => $translate('Date range'),
                    'type' => 'RangeDouble',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
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
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                ],
                // Other available filters.
                /*
                'dcterms_created' => [
                    'field' => 'dcterms:created',
                    'label' => $translate('Number'),
                    'type' => 'Number',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                ],
                'dcterms_audience' => [
                    'field' => 'dcterms:audience',
                    'label' = >$translate('Audience'),
                    'type' => 'Checkbox',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
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
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                    'options' => [
                        'from' => 'dcterms:created',
                        'to' => 'dcterms:issued',
                    ],
                ],
                'dcterms_spatial' => [
                    'field' => 'dcterms:spatial',
                    'label' => $translate('Place'),
                    'type' => 'Spatial',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                ],
                'dcterms_spatial_2' => [
                    'field' => 'dcterms:spatial',
                    'label' => $translate('Place'),
                    'type' => 'SpatialBox',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
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

        'results' => [
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
            'pagination_per_page' => 0,
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
            'display_refine' => true,
            'label_refine' => $translate('Refine search'),
            // The mode is the always the same, but passed with each facet for simplicity.
            'facets' => [
                'item_set_id' => [
                    'field' => 'item_set_id',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                    'label' => $translate('Collection'),
                    'type' => 'Checkbox',
                    'state' => 'static',
                    'more' => 10,
                    'display_count' => false,
                    'mode' => 'button',
                ],
                'resource_class_id' => [
                    'field' => 'resource_class_id',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                    'label' => $translate('Class'),
                    'type' => 'Checkbox',
                    'state' => 'static',
                    'more' => 10,
                    'display_count' => false,
                    'mode' => 'button',
                ],
                'date' => [
                    'field' => 'date',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                    'label' => $translate('Date'),
                    'type' => 'RangeDouble',
                    'state' => 'static',
                    'min' => 1454,
                    'max' => 2025,
                    'mode' => 'button',
                ],
                'dcterms:subject' => [
                    'field' => 'dcterms:subject',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                    'label' => $translate('Subject'),
                    'type' => 'Checkbox',
                    'state' => 'static',
                    'more' => 10,
                    'display_count' => false,
                    'mode' => 'button',
                ],
                'dcterms:type' => [
                    'field' => 'dcterms:type',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                    'label' => $translate('Type'),
                    'type' => 'Checkbox',
                    'state' => 'static',
                    'more' => 10,
                    'display_count' => false,
                    'mode' => 'button',
                ],
                'author' => [
                    'field' => 'author',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                    'label' => $translate('Author'),
                    'type' => 'Checkbox',
                    'order' => 'default',
                    'more' => 10,
                    'display_count' => false,
                    'mode' => 'button',
                ],
                'dcterms:language' => [
                    'field' => 'dcterms:language',
                    'language_site' => '',
                    'languages' => [],
                    'order' => 'default',
                    'limit' => 100,
                    'label' => $translate('Language'),
                    'type' => 'Checkbox',
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
