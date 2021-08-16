<?php declare(strict_types=1);
namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\FilterFieldset;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class FilterFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new FilterFieldset(null, $options);
    }
}
