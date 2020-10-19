<?php declare(strict_types=1);
/**
 * AdvancedSearchPlus
 *
 * Add some fields to the advanced search form (before/after creation date, has
 * media, etc.).
 *
 * @copyright Daniel Berthereau, 2018-2020
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

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Adapter\ItemAdapter;
use Omeka\Api\Adapter\MediaAdapter;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Adjust resource search by visibility.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.search.pre',
            [$this, 'handleApiSearchPre']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.search.pre',
            [$this, 'handleApiSearchPre']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.search.pre',
            [$this, 'handleApiSearchPre']
        );

        // Add the search query filters for resources.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.search.query',
            [$this, 'handleApiSearchQuery']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.search.query',
            [$this, 'handleApiSearchQuery']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.search.query',
            [$this, 'handleApiSearchQuery']
        );

        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
            'Omeka\Controller\Site\Item',
            'Omeka\Controller\Site\ItemSet',
            'Omeka\Controller\Site\Media',
            // TODO Add user.
        ];
        foreach ($controllers as $controller) {
            // Add the search field to the advanced search pages.
            $sharedEventManager->attach(
                $controller,
                'view.advanced_search',
                [$this, 'displayAdvancedSearch']
            );
            // Filter the search filters for the advanced search pages.
            $sharedEventManager->attach(
                $controller,
                'view.search.filters',
                [$this, 'filterSearchFilters']
            );
        }
    }

    /**
     * Removes is_public from the query if the value is '', before
     * passing it to AbstractResourceEntityAdapter, as it would
     * coerce it to boolean false for the query builder.
     *
     * @param Event $event
     */
    public function handleApiSearchPre(Event $event): void
    {
        $query = $event->getParam('request')->getContent();
        if (isset($query['is_public'])) {
            if ($query['is_public'] === '') {
                unset($query['is_public']);
                $event->getParam('request')->setContent($query);
            }
        }
    }

    /**
     * Helper to filter search queries.
     *
     * @param Event $event
     */
    public function handleApiSearchQuery(Event $event): void
    {
        /** @var \Doctrine\ORM\QueryBuilder $qb */
        $qb = $event->getParam('queryBuilder');
        $adapter = $event->getTarget();
        $query = $event->getParam('request')->getContent();
        $this->searchDateTime($qb, $adapter, $query);
        $this->buildPropertyQuery($qb, $query, $adapter);
        if ($adapter instanceof ItemAdapter) {
            $this->searchHasMedia($qb, $adapter, $query);
            $this->searchItemByMediaType($qb, $adapter, $query);
        } elseif ($adapter instanceof MediaAdapter) {
            $this->searchMediaByItemSet($qb, $adapter, $query);
        }
    }

    /**
     * Display the advanced search form via partial.
     *
     * @param Event $event
     */
    public function displayAdvancedSearch(Event $event): void
    {
        $query = $event->getParam('query', []);

        $partials = $event->getParam('partials', []);
        $resourceType = $event->getParam('resourceType');

        if ($resourceType === 'media') {
            $query['item_set_id'] = isset($query['item_set_id']) ? (array) $query['item_set_id'] : [];
            $partials[] = 'common/advanced-search/media-item-sets';
        }

        $query['datetime'] = isset($query['datetime']) ? $query['datetime'] : '';
        $partials[] = 'common/advanced-search/date-time';

        if ($resourceType === 'item') {
            $query['has_media'] = isset($query['has_media']) ? $query['has_media'] : '';
            $partials[] = 'common/advanced-search/has-media';
            $query['media_type'] = isset($query['media_type']) ? (array) $query['media_type'] : [];
            $partials[] = 'common/advanced-search/media-type';
        }

        $partials[] = 'common/advanced-search/visibility';

        $event->setParam('query', $query);
        $event->setParam('partials', $partials);
    }

    /**
     * Filter search filters.
     *
     * @param Event $event
     */
    public function filterSearchFilters(Event $event): void
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

        if (isset($query['is_public']) && $query['is_public'] !== '') {
            $value = $query['is_public'] === '0' ? $translate('Private') : $translate('Public');
            $filters[$translate('Visibility')][] = $value;
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

        if (!empty($query['media_type'])) {
            $value = is_array($query['media_type'])
                ? $query['media_type']
                : [$query['media_type']];
            foreach ($value as $subValue) {
                $filterLabel = $translate('Media type');
                $filters[$filterLabel][] = $subValue;
            }
        }

        // The query "item_set_id" is already managed by the main search filter.

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
                $queryRow = array_map('mb_strtolower', array_map('trim', $queryRow));
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
    ): void {
        $query = $this->normalizeDateTime($query);
        if (empty($query['datetime'])) {
            return;
        }

        $where = '';
        $expr = $qb->expr();

        foreach ($query['datetime'] as $queryRow) {
            $joiner = $queryRow['joiner'];
            $field = $queryRow['field'];
            $type = $queryRow['type'];
            $value = $queryRow['value'];

            // By default, sql replace missing time by 00:00:00, but this is not
            // clear for the user. And it doesn't allow partial date/time.
            switch ($type) {
                case 'gt':
                    if (strlen($value) < 19) {
                        $value = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                    }
                    $param = $adapter->createNamedParameter($qb, $value);
                    $predicateExpr = $expr->gt('omeka_root.' . $field, $param);
                    break;
                case 'gte':
                    if (strlen($value) < 19) {
                        $value = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                    }
                    $param = $adapter->createNamedParameter($qb, $value);
                    $predicateExpr = $expr->gte('omeka_root.' . $field, $param);
                    break;
                case 'eq':
                    if (strlen($value) < 19) {
                        $valueFrom = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                        $valueTo = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                        $paramFrom = $adapter->createNamedParameter($qb, $valueFrom);
                        $paramTo = $adapter->createNamedParameter($qb, $valueTo);
                        $predicateExpr = $expr->between('omeka_root.' . $field, $paramFrom, $paramTo);
                    } else {
                        $param = $adapter->createNamedParameter($qb, $value);
                        $predicateExpr = $expr->eq('omeka_root.' . $field, $param);
                    }
                    break;
                case 'neq':
                    if (strlen($value) < 19) {
                        $valueFrom = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                        $valueTo = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                        $paramFrom = $adapter->createNamedParameter($qb, $valueFrom);
                        $paramTo = $adapter->createNamedParameter($qb, $valueTo);
                        $predicateExpr = $expr->not(
                            $expr->between('omeka_root.' . $field, $paramFrom, $paramTo)
                        );
                    } else {
                        $param = $adapter->createNamedParameter($qb, $value);
                        $predicateExpr = $expr->neq('omeka_root.' . $field, $param);
                    }
                    break;
                case 'lte':
                    if (strlen($value) < 19) {
                        $value = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                    }
                    $param = $adapter->createNamedParameter($qb, $value);
                    $predicateExpr = $expr->lte('omeka_root.' . $field, $param);
                    break;
                case 'lt':
                    if (strlen($value) < 19) {
                        $value = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                    }
                    $param = $adapter->createNamedParameter($qb, $value);
                    $predicateExpr = $expr->lt('omeka_root.' . $field, $param);
                    break;
                case 'ex':
                    $predicateExpr = $expr->isNotNull('omeka_root.' . $field);
                    break;
                case 'nex':
                    $predicateExpr = $expr->isNull('omeka_root.' . $field);
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
    ): void {
        if (!isset($query['has_media'])) {
            return;
        }

        $value = (string) $query['has_media'];
        if ($value === '') {
            return;
        }

        $expr = $qb->expr();

        // With media.
        $mediaAlias = $adapter->createAlias();
        if ($value) {
            $qb->innerJoin(
                \Omeka\Entity\Media::class,
                $mediaAlias,
                Join::WITH,
                $expr->eq($mediaAlias . '.item', 'omeka_root.id')
            );
        }
        // Without media.
        else {
            $qb->leftJoin(
                \Omeka\Entity\Media::class,
                $mediaAlias,
                Join::WITH,
                $expr->eq($mediaAlias . '.item', 'omeka_root.id')
            );
            $qb->andWhere($expr->isNull($mediaAlias . '.id'));
        }
    }

    /**
     * Build query to check if media types.
     *
     * @param QueryBuilder $qb
     * @param AbstractResourceEntityAdapter $adapter
     * @param array $query
     */
    protected function searchItemByMediaType(
        QueryBuilder $qb,
        ItemAdapter $adapter,
        array $query
    ): void {
        if (!isset($query['media_type'])) {
            return;
        }

        $values = is_array($query['media_type'])
            ? $query['media_type']
            : [$query['media_type']];
        $values = array_filter(array_map('trim', $values));
        if (empty($values)) {
            return;
        }

        $mediaAlias = $adapter->createAlias();
        $expr = $qb->expr();

        $qb->innerJoin(
            \Omeka\Entity\Media::class,
            $mediaAlias,
            Join::WITH,
            $expr->andX(
                $expr->eq($mediaAlias . '.item', 'omeka_root.id'),
                $expr->in(
                    $mediaAlias . '.mediaType',
                    $adapter->createNamedParameter($qb, $values)
                )
            )
        );
    }

    /**
     * Build query to search media by item set.
     *
     * @param QueryBuilder $qb
     * @param AbstractResourceEntityAdapter $adapter
     * @param array $query
     */
    protected function searchMediaByItemSet(
        QueryBuilder $qb,
        MediaAdapter $adapter,
        array $query
    ): void {
        if (!isset($query['item_set_id'])) {
            return;
        }

        $itemSets = $query['item_set_id'];
        if (!is_array($itemSets)) {
            $itemSets = [$itemSets];
        }
        $itemSets = array_filter($itemSets, 'is_numeric');

        if ($itemSets) {
            $expr = $qb->expr();
            $itemAlias = $adapter->createAlias();
            $itemSetAlias = $adapter->createAlias();
            $qb
                ->leftJoin(
                    'omeka_root.item',
                    $itemAlias, 'WITH',
                    $expr->eq("$itemAlias.id", 'omeka_root.item')
                )
                ->innerJoin(
                    $itemAlias . '.itemSets',
                    $itemSetAlias, 'WITH',
                    $expr->in("$itemSetAlias.id", $adapter->createNamedParameter($qb, $itemSets))
                );
        }
    }

    /**
     * Build query on value.
     *
     * Complete \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildPropertyQuery()
     *
     * Note: because this filter is separate from the core one, all the
     * properties are rechecked to avoid a issue with the joiner (or/and).
     * @todo Find a way to not recheck all arguments used to search properties as long as it's not in the core.
     *
     * Query format:
     *
     * - property[{index}][joiner]: "and" OR "or" joiner with previous query
     * - property[{index}][property]: property ID
     * - property[{index}][text]: search text
     * - property[{index}][type]: search type
     *   - eq: is exactly
     *   - neq: is not exactly
     *   - in: contains
     *   - nin: does not contain
     *   - ex: has any value
     *   - nex: has no value
     *   - list: is in list
     *   - nlist: is not in list
     *   - sw: starts with
     *   - nsw: does not start with
     *   - ew: ends with
     *   - new: does not end with
     *   - res: has resource
     *   - nres: has no resource
     *
     * @param QueryBuilder $qb
     * @param array $query
     * @param AbstractResourceEntityAdapter $adapter
     */
    protected function buildPropertyQuery(QueryBuilder $qb, array $query, AbstractResourceEntityAdapter $adapter)
    {
        if (!isset($query['property']) || !is_array($query['property'])) {
            return;
        }

        $valuesJoin = 'omeka_root.values';
        $where = '';
        $expr = $qb->expr();

        $escape = function ($string) {
            return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $string);
        };

        foreach ($query['property'] as $queryRow) {
            if (!(
                is_array($queryRow)
                && array_key_exists('property', $queryRow)
                && array_key_exists('type', $queryRow)
            )) {
                continue;
            }
            $propertyId = $queryRow['property'];
            $queryType = $queryRow['type'];
            $joiner = isset($queryRow['joiner']) ? $queryRow['joiner'] : null;
            $value = isset($queryRow['text']) ? $queryRow['text'] : null;

            if (!strlen($value) && $queryType !== 'nex' && $queryType !== 'ex') {
                continue;
            }

            $valuesAlias = $adapter->createAlias();
            $positive = true;

            switch ($queryType) {
                case 'neq':
                    $positive = false;
                    // no break.
                case 'eq':
                    $param = $adapter->createNamedParameter($qb, $value);
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $adapter->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->eq("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->eq("$valuesAlias.value", $param),
                        $expr->eq("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nin':
                    $positive = false;
                    // no break.
                case 'in':
                    $param = $adapter->createNamedParameter($qb, '%' . $escape($value) . '%');
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $adapter->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->like("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nlist':
                    $positive = false;
                    // no break.
                case 'list':
                    $list = is_array($value) ? $value : explode("\n", $value);
                    $list = array_filter(array_map('trim', $list), 'strlen');
                    if (empty($list)) {
                        continue 2;
                    }
                    $param = $adapter->createNamedParameter($qb, $list);
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $adapter->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->eq("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->in("$valuesAlias.value", $param),
                        $expr->in("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nsw':
                    $positive = false;
                    // no break.
                case 'sw':
                    $param = $adapter->createNamedParameter($qb, $escape($value) . '%');
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $adapter->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->like("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                    break;

                case 'new':
                    $positive = false;
                    // no break.
                case 'ew':
                    $param = $adapter->createNamedParameter($qb, '%' . $escape($value));
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $adapter->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->like("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nres':
                    $positive = false;
                    // no break.
                case 'res':
                    $predicateExpr = $expr->eq(
                        "$valuesAlias.valueResource",
                        $adapter->createNamedParameter($qb, $value)
                    );
                    break;

                case 'nex':
                    $positive = false;
                    // no break.
                case 'ex':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.id");
                    break;

                default:
                    continue 2;
            }

            $joinConditions = [];
            // Narrow to specific property, if one is selected
            if ($propertyId) {
                if (is_numeric($propertyId)) {
                    $propertyId = (int) $propertyId;
                } else {
                    $property = $adapter->getPropertyByTerm($propertyId);
                    if ($property) {
                        $propertyId = $property->getId();
                    } else {
                        $propertyId = 0;
                    }
                }
                $joinConditions[] = $expr->eq("$valuesAlias.property", (int) $propertyId);
            }

            if ($positive) {
                $whereClause = '(' . $predicateExpr . ')';
            } else {
                $joinConditions[] = $predicateExpr;
                $whereClause = $expr->isNull("$valuesAlias.id");
            }

            if ($joinConditions) {
                $qb->leftJoin($valuesJoin, $valuesAlias, 'WITH', $expr->andX(...$joinConditions));
            } else {
                $qb->leftJoin($valuesJoin, $valuesAlias);
            }

            if ($where == '') {
                $where = $whereClause;
            } elseif ($joiner == 'or') {
                $where .= " OR $whereClause";
            } else {
                $where .= " AND $whereClause";
            }
        }

        if ($where) {
            $qb->andWhere($where);
        }
    }
}
