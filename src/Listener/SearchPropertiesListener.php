<?php declare(strict_types=1);

namespace AdvancedSearch\Listener;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Laminas\EventManager\Event;
use Omeka\Api\Adapter\ItemAdapter;
use Omeka\Api\Adapter\MediaAdapter;

class SearchPropertiesListener
{
    /**
     * List of property ids by term and id.
     *
     * @var array
     */
    protected $propertiesByTermsAndIds;

    /**
     * List of used property ids by term and id.
     *
     * @var array
     */
    protected $usedPropertiesByTerm;

    /**
     * List of resource class ids by term and id.
     *
     * @var array
     */
    protected $resourceClassesByTermsAndIds;

    /**
     * List of used resource class ids by term and id.
     *
     * @var array
     */
    protected $usedResourceClassesByTerm;

    /**
     * The adapter that is requesting.
     *
     * @var \Omeka\Api\Adapter\AbstractResourceEntityAdapter
     */
    protected $adapter;

    /**
     * Helper to filter search queries.
     */
    public function onDispatch(Event $event): void
    {
        /**
         * @var \Doctrine\ORM\QueryBuilder $qb
         * @var \Omeka\Api\Request $request
         */
        $qb = $event->getParam('queryBuilder');
        $this->adapter = $event->getTarget();
        $request = $event->getParam('request');
        $query = $request->getContent();

        // Reset the query for properties.
        $override = $request->getOption('override', []);
        if (!empty($override['property'])) {
            $query['property'] = $override['property'];
            $request->setContent($query);
            $request->setOption('override', null);
        }

        // Process advanced search plus keys.
        $this->searchResourceClassTerm($qb, $query);
        $this->searchDateTime($qb, $query);
        $this->buildPropertyQuery($qb, $query);
        if ($this->adapter instanceof ItemAdapter) {
            $this->searchHasMedia($qb, $query);
            $this->searchHasMediaOriginal($qb, $query);
            $this->searchHasMediaThumbnails($qb, $query);
            $this->searchItemByMediaType($qb, $query);
        } elseif ($this->adapter instanceof MediaAdapter) {
            $this->searchMediaByItemSet($qb, $query);
            $this->searchHasOriginal($qb, $query);
            $this->searchHasThumbnails($qb, $query);
        }
    }

    /**
     * Normalize the query for the date time argument.
     *
     * This method is used during the event "view.search.filters" via filterSearchFilters().
     */
    public function normalizeQueryDateTime(array $query): array
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
     * Allow to search a resource by a class term.
     */
    protected function searchResourceClassTerm(QueryBuilder $qb, array $query): void
    {
        if (empty($query['resource_class_term'])) {
            return;
        }

        $classes = is_array($query['resource_class_term'])
            ? $query['resource_class_term']
            : [$query['resource_class_term']];

        // When there is only one class and it is fake, no result should be
        // returned, so 0 should be used.
        $classIds = count($classes) <= 1
            ? [(int) $this->getResourceClassId(reset($classes))]
            : $this->getResourceClassIds($classes);

        $qb->andWhere($qb->expr()->in(
            'omeka_root.resourceClass',
            $this->adapter->createNamedParameter($qb, $classIds)
        ));
    }

