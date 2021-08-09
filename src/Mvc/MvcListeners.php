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

        if (!$siteSettings->get('search_redirect_itemset')) {
            return;
        }

        $searchMainPage = $siteSettings->get('search_main_page');
        if (empty($searchMainPage)) {
            return;
        }

        $siteSlug = $routeMatch->getParam('site-slug');
        $itemSetId = $routeMatch->getParam('item-set-id');

        $params = [
            '__NAMESPACE__' => 'Search\Controller',
            '__SITE__' => true,
            'controller' => \Search\Controller\IndexController::class,
            'action' => 'search',
            'site-slug' => $siteSlug,
            'id' => $searchMainPage,
            'item-set-id' => $itemSetId,
        ];
        $routeMatch = new RouteMatch($params);
        $routeMatch->setMatchedRouteName('search-page-' . $searchMainPage);
        $event->setRouteMatch($routeMatch);

        /* @var \Laminas\Stdlib\Parameters $query */
        $event->getRequest()->getQuery()
            ->set('itemSet', ['ids' => [$itemSetId]]);
    }
}
