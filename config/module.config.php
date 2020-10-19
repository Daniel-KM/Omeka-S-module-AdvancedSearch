<?php declare(strict_types=1);

namespace AdvancedSearchPlus;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'mediaTypeSelect' => Service\ViewHelper\MediaTypeSelectFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            'OptionalMultiCheckbox' => Form\Element\OptionalMultiCheckbox::class,
        ],
        'factories' => [
            Form\Element\MediaTypeSelect::class => Service\Form\Element\MediaTypeSelectFactory::class,
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'advancedsearchplus' => [
        'settings' => [
            'advancedsearchplus_restrict_used_terms' => true,
        ],
        'site_settings' => [
            'advancedsearchplus_restrict_used_terms' => true,
            'advancedsearchplus_search_fields' => [
                'common/advanced-search/fulltext',
                'common/advanced-search/properties',
                'common/advanced-search/resource-class',
                'common/advanced-search/item-sets',
                'common/advanced-search/date-time',
                'common/advanced-search/has-media',
                'common/advanced-search/media-type',
                'common/advanced-search/data-type-geography',
                'common/numeric-data-types-advanced-search',
            ],
        ],
        // This is the default list of all possible fields, allowing other modules
        // to complete it. The partials that are not set in merged Config (included
        // config/local.config.php) are not managed by this module.
        'search_fields' => [
            // From view/common/advanced-search'.
            'common/advanced-search/fulltext' => ['label' => 'Full text'], // @translate
            'common/advanced-search/properties' => ['label' => 'Properties'], // @translate
            'common/advanced-search/resource-class' => ['label' => 'Classes'], // @translate
            'common/advanced-search/resource-template' => ['label' => 'Templates', 'default' => false], // @translate
            'common/advanced-search/item-sets' => ['label' => 'Item sets'], // @translate
            'common/advanced-search/owner' => ['label' => 'Owner', 'default' => false],
            // This partial is managed separately by a core option.
            // 'common/advanced-search/resource-template-restrict' => ['label' => 'Resource template restrict'],
            // From module advanced search plus.
            'common/advanced-search/date-time' => ['label' => 'Date time'], // @translate
            'common/advanced-search/has-media' => ['label' => 'Has media'], // @translate
            'common/advanced-search/has-original' => ['label' => 'Has original file', 'default' => false], // @translate
            'common/advanced-search/has-thumbnails' => ['label' => 'Has thumbnails', 'default' => false], // @translate
            'common/advanced-search/media-type' => ['label' => 'Media types'], // @translate
            'common/advanced-search/visibility' => ['label' => 'Visibility', 'default' => false], // @translate
            // From module data type geometry.
            'common/advanced-search/data-type-geography' => ['module' => 'DataTypeGeometry', 'label' => 'Geography', 'default' => false], // @translate
            // From module numeric data type.
            'common/numeric-data-types-advanced-search' => ['module' => 'NumericDataTypes', 'label' => 'Numeric', 'default' => false], // @translate
        ],
    ],
];
