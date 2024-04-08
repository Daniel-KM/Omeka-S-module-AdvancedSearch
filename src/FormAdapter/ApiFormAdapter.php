<?php declare(strict_types=1);

namespace AdvancedSearch\FormAdapter;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Query;
use Doctrine\DBAL\Connection;

/**
 * Simulate an api search.
 *
 * Only main search and properties are managed currently, with the joiner "and".
 *
 * This is not a sub-class of AbstractFormAdapter.
 */
class ApiFormAdapter implements FormAdapterInterface
{
    /**
     * @var Connection
     */
    protected $connection;

    protected $configFormClass = \AdvancedSearch\Form\Admin\ApiFormConfigFieldset::class;

    protected $label = 'Api'; // @translate

    /**
     * @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation
     */
    protected $searchConfig;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function setSearchConfig(?SearchConfigRepresentation $searchConfig): FormAdapterInterface
    {
        $this->searchConfig = $searchConfig;
        return $this;
    }

    public function getConfigFormClass(): ?string
    {
        return $this->configFormClass;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getFormClass(): ?string
    {
        return null;
    }

    public function getFormPartialHeaders(): ?string
    {
        return null;
    }

    public function getFormPartial(): ?string
    {
        return null;
    }

    public function getForm(array $options = []): ?\Laminas\Form\Form
    {
        return null;
    }

    public function renderForm(array $options = []): string
    {
        return '';
    }

    public function toQuery(array $request, array $formSettings): \AdvancedSearch\Query
    {
        $query = new Query();

        if (isset($request['search'])) {
            $query->setQuery($request['search']);
        }

        // The site id is managed differently currently (may not be a metadata).
        if (!empty($request['site_id']) && (int) $request['site_id']) {
            $query->setSiteId((int) $request['site_id']);
        }

        $this->buildMetadataQuery($query, $request, $formSettings);
        $this->buildPropertyQuery($query, $request, $formSettings);

        return $query;
    }

    /**
     * Apply search of metadata into a search query.
     *
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildQuery()
     *
     * @todo Manage negative search and missing parameters.
     */
    protected function buildMetadataQuery(Query $query, array $request, array $formSettings): void
    {
        if (empty($formSettings['metadata'])) {
            return;
        }

        $metadata = array_filter($formSettings['metadata']);
        if (empty($metadata)) {
            return;
        }

        // Copied from \Omeka\Api\Adapter\ItemAdapter::buildQuery(), etc.

        if (isset($metadata['is_public']) && isset($request['is_public'])) {
            $query->addFilter($metadata['is_public'], (bool) $request['is_public']);
        }

        if (isset($metadata['id']) && !empty($request['id'])) {
            $this->addIntegersFilterToQuery($query, $metadata['id'], $request['id']);
        }

        if (isset($metadata['owner_id']) && !empty($request['owner_id'])) {
            $this->addIntegersFilterToQuery($query, $metadata['owner_id'], $request['owner_id']);
        }

        if (isset($metadata['site_id']) && !empty($request['site_id'])) {
            $this->addIntegersFilterToQuery($query, $metadata['owner_id'], $request['owner_id']);
        }

        if (isset($metadata['created']) && !empty($request['created'])) {
            $this->addIntegersFilterToQuery($query, $metadata['created'], $request['created']);
        }

        if (isset($metadata['modified']) && !empty($request['modified'])) {
            $this->addIntegersFilterToQuery($query, $metadata['modified'], $request['modified']);
        }

        if (isset($metadata['resource_class_label']) && !empty($request['resource_class_label'])) {
            $this->addTextsFilterToQuery($query, $metadata['resource_class_label'], $request['resource_class_label']);
        }

        if (isset($metadata['resource_class_id']) && !empty($request['resource_class_id'])) {
            $this->addIntegersFilterToQuery($query, $metadata['resource_class_id'], $request['resource_class_id']);
        }

        if (isset($metadata['resource_template_id'])
            && isset($request['resource_template_id']) && is_numeric($request['resource_template_id'])
        ) {
            $this->addIntegersFilterToQuery($query, $metadata['resource_template_id'], $request['resource_template_id']);
        }

        if (isset($metadata['item_set_id']) && !empty($request['item_set_id'])) {
            $this->addIntegersFilterToQuery($query, $metadata['item_set_id'], $request['item_set_id']);
        }

        if (isset($metadata['is_open']) && isset($request['is_open'])) {
            $query->addFilter($metadata['is_open'], (bool) $request['is_open']);
        }

        // Module Access.
        if (isset($metadata['access']) && isset($request['access'])) {
            $query->addFilter($metadata['access'], (string) $request['access']);
        }
    }

    /**
     * Apply search of properties into a search query.
     *
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildPropertyQuery()
     *
     * @todo Manage negative search and missing parameters.
     *
     * @param Query $query
     * @param array $request
     * @param array $formSettings
     */
    protected function buildPropertyQuery(Query $query, array $request, array $formSettings): void
    {
        if (!isset($request['property']) || !is_array($request['property']) || empty($formSettings['properties'])) {
            return;
        }

        $properties = array_filter($formSettings['properties']);
        if (empty($properties)) {
            return;
        }

        $withoutValueQueryTypes = [
            'ex',
            'nex',
            'exs',
            'nexs',
            'exm',
            'nexm',
            'lex',
            'nlex',
        ];

        foreach ($request['property'] as $queryRow) {
            if (!(is_array($queryRow)
                && array_key_exists('property', $queryRow)
                && array_key_exists('type', $queryRow)
            )) {
                continue;
            }
            $property = $queryRow['property'];
            $queryType = $queryRow['type'];
            // $joiner = $queryRow['joiner']) ?? null;
            $value = $queryRow['text'] ?? null;

            if (!$value && !in_array($queryType, $withoutValueQueryTypes)) {
                continue;
            }

            // Narrow to specific property, if one is selected, else use search.
            $property = $this->normalizeProperty($property);
            // TODO Manage empty properties (main search and "any property").
            if (!$property) {
                continue;
            }
            if (empty($properties[$property])) {
                continue;
            }
            $propertyField = $properties[$property];

            // $positive = true;

            switch ($queryType) {
                case 'eq':
                    $query->addFilter($propertyField, $value);
                    break;

                case 'nlist':
                case 'list':
                    $list = is_array($value) ? $value : explode("\n", $value);
                    $list = array_filter(array_map('trim', $list), 'strlen');
                    if (empty($list)) {
                        continue 2;
                    }
                    $value = $list;
                    // no break;
                case 'neq':
                case 'nin':
                case 'in':
                case 'nsw':
                case 'sw':
                case 'new':
                case 'ew':
                case 'nma':
                case 'ma':
                case 'nnear':
                case 'near':
                case 'nres':
                case 'res':
                case 'nex':
                case 'ex':
                case 'nexs':
                case 'exs':
                case 'nexm':
                case 'exm':
                case 'nlex':
                case 'lex':
                case 'nlres':
                case 'lres':
                    $query->addFilterQuery($propertyField, $value, $queryType);
                    break;
                default:
                    continue 2;
            }
        }
    }

    /**
     * Get the term from a property string or integer.
     *
     * @todo Factorize with \AdvancedSearch\Mvc\Controller\Plugin\ApiSearch::normalizeProperty().
     *
     * @param string|int $termOrId
     */
    protected function normalizeProperty($termOrId): string
    {
        static $properties;

        if (!$termOrId) {
            return '';
        }

        if (is_null($properties)) {
            $sql = <<<'SQL'
SELECT
    CONCAT(vocabulary.prefix, ":", property.local_name),
    property.id
FROM property
JOIN vocabulary ON vocabulary.id = property.vocabulary_id
SQL;
            $properties = array_map('intval', $this->connection->executeQuery($sql)->fetchAllKeyValue());
        }

        if (is_numeric($termOrId)) {
            return array_search((int) $termOrId, $properties) ?: '';
        }

        $termOrId = (string) $termOrId;
        return isset($properties[$termOrId]) ? $termOrId : '';
    }

    /**
     * Add a filter for a single value.
     *
     * @param Query $query
     * @param string $filterName
     * @param string|array|int $value
     */
    protected function addTextFilterToQuery(Query $query, $filterName, $value): void
    {
        $dataValues = trim(is_array($value) ? array_shift($value) : $value);
        if (strlen($dataValues)) {
            $query->addFilter($filterName, $dataValues);
        }
    }

    /**
     * Add a numeric filter for a single value.
     *
     * @param Query $query
     * @param string $filterName
     * @param string|array|int $value
     */
    protected function addIntegerFilterToQuery(Query $query, $filterName, $value): void
    {
        $dataValues = (int) (is_array($value) ? array_shift($value) : $value);
        if ($dataValues) {
            $query->addFilter($filterName, $dataValues);
        }
    }

    /**
     * Add a filter for a value, and make it multiple.
     *
     * @param Query $query
     * @param string $filterName
     * @param string|array|int $value
     */
    protected function addTextsFilterToQuery(Query $query, $filterName, $value): void
    {
        $dataValues = is_array($value) ? $value : [$value];
        $dataValues = array_filter(array_map('trim', $dataValues), 'strlen');
        if ($dataValues) {
            $query->addFilter($filterName, $dataValues);
        }
    }

    /**
     * Add a numeric filter for a value, and make it multiple.
     *
     * @param Query $query
     * @param string $filterName
     * @param string|array|int $value
     */
    protected function addIntegersFilterToQuery(Query $query, $filterName, $value): void
    {
        $dataValues = is_array($value) ? $value : [$value];
        $dataValues = array_filter(array_map('intval', $dataValues));
        if ($dataValues) {
            $query->addFilter($filterName, $dataValues);
        }
    }
}
