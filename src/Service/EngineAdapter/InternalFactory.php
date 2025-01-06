<?php declare(strict_types=1);

namespace AdvancedSearch\Service\EngineAdapter;

use AdvancedSearch\EngineAdapter\Internal;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class InternalFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Internal(
            $services->get('Common\EasyMeta'),
            $services->get('MvcTranslator')
        );
    }
}
