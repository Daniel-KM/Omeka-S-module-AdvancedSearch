<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form\Element;

use AdvancedSearch\Form\Element\FieldSelect;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class FieldSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new FieldSelect(null, $options ?? []);
        $element
            ->setEventManager($services->get('EventManager'));
        return $element
            ->setApiManager($services->get('Omeka\ApiManager'))
            ->setTranslator($services->get('MvcTranslator'))
        ;
    }
}
