<?php declare(strict_types=1);
namespace AdvancedSearch\Service\Adapter;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use AdvancedSearch\Adapter\InternalAdapter;

class InternalAdapterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $adapter = new InternalAdapter();
        $adapter->setServiceLocator($services);
        return $adapter;
    }
}
