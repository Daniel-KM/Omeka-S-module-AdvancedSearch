<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form\Element;

use AdvancedSearch\Form\Element\FieldSelect;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class FieldSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $helpers = $services->get('ViewHelperManager');
        $getSearchConfig = $helpers->get('getSearchConfig');
        $searchConfig = $getSearchConfig();

        // TODO Add aliases and query args in all configs.
        $searchIndex = ['aliases' => [], 'query_args' => []];
        if ($searchConfig) {
            $searchIndex = $searchConfig->setting('index', []) + $searchIndex;
        }

        $element = new FieldSelect(null, $options ?? []);
        $element
            ->setEventManager($services->get('EventManager'));

        return $element
            ->setApiManager($services->get('Omeka\ApiManager'))
            ->setSearchIndex($searchIndex)
            ->setTranslator($services->get('MvcTranslator'))
        ;
    }
}
