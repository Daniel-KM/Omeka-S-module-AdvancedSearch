<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\InputFilter\InputFilterProviderInterface;
use Omeka\Form\Element as OmekaElement;

class SearchConfigFilterFieldset extends Fieldset implements InputFilterProviderInterface
{
    /**
     * The types of filter by group of settings, used by the form and to clean
     * the settings on save.
     */
    const TYPES_LIST = ['Select', 'SelectFlat', 'SelectGroup', 'MultiSelect', 'MultiSelectFlat', 'MultiSelectGroup', 'Radio', 'Checkbox', 'MultiCheckbox', 'Tree', 'Thesaurus'];
    const TYPES_RANGE = ['Range', 'RangeDouble'];
    const TYPES_SLIDER = ['RangeDouble'];

    /**
     * The settings by group, cleaned when the type does not use them.
     */
    const SETTINGS_LIST = ['value_labels_table', 'value_labels', 'language_site', 'languages', 'order', 'limit'];
    const SETTINGS_RANGE = ['field_end'];
    const SETTINGS_SLIDER = ['scale_mode', 'scale_breakpoints', 'scale_show_ticks'];
    const SETTINGS_ADVANCED = ['default_number', 'max_number', 'field_elements', 'field_joiner', 'field_joiner_not', 'field_operator', 'field_operators', 'field_value_autosuggest', 'fields'];

