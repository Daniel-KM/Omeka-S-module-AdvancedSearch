<?php declare(strict_types=1);
namespace Search\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Search\Mvc\Controller\Plugin\TotalJobs;

class TotalJobsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new TotalJobs(
            $services->get('Omeka\EntityManager')
        );
    }
}
