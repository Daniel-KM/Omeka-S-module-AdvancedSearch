<?php declare(strict_types=1);

namespace AdvancedSearch\Adapter;

class InternalAdapter extends AbstractAdapter
{
    protected $label = 'Internal [sql]'; // @translate

    protected $configFieldsetClass = \AdvancedSearch\Form\Admin\InternalConfigFieldset::class;

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
        $multiFields = $this->searchEngine ? $this->searchEngine->settingAdapter('multifields', []) : [];
        // Don't bypass default fields with the specific ones.
        $fields = array_merge($fields, $multiFields);

        /** @var \Common\Stdlib\EasyMeta $easyMeta */
        $easyMeta = $this->getServiceLocator()->get('EasyMeta');
        $propertyLabelsByTerms = $easyMeta->propertyLabels();
        foreach ($propertyLabelsByTerms as $term => $label) {
            $propertyLabelsByTerms[$term] = ['name' => $term, 'label' => $label];
        }

        return $availableFields = array_merge($fields, $propertyLabelsByTerms);
    }

    public function getAvailableSortFields(): array
    {
        static $sortFields;

        if (isset($sortFields)) {
            return $sortFields;
        }

        $availableFields = $this->getAvailableFields();

        // There is no default score sort.
        $sortFields = [];

        $translator = $this->getServiceLocator()->get('MvcTranslator');

        $directionLabels = [
            'asc' => $translator->translate('Asc'),
            'desc' => $translator->translate('Desc'),
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

    public function getAvailableFacetFields(): array
    {
        return $this->getAvailableFields();
    }

    public function getAvailableFieldsForSelect(): array
    {
        static $availableFields;

        if (isset($availableFields)) {
            return $availableFields;
        }

        $fields = [];

        // Display specific fields first.
        $multifields = $this->searchEngine
            ? $this->searchEngine->settingAdapter('multifields', [])
            : [];
        if ($multifields) {
            $fields['multifieds'] = [
                'label' => 'Multi-fields', // @translate
                'options' => array_replace(
                    array_column($multifields, 'name', 'name'),
                    array_filter(array_column($multifields, 'label', 'name'))
                ),
            ];
        }

        // Display generic fields second, so they cannot be bypassed.
        $defaultFields = $this->getDefaultFields();
        $fields['generic'] = [
            'label' => 'Generic', // @translate
            'options' => array_column($defaultFields, 'label', 'name'),
        ];

        /** @var \Common\Stdlib\EasyMeta $easyMeta */
        $easyMeta = $this->getServiceLocator()->get('EasyMeta');
        $vocabularies = $easyMeta->vocabularyLabels();
        $propertyLabelsByTerms = $easyMeta->propertyLabels();

        // Set Dublin Core terms and types first vocabularies.
        // There is no property in dctype.
        $fields['dcterms'] = [];
        foreach ($propertyLabelsByTerms as $term => $label) {
            $prefix = strtok($term, ':');
            if (empty($fields[$prefix]['label'])) {
                $fields[$prefix] = [
                    'label' => $vocabularies[$prefix],
                    'options' => [],
                ];
            }
            $fields[$prefix]['options'][$term] = $label;
        }

        return $fields;
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
