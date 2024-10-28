<?php declare(strict_types=1);

namespace AdvancedSearch\Mvc;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\Http\RouteMatch;

class MvcListeners extends AbstractListenerAggregate
{
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            [$this, 'redirectItemSetToSearch'],
            -10
        );
    }

    /**
     * Redirect item-set/show to the search page with item set set as url query,
     * when wanted.
     */
    public function redirectItemSetToSearch(MvcEvent $event): void
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\Settings\SiteSettings $siteSettings
         */

        $routeMatch = $event->getRouteMatch();
        $matchedRouteName = $routeMatch->getMatchedRouteName();
        if ('site/item-set' !== $matchedRouteName) {
            return;
        }

        $itemSetId = $routeMatch->getParam('item-set-id');

        $services = $event->getApplication()->getServiceManager();
        $siteSettings = $services->get('Omeka\Settings\Site');

        // The other options are managed in templates search/search and
        // search/results-header-footer.
        $redirectItemSets = $siteSettings->get('advancedsearch_redirect_itemsets', ['default' => 'browse']);
        $redirectItemSetTo = ($redirectItemSets[$itemSetId] ?? $redirectItemSets['default'] ?? 'browse') ?: 'browse';
        if ($redirectItemSetTo === 'browse') {
            return;
        } elseif ($redirectItemSetTo !== 'search' && $redirectItemSetTo !== 'first') {
            if (mb_substr($redirectItemSetTo, 0, 1) === '/'
                || mb_substr($redirectItemSetTo, 0, 8) === 'https://'
                || mb_substr($redirectItemSetTo, 0, 7) === 'http://'
            ) {
                /** @see \Laminas\Mvc\Controller\Plugin\Redirect::toUrl() */
                /* // TODO Use event response in order to get statistics.
                $event->setResponse(new \Laminas\Http\Response);
                $event->getResponse()
                    ->setStatusCode(302)
                    ->getHeaders()->addHeaderLine('Location', $redirectItemSetTo);
                return;
                */
                if (!headers_sent()) {
                    $serverUrl = new \Laminas\View\Helper\ServerUrl();
                    header('Referer: ' . $serverUrl(true));
                    header('Location: ' . $redirectItemSetTo, true, 302);
                } else {
                    echo '<script>window.location.href="' . $redirectItemSetTo . '";</script>';
                    echo '<noscript><meta http-equiv="refresh" content="0;url=' . $redirectItemSetTo . '"></noscript>';
                }
                die();
            }

            // This is a page slug. Check for its presence and visibility.
            $api = $services->get('Omeka\ApiManager');
            $siteSlug = $routeMatch->getParam('site-slug');
            try {
                $site = $api->read('sites', ['slug' => $siteSlug], [], ['responseContent' => 'resource', 'initialize' => false, 'finalize' => false])->getContent();
                $api->read('site_pages', ['site' => $site->getId(), 'slug' => $redirectItemSetTo], [], ['responseContent' => 'resource', 'initialize' => false, 'finalize' => false]);
            } catch (\Exception $e) {
                return;
            }
            $params = [
                '__NAMESPACE__' => 'Omeka\Controller\Site',
                '__CONTROLLER__' => 'Page',
                '__SITE__' => true,
                'controller' => 'Omeka\Controller\Site\Page',
                'action' => 'show',
                'site-slug' => $siteSlug,
                'page-slug' => $redirectItemSetTo,
            ];
            $routeMatch = new RouteMatch($params);
            $routeMatch->setMatchedRouteName('site/page');
            $event->setRouteMatch($routeMatch);
            return;
        }

        $searchConfigId = $siteSettings->get('advancedsearch_main_config');
        if (empty($searchConfigId)) {
            return;
        }

        // The search config may have been removed, so check and get the slug.
        // It should be cached by doctrine.
        // Or use setting "'advancedsearch_all_configs".
        $api = $services->get('Omeka\ApiManager');
        try {
            $searchConfig = $api->read('search_configs', ['id' => $searchConfigId], [], ['responseContent' => 'resource'])->getContent();
            $searchConfigSlug = $searchConfig->getSlug();
        } catch (\Exception $e) {
            return;
        }

        $siteSlug = $routeMatch->getParam('site-slug');

        $params = [
            '__NAMESPACE__' => 'AdvancedSearch\Controller',
            '__SITE__' => true,
            'controller' => \AdvancedSearch\Controller\SearchController::class,
            'action' => 'search',
            'site-slug' => $siteSlug,
            'id' => $searchConfigId,
            'item-set-id' => $itemSetId,
            'page-slug' => $searchConfigSlug,
            'search-slug' => $searchConfigSlug,
        ];
        $routeMatch = new RouteMatch($params);
        $routeMatch->setMatchedRouteName('search-page-' . $searchConfigSlug);
        $event->setRouteMatch($routeMatch);

        /** @see \Laminas\Stdlib\Parameters */
        $query = $event->getRequest()->getQuery();
        $query
            ->set('item_set', ['id' => [$itemSetId]]);

        // Manage order of items in item set for module Next.
        // TODO Move the feature from module Next to here.
        if (!empty($query['sort'])) {
            return;
        }

        $orders = $siteSettings->get('next_items_order_for_itemsets');
        if (!$orders) {
            return;
        }

        // For performance, the check uses a single strpos.
        $specificOrder = null;
        $idString = ',' . $itemSetId . ',';
        foreach ($orders as $ids => $order) {
            if (strpos(',' . $ids . ',', $idString) !== false) {
                $specificOrder = $order;
                break;
            }
        }

        // Check the default order, if any.
        if (is_null($specificOrder)) {
            if (!isset($orders[0])) {
                return;
            }
            $specificOrder = $orders[0];
        }

        $query
            ->set('sort', trim($specificOrder['sort_by'] . ' ' . $specificOrder['sort_order']));
    }
}
