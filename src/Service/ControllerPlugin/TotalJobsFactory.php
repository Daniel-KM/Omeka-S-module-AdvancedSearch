<?php declare(strict_types=1);
namespace AdvancedSearch\Service\ControllerPlugin;

use AdvancedSearch\Mvc\Controller\Plugin\TotalJobs;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TotalJobsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new TotalJobs(
            $services->get('Omeka\EntityManager')
        );
    }
}
