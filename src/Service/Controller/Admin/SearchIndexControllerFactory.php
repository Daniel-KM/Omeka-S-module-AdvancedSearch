<?php declare(strict_types=1);
namespace Search\Service\Controller\Admin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Search\Controller\Admin\SearchIndexController;

class SearchIndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SearchIndexController(
            $services->get('Omeka\EntityManager'),
            $services->get('Search\AdapterManager')
        );
    }
}
