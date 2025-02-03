<?php declare(strict_types=1);

namespace AdvancedSearch\Stdlib;

use Common\Stdlib\EasyMeta;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Laminas\Log\Logger;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Adapter\ItemAdapter;
use Omeka\Api\Adapter\ItemSetAdapter;
use Omeka\Api\Adapter\MediaAdapter;
use Omeka\Api\Adapter\ResourceAdapter;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Request;

class SearchResources
{
    /**
     * Tables of all query types and their behaviors.
     *
     * May be used by other modules.
     *
     * @var array
     */
    const FIELD_QUERY = [
        'reciprocal' => [
            // Value.
            'eq' => 'neq',
            'neq' => 'eq',
            'in' => 'nin',
            'nin' => 'in',
            'sw' => 'nsw',
            'nsw' => 'sw',
            'ew' => 'new',
            'new' => 'ew',
            'near' => 'nnear',
            'nnear' => 'near',
            'ma' => 'nma',
            'nma' => 'ma',
            // Comparison.
            'lt' => 'gte',
            'lte' => 'gt',
            'gte' => 'lt',
            'gt' => 'lte',
            '<' => '≥',
            '≤' => '>',
            '≥' => '<',
            '>' => '≤',
            // Date (year).
            'yreq' => 'nyreq',
            'nyreq' => 'yreq',
            'yrlt' => 'yrgte',
            'yrlte' => 'yrgt',
            'yrgte' => 'yrlt',
            'yrgt' => 'yrlte',
            // Internal and deprecated.
            'list' => 'nlist',
            'nlist' => 'list',
            // Resource.
            'res' => 'nres',
            'nres' => 'res',
            'resq' => 'nresq',
            'nresq' => 'resq',
            // Linked resource.
            'lex' => 'nlex',
            'nlex' => 'lex',
            'lres' => 'nlres',
            'nlres' => 'lres',
            'lkq' => 'nlkq',
            'nlkq' => 'lkq',
            // Count.
            'ex' => 'nex',
            'nex' => 'ex',
            'exs' => 'nexs',
            'nexs' => 'exs',
            'exm' => 'nexm',
            'nexm' => 'exm',
            // Data type.
            'dt' => 'ndt',
            'ndt' => 'dt',
            'dtp' => 'ndtp',
            'ndtp' => 'dtp',
            'tp' => 'ntp',
            'ntp' => 'tp',
            'tpl' => 'ntpl',
            'ntpl' => 'tpl',
            'tpr' => 'ntpr',
            'ntpr' => 'tpr',
            'tpu' => 'ntpu',
            'ntpu' => 'tpu',
            // Curation (duplicates).
            'dup' => 'ndup',
            'ndup' => 'dup',
            'dupl' => 'ndupl',
            'ndupl' => 'dupl',
            'dupt' => 'ndupt',
            'ndupt' => 'dupt',
            'duptl' => 'nduptl',
            'nduptl' => 'duptl',
            'dupv' => 'ndupv',
            'ndupv' => 'dupv',
            'dupvl' => 'ndupvl',
            'ndupvl' => 'dupvl',
            'dupvt' => 'ndupvt',
            'ndupvt' => 'dupvt',
            'dupvtl' => 'ndupvtl',
            'ndupvtl' => 'dupvtl',
            'dupr' => 'ndupr',
            'ndupr' => 'dupr',
            'duprl' => 'nduprl',
            'nduprl' => 'duprl',
            'duprt' => 'nduprt',
            'nduprt' => 'duprt',
            'duprtl' => 'nduprtl',
            'nduprtl' => 'duprtl',
            'dupu' => 'ndupu',
            'ndupu' => 'dupu',
            'dupul' => 'ndupul',
            'ndupul' => 'dupul',
            'duput' => 'nduput',
            'nduput' => 'duput',
            'duputl' => 'nduputl',
            'nduputl' => 'duputl',
        ],
        'negative' => [
            // Value.
            'neq',
            'nin',
            'nsw',
            'new',
            'nnear',
            'nma',
            'nlist',
            // Date.
            'nyreq',
            // Resource.
            'nres',
            'nresq',
            // Linked resource.
            'nlex',
            'nlres',
            'nlkq',
            // Count.
            'nex',
            'nexs',
            'nexm',
            // Data type.
            'ndt',
            'ndtp',
            'ntp',
            'ntpl',
            'ntpr',
            'ntpu',
            // Curation (duplicates).
            'ndup',
            'ndupl',
            'ndupt',
            'nduptl',
            'ndupv',
            'ndupvl',
            'ndupvt',
            'ndupvtl',
            'ndupr',
            'nduprl',
            'nduprt',
            'nduprtl',
            'ndupu',
            'ndupul',
            'nduput',
            'nduputl',
        ],
        // Deprecated key: all types allow array, except single and none below.
        'value_array' => [
            'list',
            'nlist',
            'res',
            'nres',
            'resq',
            'nresq',
            'lres',
            'nlres',
            'lkq',
            'nlkq',
            'dtp',
            'ndtp',
        ],
        // These types allow only one value, but it may be an array.
        'value_single_array_or_string' => [
            'resq',
            'nresq',
            'lkq',
            'nlkq',
        ],
        'value_single' => [
            'tp',
            'ntp',
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
            'dup',
            'ndup',
            'dupl',
            'ndupl',
            'dupt',
            'ndupt',
            'duptl',
            'nduptl',
            'dupv',
            'ndupv',
            'dupvl',
            'ndupvl',
            'dupvt',
            'ndupvt',
            'dupvtl',
            'ndupvtl',
            'dupr',
            'ndupr',
            'duprl',
            'nduprl',
            'duprt',
            'nduprt',
            'duprtl',
            'nduprtl',
            'dupu',
            'ndupu',
            'dupul',
            'ndupul',
            'duput',
            'nduput',
            'duputl',
            'nduputl',
        ],
        'value_integer' => [
            'yreq',
            'nyreq',
            'yrlt',
            'yrlte',
            'yrgte',
            'yrgt',
            'res',
            'nres',
            'lres',
            'nlres',
        ],
        'value_subject' => [
            'lex',
            'nlex',
            'lres',
            'nlres',
            'lkq',
            'nlkq',
        ],
        'sub_query' => [
            'resq',
            'nresq',
            'lkq',
            'nlkq',
        ],
        // Deprecated. Optimize for properties.
        'optimize' => [
            'eq' => 'list',
            'neq' => 'nlist',
            'list' => 'list',
            'nlist' => 'nlist',
        ],
        // The main type may be value, resource or uri.
        // Only resource is useful for now.
        'main_type' => [
            // The
            'value' => [
            ],
            'resource' => [
                // Resource.
                'res',
                'nres',
                'resq',
                'nresq',
                // Linked resource.
                'lex',
                'nlex',
                'lres',
                'nlres',
                'lkq',
                'nlkq',
            ],
            'uri' => [
            ],
        ],
        'core' => [
            'eq',
            'neq',
            'in',
            'nin',
            'ex',
            'nex',
            'sw',
            'nsw',
            'ew',
            'new',
            'res',
            'nres',
            'dt',
            'ndt',
        ],
    ];

    /**
     * The adapter used to build the query.
     *
     * @var \Omeka\Api\Adapter\AbstractResourceEntityAdapter
     */
    protected $adapter;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * Contains two keys: aliases and query_args.
     *
     * @var array
     */
    protected $searchIndex = [
        'aliases' => [],
        'query_args' => [],
        'form_filters_fields' => [],
    ];

    public function __construct(
        Connection $connection,
        EasyMeta $easyMeta,
        Logger $logger,
        array $searchIndex
    ) {
        $this->connection = $connection;
        $this->easyMeta = $easyMeta;
        $this->logger = $logger;
        $this->searchIndex = $searchIndex;
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
        $query = $this->cleanQuery($query);

        // Process advanced search plus features.
        $this
            // Override for multiple sites. Replaced upstream by "in_sites".
            ->searchSites($qb, $query)
            // Override for without owner, resource class id, and resource
            // template id (with value "0").
            ->searchResources($qb, $query)
            ->searchResourceAssets($qb, $query)
            ->searchResourceClassTerm($qb, $query)
            // Partially implemented upstream.
            ->searchDateTime($qb, $query)
            // Override property with many features, replaced by filter.
            ->buildPropertyQuery($qb, $query)
            ->buildFilterQuery($qb, $query);
        if ($this->adapter instanceof ItemAdapter) {
            $this
                // Override for without item set id (with value "0").
                ->searchItemItemSets($qb, $query)
                ->searchItemMediaData($qb, $query);
        } elseif ($this->adapter instanceof MediaAdapter) {
            $this
                ->searchMediaByItemSet($qb, $query)
                ->searchHasOriginal($qb, $query)
                ->searchHasThumbnails($qb, $query)
                // Override for multiple and main media type.
                ->searchByMediaType($qb, $query);
        } elseif ($this->adapter instanceof ResourceAdapter) {
            $this
                ->searchResourcesByType($qb, $query);
        }
        $this
            ->sortQuery($qb, $query);
    }

