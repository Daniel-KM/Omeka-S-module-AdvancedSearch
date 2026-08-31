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
        // a form is a query configurator. Each filter may set "display_in"
        // ("advanced" by default, "simple" or "both") and stores its specific
        // settings in "options" and its html attributes in "attributes".
        'form' => [
            'button_submit' => true,
            'label_submit' => $translate('Search'),
            'button_reset' => false,
            'label_reset' => $translate('Reset'),
            'attribute_form' => false,

            'filters' => [
                // Ordered list of specific filters.
                'item_set_id' => [
                    'field' => 'item_set_id',
                    // A end user doesn't know "item set", but "collection".
                    'label' => $translate('Collection'), // @translate
                    'type' => 'Select',
                    'limit' => 100,
                    'options' => [
                        'multiple' => true,
                    ],
                ],
                'resource_class_id' => [
                    'field' => 'resource_class_id',
                    'label' => $translate('Class'),
                    'type' => 'Select',
                    'limit' => 100,
                    'options' => [
                        'multiple' => true,
                        'value_layout' => 'flat',
                    ],
                ],
                'resource_template_id' => [
                    'field' => 'resource_template_id',
                    'label' => $translate('Template'),
                    'type' => 'Radio',
                    'limit' => 100,
                ],
                'title' => [
                    'field' => 'title',
                    'label' => $translate('Title'),
                    'options' => [
                        'autosuggest' => true,
                    ],
                ],
                'author' => [
                    'field' => 'author',
                    'label' => $translate('Author'),
                    'type' => 'Select',
                    'limit' => 100,
                ],
                'dcterms_subject' => [
                    'field' => 'dcterms:subject',
                    'label' => $translate('Subject'),
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
                // The advanced filter is a filter like the others: its
                // settings are stored in its options.
                'advanced' => [
                    'field' => 'advanced',
                    'label' => $translate('Filters'),
                    'type' => 'Advanced',
                    'options' => [
                        'default_number' => 1,
                        'max_number' => 10,
                        'field_elements' => [
                            'joiner',
                            'joiner_not',
                            'operator',
                        ],
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
                // Other available filters.
                /*
                // The record or full text button next to the main field.
                'rft' => [
                    'field' => 'rft',
                    'type' => 'Rft',
                    'options' => [
                        'rft' => 'fulltext_checkbox',
                    ],
                ],
                'dcterms_created' => [
                    'field' => 'dcterms:created',
                    'label' => $translate('Number'),
                    'type' => 'Number',
                ],
                'dcterms_audience' => [
                    'field' => 'dcterms:audience',
                    'label' => $translate('Audience'),
                    'type' => 'Checkbox',
                    'options' => [
                        'unchecked_value' => 'no',
                        'checked_value' => 'yes',
                    ],
                ],
                */
            ],
        ],

        'results' => [
            'label_default' => $translate('Search'), // @translate
            'label_results' => $translate('Search results'), // @translate
            'label_no_results' => $translate('No results'), // @translate
            'by_resource_type' => false,
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
            'pagination_per_page' => 0,
            'per_page_list' => [
                10 => $translate('Results by 10'), // @translate
                // This is the default for Omeka.
                25 => $translate('Results by 25'), // @translate
                50 => $translate('Results by 50'), // @translate
                100 => $translate('Results by 100'), // @translate
            ],
            'label_sort' => $translate('Sort'),
            // The sort selector is a flat list "name => label".
            'sort_list' => [
                'relevance desc' => $translate('Relevance'), // @translate
                'relevance asc' => $translate('Relevance (inversed)'), // @translate
                'dcterms:title asc' => $translate('Title'), // @translate
                'dcterms:title desc' => $translate('Title (from z to a)'), // @translate
                'dcterms:date asc' => $translate('Date'), // @translate
                'dcterms:date desc' => $translate('Date (most recent first)'), // @translate
            ],
            // The properties displayed for each result ("term => label",
            // with the pseudo-properties "header" and "body"), and the
            // optional distinct list for the grid mode.
            // 'properties' => [],
            // 'properties_grid' => [],
        ],

        'facet' => [
            'label' => $translate('Facets'),
            'label_no_facets' => $translate('No facets'),
            'position' => 'before',
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
            // The mode is global: it is added to each facet at reading.
            'facets' => [
                'item_set_id' => [
                    'field' => 'item_set_id',
                    'label' => $translate('Collection'),
                    'type' => 'Checkbox',
                    'order' => 'default',
                    'limit' => 100,
                    'state' => 'static',
                    'more' => 10,
                ],
                'resource_class_id' => [
                    'field' => 'resource_class_id',
                    'label' => $translate('Class'),
                    'type' => 'Checkbox',
                    'order' => 'default',
                    'limit' => 100,
                    'state' => 'static',
                    'more' => 10,
                ],
                'date' => [
                    'field' => 'date',
                    'label' => $translate('Date'),
                    'type' => 'RangeDouble',
                    'state' => 'static',
                    'first_digits' => true,
                    'min' => 1454,
                    'max' => 2025,
                ],
                'dcterms:subject' => [
                    'field' => 'dcterms:subject',
                    'label' => $translate('Subject'),
                    'type' => 'Checkbox',
                    'order' => 'default',
                    'limit' => 100,
                    'state' => 'static',
                    'more' => 10,
                ],
                'dcterms:type' => [
                    'field' => 'dcterms:type',
                    'label' => $translate('Type'),
                    'type' => 'Checkbox',
                    'order' => 'default',
                    'limit' => 100,
                    'state' => 'static',
                    'more' => 10,
                ],
                'author' => [
                    'field' => 'author',
                    'label' => $translate('Author'),
                    'type' => 'Checkbox',
                    'order' => 'default',
                    'limit' => 100,
                    'state' => 'static',
                    'more' => 10,
                ],
                'dcterms:language' => [
                    'field' => 'dcterms:language',
                    'label' => $translate('Language'),
                    'type' => 'Checkbox',
                    'order' => 'default',
                    'limit' => 100,
                    'state' => 'static',
                    'more' => 10,
                ],
            ],
        ],
    ],
    'o:created' => null,
    'o:modified' => null,
];
