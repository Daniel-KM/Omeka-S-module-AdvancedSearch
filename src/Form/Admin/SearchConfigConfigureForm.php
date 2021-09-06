<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau, 2018-2021
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace AdvancedSearch\Form\Admin;

use AdvancedSearch\Form\Element\ArrayText;
use AdvancedSearch\Form\Element\DataTextarea;
use AdvancedSearch\Form\Element\OptionalRadio;
use AdvancedSearch\Form\Element\OptionalSelect;
use AdvancedSearch\Form\Element\OptionalUrl;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Omeka\Form\Element\ArrayTextarea;

class SearchConfigConfigureForm extends Form
{
    protected $formElementManager;

    /**
     * @var array
     */
    protected $suggesters = [];

    public function init(): void
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');
        $engine = $searchConfig->engine();
        if (empty($engine)) {
            return;
        }

        // This is the settings for the search config, not the search form one.

        // TODO Simplify the form with js, storing the whole form one time. See UserProfile and https://docs.laminas.dev/laminas-form/v3/form-creation/creation-via-factory/

        // These fields may be overridden by the available fields.
        $availableFields = $this->getAvailableFields();
        $specialOptions = [
            'is_public_field' => 'Is public', // @translate
            // TODO Create a default field for item set, resource class and resource template for Solr.
            'item_set_id_field' => 'Item set id (if available in fields)', // @translate
            'resource_class_id_field' => 'Resource class id (if available in fields)', // @translate
            'resource_template_id_field' => 'Resource template id (if available in fields)', // @translate
            'owner_id_field' => 'Owner id (if available in fields)', // @translate
        ] + $availableFields;

        // Remove some of the available fields used by the internal adapter,
        // because here, it's about special options and for any adapter.
        unset($specialOptions['item_set_id']);
        unset($specialOptions['resource_class_id']);
        unset($specialOptions['resource_template_id']);
        unset($specialOptions['owner_id']);

        $this
            ->setAttribute('id', 'search-form-configure');

        // Settings for the search engine. Can be overwritten by a specific form.

