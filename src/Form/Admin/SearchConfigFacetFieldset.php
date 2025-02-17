<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\InputFilter\InputFilterProviderInterface;

class SearchConfigFacetFieldset extends Fieldset implements InputFilterProviderInterface
{
    public function init(): void
    {
        // These fields may be overridden by the available fields.
        $availableFields = $this->getAvailableFacetFields();

        $this
            ->setAttribute('id', 'form-search-config-facet')
            ->setAttribute('class', 'form-fieldset-element form-search-config-facet')
            ->setName('facet')

            ->add([
                'name' => 'field',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Field', // @translate
                    'info' => 'The field is an index available in the search engine. The internal search engine supports property terms and aggregated fields (date, author, etc).', // @translate
                    'value_options' => $availableFields,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'form_facet_field',
                    'required' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Set field or index…', // @translate
                ],
            ])

            ->add([
                'name' => 'language_site',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Limit languages of facets', // @ŧranslate
                    'value_options' => [
                        '' => 'No limit', // @translate
                        'site' => 'Limit facets to site language or empty language', // @ŧranslate
                        'site_setting' => 'Use site setting "Filter values based on site locale"', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_language_site',
                    'required' => false,
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'languages',
                'type' => CommonElement\ArrayText::class,
                'options' => [
                    'label' => 'Limit facets to specific languages', // @translate
                    'info' => <<<'TXT'
                        Generally, facets are translated in the view, but in some cases, facet values may be translated directly in a multivalued property. Use "|" to separate multiple languages. Use a trailing "|" for values without language. When fields with languages (like subjects) and fields without language (like date) are facets, the empty language must be set to get results.
                        TXT, // @translate
                    'value_separator' => '|',
                ],
                'attributes' => [
                    'id' => 'facet_languages',
                    'placeholder' => 'fra|way|apy|',
                ],
            ])

            ->add([
                'name' => 'order',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Order', // @translate
                    'value_options' => [
                        'alphabetic asc' => 'Alphabetic (default)', // @ŧranslate
                        'alphabetic desc' => 'Alphabetic descendant', // @ŧranslate
                        'total desc' => 'Total', // @ŧranslate
                        'total asc' => 'Total ascendant', // @ŧranslate
                        'values asc' => 'Values (listed below)', // @ŧranslate
                        'values desc' => 'Values descendant', // @ŧranslate
                    ],
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'facet_order',
                    'multiple' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select order…', // @translate
                ],
            ])
            ->add([
                'name' => 'limit',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Maximum number of facets', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_limit',
                    'required' => false,
                    'value' => '100',
                ],
            ])

            ->add([
                'name' => 'label',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label', // @translate
                ],
                'attributes' => [
                    'id' => 'form_facet_label',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'type',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Input type', // @translate
                    'info' => 'The type of facet that will be displayed in the search page.', // @translate
                    // TODO Convert documentation into help. See application/view/common/form-row.phtml
                    'documentation' => nl2br(<<<'MARKDOWN'
                        #"></a><div class="field-description no-link">
                        - Input types may be Checkbox (default), RangeDouble, Select, SelectRange, Thesaurus, Tree and specific templates for mode "direct" if wanted.
                        - For "RangeDouble" and "SelectRange", the minimum and maximum should be set as "min" and "max", and "step" too.
                        - With type "Thesaurus", the option "thesaurus" should be set with the id. It requires the module Thesaurus.
                        - "Tree" can be used for item sets when module Item Sets Tree is enabled and data indexed recursively.
                        </div><a href="#
                        MARKDOWN), // @translate
                    /** @see \AdvancedSearch\Form\MainSearchForm::init() */
                    'value_options' => [
                        'Checkbox' => 'Checkbox (default)', // @translate
                        'Link' => 'Link (fake checkbox for mode "direct")', // @translate
                        'RangeDouble' => 'Slider for a range of values', // @translate
                        // A space is added to avoid an issue with translation.
                        'Select' => 'Select ', // @translate
                        'SelectRange' => 'Select range', // @translate
                        'modules' => [
                            'label' => 'Modules', // @translate
                            'options' => [
                                'Tree' => 'Item sets tree', // @translate
                                'TreeLink' => 'Item sets tree link (fake checkbox)', // @translate
                                'Thesaurus' => 'Thesaurus', // @translate
                                'ThesaurusLink' => 'Thesaurus link (fake checkbox)', // @translate
                            ],
                        ],
                    ],
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'form_facet_type',
                    'class' => 'chosen-select',
                    'required' => false,
                    'data-placeholder' => 'Set facet type…', // @translate
                ],
            ])

            ->add([
                'name' => 'state',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Display of facets', // @translate
                    'value_options' => [
                        'static' => 'Static', // @ŧranslate
                        'expand' => 'Expanded', // @ŧranslate
                        'collapse' => 'Collapsed', // @ŧranslate
                        'collapse_unless_set' => 'Collapsed unless a facet is set', // @ŧranslate
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_state',
                    'required' => false,
                    'value' => 'static',
                ],
            ])
            ->add([
                'name' => 'more',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Number of facets to display on load', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_more',
                    'required' => false,
                    'value' => '10',
                ],
            ])
            ->add([
                'name' => 'display_count',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display count', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_display_count',
                    'required' => false,
                ],
            ])
            ->add([
                'type' => CommonElement\IniTextarea::class,
                'name' => 'options',
                'options' => [
                    'label' => 'Specific options', // @translate
                    'info' => <<<'HTML'
                        List of specific options, in ini format, for example:
                        `min = 1454`,
                        `thesaurus =  151`,
                        `languages = "fra|way|apa|"`,
                        `data_types[] = "valuesuggest:idref:person"`,
                        `main_types = "resource",
                        `values[] = "Alpha"`.
                        HTML, // @translate
                    'ini_typed_mode' => true,
                ],
                'attributes' => [
                    'id' => 'form_facet_options',
                    'required' => false,
                    'placeholder' => '',
                ],
            ])
            ->add([
                'type' => CommonElement\IniTextarea::class,
                'name' => 'attributes',
                'info' => 'List of specific attributes, in ini format.', // @translate
                'options' => [
                    'label' => 'Html attributes', // @translate
                    'ini_typed_mode' => true,
                ],
                'attributes' => [
                    'id' => 'form_facet_attributes',
                    'required' => false,
                    'placeholder' => '',
                ],
            ])

            ->add([
                'name' => 'minus',
                'type' => Element\Button::class,
                'options' => [
                    'label' => ' ',
                    'label_options' => [
                        'disable_html_escape' => true,
                    ],
                    'label_attributes' => [
                        'class' => 'config-fieldset-action-label',
                    ],
                ],
                'attributes' => [
                    // Don't use o-icon-delete.
                    'class' => 'config-fieldset-action config-fieldset-minus fa fa-minus remove-value button',
                    'aria-label' => 'Remove this filter', // @translate
                ],
            ])
            ->add([
                'name' => 'up',
                'type' => Element\Button::class,
                'options' => [
                    'label' => ' ',
                    'label_options' => [
                        'disable_html_escape' => true,
                    ],
                    'label_attributes' => [
                        'class' => 'config-fieldset-action-label',
                    ],
                ],
                'attributes' => [
                    // Don't use o-icon-delete.
                    'class' => 'config-fieldset-action config-fieldset-up fa fa-arrow-up button',
                    'aria-label' => 'Move this facet up', // @translate
                ],
            ])
            ->add([
                'name' => 'down',
                'type' => Element\Button::class,
                'options' => [
                    'label' => ' ',
                    'label_options' => [
                        'disable_html_escape' => true,
                    ],
                    'label_attributes' => [
                        'class' => 'config-fieldset-action-label',
                    ],
                ],
                'attributes' => [
                    // Don't use o-icon-delete.
                    'class' => 'config-fieldset-action config-fieldset-down fa fa-arrow-down button',
                    'aria-label' => 'Move this facet down', // @translate
                ],
            ])
        ;
    }

    /**
     * This method is required when a fieldset is used as a collection, else the
     * data are not filtered and not returned with getData().
     *
     * {@inheritDoc}
     * @see \Laminas\InputFilter\InputFilterProviderInterface::getInputFilterSpecification()
     */
    public function getInputFilterSpecification()
    {
        return [
            'field' => [
                'required' => false,
            ],
            'language_site' => [
                'required' => false,
            ],
            'type' => [
                'required' => false,
            ],
        ];
    }

    protected function getAvailableFacetFields(): array
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');
        $engineAdapter = $searchConfig ? $searchConfig->engineAdapter() : null;
        return $engineAdapter
            ? $engineAdapter->getAvailableFacetFieldsForSelect()
            : [];
    }
}
