<?php
/**
 * AdvancedSearchPlus
 *
 * Add some fields to the advanced search form (before/after creation date, has
 * media, etc.).
 *
 * @copyright Daniel Berthereau, 2018
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
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
namespace AdvancedSearchPlus;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Adapter\ItemAdapter;
use Omeka\Module\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // Add the search query filters for resources.
        $adapters = [
            \Omeka\Api\Adapter\ItemAdapter::class,
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            \Omeka\Api\Adapter\MediaAdapter::class,
            // TODO Add user.
        ];
        foreach ($adapters as $adapter) {
            $sharedEventManager->attach(
                $adapter,
                'api.search.query',
                [$this, 'searchQuery']
            );
        }

        // Add the search field to the admin and public advanced search page.
        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
            // 'Omeka\Controller\Site\Item',
            // 'Omeka\Controller\Site\ItemSet',
            // 'Omeka\Controller\Site\Media',
            // TODO Add user.
        ];
        foreach ($controllers as $controller) {
            $sharedEventManager->attach(
                $controller,
                'view.advanced_search',
                [$this, 'displayAdvancedSearch']
            );
        }

        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
            // 'Omeka\Controller\Site\Item',
            // 'Omeka\Controller\Site\ItemSet',
            // 'Omeka\Controller\Site\Media',
            // TODO Add user.
        ];
        foreach ($controllers as $controller) {
            // Filter the search filters for the advanced search pages.
            $sharedEventManager->attach(
                $controller,
                'view.search.filters',
                [$this, 'filterSearchFilters']
            );
        }
    }

    /**
     * Helper to filter search queries.
     *
     * @param Event $event
     */
    public function searchQuery(Event $event)
    {
        /** @var \Doctrine\ORM\QueryBuilder $qb */
        $qb = $event->getParam('queryBuilder');
        $adapter = $event->getTarget();
        $query = $event->getParam('request')->getContent();
        $this->searchDateTime($qb, $adapter, $query);
        if ($adapter instanceof ItemAdapter) {
            $this->searchHasMedia($qb, $adapter, $query);
        }
    }

    /**
     * Display the advanced search form via partial.
     *
     * @param Event $event
     */
    public function displayAdvancedSearch(Event $event)
    {
        $query = $event->getParam('query', []);
        $query['datetime'] = isset($query['datetime']) ? $query['datetime'] : '';

        $partials = $event->getParam('partials', []);
        $partials[] = 'common/advanced-search-datetime';

        $resourceType = $event->getParam('resourceType');
        if ($resourceType === 'item') {
            $query['has_media'] = isset($query['has_media']) ? $query['has_media'] : '';
            $partials[] = 'common/advanced-search-has-media';
        }

        $event->setParam('query', $query);
        $event->setParam('partials', $partials);
    }

    /**
     * Filter search filters.
     *
     * @param Event $event
     */
    public function filterSearchFilters(Event $event)
    {
        $translate = $event->getTarget()->plugin('translate');
        $query = $event->getParam('query', []);
        $filters = $event->getParam('filters');

        $query = $this->normalizeDateTime($query);
        if (!empty($query['datetime'])) {
            $queryTypes = [
                'gt' => $translate('after'),
                'gte' => $translate('after or on'),
                'eq' => $translate('on'),
                'neq' => $translate('not on'),
                'lte' => $translate('before or on'),
                'lt' => $translate('before'),
                'ex' => $translate('has any date / time'),
                'nex' => $translate('has no date / time'),
            ];

            $value = $query['datetime'];
            $index = 0;
            foreach ($value as $queryRow) {
                $joiner = $queryRow['joiner'];
                $field = $queryRow['field'];
                $type = $queryRow['type'];
                $datetimeValue = $queryRow['value'];

                $fieldLabel = $field === 'modified' ? $translate('Modified') : $translate('Created');
                $filterLabel = $fieldLabel . ' ' . $queryTypes[$type];
                if ($index > 0) {
                    if ($joiner === 'or') {
                        $filterLabel = $translate('OR') . ' ' . $filterLabel;
                    } else {
                        $filterLabel = $translate('AND') . ' ' . $filterLabel;
                    }
                }
                $filters[$filterLabel][] = $datetimeValue;
                ++$index;
            }
        }

        if (isset($query['has_media'])) {
            $value = $query['has_media'];
            if ($value) {
                $filterLabel = $translate('Has media'); // @translate
                $filters[$filterLabel][] = $translate('yes'); // @translate
            } elseif ($value !== '') {
                $filterLabel = $translate('Has media'); // @translate
                $filters[$filterLabel][] = $translate('no'); // @translate
            }
        }

        $event->setParam('filters', $filters);
    }

    /**
     * Normalize the query for the datetime.
     *
     * @param array $query
     * @return array
     */
    protected function normalizeDateTime(array $query)
    {
        if (empty($query['datetime'])) {
            return $query;
        }

        // Manage a single date time.
        if (!is_array($query['datetime'])) {
            $query['datetime'] = [[
                'joiner' => 'and',
                'field' => 'created',
                'type' => 'eq',
                'value' => $query['datetime'],
            ]];
            return $query;
        }

        foreach ($query['datetime'] as $key => &$queryRow) {
            if (empty($queryRow)) {
                unset($query['datetime'][$key]);
                continue;
            }

            // Clean query and manage default values.
            if (is_array($queryRow)) {
                $queryRow = array_map('strtolower', array_map('trim', $queryRow));
                if (empty($queryRow['joiner'])) {
                    $queryRow['joiner'] = 'and';
                } else {
                    if (!in_array($queryRow['joiner'], ['and', 'or'])) {
                        unset($query['datetime'][$key]);
                        continue;
                    }
                }

                if (empty($queryRow['field'])) {
                    $queryRow['field'] = 'created';
                } else {
                    if (!in_array($queryRow['field'], ['created', 'modified'])) {
                        unset($query['datetime'][$key]);
                        continue;
                    }
                }

                if (empty($queryRow['type'])) {
                    $queryRow['type'] = 'eq';
                } else {
                    // "ex" and "nex" are useful only for the modified time.
                    if (!in_array($queryRow['type'], ['lt', 'lte', 'eq', 'gte', 'gt', 'neq', 'ex', 'nex'])) {
                        unset($query['datetime'][$key]);
                        continue;
                    }
                }

                if (in_array($queryRow['type'], ['ex', 'nex'])) {
                    $query['datetime'][$key]['value'] = '';
                } elseif (empty($queryRow['value'])) {
                    unset($query['datetime'][$key]);
                    continue;
                } else {
                    // Date time cannot be longer than 19 numbers.
                    // But user can choose a year only, etc.
                }
            } else {
                $queryRow = [
                    'joiner' => 'and',
                    'field' => 'created',
                    'type' => 'eq',
                    'value' => $queryRow,
                ];
            }
        }

        return $query;
    }

    /**
     * Build query on date time (created/modified), partial date/time allowed.
     *
     * The query format is inspired by Doctrine and properties.
     *
     * Query format:
     *
     * - datetime[{index}][joiner]: "and" OR "or" joiner with previous query
     * - datetime[{index}][field]: the field "created" or "modified"
     * - datetime[{index}][type]: search type
     *   - gt: greater than (after)
     *   - gte: greater than or equal
     *   - eq: is exactly
     *   - neq: is not exactly
     *   - lte: lower than or equal
     *   - lt: lower than (before)
     *   - ex: has any value
     *   - nex: has no value
     * - datetime[{index}][value]: search date time (sql format: "2017-11-07 17:21:17",
     *   partial date/time allowed ("2018-05", etc.).
     *
     * @param QueryBuilder $qb
     * @param AbstractResourceEntityAdapter $adapter
     * @param array $query
     */
    protected function searchDateTime(
        QueryBuilder $qb,
        AbstractResourceEntityAdapter $adapter,
        array $query
    ) {
        $query = $this->normalizeDateTime($query);
        if (empty($query['datetime'])) {
            return;
        }

        $where = '';

        foreach ($query['datetime'] as $queryRow) {
            $joiner = $queryRow['joiner'];
            $field = $queryRow['field'];
            $type = $queryRow['type'];
            $value = $queryRow['value'];

            $resourceClass = $adapter->getEntityClass();

            // By default, sql replace missing time by 00:00:00, but this is not
            // clear for the user. And it doesn't allow partial date/time.
            switch ($type) {
                case 'gt':
                    if (strlen($value) < 19) {
                        $value = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                    }
                    $param = $adapter->createNamedParameter($qb, $value);
                    $predicateExpr = $qb->expr()->gt($resourceClass . '.' . $field, $param);
                    break;
                case 'gte':
                    if (strlen($value) < 19) {
                        $value = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                    }
                    $param = $adapter->createNamedParameter($qb, $value);
                    $predicateExpr = $qb->expr()->gte($resourceClass . '.' . $field, $param);
                    break;
                case 'eq':
                    if (strlen($value) < 19) {
                        $valueFrom = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                        $valueTo = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                        $paramFrom = $adapter->createNamedParameter($qb, $valueFrom);
                        $paramTo = $adapter->createNamedParameter($qb, $valueTo);
                        $predicateExpr = $qb->expr()->between($resourceClass . '.' . $field, $paramFrom, $paramTo);
                    } else {
                        $param = $adapter->createNamedParameter($qb, $value);
                        $predicateExpr = $qb->expr()->eq($resourceClass . '.' . $field, $param);
                    }
                    break;
                case 'neq':
                    if (strlen($value) < 19) {
                        $valueFrom = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                        $valueTo = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                        $paramFrom = $adapter->createNamedParameter($qb, $valueFrom);
                        $paramTo = $adapter->createNamedParameter($qb, $valueTo);
                        $predicateExpr = $qb->expr()->not(
                            $qb->expr()->between($resourceClass . '.' . $field, $paramFrom, $paramTo)
                        );
                    } else {
                        $param = $adapter->createNamedParameter($qb, $value);
                        $predicateExpr = $qb->expr()->neq($resourceClass . '.' . $field, $param);
                    }
                    break;
                case 'lte':
                    if (strlen($value) < 19) {
                        $value = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                    }
                    $param = $adapter->createNamedParameter($qb, $value);
                    $predicateExpr = $qb->expr()->lte($resourceClass . '.' . $field, $param);
                    break;
                case 'lt':
                    if (strlen($value) < 19) {
                        $value = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                    }
                    $param = $adapter->createNamedParameter($qb, $value);
                    $predicateExpr = $qb->expr()->lt($resourceClass . '.' . $field, $param);
                    break;
                case 'ex':
                    $predicateExpr = $qb->expr()->isNotNull($resourceClass . '.' . $field);
                    break;
                case 'nex':
                    $predicateExpr = $qb->expr()->isNull($resourceClass . '.' . $field);
                    break;
                default:
                    continue 2;
            }

            // First expression has no joiner.
            if ($where === '') {
                $where = '(' . $predicateExpr . ')';
            } elseif ($joiner === 'or') {
                $where .= ' OR (' . $predicateExpr . ')';
            } else {
                $where .= ' AND (' . $predicateExpr . ')';
            }
        }

        if ($where) {
            $qb->andWhere($where);
        }
    }

    /**
     * Build query to check if an item has media or not.
     *
     * The argument uses "has_media", with value "1" or "0".
     *
     * @param QueryBuilder $qb
     * @param AbstractResourceEntityAdapter $adapter
     * @param array $query
     */
    protected function searchHasMedia(
        QueryBuilder $qb,
        ItemAdapter $adapter,
        array $query
    ) {
        if (!isset($query['has_media'])) {
            return;
        }

        $value = (string) $query['has_media'];
        if ($value === '') {
            return;
        }

        // With media.
        $mediaAlias = $adapter->createAlias();
        if ($value) {
            $qb->innerJoin(
                \Omeka\Entity\Media::class,
                $mediaAlias,
                'WITH',
                $qb->expr()->eq($mediaAlias . '.item', $adapter->getEntityClass() . '.id')
            );
        }
        // Without media.
        else {
            $qb->leftJoin(
                \Omeka\Entity\Media::class,
                $mediaAlias,
                'WITH',
                $qb->expr()->eq($mediaAlias . '.item', $adapter->getEntityClass() . '.id')
            );
            $qb->andWhere($qb->expr()->isNull($mediaAlias . '.id'));
        }
    }
}
