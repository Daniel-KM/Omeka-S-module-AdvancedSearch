<?php declare(strict_types=1);
namespace AdvancedSearch\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchRequestToResponseFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SearchRequestToResponse(
            $services->get('ControllerPluginManager')->get('searchRequestToResponse')
        );
    }
}
