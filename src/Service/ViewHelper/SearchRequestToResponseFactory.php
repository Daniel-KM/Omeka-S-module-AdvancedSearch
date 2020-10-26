<?php declare(strict_types=1);
namespace Search\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Search\View\Helper\SearchRequestToResponse;

class SearchRequestToResponseFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SearchRequestToResponse(
            $services->get('ControllerPluginManager')->get('searchRequestToResponse')
        );
    }
}
