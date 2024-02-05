<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Delegator;

use AdvancedSearch\Stdlib\FulltextSearchDelegator;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;

class FulltextSearchDelegatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, callable $callback, array $options = null)
    {
        // Skip delegator if not enabled.
        $settings = $services->get('Omeka\Settings');
        $fulltextSearch = (bool) $settings->get('advancedsearch_fulltextsearch_alto');
        if (!$fulltextSearch) {
            return $callback();
        }

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        return new FulltextSearchDelegator(
            $callback(),
            $services->get('Omeka\Connection'),
            // For compatibility with Omeka S < v4.1.
            $services->get('Omeka\EntityManager'),
            $basePath
        );
    }
}
