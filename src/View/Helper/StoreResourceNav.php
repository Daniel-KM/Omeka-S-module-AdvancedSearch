<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\Session\Container;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * Store a resource navigation context in the session so the block "resourceNav"
 * can display a prev/next navigation on the item page.
 *
 * Usage (typically called from a page block template):
 *
 *     $view->storeResourceNav([
 *         'type' => 'collection',
 *         'resources' => $resources,
 *         'label' => $translate('Collections à identifier'),
 *         'item_set_id' => 20000002,
 *     ]);
 *
 * Accepted keys:
 * - type (string, required): "search" | "collection" | "selection"
 * - resources (iterable of items representations) OR ids (int[])
 * - label (string): human-readable label (query, title…)
 * - url (string): back link; defaults to the current request URI
 * - subtype (string): optional subtype (ex: "album" for item sets)
 * - item_set_id (int): for collection contexts, to allow URL sharing
 * - selection_id (int): for selection contexts, to allow URL sharing
 * - show_label (bool): force theme to always display the label
 */
class StoreResourceNav extends AbstractHelper
{
    public function __invoke(array $data): void
    {
        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();

        $status = $plugins->get('status');
        if (!$status->isSiteRequest()) {
            return;
        }

        $site = $view->currentSite();
        if (!$site) {
            return;
        }

        $siteSetting = $plugins->get('siteSetting');
        $limit = (int) $siteSetting('advancedsearch_nav_resource_limit', 25);
        if ($limit <= 0) {
            return;
        }
        $enabledTypes = $siteSetting('advancedsearch_nav_resource_types', ['search', 'collection', 'selection', 'series']);
        if (!is_array($enabledTypes)) {
            $enabledTypes = [];
        }

        $type = (string) ($data['type'] ?? '');
        if (!in_array($type, $enabledTypes, true)) {
            return;
        }

        // Accept a list of resources OR a raw list of ids.
        $ids = [];
        if (!empty($data['ids']) && is_array($data['ids'])) {
            foreach ($data['ids'] as $id) {
                $ids[] = (int) $id;
            }
        } elseif (!empty($data['resources']) && is_iterable($data['resources'])) {
            foreach ($data['resources'] as $r) {
                if ($r instanceof AbstractResourceEntityRepresentation
                    && $r->resourceName() === 'items'
                ) {
                    $ids[] = (int) $r->id();
                }
            }
        }
        if (!$ids) {
            return;
        }
        if (count($ids) > $limit) {
            $ids = array_slice($ids, 0, $limit);
        }

        $url = isset($data['url'])
            ? (string) $data['url']
            : ($_SERVER['REQUEST_URI'] ?? '');

        $payload = [
            'site_id' => $site->id(),
            'type' => $type,
            'subtype' => (string) ($data['subtype'] ?? ''),
            'label' => (string) ($data['label'] ?? ''),
            'url' => $url,
            'ids' => $ids,
            'total' => (int) ($data['total'] ?? count($ids)),
            'limit' => $limit,
            'show_label' => !empty($data['show_label']),
        ];
        if (!empty($data['item_set_id'])) {
            $payload['item_set_id'] = (int) $data['item_set_id'];
        }
        if (!empty($data['selection_id'])) {
            $payload['selection_id'] = (int) $data['selection_id'];
        }

        $sessionManager = Container::getDefaultManager();
        if (!$sessionManager->sessionExists()) {
            try {
                $sessionManager->start();
            } catch (\Throwable $e) {
                return;
            }
        }
        $session = new Container('AdvancedSearch');
        $session->resource_nav = $payload;
    }
}
