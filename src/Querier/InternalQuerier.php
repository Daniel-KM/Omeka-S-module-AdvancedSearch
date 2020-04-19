<?php

namespace Search\Querier;

use ArrayObject;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;
use Search\Querier\Exception\QuerierException;
use Search\Response;

class InternalQuerier extends AbstractQuerier
{
    /**
     * MariaDB can only use 61 tables in a join and Omeka adds a join for each
     * property. To manage modules, the number is limited to 50.
     * So excluded fields can be used only when a small subset of properties is used.
     */
    const REQUEST_MAX_ARGS = 50;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var array
     */
    protected $resourceTypes;

    /**
     * @var \ArrayObject
     */
    protected $args;

    public function query()
    {
        $this->response = new Response;

        $this->args = $this->getPreparedQuery();
        if (is_null($this->args)) {
            return $this->response;
        }

        /**
         * @var \Omeka\Mvc\Controller\Plugin\Api $api
         */
        $plugins = $this->getServiceLocator()->get('ControllerPluginManager');
        $api = $plugins->get('api');

        foreach ($this->resourceTypes as $resourceType) {
            try {
                // Return scalar doesn't allow to get the total of results.
                $apiResponse = $api->search($resourceType, $this->args->getArrayCopy(), ['returnScalar' => 'id']);
                $totalResults = $api->search($resourceType, $this->args->getArrayCopy())->getTotalResults();
            } catch (\Omeka\Api\Exception\ExceptionInterface $e) {
                throw new QuerierException($e->getMessage(), $e->getCode(), $e);
            }
            $this->response->setResourceTotalResults($resourceType, $totalResults);
            $this->response->setTotalResults($this->response->getTotalResults() + $totalResults);
            if ($totalResults) {
                $result = array_map(function ($v) {
                    return ['id' => $v];
                }, $apiResponse->getContent());
            } else {
                $result = [];
            }
            $this->response->addResults($resourceType, $result);
        }

        // TODO To be removed when the filters will be groupable (see version 3.5.12 where this is after filter).
        // Facets data don't use sort or limit.
        if ($plugins->has('references')) {
            $facetData = clone $this->args;
            if (isset($facetData['sort_by'])) {
                unset($facetData['sort_by']);
            }
            if (isset($facetData['sort_order'])) {
                unset($facetData['sort_order']);
            }
            if (isset($facetData['limit'])) {
                unset($facetData['limit']);
            }
            if (isset($facetData['offset'])) {
                unset($facetData['offset']);
            }

            $this->facetResponse($facetData);
        }

        return $this->response;
    }

    /**
     * @return array|null Arguments for the Omeka api, or null if unprocessable
     * or empty results.
     *
     * List of resource types is prepared too.
     *
     * {@inheritDoc}
     * @see \Search\Querier\AbstractQuerier::getPreparedQuery()
     */
    public function getPreparedQuery()
    {
        if (empty($this->query)) {
            $this->args = null;
            return $this->args;
        }

        // The data are the ones used to build the query with the standard api.
        // Queries are multiple (one by resource type and by facet).
        // Note: the query is a scalar one, so final events are not triggered.
        // TODO Do a full api reference search or only scalar ids?
        $this->args = new ArrayObject;

        // TODO Normalize search url arguments. Here, the ones from default form, adapted from Solr, are taken.

        $indexerResourceTypes = $this->getSetting('resources', []);
        $this->resourceTypes = $this->query->getResources() ?: $indexerResourceTypes;
        $this->resourceTypes = array_intersect($this->resourceTypes, $indexerResourceTypes);
        if (empty($this->resourceTypes)) {
            $this->args = null;
            return $this->args;
        }

        $isDefaultQuery = $this->defaultQuery();
        if (!$isDefaultQuery) {
            $this->mainQuery();
        }

        // "is_public" is automatically managed by the api, but there may be an
        // option in the form.
        // TODO Manage an option "is_public".

        // The site is a specific filter that can be used as part of main query.
        $siteId = $this->query->getSiteId();
        if ($siteId) {
            $this->args['site_id'] = $siteId;
        }

        $this->filterQuery();

        if (!empty($this->args['property'])
            && count($this->args['property']) > self::REQUEST_MAX_ARGS
        ) {
            $services = $this->getServiceLocator();
            $params = $services->get('ControllerPluginManager')->get('params');
            $req = $params->fromQuery();
            unset($req['csrf']);
            $req = urldecode(http_build_query(array_filter($req)));
            $messenger = new Messenger;
            $logger = $this->getServiceLocator()->get('Omeka\Logger');
            if ($this->query->getExcludedFields()) {
                $message = new Message('The query "%1$s" uses %2$d properties, that is more than the %3$d supported currently. Excluded fields are removed.', // @translate
                    $req, count($this->args['property']), self::REQUEST_MAX_ARGS);
                $this->query->setExcludedFields([]);
                $messenger->addWarning($message);
                $logger->warn($message);
                return $this->getPreparedQuery();
            }

            $message = new Message('The query "%1$s" uses %2$d properties, that is more than the %3$d supported currently. Request is troncated.', // @translate
                $req, count($this->args['property']), self::REQUEST_MAX_ARGS);
            $messenger->addWarning($message);
            $logger->warn($message);
            $this->args['property'] = array_slice($this->args['property'], 0, self::REQUEST_MAX_ARGS);
        }

        $sort = $this->query->getSort();
        if ($sort) {
            list($sortField, $sortOrder) = explode(' ', $sort);
            $this->args['sort_by'] = $sortField;
            $this->args['sort_order'] = $sortOrder === 'desc' ? 'desc' : 'asc';
        }

        $limit = $this->query->getLimit();
        if ($limit) {
            $this->args['limit'] = $limit;
        }

        $offset = $this->query->getOffset();
        if ($offset) {
            $this->args['offset'] = $offset;
        }

        return $this->args;
    }

