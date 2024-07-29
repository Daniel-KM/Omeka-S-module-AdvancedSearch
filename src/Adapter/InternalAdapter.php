<?php declare(strict_types=1);

namespace AdvancedSearch\Adapter;

class InternalAdapter extends AbstractAdapter
{
    protected $label = 'Internal [sql]'; // @translate

    // TODO No specific engine config, but specificities in config configure.
    protected $configFieldsetClass = null;

    protected $indexerClass = \AdvancedSearch\Indexer\InternalIndexer::class;

    protected $querierClass = \AdvancedSearch\Querier\InternalQuerier::class;

    public function getAvailableFields(): array
    {
        static $availableFields;

        if (isset($availableFields)) {
            return $availableFields;
        }

        // Special fields of Omeka.
        // The mapping is set by default.
        $fields = $this->getDefaultFields();

        // Display specific fields first, but keep standard fields.
        $aliases = $this->searchConfig
            ? $this->searchConfig->subSetting('index', 'aliases', [])
            : [];
        // Don't bypass default fields with the specific ones.
        $fields = array_merge($fields, $aliases);

        /** @var \Common\Stdlib\EasyMeta $easyMeta */
        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');
        $propertyLabelsByTerms = $easyMeta->propertyLabels();
        foreach ($propertyLabelsByTerms as $term => $label) {
            $propertyLabelsByTerms[$term] = ['name' => $term, 'label' => $label];
        }

        return $availableFields = array_merge($fields, $propertyLabelsByTerms);
    }

    public function getAvailableFacetFields(): array
    {
        return $this->getAvailableFields();
    }

    public function getAvailableSortFields(): array
    {
        static $sortFields;

        if (isset($sortFields)) {
            return $sortFields;
        }

        $availableFields = $this->getAvailableFields();

        $translator = $this->getServiceLocator()->get('MvcTranslator');

        $directionLabels = [
            'asc' => $translator->translate('Asc'),
            'desc' => $translator->translate('Desc'),
        ];

        // There is no default score sort, except for full text search.
        // According to mysql, the default for relevance is "desc".
        $sortFields = [
            'relevance desc' => [
                'name' => 'relevance desc',
                'label' => $translator->translate('Relevance'), // @translate
            ],
            'relevance asc' => [
                'name' => 'relevance asc',
                'label' => $translator->translate('Relevance (inversed)'), // @translate
            ],
        ];

        foreach ($availableFields as $name => $availableField) {
            $fieldName = $availableField['name'];
            $fieldLabel = $availableField['label'];
            foreach ($directionLabels as $direction => $labelDirection) {
                $name = $fieldName . ' ' . $direction;
                $sortFields[$name] = [
                    'name' => $name,
                    'label' => $fieldLabel ? $fieldLabel . ' ' . $labelDirection : '',
                ];
            }
        }

        return $sortFields;
    }

    public function getAvailableFieldsForSelect(): array
    {
        static $fields;

        if (isset($fields)) {
            return $fields;
        }

        // Special fields of Omeka.
        $defaultFields = $this->getDefaultFields();

        /**
         * @var \Omeka\Api\Representation\VocabularyRepresentation $vocabulary
         */
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $easyMeta = $services->get('Common\EasyMeta');
        $viewHelperManager = $services->get('ViewHelperManager');
        $translate = $viewHelperManager->get('translate');

        $vocabularies = $api->search('vocabularies', ['sort_by' => 'label'])->getContent();
        $propertyLabelsByTerms = $easyMeta->propertyLabels();
        foreach ($propertyLabelsByTerms as &$label) {
            $label = $translate($label);
        }
        unset($label);
        asort($propertyLabelsByTerms);

        // Don't bypass default fields with the specific ones, so remove them.
        $aliases = $this->searchConfig
            ? $this->searchConfig->subSetting('index', 'aliases', [])
            : [];
        $aliases = array_diff_key($aliases, $defaultFields, $propertyLabelsByTerms);

        $fields = [];
        $fields['metadata'] = [
            'label' => 'Metadata', // @translate
            'options' => array_column($defaultFields, 'label', 'name'),
        ];
        $fields['aliases'] = [
            'label' => 'Aliases and aggregated fields', // @translate
            'options' => array_replace(
                array_column($aliases, 'name', 'name'),
                array_filter(array_column($aliases, 'label', 'name'))
            ),
        ];

        // Set Dublin Core terms and types first vocabularies.
        // There is no property in dctype.
        $properties = ['dcterms' => []];
        foreach ($vocabularies as $vocabulary) {
            $prefix = $vocabulary->prefix();
            $properties[$prefix] = [
                'label' => $vocabulary->label(),
                'options' => [],
            ];
            foreach ($propertyLabelsByTerms as $term => $label) {
                if (strtok($term, ':') === $prefix) {
                    $properties[$prefix]['options'][$term] = $label;
                }
            }
        }

        $fields += $properties;

        $fields = array_filter($fields);

        return $fields;
    }

