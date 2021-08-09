<?php declare(strict_types=1);

namespace AdvancedSearchPlus\Service;

use AdvancedSearchPlus\Api\ManagerDelegator;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;

class ApiManagerDelegatorFactory implements DelegatorFactoryInterface
{
    /**
     * Create the Api Manager service.
     *
     * @return ManagerDelegator
     */
    public function __invoke(ContainerInterface $services, $name, callable $callback, array $options = null)
    {
        $adapterManager = $services->get('Omeka\ApiAdapterManager');
        $acl = $services->get('Omeka\Acl');
        $logger = $services->get('Omeka\Logger');
        $translator = $services->get('MvcTranslator');

        return new ManagerDelegator($adapterManager, $acl, $logger, $translator);
    }
}
