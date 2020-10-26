<?php
namespace Search\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Search\View\Helper\ApiSearchOne;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ApiSearchOneFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ApiSearchOne(
            $services->get('ControllerPluginManager')->get('apiSearch')
        );
    }
}
