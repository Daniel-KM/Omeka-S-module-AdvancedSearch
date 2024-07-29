<?php declare(strict_types=1);

/**
 * Generic config for the internal engine (sql).
 *
 * When created, it can be modified in the admin board.
 *
 * The main point is the multi-fields option.
 *
 * Properties are grouped according to standard Dublin Core refinements.
 * Accrual and instructionnal are grouped together according to rare usage.
 *
 * The bibo terms are appended according to sub property rules.
 * Some bibo terms are not included.
 * @see https://github.com/structureddynamics/Bibliographic-Ontology-BIBO/blob/master/bibo.owl
 *
 * Some special groups are added for common usage.
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
            'default_search_partial_word' => false,
            'multifields' => [
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
                'rightsHolder' => [
                    'name' => 'rightsHolder',
                    'label' => 'Rights holder',
                    'fields' => [
                        'dcterms:rightsHolder',
                        'bibo:owner',
                    ],
                ],

                // These groups don't follow rdf rules.

                // Group similar and rarely used unrefined data.
                'accrualAndInstructional' => [
                    'name' => 'accrualAndInstructional',
                    'label' => 'Accrual and instructional metadata',
                    'fields' => [
                        'dcterms:accrualMethod',
                        'dcterms:accrualPeriodicity',
                        'dcterms:accrualPolicy',
                        'dcterms:instructionalMethod',
                    ],
                ],
                'bibliographicData' => [
                    'name' => 'bibliographicData',
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
            ],
        ],
    ],
    'o:created' => null,
    'o:modified' => null,
];
