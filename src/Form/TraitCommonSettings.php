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
                    'label' => $this instanceof SiteSettingsFieldset
                        ? 'Query types for filters of this site' // @translate
                        : 'Query types for filters of the admin board', // @translate
                    'info' => $this instanceof SiteSettingsFieldset
                        ? 'The sites display a simple list by default, unlike the admin board, that keeps all the types. The negative types are managed with the positive ones, and the duplicates are the combination of the four families and the selected variants.' // @translate
                        : 'The admin board keeps all the types by default, unlike the sites, that display a simple list. The negative types are managed with the positive ones, and the duplicates are the combination of the four families and the selected variants.', // @translate
                    'value_options' => $this->filterTypeOptions(),
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_filter_types',
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
        $negative = array_flip(SearchResources::FIELD_QUERY['negative']);
        $duplicates = SearchResources::FILTER_TYPE_DUPLICATES;
        $families = $duplicates['families'];

        // The negative types are derived from their positive one and the
        // duplicates are a product of families and variants, so only 39 of the
        // 84 types are displayed.
        $options = [];
        foreach ($groups as $group => $types) {
            $groupOptions = [];
            foreach ($types as $type) {
                if (!isset($labels[$type]) || isset($negative[$type])) {
                    continue;
                }
                // Keep the family only, the variants are common to all of them.
                if (!isset($families[$type]) && $this->duplicateFamilyOf($type) !== null) {
                    continue;
                }
                $option = [
                    'value' => $type,
                    'label' => isset($families[$type])
                        ? sprintf('%s: %s', 'duplicates', $families[$type]) // @translate
                        : $labels[$type],
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

        // The variants apply to the four families of duplicates at once.
        $variantOptions = [];
        foreach ($duplicates['variants'] as $variant => $label) {
            $variantOptions[$variant] = [
                'value' => $variant,
                'label' => $label,
            ];
        }
        $options['Duplicates'] = [
            'label' => 'Duplicates: variants', // @translate
            'options' => $variantOptions,
        ];

        return $options;
    }

    /**
     * Get the family of a type of duplicates, that is displayed as variants.
     */
    protected function duplicateFamilyOf(string $type): ?string
    {
        $duplicates = SearchResources::FILTER_TYPE_DUPLICATES;
        foreach (array_keys($duplicates['families']) as $family) {
            if (mb_strpos($type, $family) !== 0) {
                continue;
            }
            $variant = mb_substr($type, mb_strlen($family));
            if (isset($duplicates['variants'][$variant])) {
                return $family;
            }
        }
        return null;
    }

    public function setListSearchFields(array $listSearchFields): self
    {
        $this->listSearchFields = $listSearchFields;
        return $this;
    }
}
