<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Stdlib;

use AdvancedSearch\Stdlib\SearchResources;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchResourcesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SearchResources(
            $services->get('Omeka\Connection'),
            $services->get('Common\EasyMeta')
        );
    }
}
