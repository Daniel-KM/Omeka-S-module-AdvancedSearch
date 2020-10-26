<?php
namespace Search\Service\FormAdapter;

use Interop\Container\ContainerInterface;
use Search\FormAdapter\ApiFormAdapter;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ApiFormAdapterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ApiFormAdapter($services->get('Omeka\Connection'));
    }
}
