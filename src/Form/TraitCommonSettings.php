<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

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
        // No sidebar in public, so sub-query types are disabled.
        $disabled = [
            'resq' => true,
            'nresq' => true,
        ];
        $labels = self::filterTypeLabels();
        $groups = self::filterTypeGroups();
        // Build a reverse map: type => group name.
        $typeGroup = [];
        foreach ($groups as $group => $types) {
            foreach ($types as $type) {
                $typeGroup[$type] = $group;
            }
        }
        $options = [];
        $openedGroup = null;
        foreach ($labels as $value => $label) {
            $option = [
                'value' => $value,
                'label' => $label,
            ];
            if (isset($disabled[$value])) {
                $option['disabled'] = true;
            }
            $group = $typeGroup[$value] ?? null;
            if ($group && $group !== $openedGroup) {
                $option['label_attributes'] = [
                    'class' => 'filter-type-group-start',
                    'data-group-label' => $group, // @translate
                ];
                $openedGroup = $group;
            }
            $options[] = $option;
        }
        return $options;
    }

    /**
     * Canonical flat list of all filter type labels (untranslated).
     *
     * This is the single source of truth, used by the settings form
     * and by SearchFiltersTrait for display.
     */
    public static function filterTypeLabels(): array
    {
        return [
            // Value.
            // Include variants for comparison alphabetical and numerical.
            'eq' => 'is exactly', // @translate
            'neq' => 'is not exactly', // @translate
            'in' => 'contains', // @translate
            'nin' => 'does not contain', // @translate
            'sw' => 'starts with', // @translate
            'nsw' => 'does not start with', // @translate
            'ew' => 'ends with', // @translate
            'new' => 'does not end with', // @translate
            'near' => 'is similar to', // @translate
            'nnear' => 'is not similar to', // @translate
            'ma' => 'matches', // @translate
            'nma' => 'does not match', // @translate
            'lt' => 'lower than', // @translate
            'lte' => 'lower than or equal', // @translate
            'gte' => 'greater than or equal', // @translate
            'gt' => 'greater than', // @translate
            '<' => '<',
            '≤' => '≤',
            '≥' => '≥',
            '>' => '>',
            'yreq' => 'during year', // @translate
            'nyreq' => 'not during year', // @translate
            'yrgte' => 'since year', // @translate
            'yrlte' => 'until year', // @translate
            'yrgt' => 'since year (excluded)', // @translate
            'yrlt' => 'until year (excluded)', // @translate
            // Resource (duplcated for translation).
            'res' => 'is', // @translate
            'nres' => 'is not', // @translate
            'res' => 'is resource with ID', // @translate
            'nres' => 'is not resource with ID', // @translate
            'resq' => 'is resource matching query', // @translate
            'nresq' => 'is not resource matching query', // @translate
            // Linked resource (duplcated for translation).
            'lex' => 'is a linked resource', // @translate
            'nlex' => 'is not a linked resource', // @translate
            'lres' => 'is linked with resource with ID', // @translate
            'nlres' => 'is not linked with resource with ID', // @translate
            'lres' => 'is linked with resource with ID (expert)', // @translate
            'nlres' => 'is not linked with resource with ID (expert)', // @translate
            'lkq' => 'is linked with resources matching query (expert)', // @translate
            'nlkq' => 'is not linked with resources matching query (expert)', // @translate
            // Count.
            'ex' => 'has any value', // @translate
            'nex' => 'has no values', // @translate
            'exs' => 'has a single value', // @translate
            'nexs' => 'does not have a single value', // @translate
            'exm' => 'has multiple values', // @translate
            'nexm' => 'does not have multiple values', // @translate
            // Data type.
            'dtp' => 'has data type', // @translate
            'ndtp' => 'does not have data type', // @translate
            'tp' => 'has main type', // @translate
            'ntp' => 'does not have main type', // @translate
            'tpl' => 'has type literal-like', // @translate
            'ntpl' => 'does not have type literal-like', // @translate
            'tpr' => 'has type resource-like', // @translate
            'ntpr' => 'does not have type resource-like', // @translate
            'tpu' => 'has type uri-like', // @translate
            'ntpu' => 'does not have type uri-like', // @translate
            // Curation.
            'dup' => 'has duplicate values', // @translate
            'ndup' => 'does not have duplicate values', // @translate
            'dupt' => 'has duplicate values and type', // @translate
            'ndupt' => 'does not have duplicate values and type', // @translate
            'dupl' => 'has duplicate values and language', // @translate
            'ndupl' => 'does not have duplicate values and language', // @translate
            'duptl' => 'has duplicate values, type and language', // @translate
            'nduptl' => 'does not have duplicate values, type and language', // @translate
            'dupv' => 'has duplicate simple values', // @translate
            'ndupv' => 'does not have duplicate simple values', // @translate
            'dupvt' => 'has duplicate simple values and type', // @translate
            'ndupvt' => 'does not have duplicate simple values and type', // @translate
            'dupvl' => 'has duplicate simple values and language', // @translate
            'ndupvl' => 'does not have duplicate simple values and language', // @translate
            'dupvtl' => 'has duplicate simple values, type and language', // @translate
            'ndupvtl' => 'does not have duplicate simple values, type and language', // @translate
            'dupr' => 'has duplicate linked resources', // @translate
            'ndupr' => 'does not have duplicate linked resources', // @translate
            'duprt' => 'has duplicate linked resources and type', // @translate
            'nduprt' => 'does not have duplicate linked resources and type', // @translate
            'duprl' => 'has duplicate linked resources and language', // @translate
            'nduprl' => 'does not have duplicate linked resources and language', // @translate
            'duprtl' => 'has duplicate linked resources, type and language', // @translate
            'nduprtl' => 'does not have duplicate linked resources, type and language', // @translate
            'dupu' => 'has duplicate uris', // @translate
            'ndupu' => 'does not have duplicate uris', // @translate
            'duput' => 'has duplicate uris and type', // @translate
            'nduput' => 'does not have duplicate uris and type', // @translate
            'dupul' => 'has duplicate uris and language', // @translate
            'ndupul' => 'does not have duplicate uris and language', // @translate
            'duputl' => 'has duplicate uris, type and language', // @translate
            'nduputl' => 'does not have duplicate uris, type and language', // @translate
        ];
    }

    /**
     * Group mapping for filter type labels.
     */
    protected static function filterTypeGroups(): array
    {
        return [
            'Value' => ['eq', 'neq', 'in', 'nin', 'sw', 'nsw', 'ew', 'new', 'near', 'nnear', 'ma', 'nma', 'lt', 'lte', 'gte', 'gt', '<', '≤', '≥', '>', 'yreq', 'nyreq', 'yrgte', 'yrlte', 'yrgt', 'yrlt'], // @translate
            'Resource' => ['res', 'nres', 'resq', 'nresq'], // @translate
            'Linked resource' => ['lex', 'nlex', 'lres', 'nlres', 'lkq', 'nlkq'], // @translate
            'Count' => ['ex', 'nex', 'exs', 'nexs', 'exm', 'nexm'], // @translate
            'Data type' => ['dtp', 'ndtp', 'tp', 'ntp', 'tpl', 'ntpl', 'tpr', 'ntpr', 'tpu', 'ntpu'], // @translate
            'Curation' => ['dup', 'ndup', 'dupt', 'ndupt', 'dupl', 'ndupl', 'duptl', 'nduptl', 'dupv', 'ndupv', 'dupvt', 'ndupvt', 'dupvl', 'ndupvl', 'dupvtl', 'ndupvtl', 'dupr', 'ndupr', 'duprt', 'nduprt', 'duprl', 'nduprl', 'duprtl', 'nduprtl', 'dupu', 'ndupu', 'duput', 'nduput', 'dupul', 'ndupul', 'duputl', 'nduputl'], // @translate
        ];
    }

    /**
     * Default filter types for sites (exclude curation, symbols,
     * sub-query and expert linked resource types).
     */
    public static function defaultFilterTypes(): array
    {
        return [
            'eq', 'neq', 'in', 'nin',
            'sw', 'nsw', 'ew', 'new',
            'lt', 'lte', 'gte', 'gt',
            'yreq', 'nyreq', 'yrgte', 'yrlte',
            'res', 'nres',
            'lex', 'nlex',
            'ex', 'nex', 'exs', 'nexs', 'exm', 'nexm',
            'dtp', 'ndtp', 'tp', 'ntp',
        ];
    }

    public function setListSearchFields(array $listSearchFields): self
    {
        $this->listSearchFields = $listSearchFields;
        return $this;
    }
}
