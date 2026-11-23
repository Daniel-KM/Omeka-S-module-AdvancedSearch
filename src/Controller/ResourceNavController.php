<?php declare(strict_types=1);

namespace AdvancedSearch\Controller;

use AdvancedSearch\Site\ResourcePageBlockLayout\ResourceNav;
use Laminas\Mvc\Controller\AbstractActionController;

/**
 * Render the block "resourceNav" for the context kept by the browser.
 *
 * The context is sent by the script browse-context.js, so no session is used.
 * The ids are only used to select the neighbors, that are read via the api, so
 * the visibility is checked as usual.
 */
class ResourceNavController extends AbstractActionController
{
    public function indexAction()
    {
        $response = $this->getResponse();
        $response->getHeaders()
            ->addHeaderLine('Content-Type', 'text/html; charset=utf-8')
            ->addHeaderLine('X-Robots-Tag', 'noindex, nofollow');

        $site = $this->currentSite();
        $siteSettings = $this->siteSettings();
        $limit = (int) $siteSettings->get('advancedsearch_resource_nav_limit', 25);
        $enabledTypes = $siteSettings->get('advancedsearch_resource_nav_types', ['search', 'collection', 'selection', 'series', 'featured']);
        $params = $this->params();
        $type = (string) $params->fromQuery('type');
        if ($limit <= 0 || !is_array($enabledTypes) || !in_array($type, $enabledTypes, true)) {
            return $response;
        }

        $api = $this->api();
        try {
            $item = $api->read('items', (int) $params->fromRoute('id'))->getContent();
        } catch (\Throwable $e) {
            return $response;
        }

        $ids = array_slice(array_values(array_filter(array_map('intval', explode(',', (string) $params->fromQuery('ids'))))), 0, $limit);
        $url = (string) $params->fromQuery('url');
        if ($url === '' || $url[0] !== '/' || (strlen($url) > 1 && $url[1] === '/')) {
            $url = '';
        }
        $itemSetId = (int) $params->fromQuery('item_set_id');
        $selectionId = (int) $params->fromQuery('selection_id');

        $data = [
            'site_id' => $site->id(),
            'type' => $type,
            'subtype' => (string) $params->fromQuery('subtype'),
            'label' => (string) $params->fromQuery('label'),
            'url' => $url,
            'ids' => $ids,
            'total' => (int) $params->fromQuery('total') ?: count($ids),
            'limit' => $limit,
            'show_label' => in_array($params->fromQuery('show_label'), ['1', 'true'], true),
            'item_set_id' => $itemSetId ?: null,
            'selection_id' => $selectionId ?: null,
            'item_set' => $itemSetId ? $this->readOrNull('item_sets', $itemSetId) : null,
            'selection' => $selectionId ? $this->readOrNull('selections', $selectionId) : null,
        ];

        /** @var \Laminas\View\Renderer\PhpRenderer $renderer */
        $renderer = $this->viewRenderer();
        $response->setContent((new ResourceNav())->renderContext($renderer, $item, $data));
        return $response;
    }

    protected function viewRenderer()
    {
        return $this->getEvent()->getApplication()->getServiceManager()->get('ViewRenderer');
    }

    /**
     * Read a resource, or null when it is missing, private or when its module
     * is not installed.
     */
    protected function readOrNull(string $resourceName, int $id)
    {
        try {
            return $this->api()->read($resourceName, $id)->getContent();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
