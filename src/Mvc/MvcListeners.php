<?php declare(strict_types=1);

namespace AdvancedSearchPlus\Mvc;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;

/**
 * @deprecated Waiting for fix https://github.com/omeka/omeka-s/pull/1671.
 * The same in module Search.
 */
class MvcListeners extends AbstractListenerAggregate
{
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            [$this, 'fixIsPublic'],
            500
        );
    }

    /**
     * Replace "&is_public=&" by "is_public = null".
     *
     * @link https://github.com/omeka/omeka-s/pull/1671
     *
     * @param MvcEvent $event
     */
    public function fixIsPublic(MvcEvent $event): void
    {
        /** @var \Laminas\Stdlib\Parameters $query */
        $query = $event->getRequest()->getQuery();
        if ($query->get('is_public') === '') {
            $query->set('is_public', null);
        }
    }
}
