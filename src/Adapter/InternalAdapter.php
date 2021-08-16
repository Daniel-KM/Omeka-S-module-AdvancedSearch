<?php declare(strict_types=1);

namespace AdvancedSearch\Adapter;

use AdvancedSearch\Api\Representation\SearchEngineRepresentation;

class InternalAdapter extends AbstractAdapter
{
    public function getLabel(): string
    {
        return 'Internal [sql]'; // @translate
    }

    public function getConfigFieldset(): ?\Laminas\Form\Fieldset
    {
        return null;
    }

    public function getIndexerClass(): string
    {
        return \AdvancedSearch\Indexer\InternalIndexer::class;
    }

    public function getQuerierClass(): string
    {
        return \AdvancedSearch\Querier\InternalQuerier::class;
    }

    public function getAvailableFields(SearchEngineRepresentation $engine): array
    {
        static $availableFields;

        if (isset($availableFields)) {
            return $availableFields;
        }

        // Use a direct query to avoid a memory overload when there are many
        // vocabularies.
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        $qb = $connection->createQueryBuilder();
        $qb
            ->select(
                'CONCAT(vocabulary.prefix, ":", property.local_name) AS "name"',
                'property.label AS "label"'
            )
            ->from('property', 'property')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
            ->addOrderBy('vocabulary.id', 'ASC')
            ->addOrderBy('property.local_name', 'ASC');

        $stmt = $connection->executeQuery($qb);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Public field cannot be managed with internal adapter.
        $fields = [];
        $fields['item_set_id'] = [
            'name' => 'item_set_id',
            'label' => 'Item set',
        ];
        $fields['resource_class_id'] = [
            'name' => 'resource_class_id',
            'label' => 'Resource class',
        ];
        $fields['resource_template_id'] = [
            'name' => 'resource_template_id',
            'label' => 'Resource template',
        ];
        // TODO Manage query on owner (only one in core).
        /*
        $fields['owner_id'] = [
            'name' => 'owner_id',
            'label' => 'Owner',
        ];
        */
        foreach ($result as $field) {
            $fields[$field['name']] = $field;
        }

        return $availableFields = $fields;
    }

    public function getAvailableSortFields(SearchEngineRepresentation $engine): array
    {
        static $sortFields;

        if (isset($sortFields)) {
            return $sortFields;
        }

        $availableFields = $this->getAvailableFields($engine);

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

    public function getAvailableFacetFields(SearchEngineRepresentation $engine): array
    {
        return $this->getAvailableFields($engine);
    }
}
