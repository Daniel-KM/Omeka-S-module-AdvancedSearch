<?php declare(strict_types=1);

namespace AdvancedSearch\Site\ResourcePageBlockLayout;

use Laminas\Session\Container;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

/**
 * Display a prev/next navigation on the item page within the context of the
 * last browse: search results, item set (collection/album) or user selection.
 *
 * Context is read first from URL query parameters (for shared/pasted links),
 * then from the session (populated by listeners during browse). The block
 * loops over the bounded list of ids stored for the context.
 */
class ResourceNav implements ResourcePageBlockLayoutInterface
{
    public function getLabel(): string
    {
        return 'Resource navigation'; // @translate
    }

    public function getCompatibleResourceNames(): array
    {
        return [
            'items',
        ];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource): string
    {
        if (!$resource instanceof ItemRepresentation) {
            return '';
        }

        $site = $view->currentSite();
        if (!$site) {
            return '';
        }

        $plugins = $view->getHelperPluginManager();
        $siteSetting = $plugins->get('siteSetting');

        $limit = (int) $siteSetting('advancedsearch_resource_nav_limit', 25);
        if ($limit <= 0) {
            return '';
        }

        $enabledTypes = $siteSetting('advancedsearch_resource_nav_types', ['search', 'collection', 'selection']);
        if (!is_array($enabledTypes) || !$enabledTypes) {
            return '';
        }

        // Ensure session is started, else the Container is empty on item show.
        $sessionManager = Container::getDefaultManager();
        if (!$sessionManager->sessionExists()) {
            try {
                $sessionManager->start();
            } catch (\Throwable $e) {
                return '';
            }
        }

        // First, try to rebuild context from URL query params (for shared
        // links to a collection or a selection). When set, it overrides the
        // session, and the param is propagated to prev/next links below.
        $propagateQuery = [];
        $data = $this->contextFromQuery($view, $site, $limit, $enabledTypes, $propagateQuery);

        // Fallback to session.
        if (!$data) {
            $session = new Container('AdvancedSearch');
            $sessionData = $session->resource_nav ?? null;
            if (is_array($sessionData)
                && (int) ($sessionData['site_id'] ?? 0) === $site->id()
                && in_array($sessionData['type'] ?? '', $enabledTypes, true)
            ) {
                $data = $sessionData;
            }
        }

        if (!$data || empty($data['ids'])) {
            return '';
        }

        $ids = array_map('intval', $data['ids']);
        $itemId = $resource->id();
        $index = array_search($itemId, $ids, true);
        if ($index === false) {
            return '';
        }

        $total = count($ids);

        $api = $plugins->get('api');
        $prev = null;
        $next = null;
        if ($total > 1) {
            $prevIdx = ($index - 1 + $total) % $total;
            $nextIdx = ($index + 1) % $total;
            try {
                $prev = $api->read('items', $ids[$prevIdx])->getContent();
            } catch (\Throwable $e) {
            }
            try {
                $next = $api->read('items', $ids[$nextIdx])->getContent();
            } catch (\Throwable $e) {
            }
        }

        // Skip if another resource navigation block already rendered.
        $placeholder = $view->placeholder('resourcePageNavRendered');
        if ((string) $placeholder === '1') {
            return '';
        }
        $placeholder->set('1');

        // Propagate share-link query params to prev/next URLs so that the
        // context is preserved when navigating from item to item.
        $prevUrl = $prev ? $this->buildItemUrl($prev, $propagateQuery) : '';
        $nextUrl = $next ? $this->buildItemUrl($next, $propagateQuery) : '';

        return $view->partial('common/resource-page-block-layout/resource-nav', [
            'resource' => $resource,
            'type' => (string) ($data['type'] ?? ''),
            'subtype' => (string) ($data['subtype'] ?? ''),
            'label' => (string) ($data['label'] ?? ''),
            'contextUrl' => (string) ($data['url'] ?? ''),
            'position' => $index + 1,
            'total' => $total,
            'totalResults' => (int) ($data['total'] ?? $total),
            'limit' => (int) ($data['limit'] ?? $total),
            'prev' => $prev,
            'next' => $next,
            'prevUrl' => $prevUrl,
            'nextUrl' => $nextUrl,
            'itemSet' => $data['item_set'] ?? null,
            'selection' => $data['selection'] ?? null,
        ]);
    }

