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
];
