<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

class AbstractFacetTree extends AbstractFacet
{
    /**
     * @var \ItemSetsTree\ViewHelper\ItemSetsTree
     */
    protected $itemSetsTree;

    /**
     * @var \Thesaurus\Stdlib\Thesaurus
     */
    protected $thesaurus;

    /**
     * @var array
     */
    protected $tree;

    public function __invoke(?string $facetField, array $facetValues, array $options = [], bool $asData = false)
    {
        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        if ($plugins->has('itemSetsTree')) {
            $this->itemSetsTree = $plugins->get('itemSetsTree');
        }
        if ($plugins->has('thesaurus')) {
            $this->thesaurus = $plugins->get('thesaurus')();
        }
        return parent::__invoke($facetField, $facetValues, $options, $asData);
    }

    protected function prepareFacetData(string $facetField, array $facetValues, array $options): array
    {
        $isItemSetsTree = $this->itemSetsTree
            && substr($facetField, 0, 14) === 'item_sets_tree';
            // && in_array($facetField, ['item_set', 'item_set_id']);
        $isThesaurus = $this->thesaurus
            && $options['facets'][$facetField]['type'] === 'Thesaurus';
        if ($isItemSetsTree) {
            $this->tree = $this->itemSetsTreeQuick();
            $result = parent::prepareFacetData($facetField, $facetValues, $options);
            $result['facetValues'] = $this->itemSetsTreeReorderFacets($result['facetValues']);
        } elseif ($isThesaurus) {
            $this->tree = $this->thesaurusQuick($facetField, $options);
            $result = parent::prepareFacetData($facetField, $facetValues, $options);
            $result['facetValues'] = $this->thesaurusReorderAndCompleteFacets($facetField, $result['facetValues'], $options);
        } else {
            return parent::prepareFacetData($facetField, $facetValues, $options);
        }

        $result['tree'] = $this->tree;

        return $result;
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
     * Important:
     * If the list of collections is incomplete, go to /admin/item-sets-tree/edit
     * and save it.
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

    /**
     * Reorder the facets according to the tree to get correct indentation.
     */
    protected function itemSetsTreeReorderFacets(array $facetValues): array
    {
        if (!$this->tree || count($this->tree) <= 1) {
            return $facetValues;
        }

        $facetValuesByIds = [];
        foreach ($facetValues as $facetData) {
            $facetValuesByIds[$facetData['value']] = $facetData;
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

        return array_values($facetValues);
    }

    /**
     * @see \AdvancedSearch\Form\MainSearchForm::searchThesaurus()
     * @see \AdvancedSearch\View\Helper\AbstractFacetTree::thesaurusQuick()
     */
    protected function thesaurusQuick(string $facetField, array $options): ?array
    {
        $facetOptions = $options['facets'][$facetField]['options'];
        $thesaurusId = empty($facetOptions['id']) && empty($facetOptions['thesaurus'])
            ? (int) reset($facetOptions)
            : (int) ($facetOptions['thesaurus'] ?? $facetOptions['id'] ?? 0);
        if (!$thesaurusId) {
            return null;
        }
        $this->thesaurus->__invoke($thesaurusId);
        // Use really flat tree like item set tree.
        // Each element contains: id, title, top, parent, children, level.
        if (method_exists($this->thesaurus, 'simpleTree')) {
            $tree = $this->thesaurus->simpleTree();
        } else {
            $tree = $this->thesaurus->flatTree();
            foreach ($tree as $k => $v) {
                $t = $v['self'];
                $t['level'] = $v['level'];
                $tree[$k] = $t;
            }
        }
        return $tree;
    }

    /**
     * Order facets according to tree for correct indentation and add ancestors.
     */
    protected function thesaurusReorderAndCompleteFacets(string $facetField, array $facetValues, array $options): array
    {
        if (!$this->tree || count($this->tree) <= 1) {
            return $facetValues;
        }

        $treeValues = array_column($this->tree, 'id', 'title');

        // Reorder facet values.
        $facetValuesByLabels = [];
        foreach ($facetValues as $facetData) {
            $facetValuesByLabels[$facetData['value']] = $facetData;
        }
        $facetValuesByLabels = array_replace(array_fill_keys(array_keys($treeValues), null), $facetValuesByLabels);
        $facetValuesByLabels = array_filter($facetValuesByLabels, 'is_array');

        $ancestors = [];

        // Prepend ancestors to each facet.
        $result = [];
        $isFacetModeDirect = ($options['mode'] ?? '') === 'link';
        foreach (array_intersect_key($treeValues, $facetValuesByLabels) as $facetLabel => $treeId) {
            $treeElement = $this->tree[$treeId];
            if ($treeElement['top']) {
                continue;
            }
            // Get all ancestors and prepend them if not set.
            /*
            // TODO In next version of thesaurus, to get the current item will be useless.
            $ancestors = $this->thesaurus
                ->setItem($treeId)
                ->ascendants(true);
            */
            $ancestors = $this->ancestors($treeElement);
            foreach (array_reverse($ancestors, true) as $ancestor) {
                if (!isset($result[$ancestor['title']])) {
                    [$active, $url] = $this->prepareActiveAndUrl($facetField, $ancestor['title'], $isFacetModeDirect);
                    $result[$ancestor['title']] = [
                        'id' => $ancestor['id'],
                        'value' => $ancestor['title'],
                        'count' => 0,
                        'label' => $ancestor['title'],
                        'active' => $active,
                        'url' => $url,
                    ];
                }
            }
            $result[$facetLabel] = $facetValuesByLabels[$facetLabel];
        }

        // Add remaining facet values not in the tree.
        $result += $facetValuesByLabels;

        // TODO Improve process.

        // The tree should be by label In the partial for properties.
        // But only the existing facets are useful.
        $treeByLabels = [];
        $shortTree = array_intersect_key($this->tree, array_fill_keys(array_intersect_key($treeValues, $result), null));
        foreach ($shortTree as $treeElement) {
            $treeByLabels[$treeElement['title']] = $treeElement;
        }
        $this->tree = $treeByLabels;

        return array_values($result);
    }

    /**
     * Recursive method to get the ancestors of an item.
     *
     * @param array $itemData
     * @param array $list Internal param for recursive process.
     * @param int $level
     */
    protected function ancestors(?array $itemData, array $list = [], $level = 0): array
    {
        if (!$itemData) {
            return $list;
        }
        if ($level > 100) {
            return $list;
        }
        if (!$itemData['parent'] || empty($this->tree[$itemData['parent']])) {
            return $list;
        }
        $parent = $this->tree[$itemData['parent']];
        $list[$itemData['parent']] = $parent;
        return $this->ancestors($parent, $list, ++$level);
    }
}
