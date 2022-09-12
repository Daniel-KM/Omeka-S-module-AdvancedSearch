<?php declare(strict_types=1);

namespace AdvancedSearch\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Adapter\ItemAdapter;
use Omeka\Api\Adapter\ItemSetAdapter;
use Omeka\Api\Adapter\MediaAdapter;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Request;

class SearchResources extends AbstractPlugin
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * The adapter used to build the query.
     *
     * @var \Omeka\Api\Adapter\AbstractResourceEntityAdapter
     */
    protected $adapter;

    /**
     * List of used property ids by term.
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
     * List of used resource class ids by term.
     *
     * @var array
     */
    protected $usedResourceClassesByTerm;

    const PROPERTY_QUERY = [
        'reciprocal' => [
            'eq' => 'neq',
            'neq' => 'eq',
            'in' => 'nin',
            'nin' => 'in',
            'ex' => 'nex',
            'nex' => 'ex',
            'exs' => 'nexs',
            'nexs' => 'exs',
            'exm' => 'nexm',
            'nexm' => 'exm',
            'list' => 'nlist',
            'nlist' => 'list',
            'sw' => 'nsw',
            'nsw' => 'sw',
            'ew' => 'new',
            'new' => 'ew',
            'res' => 'nres',
            'nres' => 'res',
            'tp' => 'ntp',
            'ntp' => 'tp',
            'tpl' => 'ntpl',
            'ntpl' => 'tpl',
            'tpr' => 'ntpr',
            'ntpr' => 'tpr',
            'tpu' => 'ntpu',
            'ntpu' => 'tpu',
            'dtp' => 'ndtp',
            'ndtp' => 'dtp',
            'lex' => 'nlex',
            'nlex' => 'lex',
            'lres' => 'nlres',
            'nlres' => 'lres',
            'gt' => 'lte',
            'gte' => 'lt',
            'lte' => 'gt',
            'lt' => 'gte',
        ],
        'value_array' => [
            'list',
            'nlist',
            'res',
            'nres',
            'lres',
            'nlres',
            'dtp',
            'ndtp',
        ],
        'value_integer' => [
            'res',
            'nres',
            'lres',
            'nlres',
        ],
        'value_none' => [
            'ex',
            'nex',
            'exs',
            'nexs',
            'exm',
            'nexm',
            'lex',
            'nlex',
            'tpl',
            'ntpl',
            'tpr',
            'ntpr',
            'tpu',
            'ntpu',
        ],
        'value_subject' => [
            'lex',
            'nlex',
            'lres',
            'nlres',
        ],
        'optimize' => [
            'eq' => 'list',
            'neq' => 'nlist',
        ],
    ];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Helper to manage search queries before core.
     *
     * The process removes keys before core processing and re-add them after to
     * avoid a double process for core adapter keys.
     *
     * @todo Integrate the override in a way a direct call to adapter->buildQuery() can work with advanced property search (see Reference and some other modules). For now, use buildInitialQuery().
     *
     * @see \AdvancedSearch\Api\ManagerDelegator::search()
     */
    public function startOverrideRequest(Request $request): self
    {
        $query = $request->getContent();

        $override = [];
        $query = $this->startOverrideQuery($query, $override);
        if (!empty($override)) {
            $request->setOption('override', $override);
        }

        $request->setContent($query);

        return $this;
    }

    /**
     * Helper to manage search queries after core.
     */
    public function endOverrideRequest(Request $request): self
    {
        $query = $request->getContent();
        $override = $request->getOption('override', []);

        $query = $this->endOverrideQuery($query, $override);
        $request->setContent($query);
        $request->setOption('override', null);

        return $this;
    }

    /**
     * Override query keys that have improved features before process in core.
     *
     * Overridden keys are passed via referenced variable.
     *
     * @see \AdvancedSearch\Api\ManagerDelegator::search()
     */
    public function startOverrideQuery(array $query, array &$override): array
    {
        $override = [];

        // The query is cleaned first to simplify checks.
        $query = $this->cleanQuery($query);

        if (isset($query['owner_id'])) {
            $override['owner_id'] = $query['owner_id'];
            unset($query['owner_id']);
        }
        if (isset($query['resource_class_id'])) {
            $override['resource_class_id'] = $query['resource_class_id'];
            unset($query['resource_class_id']);
        }
        if (isset($query['resource_template_id'])) {
            $override['resource_template_id'] = $query['resource_template_id'];
            unset($query['resource_template_id']);
        }
        if (isset($query['item_set_id'])) {
            $override['item_set_id'] = $query['item_set_id'];
            unset($query['item_set_id']);
        }
        if (!empty($query['property'])) {
            $override['property'] = $query['property'];
            unset($query['property']);
        }
        // "site" is more complex and has already a special key "in_sites", that
        // can be true or false. This key is not overridden.
        // When the key "site_id" is set, the key "in_sites" is skipped in core.
        if (isset($query['site_id']) && (int) $query['site_id'] === 0) {
            $query['in_sites'] = false;
            unset($query['site_id']);
        }

        return $query;
    }

    /**
     * Reinclude overridden keys in the search queries after core process.
     *
     * The modules, included this one, can process them after end of overriding.
     */
    public function endOverrideQuery(array $query, ?array $override = null): array
    {
        // Reset the query for properties.
        if (!$override) {
            return $query;
        }

        if (isset($override['owner_id'])) {
            $query['owner_id'] = $override['owner_id'];
        }
        if (isset($override['resource_class_id'])) {
            $query['resource_class_id'] = $override['resource_class_id'];
        }
        if (isset($override['resource_template_id'])) {
            $query['resource_template_id'] = $override['resource_template_id'];
        }
        if (isset($override['item_set_id'])) {
            $query['item_set_id'] = $override['item_set_id'];
        }
        if (isset($override['property'])) {
            $query['property'] = $override['property'];
        }

        return $query;
    }

    public function setAdapter(AbstractResourceEntityAdapter $adapter): self
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Process this module search features. The adapter must be set first.
     */
    public function buildInitialQuery(QueryBuilder $qb, array $query): void
    {
        // Process advanced search plus keys.
        $this->searchSites($qb, $query);
        $this->searchResources($qb, $query);
        $this->searchResourceClassTerm($qb, $query);
        $this->searchDateTime($qb, $query);
        $this->buildPropertyQuery($qb, $query);
        if ($this->adapter instanceof ItemAdapter) {
            $this->searchHasMedia($qb, $query);
            $this->searchHasMediaOriginal($qb, $query);
            $this->searchHasMediaThumbnails($qb, $query);
            $this->searchByMediaType($qb, $query);
        } elseif ($this->adapter instanceof MediaAdapter) {
            $this->searchMediaByItemSet($qb, $query);
            $this->searchHasOriginal($qb, $query);
            $this->searchHasThumbnails($qb, $query);
            $this->searchByMediaType($qb, $query);
        }
    }

    /**
     * The advanced search form returns all keys, so remove useless ones.
     */
    public function cleanQuery(array $query): array
    {
        // Clean simple useless fields to avoid useless checks in many places.
        // TODO Clean property, numeric, dates, etc.
        foreach ($query as $key => $value) {
            if ($value === '' || $value === null || $value === []
                || !$key || $key === 'submit' || $key === 'numeric-toggle-time-checkbox'
            ) {
                unset($query[$key]);
            } elseif ($key === 'id') {
                $values = is_array($value) ? $value : [$value];
                $values = array_filter($values, function ($id) {
                    return $id !== '' && $id !== null;
                });
                if (count($values)) {
                    $query['id'] = $values;
                } else {
                    unset($query['id']);
                }
            } elseif (in_array($key, [
                'owner_id',
                'site_id',
            ])) {
                if (is_numeric($value)) {
                    $query[$key] = (int) $value;
                } else {
                    unset($query[$key]);
                }
            } elseif (in_array($key, [
                'resource_class_id',
                'resource_template_id',
                'item_set_id',
            ])) {
                $values = is_array($value) ? $value : [$value];
                $values = array_map('intval', array_filter($values, 'is_numeric'));
                if (count($values)) {
                    $query[$key] = $values;
                } else {
                    unset($query[$key]);
                }
            } elseif ($key === 'property') {
                if (is_array($value)) {
                    // Short properties allows to prepare optimization.
                    $shortProperties = [];
                    foreach ($query['property'] as $k => $queryRow) {
                        if (!is_array($queryRow)
                            || empty($queryRow['type'])
                            || !isset(self::PROPERTY_QUERY['reciprocal'][$queryRow['type']])
                        ) {
                            unset($query['property'][$k]);
                            continue;
                        }
                        $queryType = $queryRow['type'];
                        $queryValue = $queryRow['text'] ?? '';
                        // Quick check of value.
                        // A empty string "" is not a value, but "0" is a value.
                        if (in_array($queryType, self::PROPERTY_QUERY['value_none'], true)) {
                            $queryValue = null;
                        }
                        // Check array of values.
                        elseif (in_array($queryType, self::PROPERTY_QUERY['value_array'], true)) {
                            if ((is_array($queryValue) && !count($queryValue))
                                || (!is_array($queryValue) && !strlen((string) $queryValue))
                            ) {
                                unset($query['property'][$k]);
                                continue;
                            }
                            if (!is_array($queryValue)) {
                                $queryValue = [$queryValue];
                            }
                            $queryValue = in_array($queryType, self::PROPERTY_QUERY['value_integer'])
                                ? array_unique(array_map('intval', $queryValue))
                                : array_unique(array_filter(array_map('trim', array_map('strval', $queryValue)), 'strlen'));
                            if (empty($queryValue)) {
                                unset($query['property'][$k]);
                                continue;
                            } else {
                                $query['property'][$k]['text'] = $queryValue;
                            }
                        }
                        // The value should be a scalar in all other cases.
                        elseif (is_array($queryValue) || !strlen((string) $queryValue)) {
                            unset($query['property'][$k]);
                            continue;
                        }
                        if (is_array($queryRow['property'])) {
                            $query['property'][$k]['property'] = array_unique($query['property'][$k]['property']);
                        }
                        if (in_array($query['property'][$k]['type'], ['eq', 'list', 'neq', 'nlist'])) {
                            // TODO Manage the case where the property is numeric or term (simplify above instead of in the process below).
                            $queryRowProperty = is_array($queryRow['property']) ? implode(',', $queryRow['property']) : $queryRow['property'];
                            $short = $queryRowProperty . '/' . $queryRow['type'] . '/' . ($queryRow['joiner'] ?? 'and');
                            if (isset($shortProperties[$short])) {
                                ++$shortProperties[$short]['total'];
                            } else {
                                $shortProperties[$short]['property_string'] = $queryRowProperty;
                                $shortProperties[$short]['property'] = $queryRow['property'];
                                $shortProperties[$short]['type'] = $queryRow['type'];
                                $shortProperties[$short]['joiner'] = $queryRow['joiner'];
                                $shortProperties[$short]['total'] = 1;
                            }
                            $shortProperties[$short]['keys'][] = $k;
                            $shortProperties[$short]['texts'][] = $queryRow['text'];
                        }
                    }
                    $query = $this->optimizeQueryProperty($query, $shortProperties);
                } else {
                    unset($query['property']);
                }
            } elseif ($key === 'datetime') {
                // Manage a single date time.
                if (!is_array($query['datetime'])) {
                    $query['datetime'] = [[
                        'joiner' => 'and',
                        'field' => 'created',
                        'type' => 'eq',
                        'value' => $query['datetime'],
                    ]];
                } else {
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
                                if (!in_array($queryRow['joiner'], ['and', 'or', 'not'])) {
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
                    unset($queryRow);
                }
            } elseif ($key === 'numeric') {
                if (is_array($value)) {
                    if (empty($query['numeric']['ts']['gte']['pid']) && empty($query['numeric']['ts']['gte']['val'])) {
                        unset($query['numeric']['ts']['gte']);
                    }
                    if (empty($query['numeric']['ts']['lte']['pid']) && empty($query['numeric']['ts']['lte']['val'])) {
                        unset($query['numeric']['ts']['lte']);
                    }
                    if (empty($query['numeric']['ts']['gte']) && empty($query['numeric']['ts']['lte'])) {
                        unset($query['numeric']['ts']);
                    }
                    if (empty($query['numeric']['dur']['gt']['pid']) && empty($query['numeric']['dur']['gt']['val'])) {
                        unset($query['numeric']['dur']['gt']);
                    }
                    if (empty($query['numeric']['dur']['lt']['pid']) && empty($query['numeric']['dur']['lt']['val'])) {
                        unset($query['numeric']['dur']['lt']);
                    }
                    if (empty($query['numeric']['dur']['gt']) && empty($query['numeric']['dur']['lt'])) {
                        unset($query['numeric']['dur']);
                    }
                    if (empty($query['numeric']['ivl']['pid']) && empty($query['numeric']['ivl']['val'])) {
                        unset($query['numeric']['ivl']);
                    }
                    if (empty($query['numeric']['int']['gt']['pid']) && empty($query['numeric']['int']['gt']['val'])) {
                        unset($query['numeric']['int']['gt']);
                    }
                    if (empty($query['numeric']['int']['lt']['pid']) && empty($query['numeric']['int']['lt']['val'])) {
                        unset($query['numeric']['int']['lt']);
                    }
                    if (empty($query['numeric']['int']['gt']) && empty($query['numeric']['int']['lt'])) {
                        unset($query['numeric']['int']);
                    }
                    if (empty($query['numeric'])) {
                        unset($query['numeric']);
                    }
                } else {
                    unset($query['numeric']);
                }
            }
        }

        return $query;
    }

    /**
     * Helper to optimize query for properties.
     */
    protected function optimizeQueryProperty(array $query, array $shortProperties): array
    {
        if (!count($shortProperties) || count($query['property']) <= 1) {
            return $query;
        }

        // Replace multiple "subject = x OR subject = y" by "subject in list [x, y]"
        // and variants: not equal to not in list, and types "and" and "except".
        // On a base > 10000 items and more than three or four subjects with OR,
        // mysql never ends request.
        foreach ($shortProperties as $shortProperty) {
            if ($shortProperty['total'] < 2 || !isset(self::PROPERTY_QUERY['optimize'][$shortProperty['type']])) {
                continue;
            }
            $optimizedType = self::PROPERTY_QUERY['optimize'][$shortProperty['type']];
            $shortList = $shortProperty['property_string'] . '/' . $optimizedType . '/' . $shortProperty['joiner'];
            if (isset($shortProperties[$shortList])) {
                // TODO Replace multiple "subject in list [x] OR subject in list [y]" by "subject in list [x, y]" first (but rare).
                // if (count($shortProperties[$shortList]['keys']) > 1) {}
                reset($shortProperties[$shortList]['keys']);
                $kShortList = key($shortProperties[$shortList]['keys']);
            } else {
                $query['property'][] = [
                    'property' => $shortProperty['property'],
                    'type' => $optimizedType,
                    'joiner' => $shortProperty['joiner'],
                    'text' => [],
                ];
                end($query['property']);
                $kShortList = key($query['property']);
            }
            $query['property'][$kShortList]['text'] = $shortProperty['texts'];
            foreach ($shortProperty['keys'] as $shortPropertyKey) {
                unset($query['property'][$shortPropertyKey]);
            }
        }

        return $query;
    }

    /**
     * Allow to search a resource in multiple sites (with "or").
     */
    protected function searchSites(QueryBuilder $qb, array $query): void
    {
        if (empty($query['site_id']) || !is_array($query['site_id'])) {
            return;
        }

        // The site "0" is kept: no site, as in core adapter.
        $sites = array_values(array_unique(array_map('intval', array_filter($query['site_id'], 'is_numeric'))));
        if (!$sites) {
            return;
        }

        $expr = $qb->expr();

        // Adapted from \Omeka\Api\Adapter\ItemAdapter::buildQuery().
        if ($this->adapter instanceof ItemAdapter) {
            $siteAlias = $this->adapter->createAlias();
            $qb->innerJoin(
                'omeka_root.sites', $siteAlias, Join::WITH, $expr->in(
                    "$siteAlias.id",
                    $this->adapter->createNamedParameter($qb, $sites)
                )
            );

            if (!empty($query['site_attachments_only'])) {
                $siteBlockAttachmentsAlias = $this->adapter->createAlias();
                $qb->innerJoin(
                    'omeka_root.siteBlockAttachments',
                    $siteBlockAttachmentsAlias
                );
                $sitePageBlockAlias = $this->adapter->createAlias();
                $qb->innerJoin(
                    "$siteBlockAttachmentsAlias.block",
                    $sitePageBlockAlias
                );
                $sitePageAlias = $this->adapter->createAlias();
                $qb->innerJoin(
                    "$sitePageBlockAlias.page",
                    $sitePageAlias
                );
                $siteAlias = $this->adapter->createAlias();
                $qb->innerJoin(
                    "$sitePageAlias.site",
                    $siteAlias
                );
                $qb->andWhere($expr->in(
                    "$siteAlias.id",
                    $this->adapter->createNamedParameter($qb, $sites))
                );
            }
        }

        // Adapted from \Omeka\Api\Adapter\ItemSetAdapter::buildQuery().
        elseif ($this->adapter instanceof ItemSetAdapter) {
            $siteAdapter = $this->adapter->getAdapter('sites');
            // Though $site isn't used here, this is intended to ensure that the
            // user cannot perform a query against a private site he doesn't
            // have access to.
            // TODO To be optimized.
            foreach ($sites as &$site) {
                try {
                    $siteAdapter->findEntity($site);
                } catch (NotFoundException $e) {
                    $site = 0;
                }
            }
            unset($site);
            $this->siteItemSetsAlias = $this->adapter->createAlias();
            $qb->innerJoin(
                'omeka_root.siteItemSets',
                $this->siteItemSetsAlias
            );
            $qb->andWhere($expr->in(
                "$this->siteItemSetsAlias.site",
                $this->adapter->createNamedParameter($qb, $sites))
            );
        }
    }

    /**
     * Override the core adapter to search resource without template, etc.
     *
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildQuery()
     */
    protected function searchResources(QueryBuilder $qb, array $query): void
    {
        /**
         * Overridden keys of the query are already cleaned.
         *
         * @see \AdvancedSearch\Module::onApiSearchPre()
         * @see \AdvancedSearch\Api\ManagerDelegator::search()
         */

        $expr = $qb->expr();

        if (isset($query['owner_id'])) {
            if (empty($query['owner_id'])) {
                $qb
                    ->andWhere(
                        $expr->isNull('omeka_root.owner')
                    );
            } else {
                $userAlias = $this->adapter->createAlias();
                $qb
                    ->innerJoin(
                        'omeka_root.owner',
                        $userAlias
                    );
                    $qb
                    ->andWhere($expr->eq(
                        "$userAlias.id",
                        $this->adapter->createNamedParameter($qb, $query['owner_id']))
                    );
            }
        }

        if (isset($query['resource_class_id'])
            && $query['resource_class_id'] !== ''
            && $query['resource_class_id'] !== []
            && $query['resource_class_id'] !== null
        ) {
            $resourceClassIds = is_array($query['resource_class_id'])
                ? array_values($query['resource_class_id'])
                : [$query['resource_class_id']];
            if (array_values($resourceClassIds) === [0]) {
                $qb
                    ->andWhere(
                        $expr->isNull('omeka_root.resourceClass')
                    );
            } elseif (in_array(0, $resourceClassIds, true)) {
                $qb
                    ->andWhere($expr->orX(
                        $expr->isNull('omeka_root.resourceClass'),
                        $expr->in(
                            'omeka_root.resourceClass',
                            $this->adapter->createNamedParameter($qb, $resourceClassIds)
                        )
                    ));
            } else {
                $qb
                    ->andWhere($expr->in(
                        'omeka_root.resourceClass',
                        $this->adapter->createNamedParameter($qb, $resourceClassIds)
                    ));
            }
        }

        if (isset($query['resource_template_id'])
            && $query['resource_template_id'] !== ''
            && $query['resource_template_id'] !== []
            && $query['resource_template_id'] !== null
        ) {
            $resourceTemplateIds = is_array($query['resource_template_id'])
                ? array_values($query['resource_template_id'])
                : [$query['resource_template_id']];
            if (array_values($resourceTemplateIds) === [0]) {
                $qb
                    ->andWhere(
                        $expr->isNull('omeka_root.resourceTemplate')
                    );
            } elseif (in_array(0, $resourceTemplateIds, true)) {
                $qb
                    ->andWhere($expr->orX(
                        $expr->isNull('omeka_root.resourceTemplate'),
                        $expr->in(
                            'omeka_root.resourceTemplate',
                            $this->adapter->createNamedParameter($qb, $resourceTemplateIds)
                        )
                    ));
            } else {
                $qb
                    ->andWhere($expr->in(
                        'omeka_root.resourceTemplate',
                        $this->adapter->createNamedParameter($qb, $resourceTemplateIds)
                    ));
            }
        }

        if ($this->adapter instanceof ItemAdapter && isset($query['item_set_id'])
            && $query['item_set_id'] !== ''
            && $query['item_set_id'] !== []
            && $query['item_set_id'] !== null
        ) {
            $itemSetIds = is_array($query['item_set_id'])
                ? array_values($query['item_set_id'])
                : [$query['item_set_id']];
            $itemSetAlias = $this->adapter->createAlias();
            if (array_values($itemSetIds) === [0]) {
                $qb
                    ->leftJoin(
                        'omeka_root.itemSets',
                        $itemSetAlias
                    )
                    ->andWhere($expr->isNull("$itemSetAlias.id"));
            } elseif (in_array(0, $itemSetIds, true)) {
                $qb
                    ->leftJoin(
                        'omeka_root.itemSets',
                        $itemSetAlias
                    )
                    ->andWhere($expr->orX(
                        $expr->isNull("$itemSetAlias.id"),
                        $expr->in(
                            "$itemSetAlias.id",
                            $this->adapter->createNamedParameter($qb, $itemSetIds)
                        )
                    ));
            } else {
                $qb
                    ->innerJoin(
                        'omeka_root.itemSets',
                        $itemSetAlias, Join::WITH,
                        $expr->in("$itemSetAlias.id", $this->adapter->createNamedParameter($qb, $itemSetIds))
                    );
            }
        }
    }

    /**
     * Allow to search a resource by a class term.
     */
    protected function searchResourceClassTerm(QueryBuilder $qb, array $query): void
    {
        if (empty($query['resource_class_term'])) {
            return;
        }

        // When there are only fake classes, no result should be returned, so 0
        // should be used.
        if (is_array($query['resource_class_term'])) {
            $classIds = $this->getResourceClassIds($query['resource_class_term']);
            if (empty($classIds)) {
                $classIds = [0];
            }
        } else {
            $classIds = [(int) $this->getResourceClassId($query['resource_class_term'])];
        }

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
     * - property[{index}][joiner]: "and" OR "or" OR "not" joiner with previous query
     * - property[{index}][property]: property ID or term or array of property IDs or terms
     * - property[{index}][text]: search text or array of texts or values
     * - property[{index}][type]: search type
     * - property[{index}][datatype]: filter on data type(s)
     *   - eq: is exactly (core)
     *   - neq: is not exactly (core)
     *   - in: contains (core)
     *   - nin: does not contain (core)
     *   - ex: has any value (core)
     *   - nex: has no value (core)
     *   - exs: has a single value
     *   - nexs: has not a single value
     *   - exm: has multiple values
     *   - nexm: has not multiple values
     *   - list: is in list
     *   - nlist: is not in list
     *   - sw: starts with
     *   - nsw: does not start with
     *   - ew: ends with
     *   - new: does not end with
     *   - res: has resource (core)
     *   - nres: has no resource (core)
     *   - tp: has main type (literal-like, resource-like, uri-like)
     *   - ntp: has not main type (literal-like, resource-like, uri-like)
     *   - tpl: has type literal-like
     *   - ntpl: has not type literal-like
     *   - tpr: has type resource-like
     *   - ntpr: has not type resource-like
     *   - tpu: has type uri-like
     *   - ntpu: has not type uri-like
     *   - dtp: has data type
     *   - ndtp: has not data type
     *   - lex: is a linked resource
     *   - nlex: is not a linked resource
     *   - lres: is linked with resource #id
     *   - nlres: is not linked with resource #id
     *   Comparisons
     *   Warning: Comparisons are mysql comparisons, so alphabetic ones.
     *   TODO Add specific types to compare date and time (probably useless: use module NumericDataTypes).
     *   - gt: greater than
     *   - gte: greater than or equal
     *   - lte: lower than or equal
     *   - lt: lower than
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    protected function buildPropertyQuery(QueryBuilder $qb, array $query): void
    {
        if (empty($query['property']) || !is_array($query['property'])) {
            return;
        }

        $valuesJoin = 'omeka_root.values';
        $where = '';

        // @see \Doctrine\ORM\QueryBuilder::expr().
        $expr = $qb->expr();
        $entityManager = $this->adapter->getEntityManager();

        $escapeSql = function ($string) {
            return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $string);
        };

        // Initialize properties and used properties one time.
        $this->getPropertyIds();

        foreach ($query['property'] as $queryRow) {
            if (!is_array($queryRow)
                || empty($queryRow['type'])
                || !isset(self::PROPERTY_QUERY['reciprocal'][$queryRow['type']])
            ) {
                continue;
            }

            $queryType = $queryRow['type'];
            $value = $queryRow['text'] ?? '';

            // Quick check of value.
            // A empty string "" is not a value, but "0" is a value.
            if (in_array($queryType, self::PROPERTY_QUERY['value_none'], true)) {
                $value = null;
            }
            // Check array of values.
            elseif (in_array($queryType, self::PROPERTY_QUERY['value_array'], true)) {
                if ((is_array($value) && !count($value))
                    || (!is_array($value) && !strlen((string) $value))
                ) {
                    continue;
                }
                if (!is_array($value)) {
                    $value = [$value];
                }
                // To use array_values() avoids doctrine issue with string keys.
                $value = in_array($queryType, self::PROPERTY_QUERY['value_integer'])
                    ? array_values(array_unique(array_map('intval', $value)))
                    : array_values(array_unique(array_filter(array_map('trim', array_map('strval', $value)), 'strlen')));
                if (empty($value)) {
                    continue;
                }
            }
            // The value should be a scalar in all other cases.
            elseif (is_array($value) || !strlen((string) $value)) {
                continue;
            }

            $joiner = $queryRow['joiner'] ?? '';
            $dataType = $queryRow['datatype'] ?? '';

            // Invert the query type for joiner "not".
            if ($joiner === 'not') {
                $joiner = 'and';
                $queryType = self::PROPERTY_QUERY['reciprocal'][$queryType];
            }

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
                    $param = $this->adapter->createNamedParameter($qb, '%' . $escapeSql($value) . '%');
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
                    $param = $this->adapter->createNamedParameter($qb, $value);
                    $qb->setParameter(substr($param, 1), $value, Connection::PARAM_STR_ARRAY);
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
                    $param = $this->adapter->createNamedParameter($qb, $escapeSql($value) . '%');
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
                    $param = $this->adapter->createNamedParameter($qb, '%' . $escapeSql($value));
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
                    if (count($value) <= 1) {
                        $param = $this->adapter->createNamedParameter($qb, (int) reset($value));
                        $predicateExpr = $expr->eq("$valuesAlias.valueResource", $param);
                    } else {
                        $param = $this->adapter->createNamedParameter($qb, $value);
                        $qb->setParameter(substr($param, 1), $value, Connection::PARAM_INT_ARRAY);
                        $predicateExpr = $expr->in("$valuesAlias.valueResource", $param);
                    }
                    break;

                case 'nex':
                    $positive = false;
                    // no break.
                case 'ex':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.id");
                    break;

                case 'nexs':
                    // No predicate expression, but simplify process.
                    $predicateExpr = $expr->eq(1, 1);
                    $qb->having($expr->neq("COUNT($valuesAlias.id)", 1));
                    break;
                case 'exs':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.id");
                    $qb->having($expr->eq("COUNT($valuesAlias.id)", 1));
                    break;

                case 'nexm':
                    // No predicate expression, but simplify process.
                    $predicateExpr = $expr->eq(1, 1);
                    $qb->having($expr->lt("COUNT($valuesAlias.id)", 2));
                    break;
                case 'exm':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.id");
                    $qb->having($expr->gt("COUNT($valuesAlias.id)", 1));
                    break;

                case 'ntp':
                    $positive = false;
                    // no break.
                case 'tp':
                    if ($value === 'literal') {
                        // Because a resource or a uri can have a label stored
                        // in "value", a literal-like value is a value without
                        // resource and without uri.
                        $predicateExpr = $expr->andX(
                            $expr->isNull("$valuesAlias.valueResource"),
                            $expr->isNull("$valuesAlias.uri")
                        );
                    } elseif ($value === 'resource') {
                        $predicateExpr = $expr->isNotNull("$valuesAlias.valueResource");
                    } elseif ($value === 'uri') {
                        $predicateExpr = $expr->isNotNull("$valuesAlias.uri");
                    } else {
                        $predicateExpr = $expr->eq(1, 0);
                    }
                    break;

                case 'ntpl':
                    $positive = false;
                    // no break.
                case 'tpl':
                    // Because a resource or a uri can have a label stored
                    // in "value", a literal-like value is a value without
                    // resource and without uri.
                    $predicateExpr = $expr->andX(
                        $expr->isNull("$valuesAlias.valueResource"),
                        $expr->isNull("$valuesAlias.uri")
                    );
                    break;

                case 'ntpr':
                    $positive = false;
                    // no break.
                case 'tpr':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.valueResource");
                    break;

                case 'ntpu':
                    $positive = false;
                    // no break.
                case 'tpu':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.uri");
                    break;

                case 'ndtp':
                    $positive = false;
                    // no break.
                case 'dtp':
                    if (count($value) <= 1) {
                        $dataTypeAlias = $this->adapter->createNamedParameter($qb, reset($value));
                        $predicateExpr = $expr->eq("$valuesAlias.type", $dataTypeAlias);
                    } else {
                        $dataTypeAlias = $this->adapter->createAlias();
                        $qb->setParameter($dataTypeAlias, $value, Connection::PARAM_STR_ARRAY);
                        $predicateExpr = $expr->in("$valuesAlias.type", $dataTypeAlias);
                    }
                    break;

                // The linked resources (subject values) use the same sub-query.
                case 'nlex':
                    // For consistency, "nlex" is the reverse of "lex" even when
                    // a resource is linked with a public and a private resource.
                    // A private linked resource is not linked for an anonymous.
                case 'nlres':
                    $positive = false;
                    // no break.
                case 'lex':
                case 'lres':
                    $subValuesAlias = $this->adapter->createAlias();
                    $subResourceAlias = $this->adapter->createAlias();
                    // Use a subquery so rights are automatically managed.
                    $subQb = $entityManager
                        ->createQueryBuilder()
                        ->select("IDENTITY($subValuesAlias.valueResource)")
                        ->from(\Omeka\Entity\Value::class, $subValuesAlias)
                        ->innerJoin("$subValuesAlias.resource", $subResourceAlias)
                        ->where($expr->isNotNull("$subValuesAlias.valueResource"));
                    // Warning: the property check should be done on subjects,
                    // so the predicate expression is finalized below.
                    if (is_array($value)) {
                        // In fact, "lres" is the list of linked resources.
                        if (count($value) <= 1) {
                            $param = $this->adapter->createNamedParameter($qb, (int) reset($value));
                            $subQb->andWhere($expr->eq("$subValuesAlias.resource", $param));
                        } else {
                            $param = $this->adapter->createNamedParameter($qb, $value);
                            $qb->setParameter(substr($param, 1), $value, Connection::PARAM_INT_ARRAY);
                            $subQb->andWhere($expr->in("$subValuesAlias.resource", $param));
                        }
                    }
                    break;

                // TODO Manage uri and resources with gt, gte, lte, lt (it has a meaning at least for resource ids, but separate).
                case 'gt':
                    $predicateExpr = $expr->gt(
                        "$valuesAlias.value",
                        $this->adapter->createNamedParameter($qb, $value)
                    );
                    break;
                case 'gte':
                    $predicateExpr = $expr->gte(
                        "$valuesAlias.value",
                        $this->adapter->createNamedParameter($qb, $value)
                    );
                    break;
                case 'lte':
                    $predicateExpr = $expr->lte(
                        "$valuesAlias.value",
                        $this->adapter->createNamedParameter($qb, $value)
                    );
                    break;
                case 'lt':
                    $predicateExpr = $expr->lt(
                        "$valuesAlias.value",
                        $this->adapter->createNamedParameter($qb, $value)
                    );
                    break;

                default:
                    continue 2;
            }

            // Avoid to get results when the query is incorrect.
            // In that case, no param should be set in the current loop.
            if ($incorrectValue) {
                $where = $expr->eq('omeka_root.id', 0);
                break;
            }

            $joinConditions = [];

            // Narrow to specific properties, if one or more are selected.
            $propertyIds = $queryRow['property'] ?? null;
            // Properties may be an array with an empty value (any property) in
            // advanced form, so remove empty strings from it, in which case the
            // check should be skipped.
            if (is_array($propertyIds) && in_array('', $propertyIds, true)) {
                $propertyIds = [];
            }
            // TODO What if a property is ""?
            $excludePropertyIds = $propertyIds || empty($queryRow['except'])
                ? false
                :  array_values(array_unique($this->getPropertyIds($queryRow['except'])));
            if ($propertyIds) {
                $propertyIds = array_values(array_unique($this->getPropertyIds($propertyIds)));
                if ($propertyIds) {
                    // For queries on subject values, the properties should be
                    // checked against the sub-query.
                    if (in_array($queryType, self::PROPERTY_QUERY['value_subject'])) {
                        $subQb
                            ->andWhere(count($propertyIds) < 2
                                ? $expr->eq("$subValuesAlias.property", reset($propertyIds))
                                : $expr->in("$subValuesAlias.property", $propertyIds)
                            );
                    } else {
                        $joinConditions[] = count($propertyIds) < 2
                            ? $expr->eq("$valuesAlias.property", reset($propertyIds))
                            : $expr->in("$valuesAlias.property", $propertyIds);
                    }
                } else {
                    // Don't return results for this part for fake properties.
                    $joinConditions[] = $expr->eq("$valuesAlias.property", 0);
                }
            }
            // Use standard query if nothing to exclude, else limit search.
            elseif ($excludePropertyIds) {
                // The aim is to search anywhere except ocr content.
                // Use not positive + in() or notIn()? A full list is simpler.
                $otherIds = array_diff($this->usedPropertiesByTerm, $excludePropertyIds);
                // Avoid issue when everything is excluded.
                $otherIds[] = 0;
                if (in_array($queryType, self::PROPERTY_QUERY['value_subject'])) {
                    $subQb
                        ->andWhere($expr->in("$subValuesAlias.property", $otherIds));
                } else {
                    $joinConditions[] = $expr->in("$valuesAlias.property", $otherIds);
                }
            }

            // Finalize predicate expression on subject values.
            if (in_array($queryType, self::PROPERTY_QUERY['value_subject'])) {
                $predicateExpr = $expr->in("$valuesAlias.resource", $subQb->getDQL());
            }

            if ($dataType) {
                if (!is_array($dataType) || count($dataType) <= 1) {
                    $dataTypeAlias = $this->adapter->createNamedParameter($qb, is_array($dataType) ? reset($dataType) : $dataType);
                    $predicateExpr = $expr->andX(
                        $predicateExpr,
                        $expr->eq("$valuesAlias.type", $dataTypeAlias)
                    );
                } else {
                    $dataTypeAlias = $this->adapter->createAlias();
                    $qb->setParameter($dataTypeAlias, array_values($dataType), Connection::PARAM_STR_ARRAY);
                    $predicateExpr = $expr->andX(
                        $predicateExpr,
                        $expr->in("$valuesAlias.type", ':' . $dataTypeAlias)
                    );
                }
            }

            if ($positive) {
                $whereClause = '(' . $predicateExpr . ')';
            } else {
                $joinConditions[] = $predicateExpr;
                $whereClause = $expr->isNull("$valuesAlias.id");
            }

            if ($joinConditions) {
                $qb->leftJoin($valuesJoin, $valuesAlias, Join::WITH, $expr->andX(...$joinConditions));
            } else {
                $qb->leftJoin($valuesJoin, $valuesAlias);
            }

            if ($where == '') {
                $where = $whereClause;
            } elseif ($joiner === 'or') {
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
     * @param array $query The query should be cleaned first.
     */
    protected function searchDateTime(
        QueryBuilder $qb,
        array $query
    ): void {
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
     * Build query to check by media types.
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    protected function searchByMediaType(
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
        $values = array_values($values);

        $expr = $qb->expr();

        if ($this->adapter instanceof MediaAdapter) {
            $qb
                ->andWhere($expr->in(
                    'omeka_root.mediaType',
                    $this->adapter->createNamedParameter($qb, $values)
                ));
            return;
        }

        $mediaAlias = $this->adapter->createAlias();

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

        // Overridden keys of the query are already cleaned.

        $expr = $qb->expr();
        $itemAlias = $this->adapter->createAlias();
        $itemSetAlias = $this->adapter->createAlias();

        // To use array_values() avoids doctrine issue with string keys.
        $itemSetIds = array_values($query['item_set_id']);

        $qb
            ->innerJoin(
                'omeka_root.item',
                $itemAlias, Join::WITH,
                $expr->eq("$itemAlias.id", 'omeka_root.item')
            );

        if ($itemSetIds === [0]) {
            $qb
                ->leftJoin(
                    "$itemAlias.itemSets",
                    $itemSetAlias
                )
                ->andWhere($expr->isNull("$itemSetAlias.id"));
        } elseif (in_array(0, $itemSetIds, true)) {
            $qb
                ->leftJoin(
                    "$itemAlias.itemSets",
                    $itemSetAlias
                )
                ->andWhere($expr->orX(
                    $expr->isNull("$itemSetAlias.id"),
                    $expr->in(
                        "$itemSetAlias.id",
                        $this->adapter->createNamedParameter($qb, $itemSetIds)
                    )
                ));
        } else {
            $qb
                ->innerJoin(
                    "$itemAlias.itemSets",
                    $itemSetAlias, Join::WITH,
                    $expr->in("$itemSetAlias.id", $this->adapter->createNamedParameter($qb, $itemSetIds))
                );
        }
    }

    /**
     * Convert into a standard DateTime. Manage some badly formatted values.
     *
     * Adapted from module NumericDataType.
     * The regex pattern allows partial month and day too.
     * @link https://mariadb.com/kb/en/datetime/
     * @see \NumericDataTypes\DataType\AbstractDateTimeDataType::getDateTimeFromValue()
     *
     * Allow mysql datetime too, not only iso 8601 (so with a space, not only a
     * "T" to separate date and time).
     *
     * Warning, year "0" does not exists, so output is null in that case.
     *
     * @param string $value
     * @param bool $defaultFirst
     * @return array|null
     */
    protected function getDateTimeFromValue($value, $defaultFirst = true)
    {
        $yearMin = -292277022656;
        $yearMax = 292277026595;
        $patternIso8601 = '^(?<date>(?<year>-?\d{1,})(-(?<month>\d{1,2}))?(-(?<day>\d{1,2}))?)(?<time>((?:T| )(?<hour>\d{1,2}))?(:(?<minute>\d{1,2}))?(:(?<second>\d{1,2}))?)(?<offset>((?<offset_hour>[+-]\d{1,2})?(:(?<offset_minute>\d{1,2}))?)|Z?)$';
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
        $matches = array_filter($matches, 'strlen');
        if (!isset($matches['date'])) {
            return null;
        }

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
            'year' => empty($matches['year']) ? null : (int) $matches['year'],
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
     * Get one or more property ids by JSON-LD terms or by numeric ids.
     *
     * @todo Factorize with \AdvancedSearch\View\Helper\EasyMeta::propertyIds() (differences: return array and used properties).
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return int[] The property ids matching terms or ids, or all properties
     * by terms.
     */
    protected function getPropertyIds($termsOrIds = null): array
    {
        static $propertiesByTerms;
        static $propertiesByTermsAndIds;

        if (is_null($propertiesByTermsAndIds)) {
            $qb = $this->connection->createQueryBuilder();
            $qb
                ->select(
                    'DISTINCT CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                    'property.id AS id',
                    // Required with only_full_group_by.
                    'vocabulary.id'
                )
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('property.id', 'asc')
            ;
            $propertiesByTerms = array_map('intval', $this->connection->executeQuery($qb)->fetchAllKeyValue());
            $propertiesByTermsAndIds = array_replace($propertiesByTerms, array_combine($propertiesByTerms, $propertiesByTerms));

            $qb->innerJoin('property', 'value', 'value', 'property.id = value.property_id');
            $this->usedPropertiesByTerm = array_map('intval', $this->connection->executeQuery($qb)->fetchAllKeyValue());
        }

        if (is_null($termsOrIds)) {
            return $propertiesByTerms;
        }

        if (is_scalar($termsOrIds)) {
            return isset($propertiesByTermsAndIds[$termsOrIds])
                ? [$termsOrIds => $propertiesByTermsAndIds[$termsOrIds]]
                : [];
        }

        // TODO Keep original order.
        return array_intersect_key($propertiesByTermsAndIds, array_fill_keys($termsOrIds, null));
    }

    /**
     * Get resource class ids by JSON-LD terms or by numeric ids.
     *
     * @return int[]
     */
    protected function getResourceClassIds(array $termsOrIds): array
    {
        if (is_null($this->resourceClassesByTermsAndIds)) {
            $this->prepareResourceClasses();
        }
        return array_values(array_intersect_key($this->resourceClassesByTermsAndIds, array_flip($termsOrIds)));
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
     * Prepare the list of resource classes and used resource classes by term.
     */
    protected function prepareResourceClasses(): self
    {
        if (is_null($this->resourceClassesByTermsAndIds)) {
            $qb = $this->connection->createQueryBuilder();
            $qb
                ->select(
                    'CONCAT(vocabulary.prefix, ":", resource_class.local_name) AS term',
                    'resource_class.id AS id',
                    // Required with only_full_group_by.
                    'vocabulary.id'
                )
                ->from('resource_class', 'resource_class')
                ->innerJoin('resource_class', 'vocabulary', 'vocabulary', 'resource_class.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('resource_class.id', 'asc')
            ;
            $resourceClasses = array_map('intval', $this->connection->executeQuery($qb)->fetchAllKeyValue());
            $this->resourceClassesByTermsAndIds = array_replace($resourceClasses, array_combine($resourceClasses, $resourceClasses));

            $qb->innerJoin('resource_class', 'resource', 'resource', 'resource_class.id = resource.resource_class_id');
            $this->usedResourceClassesByTerm = array_map('intval', $this->connection->executeQuery($qb)->fetchAllKeyValue());
            return $this;
        }
    }
}
