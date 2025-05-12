<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\InputFilter\InputFilterProviderInterface;

class SearchConfigFilterFieldset extends Fieldset implements InputFilterProviderInterface
{
    public function init(): void
    {
        // These fields may be overridden by the available fields.
        $availableFields = $this->getAvailableFields();

        $this
            ->setAttribute('id', 'form-search-config-filter')
            ->setAttribute('class', 'form-fieldset-element form-search-config-filter')
            ->setName('filter')

            ->add([
                'name' => 'name',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Name (alphanumeric)', // @translate
                ],
                'attributes' => [
                    'id' => 'form_filter_name',
                    'required' => true,
                    'pattern' => '[a-zA-Z0-9_:\-]+',
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
                    'required' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Set field or index…', // @translate
                ],
            ])

            ->add([
                'name' => 'label',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label', // @translate
                ],
                'attributes' => [
                    'id' => 'form_filter_label',
                    'required' => false,
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
                        - For Number, Range and RangeDouble, "first_digits" can be set as option to extract the years from dates, but it is recommended to use an index with the year in that case to avoid strange results when casting and sorting non-normalized data.
                        - MultiSelectFlat and SelectFlat may be used to be sure that values are flatten.
                        - MultiSelectGroup and SelectGroup may be used for some specific fields that group options by default (resource classes, resource templates), in which case the options labels are removed.
                        - Tree can be used for item sets when module ItemSetsTree is enabled and data indexed recursively.
                        - For the types MultiCheckbox, Radio, Select, and derivatives, the values can be passed with the option "value_options", else the ones of the field will be used.
                        </div><a href="#
                        MARKDOWN), // @translate
                    /** @see \AdvancedSearch\Form\MainSearchForm::init() */
                    'value_options' => [
                        'text' => 'Text (default)', // @ŧranslate
                        'Advanced' => 'Advanced filter (configured below)', // @translate
                        'Checkbox' => 'Checkbox', // @translate
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
                    'id' => 'form_filter_name',
                    'class' => 'chosen-select',
                    'required' => false,
                    'data-placeholder' => 'Set filter type…', // @translate
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
                    'id' => 'form_filter_order',
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
                    'required' => false,
                    'value' => '100',
                ],
            ])
            ->add([
                'type' => CommonElement\IniTextarea::class,
                'name' => 'options',
                'options' => [
                    'label' => 'Options', // @translate
                    'info' => 'List of specific options according to types. Omeka and Laminas options are accepted, for example `empty_option = ""`, `checked_value = "yes"`, `autosuggest = true`, `value_options.first = "First"`, `first_digits = true`.', // @translate
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
                    'info' => 'Attributes to add to the input field, for example `class = "my-specific-class"`, or `min = 1454` for an input Number, or max, step, placeholder, data, etc.', // @translate
                    'ini_typed_mode' => true,
                ],
                'attributes' => [
                    'id' => 'form_filters_attributes',
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
                    'aria-label' => 'Move this filter up', // @translate
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
                    'aria-label' => 'Move this filter down', // @translate
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
