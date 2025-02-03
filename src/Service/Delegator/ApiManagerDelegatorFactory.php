<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Delegator;

use AdvancedSearch\Api\ManagerDelegator as ApiManagerDelegator;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;

class ApiManagerDelegatorFactory implements DelegatorFactoryInterface
{
    /**
     * Create the Api Manager service (delegator).
     */
    public function __invoke(ContainerInterface $serviceLocator, $name, callable $callback, array $options = null)
    {
        $adapterManager = $serviceLocator->get('Omeka\ApiAdapterManager');
        $acl = $serviceLocator->get('Omeka\Acl');
        $logger = $serviceLocator->get('Omeka\Logger');
        $translator = $serviceLocator->get('MvcTranslator');

        // TODO Include the callback ApiManager inside constructor?
        $manager = new ApiManagerDelegator($adapterManager, $acl, $logger, $translator);
        // The plugin apiSearch cannot be set directly to avoid a loop during
        // the initialization.
        // $apiSearch = $serviceLocator->get('ControllerPluginManager')->get('apiSearch');
        // $manager->setApiSearch($apiSearch);
        $controllerPlugins = $serviceLocator->get('ControllerPluginManager');

        return $manager
            ->setControllerPlugins($controllerPlugins)
            ->setSearchResources($serviceLocator->get('AdvancedSearch\SearchResources'))
        ;
    }
}