    public function getAvailableFacetFieldsForSelect(): array
    {
        return $this->getAvailableFieldsForSelect();
    }

    public function getAvailableSortFieldsForSelect(): array
    {
        static $sortFields;

        if (isset($sortFields)) {
            return $sortFields;
        }

        $availableFields = $this->getAvailableFieldsForSelect();

        $translator = $this->getServiceLocator()->get('MvcTranslator');

        $directionLabels = [
            'asc' => $translator->translate('ascendant'), // @ŧranslate
            'desc' => $translator->translate('descendant'), // @translate
        ];

        // There is no default score sort, except for full text search.
        // According to mysql, the default for relevance is "desc".
        $sortFields = [
            'relevance desc' =>$translator->translate('Relevance'), // @translate
            'relevance asc' => $translator->translate('Relevance (inversed)'), // @translate
        ];

        foreach ($availableFields as $name => $availableField) {
            if (!is_array($availableField)) {
                $sortFields[$name] = $availableField;
                continue;
            }
            // Manage grouped fields.
            $sortFields[$name] = $availableField;
            $options = [];
            foreach ($availableField['options'] ?? [] as $optionName => $optionLabel) {
                foreach ($directionLabels as $direction => $labelDirection) {
                    $sortName = $optionName . ' ' . $direction;
                    $options[$sortName] = strlen((string) $optionLabel) ? $optionLabel . ' ' . $labelDirection : $sortName;
                }
            }
            $sortFields[$name]['options'] = $options;
        }

        return $sortFields;
    }

    protected function getDefaultFields(): array
    {
        // Field names are directly managed by the form adapter and the querier.
        // It's always possible to use the standard names anyway.

        return [
            // The resource name: "items", "item_sets", etc.
            'resource_type' => [
                'name' => 'resource_type',
                'label' => 'Resource type', // @translate
                'from' => 'resource_type',
                'to' => 'resource_type',
            ],
              'id' => [
                'name' => 'id',
                'label' => 'Resource id', // @translate
                'from' => 'id',
                'to' => 'id',
            ],
            // Public field cannot be managed with internal adapter.
            /*
            'is_public' => [
                'name' => 'is_public',
                'label' => 'Public',
                'from' => 'is_public',
                'to' => 'is_public',
            ],
            */
            // TODO Manage query on owner (only one in core).
            'owner_id' => [
                'name' => 'owner_id',
                'label' => 'Owner',
                'from' => 'owner/o:id',
                'to' => 'owner_id',
            ],
            'site_id' => [
                'name' => 'site_id',
                'label' => 'Site',
                'from' => 'site/o:id',
                'to' => 'site_id',
            ],
            'resource_class_id' => [
                'name' => 'resource_class_id',
                'label' => 'Resource class',
                'from' => 'resource_class/o:id',
                'to' => 'resource_class_id',
            ],
            'resource_template_id' => [
                'name' => 'resource_template_id',
                'label' => 'Resource template',
                'from' => 'resource_template/o:id',
                'to' => 'resource_template_id',
            ],
            'item_set_id' => [
                'name' => 'item_set_id',
                'label' => 'Item set',
                'from' => 'item_set/o:id',
                'to' => 'item_set_id',
            ],
            // Module Access.
            'access' => [
                'name' => 'access',
                'label' => 'Access',
                'from' => 'access',
                'to' => 'access',
            ],
            // Module Item Sets Tree.
            'item_sets_tree' => [
                'name' => 'item_sets_tree',
                'label' => 'Item sets tree',
                'from' => 'item_sets_tree',
                'to' => 'item_sets_tree',
            ],
            // Module Thesaurus.
            'thesaurus' => [
                'name' => 'thesaurus',
                'label' => 'Thesaurus',
                'from' => 'item/o:id',
                'to' => 'thesaurus',
            ],
        ];
    }
}
