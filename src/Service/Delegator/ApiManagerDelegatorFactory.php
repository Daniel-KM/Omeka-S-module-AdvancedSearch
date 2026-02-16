<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Delegator;

use AdvancedSearch\Api\ManagerDelegator as ApiManagerDelegator;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;

/**
 * Factory for the API Manager delegator (decorator pattern).
 *
 * This factory wraps the original Omeka API Manager with a decorator that
 * intercepts search requests with `index=true` and routes them through
 * the external search engine.
 */
class ApiManagerDelegatorFactory implements DelegatorFactoryInterface
{
    /**
     * Create the Api Manager decorator.
     *
     * @param ContainerInterface $container
     * @param string $name Service name
     * @param callable $callback Creates the original Manager instance
     * @param array|null $options
     * @return ApiManagerDelegator
     */
    public function __invoke(ContainerInterface $container, $name, callable $callback, array $options = null)
    {
        // Get the original Manager instance via the callback.
        $originalManager = $callback();

        // Wrap it with our decorator.
        $decorator = new ApiManagerDelegator($originalManager);

        // Inject dependencies.
        // Note: controllerPlugins cannot be resolved immediately to avoid
        // circular dependency during service manager initialization.
        $decorator->setControllerPlugins($container->get('ControllerPluginManager'));

        return $decorator;
    }
}
