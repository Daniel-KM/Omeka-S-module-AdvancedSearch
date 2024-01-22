<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Delegator;

use AdvancedSearch\Stdlib\FulltextSearchDelegator;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;

class FulltextSearchDelegatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, callable $callback, array $options = null)
    {
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        return new FulltextSearchDelegator(
            $callback(),
            $services->get('Omeka\Connection'),
            $basePath
        );
    }
}
