<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

use Common\Form\Element as CommonElement;
use Omeka\Form\Element as OmekaElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\InputFilter\InputFilterProviderInterface;

class SearchConfigFacetFieldset extends Fieldset implements InputFilterProviderInterface
{
    use TraitInputTypeOptions;

    /**
     * The types of facet by group of settings, used by the form and to clean
     * the settings on save.
     */
    const TYPES_LIST = ['Checkbox', 'CheckboxFilter', 'Select', 'Tree', 'Thesaurus'];
    const TYPES_VALUES = ['Checkbox', 'CheckboxFilter', 'Select', 'Tree', 'Thesaurus', 'HasValue'];
    const TYPES_LINK = ['Checkbox', 'Tree', 'Thesaurus'];
    const TYPES_SLIDER = ['RangeDouble'];

    /**
     * The settings by group, cleaned when the type does not use them.
     */
    const SETTINGS_LIST = ['language_site', 'languages', 'order', 'limit', 'state', 'paginate', 'more', 'per_page'];
    const SETTINGS_VALUES = ['value_labels_table', 'value_labels', 'display_count'];
    const SETTINGS_LINK = ['as_link'];
    const SETTINGS_SLIDER = ['field_end', 'scale_mode', 'scale_breakpoints', 'scale_show_ticks'];
    const SETTINGS_BOUNDS = ['min', 'max', 'step', 'first_digits'];
    const SETTINGS_THESAURUS = ['thesaurus'];
    const TYPES_BOUNDS = ['RangeDouble', 'SelectRange'];

