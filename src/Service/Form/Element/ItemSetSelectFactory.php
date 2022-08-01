<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form\Element;

use AdvancedSearch\Form\Element\ItemSetSelect;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemSetSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new ItemSetSelect;
        $element->setApiManager($services->get('Omeka\ApiManager'));
        return $element;
    }
}