        $this
            ->add([
                'name' => 'search',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Search settings', // @translate
                ],
            ])
            ->get('search')
            ->add([
                'name' => 'default_results',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Default results to display when landing on search page', // @translate
                    'value_options' => [
                        'none' => 'Nothing', // @translate
                        'query' => 'Results of the query below', // @translate
                        'default' => 'Default results of the search engine', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'default_results',
                    'required' => false,
                    'value' => 'default',
                ],
            ])
            ->add([
                'name' => 'default_query',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Default query', // @translate
                    'default' => 'The format of the query depends on the search form and the search engine.', // @translated
                ],
                'attributes' => [
                    'id' => 'default_query',
                ],
            ])
        ;

        $this
            ->add([
                'name' => 'resource_fields',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Resources fields for external search engines', // @translate
                ],
            ])
            ->get('resource_fields')
            ->add([
                'name' => 'is_public_field',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Is public field', // @translate
                    'value_options' => $specialOptions,
                    'empty_option' => 'None', // @translate
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                    'value' => 'is_public_field',
                ],
            ])
            ->add([
                'name' => 'item_set_id_field',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Item set id field', // @translate
                    'value_options' => $specialOptions,
                    'empty_option' => 'None', // @translate
                ],
                'attributes' => [
                    'id' => 'item_set_id_field',
                    'required' => false,
                    'class' => 'chosen-select',
                    'value' => 'item_set_id_field',
                ],
            ])
            ->add([
                'name' => 'resource_class_id_field',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Resource class id field', // @translate
                    'value_options' => $specialOptions,
                    'empty_option' => 'None', // @translate
                ],
                'attributes' => [
                    'id' => 'resource_class_id_field',
                    'required' => false,
                    'class' => 'chosen-select',
                    'value' => 'resource_class_id_field',
                ],
            ])
            ->add([
                'name' => 'resource_template_id_field',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Resource template id field', // @translate
                    'value_options' => $specialOptions,
                    'empty_option' => 'None', // @translate
                ],
                'attributes' => [
                    'id' => 'resource_template_id_field',
                    'required' => false,
                    'class' => 'chosen-select',
                    'value' => 'resource_template_id_field',
                ],
            ])
            ->add([
                'name' => 'owner_id_field',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Owner id field', // @translate
                    'value_options' => $specialOptions,
                    'empty_option' => 'None', // @translate
                ],
                'attributes' => [
                    'id' => 'owner_id_field',
                    'required' => false,
                    'class' => 'chosen-select',
                    'value' => 'owner_id_field',
                ],
            ])
        ;

        $this
            ->add([
                'name' => 'autosuggest',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Auto-suggestions', // @translate
                ],
            ])
            ->get('autosuggest')
            ->add([
                'name' => 'suggester',
                'type' => OptionalSelect::class,
                'options' => [
                    'label' => 'Suggester', // @translate
                    'value_options' => $this->suggesters,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'autosuggest_suggester',
                    'multiple' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => ' ',
                ],
            ])
            ->add([
                'name' => 'url',
                'type' => OptionalUrl::class,
                'options' => [
                    'label' => 'Direct endpoint', // @translate
                    // @see https://solr.apache.org/guide/suggester.html#suggest-request-handler-parameters
                    'info' => 'This url allows to use an external endpoint to manage keywords and is generally quicker. Needed params should be appended.', // @translate
                ],
                'attributes' => [
                    'id' => 'autosuggest_url',
                ],
            ])
            ->add([
                'name' => 'url_param_name',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Optional query param name for direct endpoint', // @translate
                    'info' => 'For a direct Solr endpoint, it should be "suggest.q", else "q" is used by default.', // @translate
                ],
                'attributes' => [
                    'id' => 'autosuggest_url_param_name',
                ],
            ])
        ;

        // Settings for the form querier (advanced form and filters).

        $this
            ->add([
                'name' => 'form',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Advanced form', // @translate
                ],
            ])
            ->get('form')

            ->add([
                'name' => 'filters',
                'type' => DataTextarea::class,
                'options' => [
                    'label' => 'Filters', // @translate
                    'info' => 'List of filters that will be displayed in the search form. Format is "field = Label = Type = options". The field should exist in all resources fields.', // @translate
                    'as_key_value' => false,
                    'key_value_separator' => '=',
                    'data_keys' => [
                        'field',
                        'label',
                        'type',
                        'options',
                    ],
                    'data_array_keys' => [
                        'options' => '|',
                    ],
                ],
                'attributes' => [
                    'id' => 'filters',
                    // field (term) = label = type = options
                    'placeholder' => 'item_set_id_field = Collection = Select
resource_class_id_field = Class = SelectFlat
dcterms:title = Title = Text
dcterms:date = Date = DateRange
dcterms:subject = Subject = MultiCheckbox = alpha | beta | gamma
advanced = Filters = Advanced',
                    'rows' => 12,
                ],
            ])
            ->add([
                'name' => 'available_filters',
                'type' => ArrayTextarea::class,
                'options' => [
                    'label' => 'Available filters', // @translate
                    'info' => 'List of all available filters, among which some can be copied above.', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                ],
                'attributes' => [
                    'id' => 'available_filters',
                    'value' => $availableFields,
                    'placeholder' => 'dcterms:title = Title',
                    'rows' => 12,
                ],
            ])
            ->add([
                'name' => 'advanced',
                'type' => DataTextarea::class,
                'options' => [
                    'label' => 'Advanced filters', // @translate
                    'info' => 'List of filters that will be displayed in the search form. Format is "term = Label". The field should exist in all resources fields.', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                    'data_keys' => [
                        'value',
                        'label',
                    ],
                ],
                'attributes' => [
                    'id' => 'filters',
                    // field (term) = label (order means weight).
                    'placeholder' => 'dcterms:title = Title',
                    'rows' => 12,
                ],
            ])
            ->add([
                'name' => 'max_number',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Number of advanced filters to display', // @translate
                    'info' => 'The filters may be managed via js for a better display.', // @translate
                ],
                'attributes' => [
                    'id' => 'max_number',
                    'required' => false,
                    'value' => '5',
                    'min' => '0',
                    // A mysql query supports 61 arguments maximum.
                    'max' => '49',
                    'step' => '1',
                ],
            ])
            ->add([
                'name' => 'field_joiner',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add the joiner ("and" or "or") to the advanced filters', // @translate
                ],
                'attributes' => [
                    'id' => 'field_joiner',
                ],
            ])
            // TODO List value type (rename/remove).
            ->add([
                'name' => 'field_operator',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add the operator ("equal", "in", etc.) to the advanced filters', // @translate
                ],
                'attributes' => [
                    'id' => 'field_operator',
                ],
            ])
        ;

        // Settings for the results (sorting).

        // TODO Add the style of pagination.

        $this
            ->add([
                'name' => 'sort',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Sorting', // @translate
                ],
            ])
            ->get('sort')
            // field (term + asc/desc) = label (+ asc/desc) (order means weight).
            ->add([
                'name' => 'fields',
                'type' => DataTextarea::class,
                'options' => [
                    'label' => 'Sort fields', // @translate
                    'info' => 'List of sort fields that will be displayed in the search page. Format is "term dir = Label".', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                    'data_keys' => [
                        'name',
                        'label',
                    ],
                ],
                'attributes' => [
                    'id' => 'sorting_fields',
                    'placeholder' => 'dcterms:subject asc = Subject (asc)',
                    'rows' => 12,
                ],
            ])
            ->add([
                'name' => 'available_sort_fields',
                'type' => ArrayTextarea::class,
                'options' => [
                    'label' => 'Available sort fields', // @translate
                    'info' => 'List of all available sort fields, among which some can be copied above.', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                ],
                'attributes' => [
                    'id' => 'sorting_available_sort_fields',
                    'value' => $this->getAvailableSortFields(),
                    'placeholder' => 'dcterms:subject asc = Subject (asc)',
                    'rows' => 12,
                ],
            ])
        ;

        // Settings for the results (facets).
        // TODO Add the count or not.

        $this
            ->add([
                'name' => 'facet',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Facets', // @translate
                ],
            ])
            ->get('facet')
            // field (term) = label (order means weight).
            ->add([
                'name' => 'facets',
                'type' => DataTextarea::class,
                'options' => [
                    'label' => 'List of facets', // @translate
                    'info' => 'List of facets that will be displayed in the search page. Format is "field = Label" and optionnally " = Select".', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                    'data_keys' => [
                        'name',
                        'label',
                        'type',
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_facets',
                    'placeholder' => 'dcterms:subject = Subjects',
                    'rows' => 12,
                ],
            ])
            ->add([
                'name' => 'available_facets',
                'type' => ArrayTextarea::class,
                'options' => [
                    'label' => 'Available facets', // @translate
                    'info' => 'List of all available facets, among which some can be copied above.', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                ],
                'attributes' => [
                    'id' => 'facet_available_facets',
                    'value' => $this->getAvailableFacetFields(),
                    'placeholder' => 'dcterms:subject = Subjects',
                    'rows' => 12,
                ],
            ])
            ->add([
                'name' => 'limit',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Maximum number of facet by field', // @translate
                    'info' => 'The maximum number of values fetched for each facet', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_limit',
                    'value' => 10,
                    'min' => 1,
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'order',
                'type' => OptionalRadio::class,
                'options' => [
                    'label' => 'Order of facet items', // @translate
                    'value_options' => [
                        '' => 'Native', // @translate
                        'alphabetic asc' => 'Alphabetical', // @translate
                        'total desc' => 'Count (biggest first)', // @translate
                        'total asc' => 'Count (smallest first)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_order',
                    'required' => false,
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'languages',
                'type' => ArrayText::class,
                'options' => [
                    'label' => 'Get facets from specific languages', // @translate
                    'info' => 'Generally, facets are translated in the view, but in some cases, facet values may be translated directly in a multivalued property. Use "|" to separate multiple languages. Use a trailing "|" for values without language. When fields with languages (like subjects) and fields without language (like date) are facets, the empty language must be set to get results.', // @translate
                    'value_separator' => '|',
                ],
                'attributes' => [
                    'id' => 'facet_languages',
                    'placeholder' => 'fra|way|apy|',
                ],
            ])
            ->add([
                'name' => 'mode',
                'type' => OptionalRadio::class,
                'options' => [
                    'label' => 'Facet mode', // @translate
                    'value_options' => [
                        'button' => 'Send request with a button', // @translate
                        'link' => 'Send request directly', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_mode',
                    'required' => false,
                    'value' => 'button',
                ],
            ])
            ->add([
                'name' => 'display_button',
                'type' => OptionalRadio::class,
                'options' => [
                    'label' => 'Position of the button "Apply filters"', // @translate
                    'value_options' => [
                        'above' => 'Above facets', // @translate
                        'below' => 'Below facets', // @translate
                        'both' => 'Both', // @translate
                        'none' => 'None', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_display_button',
                    'required' => false,
                    'value' => 'above',
                ],
            ])
            ->add([
                'name' => 'display_active',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display the list of active facets', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_display_active',
                    'required' => false,
                    'value' => true,
                ],
            ])
            ->add([
                'name' => 'display_count',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display the count of each facet item', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_display_count',
                    'value' => true,
                ],
            ])
        ;

        $this
            ->addFormFieldset()
            ->prepareInputFilters();
    }

    protected function prepareInputFilters(): Form
    {
        // Input filters should be added after elements.
        $inputFilter = $this->getInputFilter();

        // A check is done because the specific form may remove them.
        if ($inputFilter->has('autosuggest')) {
            $inputFilter
                ->get('autosuggest')
                ->add([
                    'name' => 'limit',
                    'required' => false,
                ])
            ;
        }

        if ($inputFilter->has('form')) {
            $inputFilter
                ->get('form')
                ->add([
                    'name' => 'max_number',
                    'required' => false,
                ])
            ;
        }

        if ($inputFilter->has('facet')) {
            $inputFilter
                ->get('facet')
                ->add([
                    'name' => 'limit',
                    'required' => false,
                ])
                ->add([
                    'name' => 'languages',
                    'required' => false,
                ])
            ;
        }

        return $this;
    }

    protected function addFormFieldset(): self
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');

        $formAdapter = $searchConfig->formAdapter();
        if (!$formAdapter) {
            return $this;
        }

        $configFormClass = $formAdapter->getConfigFormClass();
        if (!$configFormClass) {
            return $this;
        }

        /** @var \Laminas\Form\Fieldset $fieldset */
        $fieldset = $this->formElementManager
            ->get($configFormClass, ['search_config' => $searchConfig]);

        if (method_exists($fieldset, 'skipDefaultElementsOrFieldsets')) {
            foreach ($fieldset->skipDefaultElementsOrFieldsets() as $skip) {
                $this->remove($skip);
            }
        }

        $this->add($fieldset);

        return $this;
    }

    protected function getAvailableFields(): array
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');
        $searchEngine = $searchConfig->engine();
        $searchAdapter = $searchEngine->adapter();
        if (empty($searchAdapter)) {
            return [];
        }

        $options = [];
        $fields = $searchAdapter->getAvailableFields($searchEngine);
        foreach ($fields as $name => $field) {
            $options[$name] = $field['label'] ?? $name;
        }
        return $options;
    }

    protected function getAvailableSortFields(): array
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');
        $searchEngine = $searchConfig->engine();
        $searchAdapter = $searchEngine->adapter();
        if (empty($searchAdapter)) {
            return [];
        }

        $options = [];
        $fields = $searchAdapter->getAvailableSortFields($searchEngine);
        foreach ($fields as $name => $field) {
            $options[$name] = $field['label'] ?? $name;
        }
        return $options;
    }

    protected function getAvailableFacetFields(): array
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');
        $searchEngine = $searchConfig->engine();
        $searchAdapter = $searchEngine->adapter();
        if (empty($searchAdapter)) {
            return [];
        }

        $options = [];
        $fields = $searchAdapter->getAvailableFacetFields($searchEngine);
        foreach ($fields as $name => $field) {
            $options[$name] = $field['label'] ?? $name;
        }
        return $options;
    }

    public function setSuggesters(array $suggesters): self
    {
        $this->suggesters = $suggesters;
        return $this;
    }

    public function setFormElementManager($formElementManager): self
    {
        $this->formElementManager = $formElementManager;
        return $this;
    }
}
