<?php
namespace Search\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Search\View\Helper\SearchRequestToResponse;
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
