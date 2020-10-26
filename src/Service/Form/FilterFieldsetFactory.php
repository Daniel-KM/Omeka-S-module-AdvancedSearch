<?php
namespace Search\Service\Form;

use Interop\Container\ContainerInterface;
use Search\Form\FilterFieldset;
use Laminas\ServiceManager\Factory\FactoryInterface;

class FilterFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new FilterFieldset(null, $options);
    }
}
