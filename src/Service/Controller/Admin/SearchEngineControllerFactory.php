<?php declare(strict_types=1);
namespace AdvancedSearch\Service\Controller\Admin;

use AdvancedSearch\Controller\Admin\SearchEngineController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchEngineControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SearchEngineController(
            $services->get('Omeka\EntityManager'),
            $services->get('Search\AdapterManager')
        );
    }
}