    protected function defaultQuery()
    {
        $q = $this->query->getQuery();
        if (strlen($q)) {
            return false;
        }

        $this->args->exchangeArray([]);
        return true;
    }

    protected function mainQuery()
    {
        $q = $this->query->getQuery();
        if (!strlen($q)) {
            return;
        }

        if ($this->query->getExcludedFields()) {
            $this->mainQueryWithExcludedFields();
            return;
        }

        // Try to support the exact search and the full text search.
        if (mb_substr($q, 0, 1) === '"' && mb_substr($q, -1) === '"') {
            $q = trim($q, '" ');
            $this->args['property'][] = [
                'joiner' => 'and',
                'property' => '',
                'type' => 'in',
                'text' => $q,
            ];
            return;
        }

        // The fullt text search is not available via standard api, but only
        // in a special request (see \Omeka\Module::searchFulltext()).
        $qq = array_filter(array_map('trim', explode(' ', $q)), 'mb_strlen');
        foreach ($qq as $qw) {
            $this->args['property'][] = [
                'joiner' => 'and',
                'property' => '',
                'type' => 'in',
                'text' => $qw,
            ];
        }
    }

    /**
     * Support of excluded fields is very basic and uses "or" instead of "and".
     *
     * Important: MariaDB can only use 61 tables in a join.
     *
     * @todo Add support of grouped query (mutliple properties and/or multiple other properties).
     */
    protected function mainQueryWithExcludedFields()
    {
        $q = $this->query->getQuery();

        // Currently, the only way to exclude fields is to search in all other
        // fields.
        $usedFields = $this->getUsedPropertyByIds();
        $excludedFields = $this->query->getExcludedFields();
        $usedFields = array_diff($usedFields, $excludedFields);
        if (!count($usedFields)) {
            return;
        }

        // Try to support the exact search and the full text search.
        if (mb_substr($q, 0, 1) === '"' && mb_substr($q, -1) === '"') {
            $q = trim($q, '" ');
            foreach ($usedFields as $propertyId) {
                $this->args['property'][] = [
                    'joiner' => 'or',
                    'property' => $propertyId,
                    'type' => 'in',
                    'text' => $q,
                ];
            }
            return;
        }

        // The fullt text search is not available via standard api, but only
        // in a special request (see \Omeka\Module::searchFulltext()).
        $qq = array_filter(array_map('trim', explode(' ', $q)), 'mb_strlen');
        foreach ($qq as $qw) {
            foreach ($usedFields as $propertyId) {
                $this->args['property'][] = [
                    'joiner' => 'or',
                    'property' => $propertyId,
                    'type' => 'in',
                    'text' => $qw,
                ];
            }
        }
    }

