<?php declare(strict_types=1);
namespace AdvancedSearch\Service\Controller\Admin;

use AdvancedSearch\Controller\Admin\SearchConfigController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchConfigControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SearchConfigController(
            $services->get('Omeka\EntityManager'),
            $services->get('Search\AdapterManager'),
            $services->get('Search\FormAdapterManager')
        );
    }
}
