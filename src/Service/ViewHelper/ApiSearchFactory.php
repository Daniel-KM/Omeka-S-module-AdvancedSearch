<?php declare(strict_types=1);
namespace AdvancedSearch\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use AdvancedSearch\View\Helper\ApiSearch;

class ApiSearchFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ApiSearch(
            $services->get('ControllerPluginManager')->get('apiSearch')
        );
    }
}
