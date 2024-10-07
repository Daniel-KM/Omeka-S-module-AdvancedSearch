<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\SearchFilter\Advanced;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchFilterAdvancedFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return (new Advanced(null, $options ?? []))
            ->setTranslator($services->get('MvcTranslator'))
        ;
    }
}
