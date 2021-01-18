<?php declare(strict_types=1);
namespace Search\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use Search\Api\ManagerDelegator;

class ApiManagerDelegatorFactory implements DelegatorFactoryInterface
{
    /**
     * Create the Api Manager service (delegator).
     *
     * @return ManagerDelegator
     */
    public function __invoke(ContainerInterface $serviceLocator, $name, callable $callback, array $options = null)
    {
        $adapterManager = $serviceLocator->get('Omeka\ApiAdapterManager');
        $acl = $serviceLocator->get('Omeka\Acl');
        $logger = $serviceLocator->get('Omeka\Logger');
        $translator = $serviceLocator->get('MvcTranslator');
        $manager = new ManagerDelegator($adapterManager, $acl, $logger, $translator);
        // The plugin apiSearch cannot be set directly to avoid a loop during
        // the initialization.
        // $apiSearch = $serviceLocator->get('ControllerPluginManager')->get('apiSearch');
        // $manager->setApiSearch($apiSearch);
        $controllerPlugins = $serviceLocator->get('ControllerPluginManager');
        return $manager
            ->setControllerPlugins($controllerPlugins);
    }
}
