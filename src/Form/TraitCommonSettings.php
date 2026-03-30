<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use AdvancedSearch\Stdlib\SearchResources;
use Common\Form\Element as CommonElement;

trait TraitCommonSettings
{
    /**
     * @var array
     */
    protected $listSearchFields = [];

    protected function initSearchFields(): self
    {
        return $this
            ->add([
                'name' => 'advancedsearch_search_fields',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'search',
                    'label' => 'Fields for standard advanced search form', // @translate
                    'info' => 'The check box marked with a "*" are improvements of the standard search fields. They should be replaced by equivalent arguments of the module Advanced Search to avoid side effects.', // @translate
                    'value_options' => $this->listSearchFields,
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_search_fields',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_filter_types',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'search',
                    'label' => 'Query types for filters', // @translate
                    'value_options' => $this->filterTypeOptions(),
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_filter_types',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_filter_value_autosuggest_whitelist',
                'type' => CommonElement\OptionalPropertySelect::class,
                'options' => [
                    'element_group' => 'search',
                    'label' => 'Properties with autocompletion on filter values (whitelist)', // @translate
                    'info' => 'Autocompletion requires module Reference.', // @translate
                    'term_as_value' => true,
                    'prepend_value_options' => [
                        'all' => 'All properties', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'advancedsearch_filter_value_autosuggest_whitelist',
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select properties…', // @translate
                ],
            ])
            ->add([
                'name' => 'advancedsearch_filter_value_autosuggest_blacklist',
                'type' => CommonElement\OptionalPropertySelect::class,
                'options' => [
                    'element_group' => 'search',
                    'label' => 'Properties without autocompletion on filter values (blacklist)', // @translate
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_filter_value_autosuggest_blacklist',
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select properties…', // @translate
                ],
            ])
            ->add([
                'name' => 'advancedsearch_filter_joiner_not',
                'type' => \Laminas\Form\Element\Checkbox::class,
                'options' => [
                    'element_group' => 'search',
                    'label' => 'Add joiner "not" to filters and simplify query types', // @translate
                    'info' => 'When enabled, negative query types (does not contain, is not…) are removed and replaced by the "not" joiner.', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_filter_joiner_not',
                ],
            ])
        ;
    }

    protected function filterTypeOptions(): array
    {
        $isSiteSettings = $this instanceof SiteSettingsFieldset;
        $disabled = $isSiteSettings
            ? ['resq' => true, 'nresq' => true]
            : [];
        $labels = SearchResources::FIELD_QUERY['labels'];
        $groups = SearchResources::FIELD_QUERY['groups'];

        // Build optgroup format handled by TraitGroupedMultiOptions.
        $options = [];
        foreach ($groups as $group => $types) {
            $groupOptions = [];
            foreach ($types as $type) {
                if (!isset($labels[$type])) {
                    continue;
                }
                $option = [
                    'value' => $type,
                    'label' => $labels[$type],
                ];
                if (isset($disabled[$type])) {
                    $option['disabled'] = true;
                }
                $groupOptions[$type] = $option;
            }
            if ($groupOptions) {
                $options[$group] = [
                    'label' => $group,
                    'options' => $groupOptions,
                ];
            }
        }
        return $options;
    }

    public function setListSearchFields(array $listSearchFields): self
    {
        $this->listSearchFields = $listSearchFields;
        return $this;
    }
}
