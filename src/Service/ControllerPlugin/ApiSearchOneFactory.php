<?php declare(strict_types=1);
namespace AdvancedSearch\Service\ControllerPlugin;

use AdvancedSearch\Mvc\Controller\Plugin\ApiSearchOne;
use Interop\Container\ContainerInterface;
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