    /**
     * Filter the query.
     *
     * @todo FIx the process for facets: all the facets should be displayed, and "or" by group of facets.
     * @todo Make core search properties groupable ("or" inside a group, "and" between group).
     *
     * Note: when a facet is selected, it is managed like a filter.
     */
    protected function filterQuery()
    {
        // Don't use excluded fields for filters.
        $filters = $this->query->getFilters();

        foreach ($filters as $name => $values) {
            // "is_public" is automatically managed by this internal adapter.
            // "creation_date_year_field" should be a property.
            // "date_range_field" is managed below.
            switch ($name) {
                case 'is_public':
                case 'is_public_field':
                    continue 2;

                case 'item_set_id':
                case 'item_set_id_field':
                    if (!is_array($values)) {
                        $values = [$values];
                    } elseif (is_array(reset($values))) {
                        $values = array_merge(...$values);
                    }
                    $this->args['item_set_id'] = array_filter(array_map('intval', $values));
                    continue 2;

                case 'resource_class_id':
                case 'resource_class_id_field':
                    if (!is_array($values)) {
                        $values = [$values];
                    } elseif (is_array(reset($values))) {
                        $values = array_merge(...$values);
                    }
                    $this->args['resource_class_id'] = is_numeric(reset($values))
                        ? array_filter(array_map('intval', $values))
                        : $this->listResourceClassIds($values);
                    continue 2;

                case 'resource_template_id':
                case 'resource_template_id_field':
                    if (!is_array($values)) {
                        $values = [$values];
                    } elseif (is_array(reset($values))) {
                        $values = array_merge(...$values);
                    }
                    $this->args['resource_template_id'] = is_numeric(reset($values))
                        ? array_filter(array_map('intval', $values))
                        : $this->listResourceTemplateIds($values);
                    continue 2;

                default:
                    foreach ($values as $value) {
                        // Use "or" when multiple (checkbox), else "and" (radio).
                        if (is_array($value)) {
                            foreach ($value as $val) {
                                $this->args['property'][] = [
                                    'joiner' => 'or',
                                    'property' => $name,
                                    'type' => 'eq',
                                    'text' => $val,
                                ];
                            }
                        } else {
                            $this->args['property'][] = [
                                'joiner' => 'and',
                                'property' => $name,
                                'type' => 'eq',
                                'text' => $value,
                            ];
                        }
                    }
                    break;
            }
        }

        // TODO Manage the date range filters (one or two properties?).
        /*
        $dateRangeFilters = $this->query->getFiltersDateRange();
        foreach ($dateRangeFilters as $name => $filterValues) {
            foreach ($filterValues as $filterValue) {
                $start = $filterValue['start'] ? $filterValue['start'] : '*';
                $end = $filterValue['end'] ? $filterValue['end'] : '*';
                $this->args['property'][] = [
                    'joiner' => 'and',
                    'property' => 'dcterms:date',
                    'type' => 'gte',
                    'text' => $start,
                ];
                $this->args['property'][] = [
                    'joiner' => 'and',
                    'property' => 'dcterms:date',
                    'type' => 'lte',
                    'text' => $end,
                ];
            }
        }
        */

        $filters = $this->query->getFilterQueries();
        foreach ($filters as $name => $values) {
            foreach ($values as $value) {
                $this->args['property'][] = [
                    'joiner' => $value['joiner'],
                    'property' => $name,
                    'type' => $value['type'],
                    'text' => $value['value'],
                ];
            }
        }
    }

    protected function facetResponse(ArrayObject $facetData)
    {
        /** @var \Reference\Mvc\Controller\Plugin\References $references */
        $references = $this->getServiceLocator()->get('ControllerPluginManager')->get('references');

        $facetFields = $this->query->getFacetFields();
        $facetLimit = $this->query->getFacetLimit();
        $facetLanguages = $this->query->getFacetLanguages();

        $metadataFieldsToNames = [
            'is_public' => 'is_public',
            'is_public_field' => 'is_public',
            'item_set_id' => 'o:item_set',
            'item_set_id_field' => 'o:item_set',
            'resource_class_id' => 'o:resource_class',
            'resource_class_id_field' => 'o:resource_class',
            'resource_template_id' => 'o:resource_template',
            'resource_template_id_field' => 'o:resource_template',
        ];

        // For items and item sets.
        $fields = array_map(function ($v) use ($metadataFieldsToNames) {
            return isset($metadataFieldsToNames[$v]) ? $metadataFieldsToNames[$v] : $v;
        }, $facetFields);

        foreach ($this->resourceTypes as $resourceType) {
            $options = [
                'resource_name' => $resourceType,
                // Options sql.
                'per_page' => $facetLimit,
                'page' => 1,
                'sort_by' => 'total',
                'sort_order' => 'DESC',
                'filters' => [
                    'languages' => $facetLanguages,
                ],
                'values' => [],
                // Output options.
                'first_id' => false,
                'initial' => false,
                'lang' => false,
                'include_without_meta' => false,
                'output' => 'associative',
            ];

            $values = $references
                ->setMetadata($fields)
                ->setQuery($facetData->getArrayCopy())
                ->setOptions($options)
                ->list();

            $key = 0;
            foreach ($values as $result) {
                foreach ($result['o-module-reference:values'] as $value => $count) {
                    $this->response->addFacetCount($facetFields[$key], $value, $count);
                }
                ++$key;
            }
        }
    }

