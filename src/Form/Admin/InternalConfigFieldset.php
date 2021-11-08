<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

use AdvancedSearch\Form\Element as AdvancedSearchElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class InternalConfigFieldset extends Fieldset
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'default_search_partial_word',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Partial word search for main field (instead of standard full text search)', // @translate
                    'infos' => 'Currently, this mode does not allow to exclude properties for the main search field.', // @translate
                ],
                'attributes' => [
                    'id' => 'default_search_partial_word',
                ],
            ])
            ->add([
                'name' => 'multifields',
                'type' => AdvancedSearchElement\DataTextarea::class,
                'options' => [
                    'label' => 'Multi-fields (filters and facets)', // @translate
                    'info' => 'List of fields that refers to multiple properties, formatted "name = label = property, property… The name must not be a property term or a reserved keyword.', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                    'data_keys' => [
                        'name',
                        'label',
                        'fields',
                    ],
                    'data_array_keys' => [
                        'fields' => ',',
                    ]
                ],
                'attributes' => [
                    'id' => 'multifields',
                    'placeholder' => 'author = Author = dcterms:creator, dcterms:contributor
title = Title = dcterms:title, dcterms:alternative
date = Date = dcterms:date, dcterms:created, dcterms:issued
',
                    'rows' => 12,
                ],
            ])
        ;
    }
}
