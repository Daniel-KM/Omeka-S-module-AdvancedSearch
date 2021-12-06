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
            [$this, 'fixIsPublic'],
            500
        );

        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            [$this, 'redirectItemSetToSearch'],
            -10
        );
    }

    /**
     * Replace "&is_public=&" by "is_public = null".
     *
     * @link https://github.com/omeka/omeka-s/pull/1671
     * @deprecated Waiting for fix https://github.com/omeka/omeka-s/pull/1671 (included in Omeka v3.1).
     */
    public function fixIsPublic(MvcEvent $event): void
    {
        /** @var \Laminas\Stdlib\Parameters $query */
        $query = $event->getRequest()->getQuery();
        if ($query->get('is_public') === '') {
            $query->set('is_public', null);
        }
    }

    /**
     * Redirect item-set/show to the search page with item set set as url query,
     * when wanted.
     */
    public function redirectItemSetToSearch(MvcEvent $event): void
    {
        $routeMatch = $event->getRouteMatch();
        $matchedRouteName = $routeMatch->getMatchedRouteName();
        if ('site/item-set' !== $matchedRouteName) {
            return;
        }

        $services = $event->getApplication()->getServiceManager();
        $siteSettings = $services->get('Omeka\Settings\Site');

        if (!$siteSettings->get('advancedsearch_redirect_itemset')) {
            return;
        }

        $searchMainPage = $siteSettings->get('advancedsearch_main_config');
        if (empty($searchMainPage)) {
            return;
        }

        $siteSlug = $routeMatch->getParam('site-slug');
        $itemSetId = $routeMatch->getParam('item-set-id');

        $params = [
            '__NAMESPACE__' => 'AdvancedSearch\Controller',
            '__SITE__' => true,
            'controller' => \AdvancedSearch\Controller\IndexController::class,
            'action' => 'search',
            'site-slug' => $siteSlug,
            'id' => $searchMainPage,
            'item-set-id' => $itemSetId,
        ];
        $routeMatch = new RouteMatch($params);
        $routeMatch->setMatchedRouteName('search-page-' . $searchMainPage);
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