    /**
     * Build query on value.
     *
     * Pseudo-override buildPropertyQuery() via the api manager delegator.
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildPropertyQuery()
     *
     * Query format:
     *
     * - property[{index}][joiner]: "and" OR "or" joiner with previous query
     * - property[{index}][property]: property ID
     * - property[{index}][text]: search text
     * - property[{index}][type]: search type
     *   - eq: is exactly (core)
     *   - neq: is not exactly (core)
     *   - in: contains (core)
     *   - nin: does not contain (core)
     *   - ex: has any value (core)
     *   - nex: has no value (core)
     *   - list: is in list
     *   - nlist: is not in list
     *   - sw: starts with
     *   - nsw: does not start with
     *   - ew: ends with
     *   - new: does not end with
     *   - res: has resource
     *   - nres: has no resource
     *   For date time only for now (a check is done to have a meaningful answer):
     *   TODO Remove the check for valid date time? Add another key (before/after)?
     *   Of course, it's better to use Numeric Data Types.
     *   - gt: greater than (after)
     *   - gte: greater than or equal
     *   - lte: lower than or equal
     *   - lt: lower than (before)
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    protected function buildPropertyQuery(QueryBuilder $qb, array $query): void
    {
        if (!isset($query['property']) || !is_array($query['property'])) {
            return;
        }

        $valuesJoin = 'omeka_root.values';
        $where = '';
        $expr = $qb->expr();

        $escape = function ($string) {
            return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $string);
        };

        $entityManager = $this->adapter->getEntityManager();

        foreach ($query['property'] as $queryRow) {
            if (!(
                is_array($queryRow)
                && array_key_exists('property', $queryRow)
                && array_key_exists('type', $queryRow)
            )) {
                continue;
            }

            $queryType = $queryRow['type'];
            $joiner = $queryRow['joiner'] ?? '';
            $value = $queryRow['text'] ?? '';

            // A value can be an array with types "list" and "nlist".
            if (!is_array($value)
                && !strlen((string) $value)
                && $queryType !== 'nex'
                && $queryType !== 'ex'
            ) {
                continue;
            }

            $propertyId = $queryRow['property'];
            if ($propertyId) {
                $propertyId = $this->getPropertyId($propertyId);
            }
            $excludePropertyIds = $queryRow['property'] || empty($queryRow['except']) ? false : $queryRow['except'];

            $valuesAlias = $this->adapter->createAlias();
            $positive = true;
            $incorrectValue = false;

            switch ($queryType) {
                case 'neq':
                    $positive = false;
                    // no break.
                case 'eq':
                    $param = $this->adapter->createNamedParameter($qb, $value);
                    $subqueryAlias = $this->adapter->createAlias();
                    $subquery = $entityManager
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
                    $param = $this->adapter->createNamedParameter($qb, '%' . $escape($value) . '%');
                    $subqueryAlias = $this->adapter->createAlias();
                    $subquery = $entityManager
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
                    $list = array_filter(array_map('trim', array_map('strval', $list)), 'strlen');
                    if (empty($list)) {
                        continue 2;
                    }
                    $param = $this->adapter->createNamedParameter($qb, $list);
                    $subqueryAlias = $this->adapter->createAlias();
                    $subquery = $entityManager
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->in("$subqueryAlias.title", $param));
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
                    $param = $this->adapter->createNamedParameter($qb, $escape($value) . '%');
                    $subqueryAlias = $this->adapter->createAlias();
                    $subquery = $entityManager
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
                    $param = $this->adapter->createNamedParameter($qb, '%' . $escape($value));
                    $subqueryAlias = $this->adapter->createAlias();
                    $subquery = $entityManager
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
                        $this->adapter->createNamedParameter($qb, $value)
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

                // TODO Manage uri and resources with gt, gte, lte, lt (it has a meaning at least for resource ids, but separate).
                case 'gt':
                    $valueNorm = $this->getDateTimeFromValue($value, false);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $predicateExpr = $expr->gt(
                            "$valuesAlias.value",
                            $this->adapter->createNamedParameter($qb, $valueNorm)
                        );
                    }
                    break;
                case 'gte':
                    $valueNorm = $this->getDateTimeFromValue($value, true);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $predicateExpr = $expr->gte(
                            "$valuesAlias.value",
                            $this->adapter->createNamedParameter($qb, $valueNorm)
                        );
                    }
                    break;
                case 'lte':
                    $valueNorm = $this->getDateTimeFromValue($value, false);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $predicateExpr = $expr->lte(
                            "$valuesAlias.value",
                            $this->adapter->createNamedParameter($qb, $valueNorm)
                        );
                    }
                    break;
                case 'lt':
                    $valueNorm = $this->getDateTimeFromValue($value, true);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $predicateExpr = $expr->lt(
                            "$valuesAlias.value",
                            $this->adapter->createNamedParameter($qb, $valueNorm)
                        );
                    }
                    break;
            }

            $joinConditions = [];
            // Narrow to specific property, if one is selected
            if ($propertyId) {
                $joinConditions[] = $expr->eq("$valuesAlias.property", $propertyId);
            } elseif ($excludePropertyIds) {
                $excludePropertyIds = is_array($excludePropertyIds)
                    ? $this->getPropertyIds($excludePropertyIds)
                    : array_filter([$this->getPropertyId($excludePropertyIds)]);
                // Use standard query if nothing to exclude, else limit search.
                if (count($excludePropertyIds)) {
                    // The aim is to search anywhere except ocr content.
                    // Use not positive + in() or notIn()? A full list is simpler.
                    $otherIds = array_diff($this->usedPropertiesByTerm, $excludePropertyIds);
                    $joinConditions[] = $expr->in("$valuesAlias.property", $otherIds);
                }
            }

            // Avoid to get results with some incorrect query.
            if ($incorrectValue) {
                $where = $expr->eq('incorrect date time request', '');
                break;
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
     * @param array $query
     */
    protected function searchDateTime(
        QueryBuilder $qb,
        array $query
    ): void {
        $query = $this->normalizeQueryDateTime($query);
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
            $incorrectValue = false;

            // By default, sql replace missing time by 00:00:00, but this is not
            // clear for the user. And it doesn't allow partial date/time.
            switch ($type) {
                case 'gt':
                    $valueNorm = $this->getDateTimeFromValue($value, false);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $param = $this->adapter->createNamedParameter($qb, $valueNorm);
                        $predicateExpr = $expr->gt('omeka_root.' . $field, $param);
                    }
                    break;
                case 'gte':
                    $valueNorm = $this->getDateTimeFromValue($value, true);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $param = $this->adapter->createNamedParameter($qb, $valueNorm);
                        $predicateExpr = $expr->gte('omeka_root.' . $field, $param);
                    }
                    break;
                case 'eq':
                    $valueFromNorm = $this->getDateTimeFromValue($value, true);
                    $valueToNorm = $this->getDateTimeFromValue($value, false);
                    if (is_null($valueFromNorm) || is_null($valueToNorm)) {
                        $incorrectValue = true;
                    } else {
                        if ($valueFromNorm === $valueToNorm) {
                            $param = $this->adapter->createNamedParameter($qb, $valueFromNorm);
                            $predicateExpr = $expr->eq('omeka_root.' . $field, $param);
                        } else {
                            $paramFrom = $this->adapter->createNamedParameter($qb, $valueFromNorm);
                            $paramTo = $this->adapter->createNamedParameter($qb, $valueToNorm);
                            $predicateExpr = $expr->between('omeka_root.' . $field, $paramFrom, $paramTo);
                        }
                    }
                    break;
                case 'neq':
                    $valueFromNorm = $this->getDateTimeFromValue($value, true);
                    $valueToNorm = $this->getDateTimeFromValue($value, false);
                    if (is_null($valueFromNorm) || is_null($valueToNorm)) {
                        $incorrectValue = true;
                    } else {
                        if ($valueFromNorm === $valueToNorm) {
                            $param = $this->adapter->createNamedParameter($qb, $valueFromNorm);
                            $predicateExpr = $expr->neq('omeka_root.' . $field, $param);
                        } else {
                            $paramFrom = $this->adapter->createNamedParameter($qb, $valueFromNorm);
                            $paramTo = $this->adapter->createNamedParameter($qb, $valueToNorm);
                            $predicateExpr = $expr->not(
                                $expr->between('omeka_root.' . $field, $paramFrom, $paramTo)
                            );
                        }
                    }
                    break;
                case 'lte':
                    $valueNorm = $this->getDateTimeFromValue($value, false);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $param = $this->adapter->createNamedParameter($qb, $valueNorm);
                        $predicateExpr = $expr->lte('omeka_root.' . $field, $param);
                    }
                    break;
                case 'lt':
                    $valueNorm = $this->getDateTimeFromValue($value, true);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $param = $this->adapter->createNamedParameter($qb, $valueNorm);
                        $predicateExpr = $expr->lt('omeka_root.' . $field, $param);
                    }
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

            // Avoid to get results with some incorrect query.
            if ($incorrectValue) {
                $param = $this->adapter->createNamedParameter($qb, 'incorrect value: ' . $queryRow['value']);
                $predicateExpr = $expr->eq('omeka_root.' . $field, $param);
                $joiner = 'and';
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
     * @param array $query
     */
    protected function searchHasMedia(
        QueryBuilder $qb,
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
        $mediaAlias = $this->adapter->createAlias();
        if ($value) {
            $qb
                ->innerJoin(
                    \Omeka\Entity\Media::class,
                    $mediaAlias,
                    Join::WITH,
                    $expr->eq($mediaAlias . '.item', 'omeka_root.id')
                );
        }
        // Without media.
        else {
            $qb
                ->leftJoin(
                    \Omeka\Entity\Media::class,
                    $mediaAlias,
                    Join::WITH,
                    $expr->eq($mediaAlias . '.item', 'omeka_root.id')
                )
                ->andWhere($expr->isNull($mediaAlias . '.id'));
        }
    }

    /**
     * Build query to check if an item has an original file or not.
     *
     * The argument uses "has_original", with value "1" or "0".
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    protected function searchHasMediaOriginal(
        QueryBuilder $qb,
        array $query
    ): void {
        $this->searchHasMediaSpecific($qb, $query, 'has_original');
    }

    /**
     * Build query to check if an item has thumbnails or not.
     *
     * The argument uses "has_thumbnails", with value "1" or "0".
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    protected function searchHasMediaThumbnails(
        QueryBuilder $qb,
        array $query
    ): void {
        $this->searchHasMediaSpecific($qb, $query, 'has_thumbnails');
    }

    /**
     * Build query to check if an item has an original file or thumbnails or not.
     *
     * @param QueryBuilder $qb
     * @param array $query
     * @param string $field "has_original" or "has_thumbnails".
     */
    protected function searchHasMediaSpecific(
        QueryBuilder $qb,
        array $query,
        $field
    ): void {
        if (!isset($query[$field])) {
            return;
        }

        $value = (string) $query[$field];
        if ($value === '') {
            return;
        }

        $expr = $qb->expr();
        $fields = [
            'has_original' => 'hasOriginal',
            'has_thumbnails' => 'hasThumbnails',
        ];

        // With original media.
        $mediaAlias = $this->adapter->createAlias();
        if ($value) {
            $qb->innerJoin(
                \Omeka\Entity\Media::class,
                $mediaAlias,
                Join::WITH,
                $expr->andX(
                    $expr->eq($mediaAlias . '.item', 'omeka_root.id'),
                    $expr->eq($mediaAlias . '.' . $fields[$field], 1)
                )
            );
        }
        // Without original media.
        else {
            $qb
                ->leftJoin(
                    \Omeka\Entity\Media::class,
                    $mediaAlias,
                    Join::WITH,
                    $expr->eq($mediaAlias . '.item', 'omeka_root.id')
                )
                ->andWhere($expr->orX(
                    $expr->isNull($mediaAlias . '.id'),
                    $expr->eq($mediaAlias . '.' . $fields[$field], 0)
            ));
        }
    }

    /**
     * Build query to check if media types.
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    protected function searchItemByMediaType(
        QueryBuilder $qb,
        array $query
    ): void {
        if (!isset($query['media_types'])) {
            return;
        }

        $values = is_array($query['media_types'])
            ? $query['media_types']
            : [$query['media_types']];
        $values = array_filter(array_map('trim', $values));
        if (empty($values)) {
            return;
        }

        $mediaAlias = $this->adapter->createAlias();
        $expr = $qb->expr();

        $qb->innerJoin(
            \Omeka\Entity\Media::class,
            $mediaAlias,
            Join::WITH,
            $expr->andX(
                $expr->eq($mediaAlias . '.item', 'omeka_root.id'),
                $expr->in(
                    $mediaAlias . '.mediaType',
                    $this->adapter->createNamedParameter($qb, $values)
                )
            )
        );
    }

    /**
     * Build query to check if a media has an original file or not.
     *
     * The argument uses "has_original", with value "1" or "0".
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    protected function searchHasOriginal(
        QueryBuilder $qb,
        array $query
    ): void {
        $this->searchMediaSpecific($qb, $query, 'has_original');
    }

    /**
     * Build query to check if a media has thumbnails or not.
     *
     * The argument uses "has_thumbnails", with value "1" or "0".
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    protected function searchHasThumbnails(
        QueryBuilder $qb,
        array $query
    ): void {
        $this->searchMediaSpecific($qb, $query, 'has_thumbnails');
    }

    /**
     * Build query to check if a media has an original file or thumbnails or not.
     *
     * @param QueryBuilder $qb
     * @param array $query
     * @param string $field "has_original" or "has_thumbnails".
     */
    protected function searchMediaSpecific(
        QueryBuilder $qb,
        array $query,
        $field
    ): void {
        if (!isset($query[$field])) {
            return;
        }

        $value = (string) $query[$field];
        if ($value === '') {
            return;
        }

        $fields = [
            'has_original' => 'hasOriginal',
            'has_thumbnails' => 'hasThumbnails',
        ];
        $qb
            ->andWhere($qb->expr()->eq('omeka_root.' . $fields[$field], (int) (bool) $value));
    }

    /**
     * Build query to search media by item set.
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    protected function searchMediaByItemSet(
        QueryBuilder $qb,
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
            $itemAlias = $this->adapter->createAlias();
            $itemSetAlias = $this->adapter->createAlias();
            $qb
                ->leftJoin(
                    'omeka_root.item',
                    $itemAlias, 'WITH',
                    $expr->eq("$itemAlias.id", 'omeka_root.item')
                )
                ->innerJoin(
                    $itemAlias . '.itemSets',
                    $itemSetAlias, 'WITH',
                    $expr->in("$itemSetAlias.id", $this->adapter->createNamedParameter($qb, $itemSets))
                );
        }
    }

    /**
     * Convert into a standard DateTime. Manage some badly formatted values.
     *
     * Adapted from module NumericDataType.
     * The main difference is the max/min date: from 1000 to 9999. Since fields
     * are "created" and "modified", other dates are removed.
     * The regex pattern allows partial month and day too.
     * @link https://mariadb.com/kb/en/datetime/
     * @see \NumericDataTypes\DataType\AbstractDateTimeDataType::getDateTimeFromValue()
     *
     * Allow mysql datetime too, not only iso 8601 (so with a space, not only a
     * "T" to separate date and time).
     *
     * @param string $value
     * @param bool $defaultFirst
     * @return array|null
     */
    protected function getDateTimeFromValue($value, $defaultFirst = true)
    {
        // $yearMin = -292277022656;
        // $yearMax = 292277026595;
        $yearMin = 1000;
        $yearMax = 9999;
        $patternIso8601 = '^(?<date>(?<year>-?\d{4,})(-(?<month>\d{1,2}))?(-(?<day>\d{1,2}))?)(?<time>((?:T| )(?<hour>\d{1,2}))?(:(?<minute>\d{1,2}))?(:(?<second>\d{1,2}))?)(?<offset>((?<offset_hour>[+-]\d{1,2})?(:(?<offset_minute>\d{1,2}))?)|Z?)$';
        static $dateTimes = [];

        $firstOrLast = $defaultFirst ? 'first' : 'last';
        if (isset($dateTimes[$value][$firstOrLast])) {
            return $dateTimes[$value][$firstOrLast];
        }

        $dateTimes[$value][$firstOrLast] = null;

        // Match against ISO 8601, allowing for reduced accuracy.
        $matches = [];
        if (!preg_match(sprintf('/%s/', $patternIso8601), $value, $matches)) {
            return null;
        }

        // Remove empty values.
        $matches = array_filter($matches);

        // An hour requires a day.
        if (isset($matches['hour']) && !isset($matches['day'])) {
            return null;
        }

        // An offset requires a time.
        if (isset($matches['offset']) && !isset($matches['time'])) {
            return null;
        }

        // Set the datetime components included in the passed value.
        $dateTime = [
            'value' => $value,
            'date_value' => $matches['date'],
            'time_value' => $matches['time'] ?? null,
            'offset_value' => $matches['offset'] ?? null,
            'year' => (int) $matches['year'],
            'month' => isset($matches['month']) ? (int) $matches['month'] : null,
            'day' => isset($matches['day']) ? (int) $matches['day'] : null,
            'hour' => isset($matches['hour']) ? (int) $matches['hour'] : null,
            'minute' => isset($matches['minute']) ? (int) $matches['minute'] : null,
            'second' => isset($matches['second']) ? (int) $matches['second'] : null,
            'offset_hour' => isset($matches['offset_hour']) ? (int) $matches['offset_hour'] : null,
            'offset_minute' => isset($matches['offset_minute']) ? (int) $matches['offset_minute'] : null,
        ];

        // Set the normalized datetime components. Each component not included
        // in the passed value is given a default value.
        $dateTime['month_normalized'] = $dateTime['month'] ?? ($defaultFirst ? 1 : 12);
        // The last day takes special handling, as it depends on year/month.
        $dateTime['day_normalized'] = $dateTime['day']
            ?? ($defaultFirst ? 1 : self::getLastDay($dateTime['year'], $dateTime['month_normalized']));
        $dateTime['hour_normalized'] = $dateTime['hour'] ?? ($defaultFirst ? 0 : 23);
        $dateTime['minute_normalized'] = $dateTime['minute'] ?? ($defaultFirst ? 0 : 59);
        $dateTime['second_normalized'] = $dateTime['second'] ?? ($defaultFirst ? 0 : 59);
        $dateTime['offset_hour_normalized'] = $dateTime['offset_hour'] ?? 0;
        $dateTime['offset_minute_normalized'] = $dateTime['offset_minute'] ?? 0;
        // Set the UTC offset (+00:00) if no offset is provided.
        $dateTime['offset_normalized'] = isset($dateTime['offset_value'])
            ? ('Z' === $dateTime['offset_value'] ? '+00:00' : $dateTime['offset_value'])
            : '+00:00';

        // Validate ranges of the datetime component.
        if (($yearMin > $dateTime['year']) || ($yearMax < $dateTime['year'])) {
            return null;
        }
        if ((1 > $dateTime['month_normalized']) || (12 < $dateTime['month_normalized'])) {
            return null;
        }
        if ((1 > $dateTime['day_normalized']) || (31 < $dateTime['day_normalized'])) {
            return null;
        }
        if ((0 > $dateTime['hour_normalized']) || (23 < $dateTime['hour_normalized'])) {
            return null;
        }
        if ((0 > $dateTime['minute_normalized']) || (59 < $dateTime['minute_normalized'])) {
            return null;
        }
        if ((0 > $dateTime['second_normalized']) || (59 < $dateTime['second_normalized'])) {
            return null;
        }
        if ((-23 > $dateTime['offset_hour_normalized']) || (23 < $dateTime['offset_hour_normalized'])) {
            return null;
        }
        if ((0 > $dateTime['offset_minute_normalized']) || (59 < $dateTime['offset_minute_normalized'])) {
            return null;
        }

        // Adding the DateTime object here to reduce code duplication. To ensure
        // consistency, use Coordinated Universal Time (UTC) if no offset is
        // provided. This avoids automatic adjustments based on the server's
        // default timezone.
        // With strict type, "now" is required.
        $dateTime['date'] = new \DateTime('now', new \DateTimeZone($dateTime['offset_normalized']));
        $dateTime['date']
            ->setDate(
                $dateTime['year'],
                $dateTime['month_normalized'],
                $dateTime['day_normalized']
            )
            ->setTime(
                $dateTime['hour_normalized'],
                $dateTime['minute_normalized'],
                $dateTime['second_normalized']
            );

        // Cache the date/time as a sql date time.
        $dateTimes[$value][$firstOrLast] = $dateTime['date']->format('Y-m-d H:i:s');
        return $dateTimes[$value][$firstOrLast];
    }

    /**
     * Get the last day of a given year/month.
     *
     * @param int $year
     * @param int $month
     * @return int
     */
    protected function getLastDay($year, $month)
    {
        switch ($month) {
            case 2:
                // February (accounting for leap year)
                $leapYear = date('L', mktime(0, 0, 0, 1, 1, $year));
                return $leapYear ? 29 : 28;
            case 4:
            case 6:
            case 9:
            case 11:
                // April, June, September, November
                return 30;
            default:
                // January, March, May, July, August, October, December
                return 31;
        }
    }

    /**
     * Get property ids by JSON-LD terms or by numeric ids.
     *
     * @return int[]
     */
    protected function getPropertyIds(array $termOrIds): array
    {
        if (is_null($this->propertiesByTermsAndIds)) {
            $this->prepareProperties();
        }
        return array_values(array_intersect_key($this->propertiesByTermsAndIds, array_flip($termOrIds)));
    }

    /**
     * Get a property id by JSON-LD term or by numeric id.
     */
    protected function getPropertyId($termOrId): ?int
    {
        if (is_null($this->propertiesByTermsAndIds)) {
            $this->prepareProperties();
        }
        return $this->propertiesByTermsAndIds[$termOrId] ?? null;
    }

    /**
     * Prepare the list of properties and used properties.
     */
    protected function prepareProperties(): self
    {
        if (is_null($this->propertiesByTermsAndIds)) {
            $connection = $this->adapter->getServiceLocator()->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select([
                    'DISTINCT property.id AS id',
                    'CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                    // Only the two first selects are needed, but some databases
                    // require "order by" or "group by" value to be in the select.
                    'vocabulary.id',
                    'property.id',
                ])
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('property.id', 'asc')
                ->addGroupBy('property.id')
            ;
            $stmt = $connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $properties = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $properties = array_map('intval', array_column($properties, 'id', 'term'));
            $this->propertiesByTermsAndIds = array_replace($properties, array_combine($properties, $properties));

            $qb->innerJoin('property', 'value', 'value', 'property.id = value.property_id');
            $stmt = $connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $properties = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->usedPropertiesByTerm = array_map('intval', array_column($properties, 'id', 'term'));
        }
        return $this;
    }

    /**
     * Get resource class ids by JSON-LD terms or by numeric ids.
     *
     * @return int[]
     */
    protected function getResourceClassIds(array $termOrIds): array
    {
        if (is_null($this->resourceClassesByTermsAndIds)) {
            $this->prepareResourceClasses();
        }
        return array_values(array_intersect_key($this->resourceClassesByTermsAndIds, array_flip($termOrIds)));
    }

    /**
     * Get resource class id by JSON-LD term or by numeric id.
     */
    protected function getResourceClassId($termOrId): ?int
    {
        if (is_null($this->resourceClassesByTermsAndIds)) {
            $this->prepareResourceClasses();
        }
        return $this->resourceClassesByTermsAndIds[$termOrId] ?? null;
    }

    /**
     * Prepare the list of resource classes and used properties.
     */
    protected function prepareResourceClasses(): self
    {
        if (is_null($this->propertiesByTermsAndIds)) {
            $connection = $this->adapter->getServiceLocator()->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select([
                    'DISTINCT resource_class.id AS id',
                    'CONCAT(vocabulary.prefix, ":", resource_class.local_name) AS term',
                    // Only the two first selects are needed, but some databases
                    // require "order by" or "group by" value to be in the select.
                    'vocabulary.id',
                    'resource_class.id',
                ])
                ->from('resource_class', 'resource_class')
                ->innerJoin('resource_class', 'vocabulary', 'vocabulary', 'resource_class.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('resource_class.id', 'asc')
                ->addGroupBy('resource_class.id')
            ;
            $stmt = $connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $resourceClasses = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $resourceClasses = array_map('intval', array_column($resourceClasses, 'id', 'term'));
            $this->tesourceClassesByTermsAndIds = array_replace($resourceClasses, array_combine($resourceClasses, $resourceClasses));

            // $qb->innerJoin('resource_class', 'resource', 'resource', 'resource_class.id = resource.resource_class_id');
            // $stmt = $connection->executeQuery($qb);
            // // Fetch by key pair is not supported by doctrine 2.0.
            // $resourceClasses = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            // $this->usedResourceClassesByTerm = array_map('intval', array_column($resourceClasses, 'id', 'term'));
            return $this;
        }
    }
}
