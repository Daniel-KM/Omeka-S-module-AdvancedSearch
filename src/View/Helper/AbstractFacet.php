<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class AbstractFacet extends AbstractHelper
{
    /**
     * @var \Omeka\View\Helper\Api
     */
    protected $api;

    /**
     * @var \ItemSetsTree\ViewHelper\ItemSetsTree
     */
    protected $itemSetsTree;

    /**
     * @var \Laminas\View\Helper\Partial
     */
    protected $partialHelper;

    /**
     * @var \Laminas\I18n\View\Helper\Translate
     */
    protected $translate;

    /**
     * @var \Omeka\View\Helper\Url
     */
    protected $urlHelper;

    /**
     * @var bool
     */
    protected $isTree = false;

    /**
     * @var string
     */
    protected $partial;

    /**
     * @var string
     */
    protected $route = '';

    /**
     * @var int
     */
    protected $siteId;

    /**
     * @var array
     */
    protected $params = [];

    /**
     * @var array
     */
    protected $queryBase = [];

    /**
     * @var array
     */
    protected $tree;

    /**
     * Create one facet as link, checkbox, select or button.
     *
     * @param string|array $facetField Field name or null for active facets.
     * @param array $facetValues Each facet value has two keys: value and count.
     * May have more for specific facets, like facet range.
     * For active facets, keys are names and values are list of values.
     * @return string|array
     */
    public function __invoke(?string $facetField, array $facetValues, array $options = [], bool $asData = false)
    {
        static $facetsData = [];

        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $this->api = $plugins->get('api');
        $this->urlHelper = $plugins->get('url');
        $this->translate = $plugins->get('translate');
        $this->partialHelper = $plugins->get('partial');

        $this->route = $plugins->get('matchedRouteName')();
        $this->params = $view->params()->fromRoute();
        $this->queryBase = $view->params()->fromQuery();

        // Keep browsing inside an item set.
        if (!empty($this->params['item-set-id'])) {
            $this->route = 'site/item-set';
        }

        $isSiteRequest = $plugins->get('status')->isSiteRequest();
        if ($isSiteRequest) {
            $this->siteId = $plugins
                ->get('Laminas\View\Helper\ViewModel')
                ->getRoot()
                ->getVariable('site')
                ->id();
        }

        if ($this->isTree) {
            if ($plugins->has('itemSetsTree')) {
                $this->itemSetsTree = $plugins->get('itemSetsTree');
            } else {
                $this->isTree = false;
            }
        }

        unset($this->queryBase['page']);

        // For active facets, there is no facet field.
        if ($facetField === null) {
            $facetsData[$facetField] = $this->prepareActiveFacetData($facetValues, $options);
        } elseif (!isset($facetsData[$facetField])) {
            $facetsData[$facetField] = $this->prepareFacetData($facetField, $facetValues, $options);
        }

        if ($asData) {
            return $facetsData[$facetField];
        }

        return $this->partialHelper->__invoke($this->partial, $facetField === null
            ? ['activeFacets' => $facetsData[$facetField], 'options' => $options]
            : $facetsData[$facetField]);
    }

    /**
     * Get facet values with "url" when display is direct, "active" or "label".
     *
     * May contain other keys for specific facets, like "from" and "to" for
     * facet ranges or "level" for facet tree.
     */
    protected function prepareFacetData(string $facetField, array $facetValues, array $options): array
    {
        $isFacetModeDirect = ($options['mode'] ?? '') === 'link';

        $this->tree = $this->isTree && substr($facetField, 0, 14) === 'item_sets_tree' /*&& in_array($facetField, ['item_set', 'item_set_id']) */
            ? $this->itemSetsTreeQuick()
            : null;

        foreach ($facetValues as /* $facetIndex => */ &$facetValue) {
            $facetValueValue = (string) $facetValue['value'];
            $query = $this->queryBase;

            // The facet value is compared against a string (the query args).
            $facetValueLabel = (string) $this->facetValueLabel($facetField, $facetValueValue);
            if (strlen($facetValueLabel)) {
                if (isset($query['facet'][$facetField]) && array_search($facetValueValue, $query['facet'][$facetField]) !== false) {
                    $values = $query['facet'][$facetField];
                    // TODO Remove this filter to keep all active facet values?
                    $values = array_filter($values, function ($v) use ($facetValueValue) {
                        return $v !== $facetValueValue;
                    });
                    $query['facet'][$facetField] = $values;
                    $active = true;
                } else {
                    $query['facet'][$facetField][] = $facetValueValue;
                    $active = false;
                }
                $url = $isFacetModeDirect ? $this->urlHelper->__invoke($this->route, $this->params, ['query' => $query]) : '';
            } else {
                $active = false;
                $url = '';
            }

            $facetValue['value'] = $facetValueValue;
            $facetValue['label'] = $facetValueLabel;
            $facetValue['active'] = $active;
            $facetValue['url'] = $url;
        }
        unset($facetValue);

        // For item set tree, the values should be reordered according to the
        // tree, else indentation will be incorrect.
        if ($this->isTree && $this->tree && count($this->tree) > 1) {
            $facetValuesByIds = [];
            foreach ($facetValues as $data) {
                $facetValuesByIds[$data['value']] = $data;
            }
            $facetValues = array_replace($this->tree, $facetValuesByIds);
            $facetValues = array_intersect_key($facetValues, $facetValuesByIds);
            /*
            // Keep added nodes from the tree, so use same keys.
            // Normally useless if indexed recursively.
            foreach ($facetValues as &$facetValue) {
                if (array_key_exists('level', $facetValue)) {
                    $facetValue = [
                        'value' => $facetValue['id'],
                        'count' => 0,
                        'label' => $facetValue['title'],
                        'active' => false,
                        'url' => null,
                    ];
                }
            }
            unset($facetValue);
            */
            $facetValues = array_values($facetValues);
        }

        return [
            'name' => $facetField,
            'facetValues' => $facetValues,
            'options' => $options,
            'tree' => $this->isTree ? $this->tree : null,
        ];
    }

    /**
     * The facets may be indexed by the search engine.
     *
     * @todo Remove search of facet labels: use values from the response.
     */
    protected function facetValueLabel(string $facetField, string $value): ?string
    {
        if (!strlen($value)) {
            return null;
        }

        switch ($facetField) {
            case 'access':
            case 'resource_name':
            case 'resource_type':
                return $value;

            case 'is_public':
                return $value
                    ? 'Private'
                    : 'Public';

            case 'id':
                $data = ['id' => $value];
                // The site id is required in public.
                if ($this->siteId) {
                    $data['site_id'] = $this->siteId;
                }
                /** @var \Omeka\Api\Representation\ItemSetRepresentation $resource */
                try {
                    // Resources cannot be searched, only read.
                    $resource = $this->api->read('resources', $data)->getContent();
                } catch (\Exception $e) {
                }
                return $resource
                    ? (string) $resource->displayTitle()
                    // Manage the case where a resource was indexed but removed.
                    // In public side, the item set should belong to a site too.
                    : null;

            case 'owner':
            case 'owner_id':
                /** @var \Omeka\Api\Representation\UserRepresentation $resource */
                // Only allowed users can read and search users.
                if (is_numeric($value)) {
                    try {
                        $resource = $this->api->read('users', ['id' => $value])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                    return $resource->name();
                }
                // No more check: email is not reference, so it always the name.
                return $value;

            case 'site':
            case 'site_id':
                /** @var \Omeka\Api\Representation\SiteRepresentation $resource */
                if (is_numeric($value)) {
                    try {
                        $resource = $this->api->read('sites', ['id' => $value])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                    return $resource->title();
                }
                $resource = $this->api->searchOne('sites', ['slug' => $value])->getContent();
                return $resource
                    ? $resource->title()
                    // Manage the case where a resource was indexed but removed.
                    : null;

            case 'class':
            case 'resource_class_id':
            case 'resource_class':
                if (is_numeric($value)) {
                    try {
                        /** @var \Omeka\Api\Representation\ResourceClassRepresentation $resource */
                        $resource = $this->api->read('resource_classes', ['id' => $value])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                    return $this->translate->__invoke($resource->label());
                }
                $resource = $this->api->searchOne('resource_classes', ['term' => $value])->getContent();
                return $resource
                    ? $this->translate->__invoke($resource->label())
                    // Manage the case where a resource was indexed but removed.
                    : null;

            case 'template':
            case 'resource_template_id':
            case 'resource_template':
                if (is_numeric($value)) {
                    try {
                        /** @var \Omeka\Api\Representation\ResourceTemplateRepresentation $resource */
                        $resource = $this->api->read('resource_templates', ['id' => $value])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                    return $resource->label();
                }
                $resource = $this->api->searchOne('resource_templates', ['label' => $value])->getContent();
                return $resource
                    ? $resource->label()
                    // Manage the case where a resource was indexed but removed.
                    : null;

            case 'item_sets_tree':
            case 'item_sets_tree_is':
                if (!is_numeric($value)) {
                    return $value;
                }
                if ($this->tree) {
                    if (is_numeric($value)) {
                        return $this->tree[$value]['title'] ?? $value;
                    }
                    // Confirm that the title exists.
                    // This is useless for now, since item sets tree are indexed by id.
                    $labels = array_column($this->tree ?? [], 'title', 'id');
                    $key = array_search($value, $labels);
                    if ($key !== false) {
                        return $value;
                    }
                }
                // The tree may not be available, so get title via api.
                // no break.

            case 'item_set':
            case 'item_set_id':
                $data = ['id' => $value];
                // The site id is required in public.
                if ($this->siteId) {
                    $data['site_id'] = $this->siteId;
                }
                /** @var \Omeka\Api\Representation\ItemSetRepresentation $resource */
                $resource = $this->api->searchOne('item_sets', $data)->getContent();
                return $resource
                    ? (string) $resource->displayTitle()
                    // Manage the case where a resource was indexed but removed.
                    // In public side, the item set should belong to a site too.
                    : null;

            case 'property':
            default:
                return $value;
        }
    }

    /**
     * Get flat tree of item sets quickly.
     *
     * Use a quick connection request instead of a long procedure.
     *
     * @see \AdvancedSearch\View\Helper\AbstractFacet::itemsSetsTreeQuick()
     * @see \BlockPlus\View\Helper\Breadcrumbs::itemsSetsTreeQuick()
     * @see \SearchSolr\ValueExtractor\AbstractResourceEntityValueExtractor::itemSetsTreeQuick()
     *
     * @todo Simplify ordering: by sql (for children too) or store.
     *
     * @return array
     */
    protected function itemSetsTreeQuick(): array
    {
        // Run an api request to check rights.
        $itemSetTitles = $this->api->search('item_sets', ['site_id' => $this->siteId, 'return_scalar' => 'title'])->getContent();
        if (!count($itemSetTitles)) {
            return [];
        }

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->api->read('vocabularies', 1)->getContent()->getServiceLocator()->get('Omeka\Connection');

        $sortingMethod = $this->getView()->setting('itemsetstree_sorting_method', 'title') === 'rank' ? 'rank' : 'title';
        $sortingMethodSql = $sortingMethod === 'rank'
            ? 'item_sets_tree_edge.rank'
            : 'resource.title';

        // TODO Use query builder.
        $sql = <<<SQL
SELECT
    item_sets_tree_edge.item_set_id,
    item_sets_tree_edge.item_set_id AS "id",
    item_sets_tree_edge.parent_item_set_id AS "parent",
    item_sets_tree_edge.rank AS "rank",
    resource.title as "title"
FROM item_sets_tree_edge
JOIN resource ON resource.id = item_sets_tree_edge.item_set_id
WHERE item_sets_tree_edge.item_set_id IN (:ids)
GROUP BY resource.id
ORDER BY $sortingMethodSql ASC;
SQL;
        $flatTree = $connection->executeQuery($sql, ['ids' => array_keys($itemSetTitles)], ['ids' => $connection::PARAM_INT_ARRAY])->fetchAllAssociativeIndexed();

        // Use integers or string to simplify comparaisons.
        foreach ($flatTree as &$node) {
            $node['id'] = (int) $node['id'];
            $node['parent'] = (int) $node['parent'] ?: null;
            $node['rank'] = (int) $node['rank'];
            $node['title'] = (string) $node['title'];
        }
        unset($node);

        $structure = [];
        foreach ($flatTree as $id => $node) {
            $children = [];
            foreach ($flatTree as $subId => $subNode) {
                if ($subNode['parent'] === $id) {
                    $children[$subId] = $subId;
                }
            }
            $ancestors = [];
            $nodeWhile = $node;
            while ($parentId = $nodeWhile['parent']) {
                $ancestors[$parentId] = $parentId;
                $nodeWhile = $flatTree[$parentId] ?? null;
                if (!$nodeWhile) {
                    break;
                }
            }
            $structure[$id] = $node;
            $structure[$id]['children'] = $children;
            $structure[$id]['ancestors'] = $ancestors;
            $structure[$id]['level'] = count($ancestors);
        }

        // Order by sorting method.
        if ($sortingMethod === 'rank') {
            $sortingFunction = function ($a, $b) use ($structure) {
                return $structure[$a]['rank'] - $structure[$b]['rank'];
            };
        } else {
            $sortingFunction = function ($a, $b) use ($structure) {
                return strcmp($structure[$a]['title'], $structure[$b]['title']);
            };
        }

        foreach ($structure as &$node) {
            usort($node['children'], $sortingFunction);
        }
        unset($node);

        // Get and order root nodes.
        $roots = [];
        foreach ($structure as $id => $node) {
            if (!$node['level']) {
                $roots[$id] = $node;
            }
        }

        // Root is already ordered via sql.

        // TODO The children are useless here.

        // Reorder whole structure.
        // TODO Use a while loop.
        $result = [];
        foreach ($roots as $id => $root) {
            $result[$id] = $root;
            foreach ($root['children'] ?? [] as $child1) {
                $child1 = $structure[$child1];
                $result[$child1['id']] = $child1;
                foreach ($child1['children'] ?? [] as $child2) {
                    $child2 = $structure[$child2];
                    $result[$child2['id']] = $child2;
                    foreach ($child2['children'] ?? [] as $child3) {
                        $child3 = $structure[$child3];
                        $result[$child3['id']] = $child3;
                        foreach ($child3['children'] ?? [] as $child4) {
                            $child4 = $structure[$child4];
                            $result[$child4['id']] = $child4;
                            foreach ($child4['children'] ?? [] as $child5) {
                                $child5 = $structure[$child5];
                                $result[$child5['id']] = $child5;
                                foreach ($child5['children'] ?? [] as $child6) {
                                    $child6 = $structure[$child6];
                                    $result[$child6['id']] = $child6;
                                    foreach ($child6['children'] ?? [] as $child7) {
                                        $child7 = $structure[$child7];
                                        $result[$child7['id']] = $child7;
                                        foreach ($child7['children'] ?? [] as $child8) {
                                            $child8 = $structure[$child8];
                                            $result[$child8['id']] = $child8;
                                            foreach ($child8['children'] ?? [] as $child9) {
                                                $child9 = $structure[$child9];
                                                $result[$child9['id']] = $child9;
                                                foreach ($child9['children'] ?? [] as $child10) {
                                                    $child10 = $structure[$child10];
                                                    $result[$child10['id']] = $child10;
                                                    foreach ($child10['children'] ?? [] as $child11) {
                                                        $child11 = $structure[$child11];
                                                        $result[$child11['id']] = $child11;
                                                        foreach ($child11['children'] ?? [] as $child12) {
                                                            $child12 = $structure[$child12];
                                                            $result[$child12['id']] = $child12;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $structure = $result;

        // Append missing item sets.
        foreach (array_diff_key($itemSetTitles, $flatTree) as $id => $title) {
            if (isset($structure[$id])) {
                continue;
            }
            $structure[$id] = [
                'id' => $id,
                'parent' => null,
                'rank' => 0,
                'title' => $title,
                'children' => [],
                'ancestors' => [],
                'level' => 0,
            ];
        }

        return $structure;
    }
}