    /**
     * Clear useless keys of a query.
     *
     * The advanced search form returns all keys, so clear useless ones and
     * avoid to check them in many places.
     *
     * Warning: an empty string for the keys "sort_by_default" and "sort_order_default"
     * have a meaning and are used internally, so they must not be removed.
     *
     * @todo Improve cleaning query.
     */
    public function cleanQuery(array $query): array
    {
        // Most of the time, there is only one query, but it can be used for
        // results, count, search filters, etc.
        static $cleanQueries = [];

        if (array_key_exists('__original_query', $query)) {
            return $query;
        }

        $originalQuery = $query;
        unset($originalQuery['__searchConfig']);
        unset($originalQuery['__searchQuery']);

        $sum = md5(serialize($originalQuery));
        if (isset($cleanQueries[$sum])) {
            return $cleanQueries[$sum];
        }

        $query = $this->expandFieldQueryArgs($query);

        // Add warning before cleaning. The right queries are still processed.
        foreach ($query['property'] ?? [] as $queryRow) {
            if (isset($queryRow['property']) && is_array($queryRow['property'])) {
                $this->logger->warn(
                    'The query arg "property" won’t support multiple properties in a future version, because it’s overriding the default behavior. Use arg "filter" instead. Check your queries: {url}', // @translate
                    ['url' => $_SERVER['REQUEST_URI']]
                );
            }
            if (isset($queryRow['type']) && !in_array($queryRow['type'], self::FIELD_QUERY['core'])) {
                $this->logger->warn(
                    'The query arg "property" won’t support type {type} in a future version, because it’s overriding the default behavior. Use arg "filter" instead. Check your queries: {url}', // @translate
                    ['type' => $queryRow['type'], 'url' => $_SERVER['REQUEST_URI']]
                );
            }
            if (isset($queryRow['text']) && is_array($queryRow['text'])) {
                $this->logger->warn(
                    'The query arg "property" won’t support multiple values in a future version, because it’s overriding the default behavior. Use arg "filter" instead. Check your queries: {url}', // @translate
                    ['url' => $_SERVER['REQUEST_URI']]
                );
            }
        }

        foreach ($query as $key => $value) {
            if ($key === 'sort_by_default' || $key === 'sort_order_default') {
                // Keep these keys.
                continue;
            } elseif (
                $value === ''
                || $value === null
                || $value === []
                || !$key
                || is_numeric($key)
                || $key === 'submit'
                || $key === 'numeric-toggle-time-checkbox'
            ) {
                unset($query[$key]);
            } elseif ($key === 'id') {
                /** @see \Omeka\Api\Adapter\AbstractEntityAdapter::buildBaseQuery() */
                // Avoid a strict type issue, so convert ids as string.
                if (is_int($value)) {
                    $value = [(string) $value];
                } elseif (is_string($value)) {
                    $value = strpos($value, ',') === false ? [$value] : explode(',', $value);
                } elseif (!is_array($value)) {
                    $value = [];
                }
                $value = array_map('trim', $value);
                $value = array_filter($value, 'strlen');
                if (count($value)) {
                    $query['id'] = $value;
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
                'asset_id',
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
                    $query = $this->optimizeQueryProperty($query);
                } else {
                    unset($query['property']);
                }
            } elseif ($key === 'filter') {
                if (is_array($value)) {
                    $query = $this->optimizeQueryFilter($query);
                } else {
                    unset($query['filter']);
                }
            } elseif ($key === 'datetime') {
                // Manage a single date time.
                if (!is_array($query['datetime'])) {
                    $query['datetime'] = [[
                        'join' => 'and',
                        'field' => 'created',
                        'type' => '=',
                        'val' => $query['datetime'],
                    ]];
                } else {
                    $dateTimeQueryTypes = [
                        '<' => '<',
                        '≤' => '≤',
                        '=' => '=',
                        '≠' => '≠',
                        '≥' => '≥',
                        '>' => '>',
                        'lt' => '<',
                        'lte' => '≤',
                        'eq' => '=',
                        'neq' => '≠',
                        'gte' => '≥',
                        'gt' => '>',
                        'ex' => 'ex',
                        'nex' => 'nex',
                    ];
                    foreach ($query['datetime'] as $key => &$queryRow) {
                        if (empty($queryRow)) {
                            unset($query['datetime'][$key]);
                            continue;
                        }

                        // Clean query and manage default values.
                        if (is_array($queryRow)) {
                            $queryRow = array_map('mb_strtolower', array_map('trim', $queryRow));
                            if (empty($queryRow['join'])) {
                                $queryRow['join'] = 'and';
                            } else {
                                if (!in_array($queryRow['join'], ['and', 'or', 'not'])) {
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
                                $queryRow['type'] = '=';
                            } else {
                                // "ex" and "nex" are useful only for the modified time.
                                if (!isset($dateTimeQueryTypes[$queryRow['type']])) {
                                    unset($query['datetime'][$key]);
                                    continue;
                                }
                                $queryRow['type'] = $dateTimeQueryTypes[$queryRow['type']];
                            }

                            if (in_array($queryRow['type'], ['ex', 'nex'])) {
                                $query['datetime'][$key]['val'] = '';
                            } elseif (empty($queryRow['val'])) {
                                unset($query['datetime'][$key]);
                                continue;
                            } else {
                                // Date time cannot be longer than 19 numbers.
                                // But user can choose a year only, etc.
                            }
                        } else {
                            $queryRow = [
                                'join' => 'and',
                                'field' => 'created',
                                'type' => '=',
                                'val' => $queryRow,
                            ];
                        }
                    }
                    unset($queryRow);
                }
            } elseif ($key === 'numeric') {
                if (is_array($value)) {
                    // Timestamp.
                    if (empty($query['numeric']['ts']['gt']['pid']) && empty($query['numeric']['ts']['gt']['val'])) {
                        unset($query['numeric']['ts']['gt']);
                    }
                    if (empty($query['numeric']['ts']['lt']['pid']) && empty($query['numeric']['ts']['lt']['val'])) {
                        unset($query['numeric']['ts']['lt']);
                    }
                    if (empty($query['numeric']['ts']['gte']['pid']) && empty($query['numeric']['ts']['gte']['val'])) {
                        unset($query['numeric']['ts']['gte']);
                    }
                    if (empty($query['numeric']['ts']['lte']['pid']) && empty($query['numeric']['ts']['lte']['val'])) {
                        unset($query['numeric']['ts']['lte']);
                    }
                    if (empty($query['numeric']['ts']['gt']) && empty($query['numeric']['ts']['lt']) && empty($query['numeric']['ts']['gte']) && empty($query['numeric']['ts']['lte'])) {
                        unset($query['numeric']['ts']);
                    }
                    // Duration.
                    if (empty($query['numeric']['dur']['gt']['pid']) && empty($query['numeric']['dur']['gt']['val'])) {
                        unset($query['numeric']['dur']['gt']);
                    }
                    if (empty($query['numeric']['dur']['lt']['pid']) && empty($query['numeric']['dur']['lt']['val'])) {
                        unset($query['numeric']['dur']['lt']);
                    }
                    if (empty($query['numeric']['dur']['gt']) && empty($query['numeric']['dur']['lt'])) {
                        unset($query['numeric']['dur']);
                    }
                    // Interval.
                    if (empty($query['numeric']['ivl']['pid']) && empty($query['numeric']['ivl']['val'])) {
                        unset($query['numeric']['ivl']);
                    }
                    // Integer.
                    if (empty($query['numeric']['int']['gt']['pid']) && empty($query['numeric']['int']['gt']['val'])) {
                        unset($query['numeric']['int']['gt']);
                    }
                    if (empty($query['numeric']['int']['lt']['pid']) && empty($query['numeric']['int']['lt']['val'])) {
                        unset($query['numeric']['int']['lt']);
                    }
                    if (empty($query['numeric']['int']['gt']) && empty($query['numeric']['int']['lt'])) {
                        unset($query['numeric']['int']);
                    }
                    // Global.
                    if (empty($query['numeric'])) {
                        unset($query['numeric']);
                    }
                } else {
                    unset($query['numeric']);
                }
            } elseif ($key === 'sort_ids') {
                if (is_int($value)) {
                    $value = [(string) $value];
                } elseif (is_string($value)) {
                    $value = strpos($value, ',') === false ? [$value] : explode(',', $value);
                } elseif (!is_array($value)) {
                    $value = [];
                }
                $value = array_map('trim', $value);
                $value = array_filter($value, 'strlen');
                if (count($value)) {
                    $query['sort_ids'] = $value;
                } else {
                    unset($query['sort_ids']);
                }
            }
        }

        $cleanQueries[$sum] = $query;
        $query['__original_query'] = $originalQuery;

        return $query;
    }

    /**
     * Helper to optimize query for properties.
     *
     * @deprecated Use optimizeQueryFilter() instead.
     */
    protected function optimizeQueryProperty(array $query): array
    {
        if (empty($query['property']) || !is_array($query['property'])) {
            unset($query['property']);
            return $query;
        }

        $shortProperties = [];

        foreach ($query['property'] as $k => $queryRow) {
            if (!is_array($queryRow)
                || empty($queryRow['type'])
                || !isset(self::FIELD_QUERY['reciprocal'][$queryRow['type']])
            ) {
                unset($query['property'][$k]);
                continue;
            }

            $queryType = $queryRow['type'];
            $queryValue = $queryRow['text'] ?? '';

            // Quick check of value.
            // A empty string "" is not a value, but "0" is a value.
            if (in_array($queryType, self::FIELD_QUERY['value_none'], true)) {
                $queryValue = null;
            }
            // Check array of values.
            // This feature is deprecated.
            elseif (in_array($queryType, self::FIELD_QUERY['value_array'], true)) {
                if ((is_array($queryValue) && !count($queryValue))
                    || (!is_array($queryValue) && !strlen((string) $queryValue))
                ) {
                    unset($query['property'][$k]);
                    continue;
                }
                if (!is_array($queryValue)) {
                    $queryValue = [$queryValue];
                }
                $queryValue = in_array($queryType, self::FIELD_QUERY['value_integer'])
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

            $queryRowProp = $queryRow['property'] ?? null;
            if (is_array($queryRowProp)) {
                $query['property'][$k]['property'] = array_unique($query['property'][$k]['property']);
            }

            // Init the short properties to prepare optimization.
            // Joiner should be "OR", because "AND" cannot be used with sql "IN()".
            // @see https://github.com/Daniel-KM/Omeka-S-module-AdvancedSearch/issues/4
            if (isset($query['property'][$k]['joiner'])
                && $query['property'][$k]['joiner'] === 'or'
                && in_array($queryType, ['eq', 'list', 'neq', 'nlist'])
            ) {
                // TODO Manage the case where the property is numeric or term (simplify above instead of in the process below).
                $queryRowProperty = is_array($queryRowProp) ? implode(',', $queryRowProp) : $queryRowProp;
                $short = '/or'
                    . '/' . $queryRowProperty
                    . '/' . (empty($queryRow['except']) ? '' : serialize($queryRow['except']))
                    . '/' . (in_array($queryRow['type'], ['neq', 'nlist']) ? 'nlist' : 'list')
                    . '/' . (empty($queryRow['datatype']) ? '' : serialize($queryRow['datatype']))
                    . '/';
                if (isset($shortProperties[$short])) {
                    ++$shortProperties[$short]['total'];
                    $shortProperties[$short]['texts'] = array_values(array_unique(array(
                        $shortProperties[$short]['texts'],
                        $queryRow['text']
                    )));
                } else {
                    $shortProperties[$short]['property_string'] = $queryRowProperty;
                    $shortProperties[$short]['joiner'] = 'or';
                    $shortProperties[$short]['property'] = $queryRowProp;
                    $shortProperties[$short]['except'] = $queryRow['except'] ?? '';
                    $shortProperties[$short]['type'] = $queryRow['type'];
                    $shortProperties[$short]['texts'] = $queryRow['text'];
                    $shortProperties[$short]['datatype'] = $queryRow['datatype'] ?? '';
                    $shortProperties[$short]['total'] = 1;
                }
                $shortProperties[$short]['keys'][] = $k;
            }
        }

        if (count($shortProperties) <= 1) {
            return $query;
        }

        // Replace multiple "subject = x OR subject = y" by "subject = [x, y]".
        // On a base > 10000 items and more than three or four subjects with OR,
        // mysql never ends request.
        // The issue is fixed in Omeka S v4.1.
        /** @see https://github.com/omeka/omeka-s/commits/consecutive-or-optimize */
        foreach ($shortProperties as $shortProperty) {
            if ($shortProperty['total'] < 2) {
                continue;
            }
            // Remove optimized filters.
            foreach ($shortProperty['keys'] as $shortPropertyKey) {
                unset($query['property'][$shortPropertyKey]);
            }
            // Replace the last key.
            $propertyFilter = [
                'joiner' => $shortProperty['joiner'],
                'property' => $shortProperty['property'],
                'except' => $shortProperty['except'],
                'type' => $shortProperty['type'],
                'text' => $shortProperty['texts'],
                'datatype' => $shortProperty['datatype'],
            ];
            $query['property'][$shortPropertyKey] = array_filter($propertyFilter, fn ($v) => $v !== null);
        }

        return $query;
    }

    /**
     * Helper to optimize query for filters.
     *
     * The main point is to avoid issues with mysql and too much joins.
     * Second, it improves performance on big bases.
     *
     * @todo Check workflow with buildQueryForRow() (for now use a flag).
     */
    protected function optimizeQueryFilter(array $query): array
    {
        $shortFilters = [];

        foreach ($query['filter'] as $k => $queryRow) {
            if (!is_array($queryRow)
                || empty($queryRow['type'])
                || !isset(self::FIELD_QUERY['reciprocal'][$queryRow['type']])
            ) {
                unset($query['filter'][$k]);
                continue;
            }

            if ($queryRow['type'] === 'list') {
                $queryRow['type'] = 'eq';
            } elseif ($queryRow['type'] === 'nlist') {
                $queryRow['type'] = 'neq';
            }

            $queryType = $queryRow['type'];
            $queryVal = $queryRow['val'] ?? '';

            // Quick check of value.
            // A empty string "" is not a value, but "0" is a value.
            if (in_array($queryType, self::FIELD_QUERY['value_none'], true)) {
                $queryVal = null;
            }
            // Check array of values.
            elseif (!in_array($queryType, self::FIELD_QUERY['value_single'], true)) {
                if ((is_array($queryVal) && !count($queryVal))
                    || (!is_array($queryVal) && !strlen((string) $queryVal))
                ) {
                    unset($query['filter'][$k]);
                    continue;
                }
                if (!in_array($queryType, self::FIELD_QUERY['value_single_array_or_string'])) {
                    if (!is_array($queryVal)) {
                        $queryVal = [$queryVal];
                    }
                    // Normalize as array of integers or strings for next process.
                    // To use array_values() avoids doctrine issue with string keys.
                    if (in_array($queryType, self::FIELD_QUERY['value_integer'])) {
                        $queryVal = array_values(array_unique(array_map('intval', array_filter($queryVal, fn ($v) => is_numeric($queryVal) && $v == (int) $v))));
                    } elseif (in_array($queryType, ['<', '≤', '≥', '>'])) {
                        // Casting to float is complex and rarely used, so only integer.
                        $queryVal = array_values(array_unique($queryVal, array_map(fn ($v) => is_numeric($v) && $v == (int) $v ? (int) $v : $v, $queryVal)));
                        // When there is at least one string, set all values as
                        // string for doctrine.
                        if (count(array_filter($queryVal, 'is_int')) !== count($queryVal)) {
                            $queryVal = array_map('strval', $queryVal);
                        }
                    } else {
                        $queryVal = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $queryVal)), 'strlen')));
                    }
                    if (empty($queryVal)) {
                        unset($query['filter'][$k]);
                        continue;
                    } else {
                        $query['filter'][$k]['val'] = $queryVal;
                    }
                }
            }
            // The value should be a scalar in all other cases.
            elseif (is_array($queryVal) || !strlen((string) $queryVal)) {
                unset($query['filter'][$k]);
                continue;
            } else {
                $queryVal = trim((string) $queryVal);
                if (!strlen($queryVal)) {
                    unset($query['filter'][$k]);
                    continue;
                }
                if (in_array($queryType, self::FIELD_QUERY['value_integer'])) {
                    if (!is_numeric($queryVal) || $queryVal != (int) $queryVal) {
                        unset($query['filter'][$k]);
                        continue;
                    }
                    $query['filter'][$k]['val'] = (int) $queryVal;
                } elseif (in_array($queryType, ['<', '≤', '≥', '>'])) {
                    // The types "integer" and "string" are automatically
                    // infered from the php type.
                    // Warning: "float" is managed like string in mysql via pdo.
                    if (is_numeric($queryVal) && $queryVal == (int) $queryVal) {
                        $query['filter'][$k]['val'] = (int) $queryVal;
                    }
                }
            }

            $queryRowField = $queryRow['field'] ?? null;
            if (is_array($queryRowField)) {
                $query['filter'][$k]['field'] = array_unique($query['filter'][$k]['field']);
            }

            // Init the short filters to prepare optimization.
            // Unlike properties, many types support multiple values.
            if (count($query['filter']) > 1
                && !in_array($queryType, self::FIELD_QUERY['value_none'])
                && !in_array($queryType, self::FIELD_QUERY['value_single'])
                && !in_array($queryType, self::FIELD_QUERY['value_single_array_or_string'])
            ) {
                // The joiner should be "OR", because "AND" cannot be used with sql
                // "IN()". But some types for min/max don't use it.
                // @see https://github.com/Daniel-KM/Omeka-S-module-AdvancedSearch/issues/4
                if (!(
                    (isset($query['filter'][$k]['join']) && $query['filter'][$k]['join'] === 'or')
                    || in_array($queryType, ['lt', 'lte', 'gte', 'gt', '<', '≤', '≥', '>', 'yreq', 'yrlt', 'yrlte', 'yrgte', 'yrgt', 'nyreq', 'nyrlt', 'nyrlte', 'nyrgte', 'nyrgt'])
                )) {
                    continue;
                }
                // TODO Manage the case where the filter is numeric or term (simplify above instead of in the process below).
                $queryRowFieldString = is_array($queryRowField) ? implode(',', $queryRowField) : $queryRowField;
                $short = '/' . ($queryRow['join'] ?? 'and')
                    . '/' . $queryRowFieldString
                    . '/' . (empty($queryRow['except']) ? '' : serialize($queryRow['except']))
                    . '/' . $queryRow['type']
                    . '/' . (empty($queryRow['datatype']) ? '' : serialize($queryRow['datatype']))
                    . '/';
                if (isset($shortFilters[$short])) {
                    ++$shortFilters[$short]['total'];
                    $shortFilters[$short]['vals'] = array_values(array_unique(array_merge(
                        $shortFilters[$short]['vals'],
                        array_values($queryRow['val'])
                    )));
                } else {
                    $shortFilters[$short]['join'] = $queryRow['join'] ?? 'and';
                    $shortFilters[$short]['field_string'] = $queryRowFieldString;
                    $shortFilters[$short]['field'] = $queryRowField;
                    $shortFilters[$short]['except'] = $queryRow['except'] ?? '';
                    $shortFilters[$short]['type'] = $queryRow['type'];
                    $shortFilters[$short]['vals'] = $queryRow['val'];
                    $shortFilters[$short]['datatype'] = $queryRow['datatype'] ?? '';
                    $shortFilters[$short]['total'] = 1;
                }
                $shortFilters[$short]['keys'][] = $k;
            }
        }

        if (!count($shortFilters) || count($query['filter']) <= 1) {
            return $query;
        }

        // Replace multiple "subject = x OR subject = y" by "subject = [x, y]"
        // and variants. For lower/greater, the joiner may be any of them.
        foreach ($shortFilters as $shortFilter) {
            if ($shortFilter['total'] < 2) {
                continue;
            }
            // Remove optimized filters.
            foreach ($shortFilter['keys'] as $shortFilterKey) {
                unset($query['filter'][$shortFilterKey]);
            }
            // Replace the last key.
            $filter = [
                'join' => $shortFilter['join'],
                'field' => $shortFilter['field'],
                'except' => $shortFilter['except'],
                'type' => $shortFilter['type'],
                'val' => $shortFilter['vals'],
                'datatype' => $shortFilter['datatype'],
            ];
            $query['filter'][$shortFilterKey] = array_filter($filter, fn ($v) => $v !== null);
        }

        return $query;
    }

    /**
     * Expand form fields according to the config.
     *
     * This method is used early by the form adapter.
     */
    public function expandFieldQueryArgs(array $query): array
    {
        static $expandedQueries = [];

        $originalQuery = $query;
        unset($originalQuery['__searchConfig']);
        unset($originalQuery['__searchQuery']);

        $sum = md5(serialize($originalQuery));
        if (isset($expandedQueries[$sum])) {
            return $expandedQueries[$sum];
        }

        // TODO Use an option to specify the default type (eq or in) to have the same than field query args.
        // For now, use "in" in main search form filters and "eq" in standard
        // advanced form (see TraitFormAdapterClassic).

        $typeDefault = 'in';

        foreach ($query['filter'] ?? [] as $key => $filter) {
            if (empty($filter['field']) || is_array($filter['field'])) {
                continue;
            }
            // TODO Fill except? Useless for now, it is an internal key, so a list of terms.
            $field = $filter['field'];
            if (isset($this->searchIndex['query_args'][$field])) {
                $filter = [
                    'join' => $this->searchIndex['query_args'][$field]['join'] ?? 'and',
                    'field' => $this->searchIndex['aliases'][$field]['fields'] ?? $field,
                    'except' => $this->searchIndex['query_args'][$field]['except'] ?? null,
                    'type' => $this->searchIndex['query_args'][$field]['type'] ?? $typeDefault,
                    'val' => $query['filter'][$key]['val'] ?? null,
                    'datatype' => $this->searchIndex['query_args'][$field]['datatype'] ?? null,
                    'label' => $this->searchIndex['form_filters_fields'][$field]
                        ?? $this->searchIndex['aliases'][$field]['label']
                        ?? null,
                    'is_form_filter' => !empty($this->searchIndex['form_filters_fields'][$field]),
                    'replaced_field' => $field,
                    'replaced_value' => $filter,
                    'replaced_filter_key' => $key,
                ];
                $query['filter'][$key] = array_filter($filter, fn ($v) => $v !== null);
            }
        }

        $typeDefault = 'eq';

        // The label is used for the search filters.

        foreach ($query as $field => $value) {
            if (isset($this->searchIndex['query_args'][$field])) {
                $filter = [
                    'join' => $this->searchIndex['query_args'][$field]['join'] ?? 'and',
                    'field' => $this->searchIndex['aliases'][$field]['fields'] ?? $field,
                    'except' => $this->searchIndex['query_args'][$field]['except'] ?? null,
                    'type' => $this->searchIndex['query_args'][$field]['type'] ?? $typeDefault,
                    'val' => $value,
                    'datatype' => $this->searchIndex['query_args'][$field]['datatype'] ?? null,
                    // Label and next data  are kept for search filters.
                    'label' => $this->searchIndex['form_filters_fields'][$field]
                        ?? $this->searchIndex['aliases'][$field]
                        ?? null,
                    'is_form_filter' => !empty($this->searchIndex['form_filters_fields'][$field]),
                    'replaced_field' => $field,
                    'replaced_value' => $value,
                    'replaced_filter_key' => null,
                ];
                $query['filter'][] = array_filter($filter, fn ($v) => $v !== null);
                unset($query[$field]);
            } elseif ($term = $this->easyMeta->propertyTerm($field)) {
                // When the shortcut is not listed, it means a standard query.
                $filter = [
                    'join' => 'and',
                    'field' => $term,
                    'type' => $typeDefault,
                    'val' => $value,
                    'label' => $this->searchIndex['form_filters_fields'][$field]
                        ?? $this->searchIndex['aliases'][$field]['label']
                        ?? null,
                    'is_form_filter' => !empty($this->searchIndex['form_filters_fields'][$field]),
                    'replaced_field' => $field,
                    'replaced_value' => $value,
                    'replaced_filter_key' => null,
                ];
                $query['filter'][] = $filter;
                unset($query[$field]);
            }
        }

        $expandedQueries[$sum] = $query;

        return $query;
    }

    /**
     * Allow to search a resource in multiple sites (with "or").
     */
    protected function searchSites(QueryBuilder $qb, array $query): self
    {
        if (empty($query['site_id']) || !is_array($query['site_id'])) {
            return $this;
        }

        // The site "0" is kept: no site, as in core adapter.
        $sites = array_values(array_unique(array_map('intval', array_filter($query['site_id'], 'is_numeric'))));
        if (!$sites) {
            return $this;
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

        return $this;
    }

    /**
     * Omeka S v4.1 allows to search resources, but it is not possible to filter
     * by resource type yet.
     */
    protected function searchResourcesByType(QueryBuilder $qb, array $query): self
    {
        if (empty($query['resource_type'])) {
            return $this;
        }

        $expr = $qb->expr();

        $resourceTypes = is_array($query['resource_type'])
            ? $query['resource_type']
            : [$query['resource_type']];

        // With discriminator map, the resource type can't be filtered directly,
        // but all derivated tables are left-joined.
        // So resource.resourceType or resource.resource_type cannot be used,
        // so use isInstanceOf, that does the same.

        $entityClasses = array_unique($this->easyMeta->entityResourceClasses($resourceTypes));
        if (!$entityClasses) {
            $qb
                ->andWhere($qb->expr()->eq(0, 1));
        } elseif (!in_array(\Omeka\Entity\Resource::class, $entityClasses)) {
            if (count($entityClasses) === 1) {
                $qb
                    ->andWhere($expr->isInstanceOf('omeka_root', reset($entityClasses)));
            } else {
                $or = [];
                foreach ($entityClasses as $entityClass) {
                    $or[] = $expr->isInstanceOf('omeka_root', $entityClass);
                }
                $qb
                    ->andWhere($expr->orX(...$or));
            }
        }

        return $this;
    }

    /**
     * Omeka S v4.1 allows to search resources, but to search full text requires
     * a specific adapter, so override it to manage it, allowing filtering the
     * specified resource types in option "resource_type" with api names.
     *
     * Another way is to remove the event and to pass the good one.
     */
    public function searchResourcesFullText(QueryBuilder $qb, array $query): self
    {
        // A full text search cannot be "*" alone. Anyway it has no meaning.
        if (empty($query['fulltext_search']) || $query['fulltext_search'] === '*') {
            return $this;
        }

        // Doctrine does not allow to modify a join, so get them all, remove
        // them all, and update the one for full text.
        $dqlJoins = $qb->getDQLPart('join');
        if (empty($dqlJoins['omeka_root'])) {
            return $this;
        }

        $qb->resetDQLPart('join');
        /** @var \Doctrine\ORM\Query\Expr\Join $join */
        foreach ($dqlJoins as $alias => $joins) foreach ($joins as $join) {
            if ($alias === 'omeka_root'
                && $join->getAlias() === 'omeka_fulltext_search'
                && $join->getJoin() === \Omeka\Entity\FulltextSearch::class
            ) {
                // The condition is always the same, because it is managed in
                // one place (@see \Omeka\Module::searchFulltext()).
                // The parameter is something like "omeka_0".
                $condition = $join->getCondition();
                $parameterName = substr($condition, strrpos($condition, ':') + 1);
                $hasResourceType = !empty($query['resource_type']);
                $join = new \Doctrine\ORM\Query\Expr\Join(
                    $join->getJoinType(),
                    \Omeka\Entity\FulltextSearch::class,
                    'omeka_fulltext_search',
                    $join->getConditionType(),
                    $hasResourceType
                        ? "omeka_fulltext_search.id = omeka_root.id AND omeka_fulltext_search.resource IN (:$parameterName)"
                        : 'omeka_fulltext_search.id = omeka_root.id',
                    $join->getIndexBy()
                );
                if ($hasResourceType) {
                    $resourceTypes = is_array($query['resource_type']) ? $query['resource_type'] : [$query['resource_type']];
                    $qb
                        ->setParameter($parameterName, $resourceTypes, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
                } else {
                    // The parameter is kept for simplicity.
                    $qb
                        ->andWhere($qb->expr()->eq(":$parameterName", ":$parameterName"));
                }
            }
            $qb->add('join', [$alias => $join], true);
        }

        return $this;
    }

    /**
     * Override the core adapter to search resource without template, etc.
     *
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildQuery()
     */
    protected function searchResources(QueryBuilder $qb, array $query): self
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
                        $this->adapter->createNamedParameter($qb, $query['owner_id'])
                    ));
            }
        }

        if (isset($query['resource_class_id'])
            && $query['resource_class_id'] !== ''
            && $query['resource_class_id'] !== []
        ) {
            $resourceClassIds = is_array($query['resource_class_id'])
                ? array_values(array_unique($query['resource_class_id']))
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

        return $this;
    }

    protected function searchResourceAssets(QueryBuilder $qb, array $query): self
    {
        $expr = $qb->expr();

        if (isset($query['has_asset']) && (string) $query['has_asset'] !== '') {
            if ($query['has_asset']) {
                $qb
                    ->andWhere($expr->isNotNull('omeka_root.thumbnail'));
            } else {
                $qb
                    ->andWhere($expr->isNull('omeka_root.thumbnail'));
            }
        }

        if (isset($query['asset_id'])
            && $query['asset_id'] !== ''
            && $query['asset_id'] !== []
        ) {
            $assetIds = is_array($query['asset_id'])
                ? array_values(array_unique($query['asset_id']))
                : [$query['asset_id']];
            if (array_values($assetIds) === [0]) {
                $qb
                    ->andWhere(
                        $expr->isNull('omeka_root.thumbnail')
                    );
            } elseif (in_array(0, $assetIds, true)) {
                $qb
                    ->andWhere($expr->orX(
                        $expr->isNull('omeka_root.thumbnail'),
                        $expr->in(
                            'omeka_root.thumbnail',
                            $this->adapter->createNamedParameter($qb, $assetIds)
                        )
                    ));
            } else {
                $qb
                    ->andWhere($expr->in(
                        'omeka_root.thumbnail',
                        $this->adapter->createNamedParameter($qb, $assetIds)
                    ));
            }
        }

        return $this;
    }

    /**
     * Allow to search a resource by a class term.
     */
    protected function searchResourceClassTerm(QueryBuilder $qb, array $query): self
    {
        if (empty($query['resource_class_term'])) {
            return $this;
        }

        // When there are only fake classes, no result should be returned, so 0
        // should be used.
        if (is_array($query['resource_class_term'])) {
            $classIds = $this->easyMeta->resourceClassIds($query['resource_class_term']);
            if (empty($classIds)) {
                $classIds = [0];
            }
        } else {
            $classIds = [(int) $this->easyMeta->resourceClassId($query['resource_class_term'])];
        }

        $qb->andWhere($qb->expr()->in(
            'omeka_root.resourceClass',
            $this->adapter->createNamedParameter($qb, $classIds)
        ));

        return $this;
    }

    /**
     * Build query on value.
     *
     * Pseudo-override buildPropertyQuery() via the api manager delegator.
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildPropertyQuery()
     *
     * @see \AdvancedSearch\Stdlib\SearchResources::buildPropertyQuery()
     * @see \Annotate\Api\Adapter\QueryPropertiesTrait::buildPropertyQuery()
     *
     * Query format:
     *
     * - property[{index}][joiner]: "and" OR "or" joiner with previous query
     * - property[{index}][property]: property ID or term
     * - property[{index}][type]: search type
     * - property[{index}][text]: search text
     *
     * Improved query format (deprecated: use filters for portability):
     *
     * - property[{index}][joiner]: "and" OR "or" OR "not" joiner with previous query
     * - property[{index}][property]: property ID or term or array of property IDs or terms
     * - property[{index}][except]: list of property IsD or terms to exclude
     * - property[{index}][type]: search type
     * - property[{index}][text]: search text or array of texts or values
     * - property[{index}][datatype]: filter on data type(s)
     *
     * @see self::buildQueryForRow() for details.
     */
    protected function buildPropertyQuery(QueryBuilder $qb, array $query): self
    {
        if (empty($query['property']) || !is_array($query['property'])) {
            return $this;
        }

        $isFilter = false;

        $valuesJoin = 'omeka_root.values';
        $where = '';

        // See below "Consecutive OR optimization" comment
        $previousPropertyIds = null;
        $previousAlias = null;
        $previousPositive = null;

        foreach ($query['property'] as $queryRow) {
            if (!is_array($queryRow)
                || empty($queryRow['type'])
                || !isset(self::FIELD_QUERY['reciprocal'][$queryRow['type']])
            ) {
                continue;
            }

            $propertyIds = $queryRow['property'] ?? null;
            $queryType = $queryRow['type'];
            $joiner = $queryRow['joiner'] ?? '';
            $value = $queryRow['text'] ?? '';
            $dataType = $queryRow['datatype'] ?? '';

            $result = $this->buildQueryForRow(
                $qb,
                compact(
                    'query',
                    'previousPropertyIds',
                    'previousAlias',
                    'previousPositive',
                    'valuesJoin',
                    'where',
                    'queryRow',
                    'propertyIds',
                    'queryType',
                    'joiner',
                    'value',
                    'dataType',
                    'isFilter'
                ),
                true
            );

            if (!$result) {
                continue;
            }

            [
                // The only output required.
                $where,
                // See above "Consecutive OR optimization" comment
                $previousPropertyIds,
                $previousAlias,
                $previousPositive,
                $break,
            ] = array_values($result);
            if ($break) {
                break;
            }
        }

        if ($where) {
            $qb->andWhere($where);
        }

        return $this;
    }

    /**
     * Build query filter on value on a field (property, term or index).
     *
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildPropertyQuery()
     * @see \AdvancedSearch\Stdlib\SearchResources::buildPropertyQuery()
     * @see \Annotate\Api\Adapter\QueryPropertiesTrait::buildPropertyQuery()
     *
     * Query format:
     *
     * - filter[{index}][join]: "and" OR "or" OR "not" joiner with previous query
     * - filter[{index}][field]: property ID, term or indexed field, or array of
     *   property IDs, terms or indexed fields
     * - filter[{index}][except]: list of property IsD or terms to exclude
     * - filter[{index}][type]: search type
     * - filter[{index}][val]: search text or array of texts or values
     * - filter[{index}][datatype]: filter on data type(s)
     *
     * @see self::buildQueryForRow() for details.
     */
    protected function buildFilterQuery(QueryBuilder $qb, array $query): self
    {
        if (empty($query['filter']) || !is_array($query['filter'])) {
            return $this;
        }

        $isFilter = true;

        $valuesJoin = 'omeka_root.values';
        $where = '';

        // See below "Consecutive OR optimization" comment
        $previousPropertyIds = null;
        $previousAlias = null;
        $previousPositive = null;

        foreach ($query['filter'] as $queryRow) {
            if (!is_array($queryRow)
                || empty($queryRow['type'])
                || !isset(self::FIELD_QUERY['reciprocal'][$queryRow['type']])
            ) {
                continue;
            }

            $propertyIds = $queryRow['field'] ?? null;
            $queryType = $queryRow['type'];
            $joiner = $queryRow['join'] ?? '';
            $value = $queryRow['val'] ?? '';
            $dataType = $queryRow['datatype'] ?? '';

            $result = $this->buildQueryForRow(
                $qb,
                compact(
                    'query',
                    'previousPropertyIds',
                    'previousAlias',
                    'previousPositive',
                    'valuesJoin',
                    'where',
                    'queryRow',
                    'propertyIds',
                    'queryType',
                    'joiner',
                    'value',
                    'dataType',
                    'isFilter'
                ),
                false
            );

            if (!$result) {
                continue;
            }

            [
                // The only output required.
                $where,
                // See above "Consecutive OR optimization" comment
                $previousPropertyIds,
                $previousAlias,
                $previousPositive,
                $break,
            ] = array_values($result);
            if ($break) {
                break;
            }
        }

        if ($where) {
            $qb->andWhere($where);
        }

        return $this;
    }

    /**
     * Manage standard and improved query for properties and query for filters.
     *
     * Value
     *   - eq: is exactly (core)
     *   - neq: is not exactly (core)
     *   - in: contains (core)
     *   - nin: does not contain (core)
     *   - sw: starts with
     *   - nsw: does not start with
     *   - ew: ends with
     *   - new: does not end with
     *   - near: is similar to
     *   - nnear: is not similar to
     *   - ma: matches a simple regex
     *   - nma: does not match a simple regex
     *   Comparisons (alphabetical)
     *   - lt: lower than
     *   - lte: lower than or equal
     *   - gte: greater than or equal
     *   - gt: greater than
     *   Comparisons (numerical, with casting to integer for numbers):
     *   - <: lower than
     *   - ≤: lower than or equal
     *   - ≥: greater than or equal
     *   - >: greater than
     *   Date (year: via casting to int):
     *   - yreq: during year
     *   - nyreq: not during year
     *   - yrlt: until year (excluded)
     *   - yrlte: until year
     *   - yrgte: since year
     *   - yrgt: since year (excluded)
     *   Internal, deprecated (any type can have multiple values with filters)
     *   - list: is in list
     *   - nlist: is not in list
     * Resource
     *   - res: is resource with ID (core)
     *   - nres: is not resource with ID (core)
     *   - resq: is resource matching query
     *   - nresq: is not resource matching query
     * Linked resource
     *   - lex: is a linked resource
     *   - nlex: is not a linked resource
     *   - lres: is linked with resource with ID (expert)
     *   - nlres: is not linked with resource with ID (expert)
     *   - lkq: is linked with resources matching query (expert)
     *   - nlkq: is not linked with resources matching query (expert)
     * Count
     *   - ex: has any value (core)
     *   - nex: has no values (core)
     *   - exs: has a single value
     *   - nexs: does not have a single value
     *   - exm: has multiple values
     *   - nexm: does not have multiple values
     * Data type
     *   - dt: has data type (core)
     *   - ndt: does not have data type (core)
     *   - dtp: has data type
     *   - ndtp: does not have data type
     *   - tp: has main type
     *   - ntp: does not have main type
     *   - tpl: has type literal-like
     *   - ntpl: does not have type literal-like
     *   - tpr: has type resource-like
     *   - ntpr: does not have type resource-like
     *   - tpu: has type uri-like
     *   - ntpu: does not have type uri-like
     * Curation (duplicates)
     *   Curation duplicate all (values as generic)
     *   - dup: has duplicate values
     *   - ndup: does not have duplicate values
     *   - dupt: has duplicate values and type
     *   - ndupt: does not have duplicate values and type
     *   - dupl: has duplicate values and language
     *   - ndupl: does not have duplicate values and language
     *   - duptl: has duplicate values, type and language
     *   - nduptl: does not have duplicate values, type and language
     *   Curation duplicate values (values as strict)
     *   - dupv: has duplicate simple values
     *   - ndupv: does not have duplicate simple values
     *   - dupvt: has duplicate simple values and type
     *   - ndupvt: does not have duplicate simple values and type
     *   - dupvl: has duplicate simple values and language
     *   - ndupvl: does not have duplicate simple values and language
     *   - dupvtl: has duplicate simple values, type and language
     *   - ndupvtl: does not have duplicate simple values, type and language
     *   Curation duplicate linked resources
     *   - dupr: has duplicate linked resources
     *   - ndupr: does not have duplicate linked resources
     *   - duprt: has duplicate linked resources and type
     *   - nduprt: does not have duplicate linked resources and type
     *   - duprl: has duplicate linked resources and language
     *   - nduprl: does not have duplicate linked resources and language
     *   - duprtl: has duplicate linked resources, type and language
     *   - nduprtl: does not have duplicate linked resources, type and language
     *   Curation duplicate uris
     *   - dupu: has duplicate uris
     *   - ndupu: does not have duplicate uris
     *   - duput: has duplicate uris and type
     *   - nduput: does not have duplicate uris and type
     *   - dupul: has duplicate uris and language
     *   - ndupul: does not have duplicate uris and language
     *   - duputl: has duplicate uris, type and language
     *   - nduputl: does not have duplicate uris, type and language
     *
     * @todo Add specific types to compare date and time (probably useless: alphabetical is enough with formatted date, or use module NumericDataTypes).
     * @todo The multiple types of duplicates are related to the database structure, only first should be needed.
     * @todo Duplicates with or (dupo: duplicate literal value or duplicate linked resource or duplicate uri)
     * @todo Duplicates with value annotations.
     * @todo La recherche des doublons nécessite que les valeurs soient propres (espaces et nuls).
     *
     * Note for "nlex":
     * For consistency, "nlex" is the reverse of "lex" even when a resource is
     * linked with a public and a private resource.
     * A private linked resource is not linked for an anonymous.
     */
    protected function buildQueryForRow(QueryBuilder $qb, array $vars, bool $isPropertyQuery): ?array
    {
        /**
         * @var array $query
         * @var array $previousPropertyIds
         * @var string $previousAlias
         * @var bool $previousPositive
         * @var string $valuesJoin
         * @var string $where
         * @var array $queryRow
         * @var array $propertyIds
         * @var string $queryType
         * @var string $joiner
         * @var mixed $value
         * @var string $dataType
         * @var bool $isFilter
         */
        extract($vars);

        // TODO Normally, the rows are cleaned, so clarify workflow and remove these lastest checks.

        /**
         * @see \Doctrine\ORM\QueryBuilder::expr().
         * @var \Doctrine\ORM\EntityManager $entityManager
         */
        $expr = $qb->expr();
        $entityManager = $this->adapter->getEntityManager();

        $escapeSqlLike = fn ($string) => str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $string);

        // Quick check of value.
        // An empty string "" is not a value, but "0" is a value.
        if (in_array($queryType, self::FIELD_QUERY['value_none'], true)) {
            $value = null;
        }
        // Check array of values, that are allowed only by filters.
        elseif (!in_array($queryType, self::FIELD_QUERY['value_single'], true)) {
            if ((is_array($value) && !count($value))
                || (!is_array($value) && !strlen((string) $value))
            ) {
                return null;
            }
            if (!in_array($queryType, self::FIELD_QUERY['value_single_array_or_string'])) {
                if (!is_array($value)) {
                    $value = [$value];
                }
                // Normalize as array of integers or strings for next process.
                // To use array_values() avoids doctrine issue with string keys.
                if (in_array($queryType, self::FIELD_QUERY['value_integer'])) {
                    $value = array_values(array_unique(array_map('intval', array_filter($value, fn ($v) => is_numeric($value) && $v == (int) $v))));
                } elseif (in_array($queryType, ['<', '≤', '≥', '>'])) {
                    // Casting to float is complex and rarely used, so only integer.
                    $value = array_values(array_unique($value, array_map(fn ($v) => is_numeric($v) && $v == (int) $v ? (int) $v : $v, $value)));
                    // When there is at least one string, set all values as
                    // string for doctrine.
                    if (count(array_filter($value, 'is_int')) !== count($value)) {
                        $value = array_map('strval', $value);
                    }
                } else {
                    $value = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $value)), 'strlen')));
                }
                if (empty($value)) {
                    return null;
                }
            }
        }
        // The value should be scalar in all other cases (integer or string).
        elseif (is_array($value) || $value === '') {
            return null;
        } else {
            $value = trim((string) $value);
            if (!strlen($value)) {
                return null;
            }
            if (in_array($queryType, self::FIELD_QUERY['value_integer'])) {
                if (!is_numeric($value) || $value != (int) $value) {
                    return null;
                }
                $value = (int) $value;
            } elseif (in_array($queryType, ['<', '≤', '≥', '>'])) {
                // The types "integer" and "string" are automatically
                // infered from the php type.
                // Warning: "float" is managed like string in mysql via pdo.
                if (is_numeric($value) && $value == (int) $value) {
                    $value = (int) $value;
                }
            }
            // Convert single values into array except if array isn't supported.
            if (!in_array($queryType, self::FIELD_QUERY['value_single_array_or_string'], true)
                && !in_array($queryType, self::FIELD_QUERY['value_single'], true)
            ) {
                $value = [$value];
            }
        }

        // The three joiners are "and" (default), "or" and "not".
        // Check joiner and invert the query type for joiner "not".
        if ($joiner === 'not') {
            $joiner = 'and';
            $queryType = self::FIELD_QUERY['reciprocal'][$queryType];
        } elseif ($joiner && $joiner !== 'or') {
            $joiner = 'and';
        }

        if (in_array($queryType, self::FIELD_QUERY['negative'], true)
            // Manage exceptions for specific negative queries.
            && !in_array($queryType, ['nexs', 'nexm'], true)
        ) {
            $positive = false;
            $queryType = self::FIELD_QUERY['reciprocal'][$queryType];
        } else {
            $positive = true;
        }

        // Narrow to specific properties, if one or more are selected.
        $fakePropertyIds = false;
        // Properties may be an array with an empty value (any property) in
        // advanced form, so remove empty strings from it, in which case the
        // check should be skipped.
        if (is_array($propertyIds) && in_array('', $propertyIds, true)) {
            $propertyIds = [];
        } elseif ($propertyIds) {
            $propertyIds = array_values(array_unique($this->easyMeta->propertyIds($propertyIds)));
            $fakePropertyIds = empty($propertyIds);
        }

        // Note: a list of "or" with the same property should be optimized
        // early with type "list".
        // TODO Optimize early search type "or" with same properties and type.

        // Consecutive OR optimization
        //
        // When we have a run of query rows that are joined by OR and share
        // the same property ID (or lack thereof), we don't actually need a
        // separate join to the values table; we can just tack additional OR
        // clauses onto the WHERE while using the same join and alias. The
        // extra joins are expensive, so doing this improves performance where
        // many ORs are used.
        //
        // Rows using "negative" searches need their own separate join to the
        // values table, so they're excluded from this optimization on both
        // sides: if either the current or previous row is a negative query,
        // the current row does a new join.
        if ($previousAlias
            && $previousPropertyIds === $propertyIds
            && $previousPositive
            && $positive
            && $joiner === 'or'
        ) {
            $valuesAlias = $previousAlias;
            $usePrevious = true;
        } else {
            $valuesAlias = $this->adapter->createAlias();
            $usePrevious = false;
        }

        $incorrectValue = false;

        switch ($queryType) {
            case 'eq':
            case 'list':
                $subqueryAlias = $this->adapter->createAlias();
                $subquery = $entityManager
                    ->createQueryBuilder()
                    ->select("$subqueryAlias.id")
                    ->from('Omeka\Entity\Resource', $subqueryAlias);
                if (count($value) <= 1) {
                    $value = reset($value);
                    $param = $this->adapter->createNamedParameter($qb, $value);
                    $subquery
                        ->where($expr->eq("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->eq("$valuesAlias.value", $param),
                        $expr->eq("$valuesAlias.uri", $param)
                    );
                } else {
                    $param = $this->adapter->createNamedParameter($qb, $value);
                    $qb->setParameter(substr($param, 1), $value, Connection::PARAM_STR_ARRAY);
                    $subquery
                        ->where($expr->in("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->in("$valuesAlias.value", $param),
                        $expr->in("$valuesAlias.uri", $param)
                    );
                }
                break;

            case 'in':
                $subqueryAlias = $this->adapter->createAlias();
                $subquery = $entityManager
                    ->createQueryBuilder()
                    ->select("$subqueryAlias.id")
                    ->from('Omeka\Entity\Resource', $subqueryAlias);
                $sub = [];
                foreach ($value as $val) {
                    $param = $this->adapter->createNamedParameter($qb, '%' . $escapeSqlLike($val) . '%');
                    $subquery
                        ->where($expr->like("$subqueryAlias.title", $param));
                    $sub[] = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                }
                $predicateExpr = count($value) <= 1
                    ? reset($sub)
                    : $expr->orX(...$sub);
                break;

            case 'sw':
                $subqueryAlias = $this->adapter->createAlias();
                $subquery = $entityManager
                    ->createQueryBuilder()
                    ->select("$subqueryAlias.id")
                    ->from('Omeka\Entity\Resource', $subqueryAlias);
                $sub = [];
                foreach ($value as $val) {
                    $param = $this->adapter->createNamedParameter($qb, $escapeSqlLike($value) . '%');
                    $subquery
                        ->where($expr->like("$subqueryAlias.title", $param));
                    $sub[] = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                }
                $predicateExpr = count($value) <= 1
                    ? reset($sub)
                    : $expr->orX(...$sub);
                break;

            case 'ew':
                $subqueryAlias = $this->adapter->createAlias();
                $subquery = $entityManager
                    ->createQueryBuilder()
                    ->select("$subqueryAlias.id")
                    ->from('Omeka\Entity\Resource', $subqueryAlias);
                $sub = [];
                foreach ($value as $val) {
                    $param = $this->adapter->createNamedParameter($qb, '%' . $escapeSqlLike($value));
                    $subquery
                        ->where($expr->like("$subqueryAlias.title", $param));
                    $sub[]= $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                }
                $predicateExpr = count($value) <= 1
                    ? reset($sub)
                    : $expr->orX(...$sub);
                break;

            case 'near':
                // The mysql soundex() is not standard, because it returns more
                // than four characters, so the comparaison cannot be done with
                // a static value from the php soundex() function.
                $subqueryAlias = $this->adapter->createAlias();
                $subquery = $entityManager
                    ->createQueryBuilder()
                    ->select("$subqueryAlias.id")
                    ->from('Omeka\Entity\Resource', $subqueryAlias);
                $sub = [];
                foreach ($value as $val) {
                    $param = $this->adapter->createNamedParameter($qb, $value);
                    $subquery
                        ->where($expr->eq("SOUNDEX($subqueryAlias.title)", "SOUNDEX($param)"));
                    $sub[] = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->eq("SOUNDEX($valuesAlias.value)", "SOUNDEX($param)") /*,
                        // A soundex on a uri has no meaning.
                        $expr->eq("SOUNDEX($valuesAlias.uri)", "SOUNDEX($param)")
                        */
                    );
                }
                $predicateExpr = count($value) <= 1
                    ? reset($sub)
                    : $expr->orX(...$sub);
                break;

            case 'ma':
                // The doctrine dql requires "TRUE" and will be converted to a
                // standard mysql query.
                $subqueryAlias = $this->adapter->createAlias();
                $subquery = $entityManager
                    ->createQueryBuilder()
                    ->select("$subqueryAlias.id")
                    ->from('Omeka\Entity\Resource', $subqueryAlias);
                $sub = [];
                foreach ($value as $val) {
                    $subquery
                        ->where("REGEXP($subqueryAlias.title, $param) = TRUE");
                    $param = $this->adapter->createNamedParameter($qb, $value);
                    $sub[] = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        "REGEXP($valuesAlias.value, $param) = TRUE",
                        "REGEXP($valuesAlias.uri, $param) = TRUE"
                    );
                }
                $predicateExpr = count($value) <= 1
                    ? reset($sub)
                    : $expr->orX(...$sub);
                break;

            case 'lt':
            case 'lte':
            case 'gte':
            case 'gt':
                // With a list of lt/lte/gte/gt, get the right value first in order
                // to avoid multiple sql conditions.
                // But the language cannot be determined: language of the site? of
                // the data? of the user who does query?
                // Practically, mysql/mariadb sort with generic unicode rules by
                // default, so use a generic sort.
                /** @see https://www.unicode.org/reports/tr10/ */
                if (count($value) > 1) {
                    if (extension_loaded('intl')) {
                        $col = new \Collator('root');
                        $col->sort($value);
                    } else {
                        natcasesort($value);
                    }
                }
                // TODO Manage uri and resources with lt, lte, gte, gt (it has a meaning at least for resource ids, but separate).
                if ($queryType === 'lt') {
                    $value = reset($value);
                    $predicateExpr = $expr->lt(
                        "$valuesAlias.value",
                        $this->adapter->createNamedParameter($qb, $value)
                    );
                } elseif ($queryType === 'lte') {
                    $value = reset($value);
                    $predicateExpr = $expr->lte(
                        "$valuesAlias.value",
                        $this->adapter->createNamedParameter($qb, $value)
                    );
                } elseif ($queryType === 'gte') {
                    $value = array_pop($value);
                    $predicateExpr = $expr->gte(
                        "$valuesAlias.value",
                        $this->adapter->createNamedParameter($qb, $value)
                    );
                } elseif ($queryType === 'gt') {
                    $value = array_pop($value);
                    $predicateExpr = $expr->gt(
                        "$valuesAlias.value",
                        $this->adapter->createNamedParameter($qb, $value)
                    );
                }
                break;

            case '<':
            case '≤':
                // The values are already cleaned.
                $first = reset($value);
                if (count($value) > 1) {
                    if (is_int($first)) {
                        $value = min($value);
                    } else {
                        extension_loaded('intl') ? (new \Collator('root'))->sort($value, \Collator::SORT_NUMERIC) : sort($value);
                        $value = reset($value);
                    }
                } else {
                    $value = $first;
                }
                // Use adapter method in order to increment internal index.
                $param = $this->adapter->createNamedParameter($qb, $value);
                $qb->setParameter(substr($param, 1), $value, is_int($value) ? ParameterType::INTEGER : ParameterType::STRING);
                $predicateExpr = $queryType === '<'
                    ? $expr->lt("$valuesAlias.value", $param)
                    : $expr->lte("$valuesAlias.value", $param);
                break;
            case '≥':
            case '>':
                $first = reset($value);
                if (count($value) > 1) {
                    if (is_int($first)) {
                        $value = max($value);
                    } else {
                        extension_loaded('intl') ? (new \Collator('root'))->sort($value, \Collator::SORT_NUMERIC) : sort($value);
                        $value = array_pop($value);
                    }
                } else {
                    $value = $first;
                }
                $param = $this->adapter->createNamedParameter($qb, $value);
                $qb->setParameter(substr($param, 1), $value, is_int($value) ? ParameterType::INTEGER : ParameterType::STRING);
                $predicateExpr = $queryType === '>'
                    ? $expr->gt("$valuesAlias.value", $param)
                    : $expr->gte("$valuesAlias.value", $param);
                break;

            case 'yreq':
                // The casting to integer is the simplest way to get the year:
                // it avoids multiple substring_index, replace, etc. and it
                // works fine in most of the real cases, except when the date
                // does not look like a standard date, but normally it is
                // checked earlier.
                // Values are already casted to int.
                if (count($value) <= 1) {
                    $value = reset($value);
                    $param = $this->adapter->createNamedParameter($qb, $value);
                    $qb->setParameter(substr($param, 1), $value, ParameterType::INTEGER);
                    $predicateExpr = $expr->eq("$valuesAlias.value + 0", $param);
                } else {
                    $param = $this->adapter->createNamedParameter($qb, $value);
                    $qb->setParameter(substr($param, 1), $value, Connection::PARAM_INT_ARRAY);
                    $predicateExpr = $expr->in("$valuesAlias.value + 0", $param);
                }
                break;
            case 'yrlt':
                $value = min($value);
                $param = $this->adapter->createNamedParameter($qb, $value);
                $qb->setParameter(substr($param, 1), $value, ParameterType::INTEGER);
                $predicateExpr = $expr->lt("$valuesAlias.value + 0", $param);
                break;
            case 'yrlte':
                $value = min($value);
                $param = $this->adapter->createNamedParameter($qb, $value);
                $qb->setParameter(substr($param, 1), $value, ParameterType::INTEGER);
                $predicateExpr = $expr->lte("$valuesAlias.value + 0", $param);
                break;
            case 'yrgte':
                $value = max($value);
                $param = $this->adapter->createNamedParameter($qb, $value);
                $qb->setParameter(substr($param, 1), $value, ParameterType::INTEGER);
                $predicateExpr = $expr->gte("$valuesAlias.value + 0", $param);
                break;
            case 'yrgt':
                $value = max($value);
                $param = $this->adapter->createNamedParameter($qb, $value);
                $qb->setParameter(substr($param, 1), $value, ParameterType::INTEGER);
                $predicateExpr = $expr->gt("$valuesAlias.value + 0", $param);
                break;

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

            case 'resq':
                // TODO For now, only one sub-query (and the sub-query may be a complex one and it is largely enough in most of the cases).
                // TODO Allow to pass an array instead of encoded url args (but it is cleaned above). See 'lkq' too.
                if (is_array($value) && is_numeric(key($value))) {
                    $value = reset($value);
                }
                if (!is_array($value)) {
                    $aValue = null;
                    parse_str($value, $aValue);
                    $value = $aValue;
                }
                // TODO Use a subquery.
                $api = $this->adapter->getServiceLocator()->get('Omeka\ApiManager');
                $value = $api->search($this->adapter->getResourceName(), $value, ['returnScalar' => 'id'])->getContent();
                $param = $this->adapter->createNamedParameter($qb, $value);
                $qb->setParameter(substr($param, 1), $value, Connection::PARAM_INT_ARRAY);
                $predicateExpr = $expr->in("$valuesAlias.valueResource", $param);
                break;

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

            // The linked resources (subject values) use the same sub-query.
            case 'lex':
            case 'lres':
            case 'lkq':
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
                if (in_array($queryType, ['lres', 'nlres'])) {
                    // In fact, "lres" is the list of linked resources.
                    if (count($value) <= 1) {
                        $param = $this->adapter->createNamedParameter($qb, (int) reset($value));
                        $subQb->andWhere($expr->eq("$subValuesAlias.resource", $param));
                    } else {
                        $param = $this->adapter->createNamedParameter($qb, $value);
                        $qb->setParameter(substr($param, 1), $value, Connection::PARAM_INT_ARRAY);
                        $subQb->andWhere($expr->in("$subValuesAlias.resource", $param));
                    }
                } elseif (in_array($queryType, ['lkq', 'nlkq'])) {
                    // Only a single level: see above "resq"/"nresq".
                    if (is_array($value) && is_numeric(key($value))) {
                        $value = reset($value);
                    }
                    if (!is_array($value)) {
                        $aValue = null;
                        parse_str($value, $aValue);
                        $value = $aValue;
                    }
                    /* // TODO Create a full sub sub dql query from the adapter instead of querying ids first.
                    $subSubQuery = $entityManager
                        ->createQueryBuilder()
                        ->select("IDENTITY($subValuesAlias.valueResource)")
                        ->from(\Omeka\Entity\Value::class, $subValuesAlias)
                        ->innerJoin("$subValuesAlias.resource", $subResourceAlias)
                        ->where($expr->isNotNull("$subValuesAlias.valueResource"));
                    $param = $this->adapter->createNamedParameter($qb, $value);
                    $qb->setParameter(substr($param, 1), $value, Connection::PARAM_INT_ARRAY);
                    $subQb->andWhere($expr->in("$subValuesAlias.resource", $subSubQuery->getDQL()));
                     */
                    $api = $this->adapter->getServiceLocator()->get('Omeka\ApiManager');
                    $value = $api->search($this->adapter->getResourceName(), $value, ['returnScalar' => 'id'])->getContent();
                    $param = $this->adapter->createNamedParameter($qb, $value);
                    $qb->setParameter(substr($param, 1), $value, Connection::PARAM_INT_ARRAY);
                    $subQb->andWhere($expr->in("$subValuesAlias.resource", $param));
                }
                // "nlex" and "lex'" have no value.
                break;

            case 'tp':
                if ($value === 'literal') {
                    // Because a resource or a uri can have a label stored
                    // in "value", a literal-like value is a value without
                    // resource and without uri.
                    $predicateExpr = $expr->andX(
                        $expr->isNull("$valuesAlias.valueResource"),
                        $expr->orX(
                            $expr->isNull("$valuesAlias.uri"),
                            $expr->eq("$valuesAlias.uri", '')
                        )
                    );
                } elseif ($value === 'resource') {
                    $predicateExpr = $expr->isNotNull("$valuesAlias.valueResource");
                } elseif ($value === 'uri') {
                    $predicateExpr = $expr->andX(
                        $expr->isNotNull("$valuesAlias.uri"),
                        $expr->neq("$valuesAlias.uri", '')
                    );
                } else {
                    $predicateExpr = $expr->eq(1, 0);
                }
                break;

            case 'tpl':
                // Because a resource or a uri can have a label stored
                // in "value", a literal-like value is a value without
                // resource and without uri.
                $predicateExpr = $expr->andX(
                    $expr->isNull("$valuesAlias.valueResource"),
                    $expr->orX(
                        $expr->isNull("$valuesAlias.uri"),
                        $expr->eq("$valuesAlias.uri", '')
                    )
                );
                break;

            case 'tpr':
                $predicateExpr = $expr->isNotNull("$valuesAlias.valueResource");
                break;

            case 'tpu':
                $predicateExpr = $expr->andX(
                    $expr->isNotNull("$valuesAlias.uri"),
                    $expr->neq("$valuesAlias.uri", '')
                );
                break;

            case 'dt':
            case 'dtp':
                if (count($value) <= 1) {
                    $dataTypeAlias = $this->adapter->createNamedParameter($qb, reset($value));
                    $predicateExpr = $expr->eq("$valuesAlias.type", $dataTypeAlias);
                } else {
                    $dataTypeAlias = $this->adapter->createAlias();
                    $qb->setParameter($dataTypeAlias, $value, Connection::PARAM_STR_ARRAY);
                    $predicateExpr = $expr->in("$valuesAlias.type", ":$dataTypeAlias");
                }
                break;

            case 'dup':
            case 'dupl':
            case 'dupt':
            case 'duptl':
            case 'dupv':
            case 'dupvl':
            case 'dupvt':
            case 'dupvtl':
            case 'dupr':
            case 'duprl':
            case 'duprt':
            case 'duprtl':
            case 'dupu':
            case 'dupul':
            case 'duput':
            case 'duputl':
                // Has duplicate values: same value, value resource, uri,
                // data type and language.
                $subqueryAlias = $this->adapter->createAlias();
                // Find duplicates for each resource and each property.
                $groupBy = [
                    "$subqueryAlias.resource",
                    "$subqueryAlias.property",
                ];
                // Duplicates may be values, linked resources and uris.
                if (in_array($queryType, ['dup', 'dupl', 'dupt', 'duptl'])) {
                    $groupBy[] = "$subqueryAlias.value";
                    $groupBy[] = "$subqueryAlias.valueResource";
                    $groupBy[] = "$subqueryAlias.uri";
                } elseif (in_array($queryType, ['dupv', 'dupvl', 'dupvt', 'dupvtl'])) {
                    $groupBy[] = "$subqueryAlias.value";
                } elseif (in_array($queryType, ['dupr', 'duprl', 'duprt', 'duprtl'])) {
                    $groupBy[] = "$subqueryAlias.valueResource";
                } elseif (in_array($queryType, ['dupu', 'dupul', 'duput', 'duputl'])) {
                    $groupBy[] = "$subqueryAlias.uri";
                }
                // Duplicates may be strict: same data type and language.
                if (in_array($queryType, ['dupl', 'dupvl', 'duprl', 'dupul'])) {
                    $groupBy[] = "$subqueryAlias.lang";
                } elseif (in_array($queryType, ['dupt', 'dupvt', 'duprt', 'duput'])) {
                    $groupBy[] = "$subqueryAlias.type";
                } elseif (in_array($queryType, ['duptl', 'dupvtl', 'duprtl', 'duputl'])) {
                    $groupBy[] = "$subqueryAlias.type";
                    $groupBy[] = "$subqueryAlias.lang";
                }
                $subquery = $entityManager
                    ->createQueryBuilder()
                    ->select("IDENTITY($subqueryAlias.resource)")
                    ->from(\Omeka\Entity\Value::class, $subqueryAlias)
                    ->groupBy(...$groupBy)
                    ->having($expr->gt("COUNT($subqueryAlias.resource)", 1));
                if ($propertyIds) {
                    // The property alias used in subquery is bound to main
                    // query because the subquery is dqlized in main query.
                    $propAlias = $this->adapter->createAlias();
                    $subquery
                        ->andWhere($expr->in("$subqueryAlias.property", ":$propAlias"));
                    $qb
                        ->setParameter($propAlias, $propertyIds, Connection::PARAM_INT_ARRAY);
                }
                $predicateExpr = $expr->in("$valuesAlias.resource", $subquery->getDQL());
                break;

            default:
                // Normally not possible because types are already checked.
                return null;
        }

        // Avoid to get results when the query is incorrect.
        // In that case, no param should be set in the current loop.
        if ($incorrectValue) {
            $where = $expr->eq('omeka_root.id', 0);
            return [
                // The only output required.
                $where,
                // See above "Consecutive OR optimization" comment
                null,
                null,
                null,
                true,
            ];
        }

        $joinConditions = [];

        // Don't return results for this part for fake properties.
        $hasSpecificJoinConditions = false;
        if ($fakePropertyIds) {
            $hasSpecificJoinConditions = true;
            $joinConditions[] = $expr->eq("$valuesAlias.property", 0);
        } elseif ($propertyIds) {
            // For queries on subject values, the properties should be
            // checked against the sub-query.
            if (in_array($queryType, self::FIELD_QUERY['value_subject'])) {
                $subQb
                    ->andWhere(count($propertyIds) < 2
                        ? $expr->eq("$subValuesAlias.property", reset($propertyIds))
                        : $expr->in("$subValuesAlias.property", $propertyIds)
                    );
            } else {
                $hasSpecificJoinConditions = true;
                $joinConditions[] = count($propertyIds) < 2
                    ? $expr->eq("$valuesAlias.property", reset($propertyIds))
                    : $expr->in("$valuesAlias.property", $propertyIds);
            }
        } else {
            // TODO What if a property is ""?
            $excludePropertyIds = $propertyIds || empty($queryRow['except'])
                ? false
                : array_values(array_unique($this->easyMeta->propertyIds($queryRow['except'])));
            // Use standard query if nothing to exclude, else limit search.
            if ($excludePropertyIds) {
                // The aim is to search anywhere except ocr content.
                // Use not positive + in() or notIn()? A full list is simpler.
                $otherIds = array_diff($this->easyMeta->propertyIdsUsed(), $excludePropertyIds);
                // Avoid issue when everything is excluded.
                $otherIds[] = 0;
                if (in_array($queryType, self::FIELD_QUERY['value_subject'])) {
                    $subQb
                        ->andWhere($expr->in("$subValuesAlias.property", $otherIds));
                } else {
                    $hasSpecificJoinConditions = true;
                    $joinConditions[] = $expr->in("$valuesAlias.property", $otherIds);
                }
            }
        }

        // Finalize predicate expression on subject values.
        if (in_array($queryType, self::FIELD_QUERY['value_subject'])) {
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

        // See above "Consecutive OR optimization" comment
        if (!$usePrevious || $hasSpecificJoinConditions) {
            if ($usePrevious && $hasSpecificJoinConditions) {
                $usePrevious = false;
                $valuesAlias = $this->adapter->createAlias();
            }
            if ($joinConditions) {
                $qb->leftJoin($valuesJoin, $valuesAlias, Join::WITH, $expr->andX(...$joinConditions));
            } else {
                $qb->leftJoin($valuesJoin, $valuesAlias);
            }
        }

        if ($where == '') {
            $where = $whereClause;
        } elseif ($joiner === 'or') {
            $where .= " OR $whereClause";
        } else {
            $where .= " AND $whereClause";
        }

        // See above "Consecutive OR optimization" comment
        $previousPropertyIds = $propertyIds;
        $previousPositive = $positive;
        $previousAlias = $valuesAlias;

        return [
            // The only output required.
            $where,
            // See above "Consecutive OR optimization" comment
            $previousPropertyIds,
            $previousAlias,
            $previousPositive,
            false,
        ];
    }

    /**
     * Build query on created/modified date time with partial date/time allowed.
     *
     * The query format is inspired by Doctrine and properties.
     *
     * Query format:
     *
     * - datetime[{index}][join]: "and" OR "or" joiner with previous query
     * - datetime[{index}][field]: the field "created" or "modified"
     * - datetime[{index}][type]: search type
     *   - < / lt: lower than (before)
     *   - ≤ / lte: lower than or equal
     *   - = / eq: is exactly
     *   - ≠ / neq: is not exactly
     *   - ≥ / gte: greater than or equal
     *   - > / gt: greater than (after)
     *   - ex: has any value
     *   - nex: has no value
     * - datetime[{index}][val]: search date time (sql format: "2017-11-07 17:21:17",
     *   partial date/time allowed ("2018-05", etc.). Human values are allowed:
     *   "+1 day", "last Monday"… See php function `strtotime()`.
     *
     * @param QueryBuilder $qb
     * @param array $query The query should be cleaned first.
     */
    protected function searchDateTime(QueryBuilder $qb, array $query): self
    {
        if (empty($query['datetime'])) {
            return $this;
        }

        $where = '';
        $expr = $qb->expr();

        $dateTimeQueryTypes = [
            '<' => '<',
            'lt' => '<',
            '≤' => '≤',
            'lte' => '≤',
            '=' => '=',
            'eq' => '=',
            '≠' => '≠',
            'neq' => '≠',
            '≥' => '≥',
            'gte' => '≥',
            '>' => '>',
            'gt' => '>',
            'ex' => 'ex',
            'nex' => 'nex',
        ];

        foreach ($query['datetime'] as $queryRow) {
            $type = $queryRow['type'] ?? null;
            if (!isset($dateTimeQueryTypes[$type])) {
                continue;
            }

            $type = $dateTimeQueryTypes[$type];
            $joiner = $queryRow['join'] ?? null;
            $field = $queryRow['field'] ?? null;
            $value = $queryRow['val'] ?? null;
            $incorrectValue = false;

            // By default, sql replace missing time by 00:00:00, but this is not
            // clear for the user. And it doesn't allow partial date/time.
            switch ($type) {
                case '<':
                    $valueNorm = $this->getDateTimeFromValue($value, true) ?? $this->getDateTimeViaAnyString($value);
                    if ($valueNorm === null) {
                        $incorrectValue = true;
                    } else {
                        $param = $this->adapter->createNamedParameter($qb, $valueNorm);
                        $predicateExpr = $expr->lt('omeka_root.' . $field, $param);
                    }
                    break;
                case '≤':
                    $valueNorm = $this->getDateTimeFromValue($value, false) ?? $this->getDateTimeViaAnyString($value);
                    if ($valueNorm === null) {
                        $incorrectValue = true;
                    } else {
                        $param = $this->adapter->createNamedParameter($qb, $valueNorm);
                        $predicateExpr = $expr->lte('omeka_root.' . $field, $param);
                    }
                    break;
                case '=':
                    $valueFromNorm = $this->getDateTimeFromValue($value, true) ?? $this->getDateTimeViaAnyString($value);
                    $valueToNorm = $this->getDateTimeFromValue($value, false) ?? $this->getDateTimeViaAnyString($value);
                    if ($valueFromNorm === null || $valueToNorm === null) {
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
                case '≠':
                    $valueFromNorm = $this->getDateTimeFromValue($value, true) ?? $this->getDateTimeViaAnyString($value);
                    $valueToNorm = $this->getDateTimeFromValue($value, false) ?? $this->getDateTimeViaAnyString($value);
                    if ($valueFromNorm === null || $valueToNorm === null) {
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
                case '≥':
                    $valueNorm = $this->getDateTimeFromValue($value, true) ?? $this->getDateTimeViaAnyString($value);
                    if ($valueNorm === null) {
                        $incorrectValue = true;
                    } else {
                        $param = $this->adapter->createNamedParameter($qb, $valueNorm);
                        $predicateExpr = $expr->gte('omeka_root.' . $field, $param);
                    }
                    break;
                case '>':
                    $valueNorm = $this->getDateTimeFromValue($value, false) ?? $this->getDateTimeViaAnyString($value);
                    if ($valueNorm === null) {
                        $incorrectValue = true;
                    } else {
                        $param = $this->adapter->createNamedParameter($qb, $valueNorm);
                        $predicateExpr = $expr->gt('omeka_root.' . $field, $param);
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
                $param = $this->adapter->createNamedParameter($qb, 'incorrect value: ' . $value);
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

        return $this;
    }

    protected function sortQuery(QueryBuilder $qb, array $query): self
    {
        // Order by is the last part or a sql query.
        // Sort by listed id.
        // The list is the one set in key "sort_ids" or in main query key "id".
        // The sort order is "desc" for resource/browse (see plugin SetBrowseDefault::__invoke())
        // and as "asc" in api (see AbstractEntityAdapter::buildQuery()).
        if (isset($query['sort_by'])
            && $query['sort_by'] === 'ids'
            && (!empty($query['sort_ids']) || !empty($query['id']))
        ) {
            $expr = $qb->expr();
            /** @see \Omeka\Api\Adapter\AbstractEntityAdapter::buildBaseQuery() */
            // Avoid a strict type issue, so convert ids as string.
            // Normally, the query is cleaned before.
            $ids = empty($query['sort_ids']) ? $query['id'] : $query['sort_ids'];
            if (is_int($ids)) {
                $ids = [(string) $ids];
            } elseif (is_string($ids)) {
                $ids = strpos($ids, ',') === false ? [$ids] : explode(',', $ids);
            } elseif (!is_array($ids)) {
                $ids = [];
            }
            $ids = array_map('trim', $ids);
            $ids = array_filter($ids, 'strlen');
            if ($ids) {
                $idsAlias = $this->adapter->createAlias();
                $idsPlaceholder = ':' . $idsAlias;
                $qb
                    ->setParameter($idsAlias, $ids, Connection::PARAM_INT_ARRAY)
                    ->addOrderBy("FIELD(omeka_root.id, $idsPlaceholder)", $query['sort_order'])
                    // In AbstractEntityAdapter::search(), the countQb is a
                    // clone of this qb that removes the orderBy part, but not
                    // the parameters associated to it. So a fake argument Where
                    // is added , to avoid a doctrine issue.
                    // TODO Patch omeka: get the dql part orderBy, get the parameter associated to it, check if is used somewhere before removing it.
                    ->andWhere($expr->in(
                        $this->adapter->createNamedParameter($qb, reset($ids)),
                        $idsPlaceholder
                    ));
            }
        } elseif (isset($query['sort_by'])
            // The event "api.search.query.finalize is skipped In scalar search,
            // so pass it here.
            /** @see \Omeka\Module::searchFullText() */
            && isset($query['fulltext_search'])
            && in_array($query['sort_by'], ['relevance', 'relevance desc', 'relevance asc'])
            && !in_array(trim($query['fulltext_search']), ['', '*'], true)
        ) {
            // The order is slightly different from the standard one, because
            // an order by id desc is appended automatically, so all results
            // with the same score are sorted by id desc and not randomly.
            // Don't use "`" here for doctrine.
            $matchOrder = 'MATCH(omeka_fulltext_search.title, omeka_fulltext_search.text) AGAINST (:omeka_fulltext_search)';
            $sortOrder = $query['sort_by'] === 'relevance asc' ? 'ASC' : 'DESC';
            $qb
                // The hidden select and "group by" avoids issue with mysql mode "only_full_group_by".
                // But the select is not available when returning scalar ids.
                // And to add it in AbstractEntityAdapter does not help, because
                // the paginator requires a total count and remove all select
                // to get it.
                // ->addSelect($matchOrder . ' AS HIDDEN orderMatch')
                // ->addGroupBy('orderMatch')
                // So add a hidden select and remove order before count, but
                // directly in the adapter.
                // When requesting scalar results, the fix is included via a
                // later event, awaiting integration of fix omeka/omeka-s#2224.
                ->addOrderBy($matchOrder, $sortOrder);
        }

        return $this;
    }

    /**
     * Build query to check data of empty item sets for items.
     */
    protected function searchItemItemSets(QueryBuilder $qb, array $query): self
    {
        if ($this->adapter instanceof ItemAdapter
            && isset($query['item_set_id'])
            && $query['item_set_id'] !== ''
            && $query['item_set_id'] !== []
        ) {
            $expr = $qb->expr();
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

        return $this;
    }

    /**
     * Build query to check data of medias from items.
     *
     * Use a single method in order to manage a single sql join, because they
     * are heavy in doctrine when the resource is a discriminated one.
     */
    protected function searchItemMediaData(QueryBuilder $qb, array $query): self
    {
        $hasOriginal = isset($query['has_original']) && (string) $query['has_original'] !== ''
            ? (bool) $query['has_original']
            : null;
        $hasThumbnails = isset($query['has_thumbnails']) && (string) $query['has_thumbnails'] !== ''
            ? (bool) $query['has_thumbnails']
            : null;
        $mediaTypes = isset($query['media_types'])
            ? array_filter(array_map('trim', is_array($query['media_types']) ? $query['media_types'] : [$query['media_types']]))
            : null;

        if ($hasOriginal === null && $hasThumbnails === null && !$mediaTypes) {
            return $this;
        }

        $expr = $qb->expr();

        // Has media was reimplemented in core in Omeka S v4.0.
        // Nevertheless, it allows to check if a inner/left join was added.
        $hasMedia = isset($query['has_media']) && (string) $query['has_media'] !== ''
            ? (bool) $query['has_media']
            : null;

        // If has media is set, a join is already set, so get it, else create a new one.
        $joinInner = $hasOriginal === true || $hasThumbnails === true || $mediaTypes;
        $joinLeft = $hasOriginal === false || $hasThumbnails === false;

        $mediaAliasInner = null;
        $mediaAliasLeft = null;
        if ($hasMedia !== null) {
            // Get the media alias for the existing join.
            // The process is quick because there should be less than some
            // joins, but it requires a sub-loop.
            /** @var \Doctrine\ORM\Query\Expr\Join $join */
            $dqlJoins = $qb->getDQLPart('join');
            foreach ($dqlJoins['omeka_root'] ?? [] as $join) {
                if ($join->getJoin() === 'omeka_root.media' && !$join->getConditionType()) {
                    $joinType = $join->getJoinType();
                    if ($joinType === \Doctrine\ORM\Query\Expr\Join::INNER_JOIN) {
                        $mediaAliasInner = $join->getAlias();
                    } elseif ($joinType === \Doctrine\ORM\Query\Expr\Join::LEFT_JOIN) {
                        $mediaAliasLeft = $join->getAlias();
                    }
                }
            }
        }

        // Use "where" instead of modifying condition like in previsous version.

        if ($joinInner) {
            if (!$mediaAliasInner) {
                $mediaAliasInner = $this->adapter->createAlias();
                $qb
                    ->innerJoin('omeka_root.media', $mediaAliasInner);
            }
            if ($hasOriginal === true) {
                $qb
                    ->andWhere($expr->eq($mediaAliasInner . '.hasOriginal', 1));
            }
            if ($hasThumbnails === true) {
                $qb
                    ->andWhere($expr->eq($mediaAliasInner . '.hasThumbnails', 1));
            }
            if ($mediaTypes) {
                $qb
                    ->andWhere($expr->in(
                        $mediaAliasInner . '.mediaType',
                        $this->adapter->createNamedParameter($qb, $mediaTypes)
                    ));
            }
        }

        if ($joinLeft) {
            // Most of the time, there is no join left here.
            if (!$mediaAliasLeft) {
                $mediaAliasLeft = $this->adapter->createAlias();
                $qb
                    ->leftJoin('omeka_root.media', $mediaAliasLeft);
            }
            if ($hasOriginal === false) {
                $qb
                    ->andWhere($expr->orX(
                        $expr->isNull($mediaAliasLeft . '.id'),
                        $expr->eq($mediaAliasLeft . '.hasOriginal', 0)
                    ));
            }
            if ($hasThumbnails === false) {
                $qb
                    ->andWhere($expr->orX(
                        $expr->isNull($mediaAliasLeft . '.id'),
                        $expr->eq($mediaAliasLeft . '.hasThumbnails', 0)
                    ));
            }
        }

        return $this;
    }

    /**
     * Build query to check by multiple media types.
     */
    protected function searchByMediaType(QueryBuilder $qb, array $query): self
    {
        if (!isset($query['media_types'])) {
            return $this;
        }

        $values = is_array($query['media_types'])
            ? $query['media_types']
            : [$query['media_types']];
        $values = array_filter(array_map('trim', $values));
        if (empty($values)) {
            return $this;
        }
        $values = array_values($values);

        $expr = $qb->expr();

        $qb
            ->andWhere($expr->in(
                'omeka_root.mediaType',
                $this->adapter->createNamedParameter($qb, $values)
            ));
        return $this;
    }

    /**
     * Build query to check if a media has an original file or not.
     *
     * The argument uses "has_original", with value "1" or "0".
     */
    protected function searchHasOriginal(QueryBuilder $qb, array $query): self
    {
        return $this->searchMediaSpecific($qb, $query, 'has_original');
    }

    /**
     * Build query to check if a media has thumbnails or not.
     *
     * The argument uses "has_thumbnails", with value "1" or "0".
     */
    protected function searchHasThumbnails(QueryBuilder $qb, array $query): self
    {
        return $this->searchMediaSpecific($qb, $query, 'has_thumbnails');
    }

    /**
     * Build query to check if a media has an original file or thumbnails or not.
     *
     * @param string $field "has_original" or "has_thumbnails".
     */
    protected function searchMediaSpecific(QueryBuilder $qb, array $query, $field): self
    {
        if (!isset($query[$field])) {
            return $this;
        }

        $value = (string) $query[$field];
        if ($value === '') {
            return $this;
        }

        $fields = [
            'has_original' => 'hasOriginal',
            'has_thumbnails' => 'hasThumbnails',
        ];
        $qb
            ->andWhere($qb->expr()->eq('omeka_root.' . $fields[$field], (int) (bool) $value));

        return $this;
    }

    /**
     * Build query to search media by item set.
     */
    protected function searchMediaByItemSet(QueryBuilder $qb, array $query): self
    {
        if (!isset($query['item_set_id'])) {
            return $this;
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

        return $this;
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
     * @return string|null
     */
    protected function getDateTimeFromValue($value, $defaultFirst = true): ?string
    {
        $yearMin = -292277022656;
        $yearMax = 292277026595;
        $patternIso8601 = '^(?<date>(?<year>-?\d{1,})(-(?<month>\d{1,2}))?(-(?<day>\d{1,2}))?)(?<time>((?:T| )(?<hour>\d{1,2}))?(:(?<minute>\d{1,2}))?(:(?<second>\d{1,2}))?)(?<offset>((?<offset_hour>[+-]\d{1,2})?(:?(?<offset_minute>\d{1,2}))?)|Z?)$';

        static $dateTimes = [];

        if (!$value) {
            return null;
        }

        $firstOrLast = $defaultFirst ? 'first' : 'last';
        if (array_key_exists($value, $dateTimes) && array_key_exists($firstOrLast, $dateTimes[$value])) {
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
        $dateTime['date'] = new DateTime('now', new DateTimeZone($dateTime['offset_normalized']));
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
     * Get a sql-formatted date time via any string managed by php.
     */
    protected function getDateTimeViaAnyString($value): ?string
    {
        // Don't use strtotime() directly in order to use the same date time
        // zone than the method getDateTimeFromValue().
        try {
            return (new DateTime((string) $value))->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the last day of a given year/month.
     */
    protected function getLastDay($year, $month): int
    {
        $month = (int) $month;
        if (in_array($month, [4, 6, 9, 11], true)) {
            return 30;
        } elseif ($month === 2) {
            return date('L', mktime(0, 0, 0, 1, 1, $year)) ? 29 : 28;
        } else {
            return 31;
        }
    }
}
