<?php declare(strict_types=1);
namespace AdvancedSearch\Service\FormAdapter;

use AdvancedSearch\FormAdapter\ApiFormAdapter;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ApiFormAdapterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ApiFormAdapter($services->get('Omeka\Connection'));
    }
}
