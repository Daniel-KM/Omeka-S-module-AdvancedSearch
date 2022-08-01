<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

use AdvancedSearch\Form\Element as AdvancedSearchElement;
use AdvancedSearch\View\Helper\EasyMeta;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class ApiFormConfigFieldset extends Fieldset
{
    /**
     * @var EasyMeta
     */
    protected $easyMeta;

    public function init(): void
    {
        // Mapping between omeka api and search engine.

        $this
            ->setName('form')
            ->setLabel('Specific settings'); // @translate

        $availableFields = $this->getAvailableFields();

        $this
            ->add([
                'name' => 'options',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'All options should be updated when the search engine is updated', // @translate
                ],
                'attributes' => [
                    'id' => 'options',
                ],
            ])
            ->get('options')
            ->add([
                'name' => 'max_results',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Maximum number of results by response', // @translate
                    'info' => 'It is recommended to keep the value low (under 100 or 1000) to avoid overload of the server, or to use a paginator.', // @translate
                ],
                'attributes' => [
                    'required' => true,
                    'min' => 1,
                    'value' => 100,
                ],
            ])
        ;

        $this
            ->add([
                'name' => 'metadata',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Mapping metadata to search fields', // @translate
                ],
                'attributes' => [
                    'id' => 'metadata',
                ],
            ])
            ->get('metadata')
            ->add([
                'name' => 'id',
                'type' => AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Internal id', // @translate
                    'value_options' => $availableFields,
                    'empty_option' => 'None', // @translate
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'is_public',
                'type' => AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Is Public', // @translate
                    'value_options' => $availableFields,
                    'empty_option' => 'None', // @translate
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'owner_id',
                'type' => AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Owner id', // @translate
                    'value_options' => $availableFields,
                    'empty_option' => 'None', // @translate
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'site_id',
                'type' => AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Site id', // @translate
                    'value_options' => $availableFields,
                    'empty_option' => 'None', // @translate
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'created',
                'type' => AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Created', // @translate
                    'value_options' => $availableFields,
                    'empty_option' => 'None', // @translate
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'modified',
                'type' => AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Modified', // @translate
                    'value_options' => $availableFields,
                    'empty_option' => 'None', // @translate
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'resource_class_label',
                'type' => AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Resource class label', // @translate
                    'value_options' => $availableFields,
                    'empty_option' => 'None', // @translate
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'resource_class_id',
                'type' => AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Resource class id', // @translate
                    'value_options' => $availableFields,
                    'empty_option' => 'None', // @translate
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'resource_template_id',
                'type' => AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Resource template id', // @translate
                    'value_options' => $availableFields,
                    'empty_option' => 'None', // @translate
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'item_set_id',
                'type' => AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Item set id', // @translate
                    'value_options' => $availableFields,
                    'empty_option' => 'None', // @translate
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'is_open',
                'type' => AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Is open', // @translate
                    'value_options' => $availableFields,
                    'empty_option' => 'None', // @translate
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                ],
            ])
        ;

        // Prefill the mapping (the specific metadata are mapped above).
        $sourceFields = $this->easyMeta->propertyIds();
        $prefill = [];
        foreach (array_keys($sourceFields) as $sourceField) {
            if (isset($availableFields[$sourceField])) {
                $prefill[$sourceField] = $sourceField;
                continue;
            }
            $sourceFieldU = str_replace(':', '_', $sourceField);
            if (isset($availableFields[$sourceFieldU])) {
                $prefill[$sourceField] = $sourceFieldU;
                continue;
            }
            foreach (array_keys($availableFields) as $availableField) {
                if (strpos($availableField, $sourceField) === 0
                    || strpos($availableField, $sourceFieldU) === 0
                ) {
                    $prefill[$sourceField] = $availableField;
                    continue 2;
                }
                // Separated in order to check full path first.
                if (strpos($availableField, $sourceField) !== false
                    || strpos($availableField, $sourceFieldU) !== false
                ) {
                    $prefill[$sourceField] = $availableField;
                    continue 2;
                }
            }
            $prefill[$sourceField] = '';
        }

        $this
            // Mapping between source field (term) = field destination (search engine).
            ->add([
                'name' => 'properties',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Mapping between source (omeka) and destination (search engine)', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                ],
                'attributes' => [
                    'id' => 'properties',
                    'value' => $prefill,
                    'placeholder' => 'dcterms:subject = dcterms_subjects_ss',
                    'rows' => 12,
                ],
            ])
            ->add([
                'name' => 'available_properties',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Available source fields for mapping', // @translate
                    'info' => 'List of all available properties to use above.', // @translate
                    'as_key_value' => false,
                ],
                'attributes' => [
                    'id' => 'form_available_properies',
                    'value' => $this->easyMeta->propertyTerms(),
                    'placeholder' => 'dcterms_subjects_ss',
                    'rows' => 12,
                ],
            ])
            ->add([
                'name' => 'available_fields',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Available destination fields for mapping', // @translate
                    'info' => 'List of all available fields to use above.', // @translate
                    'as_key_value' => false,
                ],
                'attributes' => [
                    'id' => 'form_available_fields',
                    'value' => array_keys($availableFields),
                    'placeholder' => 'dcterms_subjects_ss',
                    'rows' => 12,
                ],
            ])
        ;

        // Quick hack to get the available sort fields inside the form settings
        // The sort fields may be different from the indexed fields in some
        // search engine, so they should be checked when the api is used, since
        // there is no user form validation.
        $this
            ->add([
                'name' => 'sort_fields',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Sort (for internal use only, don’t modify it)', // @translate
                    'as_key_value' => false,
                ],
                'attributes' => [
                    'id' => 'sort_fields',
                    'value' => array_keys($this->getAvailableSortFields()),
                    'rows' => 1,
                ],
            ])
        ;
    }

    /**
     * Remove elements or fieldsets not managed by the api.
     */
    public function skipDefaultElementsOrFieldsets(): array
    {
        return [
            'search',
            'autosuggest',
            // The form is overwrittable.
            // 'form',
            'sort',
            'facet',
        ];
    }

    protected function getAvailableFields(): array
    {
        $options = [];
        $searchConfig = $this->getOption('search_config');
        $searchEngine = $searchConfig->engine();
        $searchAdapter = $searchEngine->adapter();
        if (empty($searchAdapter)) {
            return [];
        }
        $fields = $searchAdapter->setSearchEngine($searchEngine)->getAvailableFields();
        foreach ($fields as $name => $field) {
            $options[$name] = $field['label'] ?? $name;
        }
        return $options;
    }

    protected function getAvailableSortFields(): array
    {
        $options = [];
        $searchConfig = $this->getOption('search_config');
        $searchEngine = $searchConfig->engine();
        $searchAdapter = $searchEngine->adapter();
        if (empty($searchAdapter)) {
            return [];
        }
        $fields = $searchAdapter->setSearchEngine($searchEngine)->getAvailableSortFields();
        foreach ($fields as $name => $field) {
            $options[$name] = $field['label'] ?? $name;
        }
        return $options;
    }

    public function setEasyMeta(EasyMeta $easyMeta): self
    {
        $this->easyMeta = $easyMeta;
        return $this;
    }
}
