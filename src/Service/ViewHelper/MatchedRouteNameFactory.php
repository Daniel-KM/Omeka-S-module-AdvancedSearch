<?php declare(strict_types=1);

namespace AdvancedSearch\Service\ViewHelper;

use AdvancedSearch\View\Helper\MatchedRouteName;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MatchedRouteNameFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MatchedRouteName(
            $services->get('Application')->getMvcEvent()->getRouteMatch()->getMatchedRouteName()
        );
    }
}