    /**
     * Convert a list of terms into a list of property ids.
     *
     * @see \Reference\Mvc\Controller\Plugin\Reference::listPropertyIds()
     *
     * @param array $values
     * @return array Only values that are terms are converted into ids, the
     * other are removed.
     */
    protected function listPropertyIds(array $values)
    {
        $values = array_filter(array_map(function ($term) {
            return $this->isTerm($term) ? $term : null;
        }, $values));
        return array_intersect_key($this->getPropertyIds(), array_fill_keys($values, null));
    }

    /**
     * Get all property terms by id, ordered by descendant total values.
     *
     * @return array Associative array of ids by term.
     */
    protected function getUsedPropertyByIds()
    {
        static $properties;

        if (is_null($properties)) {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->getServiceLocator()->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select([
                    'DISTINCT property.id AS id',
                    "CONCAT(vocabulary.prefix, ':', property.local_name) AS term",
                    // 'COUNT(value.id) AS total',
                ])
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->innerJoin('property', 'value', 'value', 'property.id = value.property_id')
                ->addGroupBy('id')
                ->orderBy('COUNT(value.id)', 'DESC')
            ;
            $stmt = $connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $properties = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $properties = array_column($properties, 'term', 'id');
        }

        return $properties;
    }

    /**
     * Get all property ids by term.
     *
     * @see \Reference\Mvc\Controller\Plugin\Reference::getPropertyIds()
     *
     * @return array Associative array of ids by term.
     */
    protected function getPropertyIds()
    {
        static $properties;

        if (is_null($properties)) {
            /** @var \Doctrine\ORM\EntityManager $entityManager */
            $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
            $qb = $entityManager->createQueryBuilder();
            $expr = $qb->expr();
            $qb
                ->select([
                    "CONCAT(vocabulary.prefix, ':', property.localName) AS term",
                    'property.id AS id',
                ])
                ->from(\Omeka\Entity\Property::class, 'property')
                ->innerJoin(
                    \Omeka\Entity\Vocabulary::class,
                    'vocabulary',
                    \Doctrine\ORM\Query\Expr\Join::WITH,
                    $expr->eq('vocabulary.id', 'property.vocabulary')
                )
            ;

            $properties = $qb->getQuery()->getScalarResult();
        }

        return $properties;
    }

    /**
     * Convert a list of terms into a list of resource class ids.
     *
     * @see \Reference\Mvc\Controller\Plugin\Reference::listResourceClassIds()
     *
     * @param array $values
     * @return array Only values that are terms are converted into ids, the
     * other are removed.
     */
    protected function listResourceClassIds(array $values)
    {
        return array_values(array_intersect_key($this->getResourceClassIds(), array_fill_keys($values, null)));
    }

    /**
     * Get all resource class ids by term.
     *
     * @see \Reference\Mvc\Controller\Plugin\Reference::getResourceClassIds()
     *
     * @return array Associative array of ids by term.
     */
    protected function getResourceClassIds()
    {
        static $resourceClasses;

        if (is_null($resourceClasses)) {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->getServiceLocator()->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select([
                    "CONCAT(vocabulary.prefix, ':', resource_class.local_name) AS term",
                    'resource_class.id AS id',
                ])
                ->from('resource_class', 'resource_class')
                ->innerJoin('resource_class', 'vocabulary', 'vocabulary', 'resource_class.vocabulary_id = vocabulary.id')
            ;
            $stmt = $connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $resourceClasses = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $resourceClasses = array_column($resourceClasses, 'id', 'term');
        }

        return $resourceClasses;
    }

    /**
     * Convert a list of terms into a list of resource template ids.
     *
     * @param array $values
     * @return array Only values that are labels are converted into ids, the
     * other are removed.
     */
    protected function listResourceTemplateIds(array $values)
    {
        return array_values(array_intersect_key($this->getResourceTemplateIds(), array_fill_keys($values, null)));
    }

    /**
     * Get all resource template ids by label.
     *
     * @return array Associative array of ids by label.
     */
    protected function getResourceTemplateIds()
    {
        static $resourceTemplates;

        if (is_null($resourceTemplates)) {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->getServiceLocator()->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select([
                    'resource_template.label AS label',
                    'resource_template.id AS id',
                ])
                ->from('resource_template', 'resource_template')
            ;
            $stmt = $connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $resourceTemplates = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $resourceTemplates = array_column($resourceTemplates, 'id', 'label');
        }

        return $resourceTemplates;
    }
}
