<?php declare(strict_types=1);
namespace Search\Service\FormAdapter;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Search\FormAdapter\ApiFormAdapter;

class ApiFormAdapterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ApiFormAdapter($services->get('Omeka\Connection'));
    }
}