    public function init(): void
    {
        // These fields may be overridden by the available fields.
        $availableFields = $this->getAvailableFields();

        // Field order is aligned with SearchConfigFacetFieldset:

        $this
            ->setAttribute('id', 'search-config-filter-form')
            ->setAttribute('class', 'form-fieldset-element form-search-config-filter')
            ->setName('filter')

            ->add([
                'name' => 'name',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Name (alphanumeric)', // @translate
                    'info' => 'The technical key of the filter, used in the url of the query. Leave empty to derive it from the field; set it to distinguish two filters on the same field, or for a short url.', // @translate
                ],
                'attributes' => [
                    'id' => 'form_filter_name',
                    'required' => false,
                    'pattern' => '[a-zA-Z0-9_:\-]+',
                    'data-filter-types-not' => 'Advanced',
                ],
            ])
            ->add([
                'name' => 'field',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Field', // @translate
                    'info' => 'The field is an index available in the search engine. The internal search engine supports property terms and aggregated fields (date, author, etc).', // @translate
                    'value_options' => [
                        'advanced' => 'Advanced filter', // @translate
                    ] + $availableFields,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'form_filter_field',
                    'data-common' => '1',
                    'data-filter-types-not' => 'Advanced',
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
                    'info' => 'For Range/RangeDouble filters on uncertain dates: when set, "Field" is used as the interval start and this field as the interval end. Search matches resources whose [start, end] overlaps the queried [from, to].', // @translate
                    'value_options' => $availableFields,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'form_filter_field_end',
                    'data-common' => '1',
                    'data-filter-types' => implode(' ', self::TYPES_RANGE),
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
                    'info' => 'The label displayed to the visitor. Leave empty to use the label of the field.', // @translate
                ],
                'attributes' => [
                    'id' => 'form_filter_label',
                    'data-common' => '1',
                    'required' => false,
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
                    'id' => 'form_filter_value_labels_table',
                    'data-filter-types' => implode(' ', self::TYPES_LIST),
                    'required' => false,
                    'placeholder' => 'my-table-slug',
                ],
            ])
            ->add([
                'name' => 'value_labels',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Value labels', // @translate
                    'info' => 'One pair per line: indexed_value = displayed_label. Replaces the raw value in select/radio/checkbox options and in active filter chips. Mainly useful for boolean fields (e.g. 1 = Only with image / 0 = Without image) and small enumerations. Overrides the table source above for the listed codes.', // @translate
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'form_filter_value_labels',
                    'data-filter-types' => implode(' ', self::TYPES_LIST),
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
                    'info' => 'The type of filter that will be displayed in the search form.', // @translate
                    // TODO Convert documentation into help. See application/view/common/form-row.phtml
                    'documentation' => nl2br(<<<'MARKDOWN'
                        #"></a><div class="field-description no-link">
                        - The types are html input types: Text (default), Advanced (list of advanced filters), Checkbox, Hidden, Number, Radio, Range, RangeDouble, Select, SelectFlat, SelectGroup, MultiCheckbox, MultiSelect, MultiSelectFlat, MultiSelectGroup, MultiText, and, for modules, Access, Thesaurus, and Tree (item sets tree).
                        - Text: the default html input field may be improved with an autosuggester. Enable it with option "autosuggest" and value "true". An external url can be set via attribute "'data-autosuggest-url'."
                        - Checkbox: the keys "unchecked_value" and "checked_value" allow to define a specific value to be returned.
                        - Hidden: the value can be passed with key "value". If the value is not a scalar, it is serialized as json.
                        - Number: the keys "min", "max" and "step" can be set as attributes, else they will be extracted from data. Of course, data should be numbers.
                        - Range and RangeDouble allows to display a slider with one or two values. Min and max are extracted from data if not set as attributes.
                        - For Number, Range and RangeDouble, the option "first_digits" is enabled by default to extract the year from dates. Set "first_digits = false" to disable it. It is recommended to use an index with the year to avoid strange results when casting and sorting non-normalized data.
                        - MultiSelectFlat and SelectFlat may be used to be sure that values are flatten.
                        - MultiSelectGroup and SelectGroup may be used for some specific fields that group options by default (resource classes, resource templates), in which case the options labels are removed.
                        - Tree can be used for item sets when module ItemSetsTree is enabled and data indexed recursively.
                        - For the types MultiCheckbox, Radio, Select, and derivatives, the values can be passed with the option "value_options", else the ones of the field will be used.
                        </div><a href="#
                        MARKDOWN), // @translate
                    /** @see \AdvancedSearch\Form\MainSearchForm::init() */
                    'value_options' => [
                        'text' => 'Text (default)', // @ŧranslate
                        'Advanced' => 'Advanced filter', // @translate
                        'Checkbox' => 'Checkbox', // @translate
                        'HasValue' => 'Checkbox: has a value / has no value', // @translate
                        // 'Date' => 'Date',
                        'MultiCheckbox' => 'Multi checkbox', // @translate
                        'Hidden' => 'Hidden', // @translate
                        'Number' => 'Number', // @translate
                        //  'Place' => 'Place',
                        'Radio' => 'Radio', // @translate
                        'Range' => 'Range', // @translate
                        'RangeDouble' => 'Slider for a range of values', // @translate
                        // A space is added to avoid an issue with translation.
                        'Select' => 'Select ', // @translate
                        'SelectFlat' => 'Select (flat)', // @translate
                        'SelectGroup' => 'Select (group)', // @translate
                        'MultiSelect' => 'Select (multiple choices)', // @translate
                        'MultiSelectFlat' => 'Select (multiple choices, flat)', // @translate'
                        'MultiSelectGroup' => 'Select (multiple choices, group)', // @translate'
                        'MultiText' => 'Text (multiple, with a separator)', // @translate
                        'Specific' => 'Specific (set as option)', // @translate
                        'modules' => [
                            'label' => 'Modules', // @translate
                            'options' => [
                                'Access' => 'Access', // @translate
                                'Tree' => 'Item sets tree', // @translate
                                'Thesaurus' => 'Thesaurus', // @translate
                            ],
                        ],
                    ],
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'form_filter_type',
                    'class' => 'chosen-select',
                    'required' => false,
                    'data-common' => '1',
                    'data-type-default' => 'text',
                    'data-placeholder' => 'Set filter type…', // @translate
                ],
            ])
            // Settings of the advanced filter (type Advanced), stored with the
            // filter itself.
            ->add([
                'name' => 'fields',
                'type' => CommonElement\DataTextarea::class,
                'options' => [
                    'label' => 'Fields proposed to the visitor', // @translate
                    'info' => 'One field by line, in this order: "term or field = Label".', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                    'data_options' => [
                        'value' => null,
                        'label' => null,
                    ],
                ],
                'attributes' => [
                    'id' => 'form_filter_fields',
                    'data-filter-types' => 'Advanced',
                    'data-common' => '1',
                    // field (term) = label (order means weight).
                    'placeholder' => 'dcterms:title = Title',
                    'rows' => 6,
                ],
            ])
            ->add([
                'name' => 'default_number',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Rows displayed by default', // @translate
                ],
                'attributes' => [
                    'id' => 'form_filter_default_number',
                    'data-filter-types' => 'Advanced',
                    'data-common' => '1',
                    'data-inline' => 'rows',
                    'required' => false,
                    'value' => '1',
                    'min' => '0',
                    // A mysql query supports 61 arguments maximum.
                    'max' => '49',
                    'step' => '1',
                ],
            ])
            ->add([
                'name' => 'max_number',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Maximum rows', // @translate
                ],
                'attributes' => [
                    'id' => 'form_filter_max_number',
                    'data-filter-types' => 'Advanced',
                    'data-common' => '1',
                    'data-inline' => 'rows',
                    'required' => false,
                    'value' => '10',
                    'min' => '0',
                    // A mysql query supports 61 arguments maximum.
                    'max' => '49',
                    'step' => '1',
                ],
            ])
            ->add([
                'name' => 'field_elements',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'label' => 'Elements of a row', // @translate
                    'value_options' => [
                        'joiner' => 'Joiner ("and" / "or")', // @translate
                        'joiner_not' => 'Joiner "not"', // @translate
                        'operator' => 'Operator ("is exactly", "contains", etc.)', // @translate
                        'autosuggest' => 'Autocompletion of values (requires module Reference or SearchSolr)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'form_filter_field_elements',
                    'data-filter-types' => 'Advanced',
                    'data-common' => '1',
                    'value' => ['joiner', 'operator'],
                ],
            ])
            ->add([
                'name' => 'field_operators',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Available operators', // @translate
                    'info' => 'By default, all the operators of the standard advanced search form. Negative operators are removed when the joiner "not" is used.', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                ],
                'attributes' => [
                    'id' => 'form_filter_field_operators',
                    'data-filter-types' => 'Advanced',
                    'rows' => 12,
                    // This placeholder does not contain all query types.
                    'placeholder' => <<<'STRING'
                        eq = is exactly
                        in = contains
                        sw = starts with
                        ew = ends with
                        STRING, // @translate
                ],
            ])
            ->add([
                'name' => 'language_site',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Limit languages of filters (internal querier)', // @ŧranslate
                    'value_options' => [
                        '' => 'No limit', // @translate
                        'site' => 'Limit filters to site language or empty language', // @ŧranslate
                        'site_setting' => 'Use site setting "Filter values based on site locale"', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'form_filter_language_site',
                    'data-filter-types' => implode(' ', self::TYPES_LIST),
                    'required' => false,
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'languages',
                'type' => CommonElement\ArrayText::class,
                'options' => [
                    'label' => 'Limit filters to specific languages (internal querier)', // @translate
                    'info' => <<<'TXT'
                        Use "|" to separate multiple languages. Use a trailing "|" for values without language. When fields with languages (like subjects) and fields without language (like date) are facets, the empty language must be set to get results.
                        TXT, // @translate
                    'value_separator' => '|',
                ],
                'attributes' => [
                    'id' => 'form_filter_languages',
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
                        'alphabetic asc' => 'Alphabetic (default)', // @ŧranslate
                        'alphabetic desc' => 'Alphabetic descendant', // @ŧranslate
                        'total desc' => 'Total', // @ŧranslate
                        'total asc' => 'Total ascendant', // @ŧranslate
                        'total_alpha desc' => 'Total then alphabetic for hidden values', // @ŧranslate
                        'values asc' => 'Values (listed below)', // @ŧranslate
                        'values desc' => 'Values descendant', // @ŧranslate
                    ],
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'form_filter_order',
                    'data-common' => '1',
                    'data-filter-types' => implode(' ', self::TYPES_LIST),
                    'multiple' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select order…', // @translate
                ],
            ])
            ->add([
                'name' => 'limit',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Maximum number of filters', // @translate
                ],
                'attributes' => [
                    'id' => 'form_filter_limit',
                    'data-common' => '1',
                    'data-filter-types' => implode(' ', self::TYPES_LIST),
                    'required' => false,
                    'value' => '100',
                ],
            ])

            // Slider scale (Range and RangeDouble only). Mode "linear" is the
            // default and ignores breakpoints. Mode "piecewise" requires at
            // least two breakpoints with values and positions strictly
            // increasing from 0 to 100.
            ->add([
                'name' => 'scale_mode',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Slider scale (RangeDouble only)', // @translate
                    'value_options' => [
                        'linear' => 'Linear', // @translate
                        'log' => 'Logarithmic', // @translate
                        'piecewise' => 'Piecewise (with breakpoints)', // @translate
                        'auto' => 'Auto (quartiles from data)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'form_filter_scale_mode',
                    'data-filter-types' => implode(' ', self::TYPES_SLIDER),
                    'value' => 'linear',
                ],
            ])
            ->add([
                'name' => 'scale_breakpoints',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Scale breakpoints', // @translate
                    'info' => 'One pair per line: value = position. Position is a percentage between 0 and 100.', // @translate
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'form_filter_scale_breakpoints',
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
                    'id' => 'form_filter_scale_show_ticks',
                    'data-filter-types' => implode(' ', self::TYPES_SLIDER),
                ],
            ])

            ->add([
                'type' => CommonElement\IniTextarea::class,
                'name' => 'options',
                'options' => [
                    'label' => 'Options', // @translate
                    'info' => <<<'HTML'
                        List of specific options, in ini format, for example:
                        `empty_option = ""`,
                        `checked_value = "yes"`,
                        `value_label = "Has an image"`,
                        `autosuggest = true`,
                        `value_options.first = "First"`,
                        `first_digits = false`.
                        Omeka and Laminas options are accepted.
                        Note: "min", "max", "step" should be set in "Html attributes".
                        HTML, // @translate
                    'ini_typed_mode' => true,
                ],
                'attributes' => [
                    'id' => 'form_filters_options',
                    'required' => false,
                    'placeholder' => '',
                ],
            ])
            ->add([
                'type' => CommonElement\IniTextarea::class,
                'name' => 'attributes',
                'options' => [
                    'label' => 'Html attributes', // @translate
                    'info' => 'Attributes to add to the input field, for example `class = "my-specific-class"`, or `min = 1454` for Number/Range/RangeDouble, or max, step, placeholder, data, etc.', // @translate
                    'ini_typed_mode' => true,
                ],
                'attributes' => [
                    'id' => 'form_filters_attributes',
                    'required' => false,
                    'placeholder' => '',
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
            'field_end' => [
                'required' => false,
            ],
            'type' => [
                'required' => false,
            ],
        ];
    }

    protected function getAvailableFields(): array
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');
        $engineAdapter = $searchConfig ? $searchConfig->engineAdapter() : null;
        return $engineAdapter
            ? $engineAdapter->getAvailableFieldsForSelect()
            : [];
    }
}
