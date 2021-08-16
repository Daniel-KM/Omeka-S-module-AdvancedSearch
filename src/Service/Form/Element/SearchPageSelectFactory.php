<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form\Element;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use AdvancedSearch\Form\Element\SearchPageSelect;

class SearchPageSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $apiManager = $services->get('Omeka\ApiManager');

        $element = new SearchPageSelect;
        $element->setApiManager($apiManager);

        return $element;
    }
}
