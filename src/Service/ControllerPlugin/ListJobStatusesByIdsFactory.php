<?php declare(strict_types=1);

namespace AdvancedSearch\Service\ControllerPlugin;

use AdvancedSearch\Mvc\Controller\Plugin\ListJobStatusesByIds;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ListJobStatusesByIdsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ListJobStatusesByIds(
            $services->get('Omeka\EntityManager')
        );
    }
}