    protected function buildItemUrl(\Omeka\Api\Representation\ItemRepresentation $item, array $queryParams): string
    {
        $url = $item->siteUrl();
        if (!$queryParams) {
            return $url;
        }
        return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($queryParams);
    }

    /**
     * Rebuild navigation context from URL query parameters.
     *
     * Supported params:
     * - ?resource_nav_item_set=ID
     * - ?resource_nav_selection=ID
     */
    protected function contextFromQuery(
        PhpRenderer $view,
        \Omeka\Api\Representation\SiteRepresentation $site,
        int $limit,
        array $enabledTypes,
        array &$propagateQuery
    ): ?array {
        $params = $view->params();
        $itemSetId = (int) $params->fromQuery('resource_nav_item_set');
        $selectionId = (int) $params->fromQuery('resource_nav_selection');

        if (!$itemSetId && !$selectionId) {
            return null;
        }

        $api = $view->getHelperPluginManager()->get('api');

        if ($itemSetId && in_array('collection', $enabledTypes, true)) {
            $propagateQuery['resource_nav_item_set'] = $itemSetId;
            try {
                /** @var \Omeka\Api\Representation\ItemSetRepresentation $itemSet */
                $itemSet = $api->read('item_sets', $itemSetId)->getContent();
            } catch (\Throwable $e) {
                return null;
            }
            $apiResponse = $api->search('items', [
                'item_set_id' => $itemSetId,
                'site_id' => $site->id(),
                'limit' => $limit,
                'offset' => 0,
            ], ['returnScalar' => 'id']);
            $ids = array_values(array_map('intval', $apiResponse->getContent() ?: []));
            if (!$ids) {
                return null;
            }
            return [
                'site_id' => $site->id(),
                'type' => 'collection',
                'subtype' => $this->itemSetSubtype($itemSet),
                'label' => $itemSet->displayTitle(),
                'url' => $itemSet->siteUrl(),
                'ids' => $ids,
                'total' => (int) $apiResponse->getTotalResults(),
                'limit' => $limit,
                'item_set' => $itemSet,
            ];
        }

        if ($selectionId && in_array('selection', $enabledTypes, true)) {
            $propagateQuery['resource_nav_selection'] = $selectionId;
            try {
                $selection = $api->read('selections', $selectionId)->getContent();
            } catch (\Throwable $e) {
                return null;
            }
            $srResponse = $api->search('selection_resources', [
                'selection_id' => $selectionId,
                'limit' => $limit,
                'offset' => 0,
                'sort_by' => 'id',
                'sort_order' => 'asc',
            ]);
            $selectionResources = $srResponse->getContent() ?: [];
            $ids = [];
            foreach ($selectionResources as $sr) {
                $resource = $sr->resource();
                if ($resource && $resource->resourceName() === 'items') {
                    $ids[] = (int) $resource->id();
                }
            }
            if (!$ids) {
                return null;
            }
            return [
                'site_id' => $site->id(),
                'type' => 'selection',
                'subtype' => '',
                'label' => method_exists($selection, 'displayTitle') ? (string) $selection->displayTitle() : '',
                'url' => method_exists($selection, 'siteUrl') ? (string) $selection->siteUrl() : '',
                'ids' => $ids,
                'total' => (int) $srResponse->getTotalResults(),
                'limit' => $limit,
                'selection' => $selection,
            ];
        }

        return null;
    }

    /**
     * Detect an item set subtype from a curation property, so themes can
     * customize the label ("Album" vs "Collection").
     */
    protected function itemSetSubtype(\Omeka\Api\Representation\ItemSetRepresentation $itemSet): string
    {
        $val = $itemSet->value('curation:set');
        if (!$val) {
            return '';
        }
        $raw = strtolower((string) $val);
        return $raw;
    }
}