    public function init(): void
    {
        /** @var \Laminas\I18n\Translator\TranslatorInterface $translator */
        $translator = $this->getOption('translator');
        $tr = fn (string $string): string => $translator ? $translator->translate($string) : $string;
        // These fields may be overridden by the available fields.
        $availableFacetFields = $this->getAvailableFacetFields();

        // Field order is aligned with SearchConfigFilterFieldset:

        $this
            ->setAttribute('id', 'search-config-facet-form')
            ->setAttribute('class', 'form-fieldset-element form-search-config-facet')
            ->setName('facet')

            ->add([
                'name' => 'field',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Field', // @translate
                    'info' => 'The field is an index available in the search engine. The internal search engine supports property terms and aggregated fields (date, author, etc).', // @translate
                    'value_options' => $availableFacetFields,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'form_facet_field',
                    'data-common' => '1',
                    'required' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Set field or index…', // @translate
                ],
            ])
            ->add([
                'name' => 'field_end',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Field (interval end)', // @translate
                    'info' => 'For RangeDouble facets on uncertain dates: when set, "Field" is used as the interval start and this field as the interval end. Search matches resources whose [start, end] overlaps the queried [from, to].', // @translate
                    'value_options' => $availableFacetFields,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'form_facet_field_end',
                    'data-filter-types' => implode(' ', self::TYPES_SLIDER),
                    'data-common' => '1',
                    'required' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Set interval end field…', // @translate
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
                    'data-common' => '1',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'thesaurus',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'label' => 'Id of the thesaurus', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_thesaurus',
                    'data-filter-types' => 'Thesaurus',
                    'data-advanced-section' => $tr('Values'), // @translate
                    'required' => false,
                    'min' => '0',
                ],
            ])
            ->add([
                'name' => 'value_labels_table',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Value labels: table source', // @translate
                    'info' => 'Optional slug or id of a table (module Table) used as the base code / label mapping. Inline "Value labels" below override the table entries when both are defined.', // @translate
                ],
                'attributes' => [
                    'id' => 'form_facet_value_labels_table',
                    'data-advanced-section' => $tr('Values'), // @translate
                    'data-filter-types' => implode(' ', self::TYPES_VALUES),
                    'required' => false,
                    'placeholder' => 'my-table-slug',
                ],
            ])
            ->add([
                'name' => 'value_labels',
                'type' => CommonElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Value labels', // @translate
                    'info' => 'One pair per line: indexed_value = displayed_label. Replaces the raw value in facet items, "see more" buttons and active facets. Mainly useful for boolean fields (e.g. 1 = Only with image / 0 = Without image) and small enumerations. Overrides the table source above for the listed codes.', // @translate
                    'as_key_value' => true,
                    'pairs_editor' => [
                        'key_label' => $tr('Value'), // @translate
                        'value_label' => $tr('Label'), // @translate
                        'sortable' => false,
                    ],
                ],
                'attributes' => [
                    'id' => 'form_facet_value_labels',
                    'data-advanced-section' => $tr('Values'), // @translate
                    'data-filter-types' => implode(' ', self::TYPES_VALUES),
                    'required' => false,
                    'rows' => 3,
                    'placeholder' => <<<'TXT'
                        1 = Only with image
                        0 = Without image
                        TXT,
                ],
            ])
            ->add([
                'name' => 'type',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Input type', // @translate
                    'info' => 'The type of facet displayed in the page of results. Each type has its own settings below; the preview shows what the visitor will see.', // @translate
                    /** @see \AdvancedSearch\Form\MainSearchForm::init() */
                    'value_options' => $this->inputTypeOptions([
                        'Checkbox' => 'Checkbox (default)', // @translate
                        'CheckboxFilter' => 'Checkbox with filter input', // @translate
                        'HasValue' => 'Boolean (has a value / has no value)', // @translate
                        'RangeDouble' => 'Slider for a range of values', // @translate
                        // A space is added to avoid an issue with translation.
                        'Select' => 'Select ', // @translate
                        'SelectRange' => 'Select range', // @translate
                        'modules' => [
                            'label' => 'Modules', // @translate
                            'options' => [
                                'Tree' => 'Item sets tree', // @translate
                                'Thesaurus' => 'Thesaurus', // @translate
                            ],
                        ],
                    ]),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'form_facet_type',
                    'data-common' => '1',
                    'data-type-default' => 'Checkbox',
                    'class' => 'chosen-select',
                    'required' => false,
                    'data-placeholder' => 'Set facet type…', // @translate
                ],
            ])

            ->add([
                'name' => 'language_site',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Limit languages of facets (internal querier)', // @translate
                    'value_options' => [
                        '' => 'No limit', // @translate
                        'site' => 'Limit facets to site language or empty language', // @translate
                        'site_setting' => 'Use site setting "Filter values based on site locale"', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_language_site',
                    'data-advanced-section' => $tr('Values'), // @translate
                    'data-filter-types' => implode(' ', self::TYPES_LIST),
                    'required' => false,
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'languages',
                'type' => CommonElement\ArrayText::class,
                'options' => [
                    'label' => 'Limit facets to specific languages (internal querier)', // @translate
                    'info' => <<<'TXT'
                        Use "|" to separate multiple languages. Use a trailing "|" for values without language. When fields with languages (like subjects) and fields without language (like date) are facets, the empty language must be set to get results.
                        TXT, // @translate
                    'value_separator' => '|',
                ],
                'attributes' => [
                    'id' => 'facet_languages',
                    'data-advanced-section' => $tr('Values'), // @translate
                    'data-filter-types' => implode(' ', self::TYPES_LIST),
                    'placeholder' => 'fra|way|apy|',
                ],
            ])
            ->add([
                'name' => 'order',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Order', // @translate
                    'value_options' => [
                        'alphabetic asc' => 'Alphabetic (default)', // @translate
                        'alphabetic desc' => 'Alphabetic descendant', // @translate
                        'total desc' => 'Total', // @translate
                        'total asc' => 'Total ascendant', // @translate
                        'total_alpha desc' => 'Total then alphabetic for hidden values', // @translate
                        'values asc' => 'Values (listed below)', // @translate
                        'values desc' => 'Values descendant', // @translate
                    ],
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'facet_order',
                    'data-filter-types' => implode(' ', self::TYPES_LIST),
                    'data-common' => '1',
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
                    'data-filter-types' => implode(' ', self::TYPES_LIST),
                    'data-common' => '1',
                    'required' => false,
                    'value' => '100',
                ],
            ])

            // Facet-specific fields.

            ->add([
                'name' => 'state',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Display of facets', // @translate
                    'value_options' => [
                        'static' => 'Static', // @translate
                        'expand' => 'Expanded', // @translate
                        'collapse' => 'Collapsed', // @translate
                        'collapse_unless_set' => 'Collapsed unless a facet is set', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_state',
                    'data-advanced-section' => $tr('Display'), // @translate
                    'data-filter-types' => implode(' ', self::TYPES_LIST),
                    'required' => false,
                    'value' => 'static',
                ],
            ])
            ->add([
                'name' => 'paginate',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Enable pagination', // @translate
                    'info' => 'Paginate the facet values with a per page navigation, instead of a "see more" button. Uses "per page" as the page size and ignores "display on load".', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_paginate',
                    'data-advanced-section' => $tr('Display'), // @translate
                    'data-filter-types' => implode(' ', self::TYPES_LIST),
                ],
            ])
            ->add([
                'name' => 'more',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Values displayed on load', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_more',
                    'data-advanced-section' => $tr('Display'), // @translate
                    'data-inline' => 'pages',
                    'data-filter-types' => implode(' ', self::TYPES_LIST),
                    'required' => false,
                    'value' => '10',
                ],
            ])
            ->add([
                'name' => 'per_page',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Values per page (with pagination)', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_per_page',
                    'data-advanced-section' => $tr('Display'), // @translate
                    'data-inline' => 'pages',
                    'data-filter-types' => implode(' ', self::TYPES_LIST),
                    'required' => false,
                    'value' => '10',
                    'min' => 0,
                ],
            ])
            ->add([
                'name' => 'as_link',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display the values as links', // @translate
                    'info' => 'The values are simple links instead of checkboxes: the facet applies on click, without button.', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_as_link',
                    'data-filter-types' => implode(' ', self::TYPES_LINK),
                    'data-advanced-section' => $tr('Display'), // @translate
                    'required' => false,
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
                    'data-advanced-section' => $tr('Display'), // @translate
                    'data-filter-types' => implode(' ', self::TYPES_VALUES),
                    'required' => false,
                ],
            ])

            ->add([
                'name' => 'boolean_filter',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Boolean buckets (fields ending with "_b")', // @translate
                    'info' => 'Filter Solr facet buckets for boolean fields. Default shows all returned buckets (typically Yes and No).', // @translate
                    'value_options' => [
                        '' => 'Show all buckets', // @translate
                        'truthy_only' => 'Show only "Yes" bucket', // @translate
                        'falsy_only' => 'Show only "No" bucket', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_boolean_filter',
                    'data-advanced-section' => $tr('Values'), // @translate
                    'required' => false,
                    'value' => '',
                ],
            ])

            // Slider scale (RangeDouble and SelectRange only). Mode "linear" is
            // the default and ignores breakpoints. Mode "piecewise" requires at
            // least two breakpoints with values and positions strictly
            // increasing from 0 to 100.
            ->add([
                'name' => 'min',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'label' => 'Minimum', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_min',
                    'data-filter-types' => 'RangeDouble SelectRange',
                    'data-advanced-section' => $tr('Slider'), // @translate
                    'data-inline' => 'bounds',
                    'required' => false,
                    'step' => 'any',
                ],
            ])
            ->add([
                'name' => 'max',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'label' => 'Maximum', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_max',
                    'data-filter-types' => 'RangeDouble SelectRange',
                    'data-advanced-section' => $tr('Slider'), // @translate
                    'data-inline' => 'bounds',
                    'required' => false,
                    'step' => 'any',
                ],
            ])
            ->add([
                'name' => 'step',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'label' => 'Step', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_step',
                    'data-filter-types' => 'RangeDouble SelectRange',
                    'data-advanced-section' => $tr('Slider'), // @translate
                    'data-inline' => 'bounds',
                    'required' => false,
                    'step' => 'any',
                    'min' => '0',
                ],
            ])
            ->add([
                'name' => 'first_digits',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Extract the first digits of the values (year of a date)', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_first_digits',
                    'data-filter-types' => 'RangeDouble SelectRange',
                    'data-advanced-section' => $tr('Slider'), // @translate
                    'required' => false,
                    'value' => '1',
                ],
            ])
            ->add([
                'name' => 'scale_mode',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Slider scale (RangeDouble)', // @translate
                    'value_options' => [
                        'linear' => 'Linear', // @translate
                        'log' => 'Logarithmic', // @translate
                        'piecewise' => 'Piecewise (with breakpoints)', // @translate
                        'auto' => 'Auto (quartiles from data)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'form_facet_scale_mode',
                    'data-advanced-section' => $tr('Slider'), // @translate
                    'data-filter-types' => implode(' ', self::TYPES_SLIDER),
                    'value' => 'linear',
                ],
            ])
            ->add([
                'name' => 'scale_breakpoints',
                'type' => CommonElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Scale breakpoints', // @translate
                    'info' => 'One pair per line: value = position. Position is a percentage between 0 and 100.', // @translate
                    'as_key_value' => true,
                    'pairs_editor' => [
                        'key_label' => $tr('Value'), // @translate
                        'value_label' => $tr('Position (%)'), // @translate
                        'value_type' => 'number',
                    ],
                ],
                'attributes' => [
                    'id' => 'form_facet_scale_breakpoints',
                    'data-advanced-section' => $tr('Slider'), // @translate
                    'data-filter-types' => implode(' ', self::TYPES_SLIDER),
                    'required' => false,
                    'rows' => 5,
                    'placeholder' => <<<TXT
                        min = 0
                        1 = 20
                        1789 = 50
                        max = 100
                        TXT,
                ],
            ])
            ->add([
                'name' => 'scale_show_ticks',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display ticks at breakpoints', // @translate
                ],
                'attributes' => [
                    'id' => 'form_facet_scale_show_ticks',
                    'data-advanced-section' => $tr('Slider'), // @translate
                    'data-filter-types' => implode(' ', self::TYPES_SLIDER),
                ],
            ])

            // Common fields continued (same order as filters).

            ->add([
                'type' => CommonElement\IniTextarea::class,
                'name' => 'options',
                'options' => [
                    'label' => 'Options', // @translate
                    'info' => <<<'HTML'
                        List of specific options, in ini format, for example:
                        `thesaurus = 151`,
                        `languages = "fra|way|apa|"`,
                        `data_types[] = "valuesuggest:idref:person"`,
                        `main_types = "resource"`,
                        `values[] = "Alpha"`,
                        `first_digits = false`.
                        Note: "min", "max", "step" should be set in "Html attributes".
                        HTML, // @translate
                    'ini_typed_mode' => true,
                    'pairs_editor' => [
                        'key_label' => $tr('Option'), // @translate
                        'value_label' => $tr('Value'), // @translate
                        'sortable' => false,
                    ],
                ],
                'attributes' => [
                    'id' => 'form_facet_options',
                    'data-advanced-section' => $tr('Advanced'), // @translate
                    'required' => false,
                    'placeholder' => '',
                ],
            ])
            ->add([
                'type' => CommonElement\ArrayTextarea::class,
                'name' => 'attributes',
                'options' => [
                    'label' => 'Html attributes', // @translate
                    'info' => 'Rarely used attributes to add to the input field, for example `class = "my-specific-class"`, or placeholder, data, etc. A key set here takes precedence over the dedicated fields above.', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                    'pairs_editor' => [
                        'key_label' => $tr('Attribute'), // @translate
                        'value_label' => $tr('Value'), // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'form_facet_attributes',
                    'data-advanced-section' => $tr('Advanced'), // @translate
                    'required' => false,
                    'placeholder' => '',
                ],
            ])

            // Action buttons.
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
            'field_end' => [
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
