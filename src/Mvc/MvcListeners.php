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
            [$this, 'redirectItemSet'],
            // More prioritary than Block Plus.
            -10
        );
    }

    /**
     * Redirect item-set/show to the search page with item set set as url query,
     * when wanted.
     *
     * Furthermore, the items are reordered according to the option of module blockplus.
     *
     * Adapted:
     * @see \AdvancedSearch\Mvc\MvcListeners::redirectItemSet()
     * @see \BlockPlus\Mvc\MvcListeners::handleItemSetShow()
     * @see \Selection\Mvc\MvcListeners::redirectSelection()
     */
    public function redirectItemSet(MvcEvent $event): void
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\Settings\SiteSettings $siteSettings
         */

        $routeMatch = $event->getRouteMatch();
        $matchedRouteName = $routeMatch->getMatchedRouteName();

        if ($matchedRouteName === 'site/resource') {
            if ($routeMatch->getParam('action') !== 'browse'
                // The name of the controller may be in __CONTROLLER__ or
                // controller and can change randomly.
                || !in_array($routeMatch->getParam('controller') ?: $routeMatch->getParam('__CONTROLLER__'), ['Omeka\Controller\Site\ItemSet', 'item-set'])
            ) {
                return;
            }

            // Browse item sets.

            $services = $event->getApplication()->getServiceManager();
            $api = $services->get('Omeka\ApiManager');
            $siteSettings = $services->get('Omeka\Settings\Site');

            $siteSlug = $routeMatch->getParam('site-slug');

            // Browse item sets is redirected to a page.

            $redirectTo = $siteSettings->get('advancedsearch_item_sets_browse_page');
            if ($redirectTo) {
                if (mb_substr($redirectTo, 0, 1) === '/'
                    || mb_substr($redirectTo, 0, 8) === 'https://'
                    || mb_substr($redirectTo, 0, 7) === 'http://'
                ) {
                    /** @see \Laminas\Mvc\Controller\Plugin\Redirect::toUrl() */
                    /* // TODO Use event response in order to get statistics.
                    $event->setResponse(new \Laminas\Http\Response);
                    $event->getResponse()
                        ->setStatusCode(302)
                        ->getHeaders()->addHeaderLine('Location', $redirectTo);
                    return;
                     */
                    if (!headers_sent()) {
                        $serverUrl = new \Laminas\View\Helper\ServerUrl();
                        header('Referer: ' . $serverUrl(true));
                        header('Location: ' . $redirectTo, true, 302);
                    } else {
                        echo '<script>window.location.href="' . $redirectTo . '";</script>';
                        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $redirectTo . '"></noscript>';
                    }
                    die();
                }

                // This is a page slug. Check for its presence and visibility.
                try {
                    $site = $api->read('sites', ['slug' => $siteSlug], [], ['responseContent' => 'resource', 'initialize' => false, 'finalize' => false])->getContent();
                    $api->read('site_pages', ['site' => $site->getId(), 'slug' => $redirectTo], [], ['responseContent' => 'resource', 'initialize' => false, 'finalize' => false]);
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
                    'page-slug' => $redirectTo,
                    'redirected' => 'item-sets',
                ];
                $routeMatch = new RouteMatch($params);
                $routeMatch->setMatchedRouteName('site/page');
                $event->setRouteMatch($routeMatch);
                return;
            }

            // Browse item sets is redirected to a search.

            $redirectTo = (int) $siteSettings->get('advancedsearch_item_sets_browse_config');
            if ($redirectTo) {
                // The search config may have been removed, so check and get the slug.
                // It should be cached by doctrine.
                $searchConfigId = &$redirectTo;
                try {
                    $searchConfig = $api->read('search_configs', ['id' => $searchConfigId], [], ['responseContent' => 'resource'])->getContent();
                    $searchConfigSlug = $searchConfig->getSlug();
                } catch (\Exception $e) {
                    return;
                }

                $params = [
                    '__NAMESPACE__' => 'AdvancedSearch\Controller',
                    '__SITE__' => true,
                    'controller' => \AdvancedSearch\Controller\SearchController::class,
                    'action' => 'search',
                    'site-slug' => $siteSlug,
                    'id' => $searchConfigId,
                    'page-slug' => $searchConfigSlug,
                    'search-slug' => $searchConfigSlug,
                    'redirected' => 'item-sets',
                ];
                $routeMatch = new RouteMatch($params);
                $routeMatch->setMatchedRouteName('search-page-' . $searchConfigSlug);
                $event->setRouteMatch($routeMatch);

                /** @var \Laminas\Stdlib\Parameters $query */
                $query = $event->getRequest()->getQuery();
                $query
                    ->set('resource_type', 'item_sets');
            }
            return;
        }

        if ($matchedRouteName !== 'site/item-set') {
            return;
        }

        // Browse items for a single item set.

        $services = $event->getApplication()->getServiceManager();
        $api = $services->get('Omeka\ApiManager');
        $siteSettings = $services->get('Omeka\Settings\Site');

        $siteSlug = $routeMatch->getParam('site-slug');
        $itemSetId = $routeMatch->getParam('item-set-id');

        // The other options are managed in templates search/search and
        // search/results-header-footer.
        $redirectItemSets = $siteSettings->get('advancedsearch_item_sets_redirects', ['default' => 'browse']);
        $redirectTo = ($redirectItemSets[$itemSetId] ?? $redirectItemSets['default'] ?? 'browse') ?: 'browse';

        if ($redirectTo === 'browse') {
            // Browse items for a a single item set in the standard way.
            return;
        } elseif ($redirectTo !== 'search' && $redirectTo !== 'first') {
            // Browse items for a a single item set via a page.
            if (mb_substr($redirectTo, 0, 1) === '/'
                || mb_substr($redirectTo, 0, 8) === 'https://'
                || mb_substr($redirectTo, 0, 7) === 'http://'
            ) {
                /** @see \Laminas\Mvc\Controller\Plugin\Redirect::toUrl() */
                /* // TODO Use event response in order to get statistics.
                $event->setResponse(new \Laminas\Http\Response);
                $event->getResponse()
                    ->setStatusCode(302)
                    ->getHeaders()->addHeaderLine('Location', $redirectTo);
                return;
                */
                if (!headers_sent()) {
                    $serverUrl = new \Laminas\View\Helper\ServerUrl();
                    header('Referer: ' . $serverUrl(true));
                    header('Location: ' . $redirectTo, true, 302);
                } else {
                    echo '<script>window.location.href="' . $redirectTo . '";</script>';
                    echo '<noscript><meta http-equiv="refresh" content="0;url=' . $redirectTo . '"></noscript>';
                }
                die();
            }

            // This is a page slug. Check for its presence and visibility.

            try {
                $site = $api->read('sites', ['slug' => $siteSlug], [], ['responseContent' => 'resource', 'initialize' => false, 'finalize' => false])->getContent();
                $api->read('site_pages', ['site' => $site->getId(), 'slug' => $redirectTo], [], ['responseContent' => 'resource', 'initialize' => false, 'finalize' => false]);
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
                'page-slug' => $redirectTo,
                'redirected' => 'item-set',
                'item-set-id' => $itemSetId,
            ];
            $routeMatch = new RouteMatch($params);
            $routeMatch->setMatchedRouteName('site/page');
            $event->setRouteMatch($routeMatch);
            return;
        }

        // Browse items for a a single item set via a search.
        $searchConfigId = $siteSettings->get('advancedsearch_item_sets_config')
            ?: $siteSettings->get('advancedsearch_main_config');

        if (empty($searchConfigId)) {
            return;
        }

        // The search config may have been removed, so check and get the slug.
        // It should be cached by doctrine.
        // Or use setting "'advancedsearch_all_configs".

        try {
            $searchConfig = $api->read('search_configs', ['id' => $searchConfigId], [], ['responseContent' => 'resource'])->getContent();
            $searchConfigSlug = $searchConfig->getSlug();
        } catch (\Exception $e) {
            return;
        }

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
            'redirected' => 'item-set',
        ];
        $routeMatch = new RouteMatch($params);
        $routeMatch->setMatchedRouteName('search-page-' . $searchConfigSlug);
        $event->setRouteMatch($routeMatch);

        /** @var \Laminas\Stdlib\Parameters $query */
        $query = $event->getRequest()->getQuery();
        $query
            ->set('item_set_id', $itemSetId);

        // Don't process if an order is set.
        // Check for module Advanced Search and Block Plus.
        if (!empty($query['sort_by'])
            || !empty($query['sort'])
        ) {
            return;
        }

        $orders = $siteSettings->get('blockplus_items_order_for_itemsets');
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
        if ($specificOrder === null) {
            if (!isset($orders[0])) {
                return;
            }
            $specificOrder = $orders[0];
        }

        $query
            ->set('sort', trim($specificOrder['sort_by'] . ' ' . $specificOrder['sort_order']));
    }
}
