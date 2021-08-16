<?php declare(strict_types=1);
namespace AdvancedSearch\Service\Controller\Admin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use AdvancedSearch\Controller\Admin\SearchPageController;

class SearchPageControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SearchPageController(
            $services->get('Omeka\EntityManager'),
            $services->get('Search\AdapterManager'),
            $services->get('Search\FormAdapterManager')
        );
    }
}
