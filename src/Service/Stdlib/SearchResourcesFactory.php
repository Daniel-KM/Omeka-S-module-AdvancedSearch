<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Stdlib;

use AdvancedSearch\Stdlib\SearchResources;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchResourcesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        // TODO For aliases and query args to use with standard search: use a separate config for internal search? Just an option to select the good one? Move the options from main settings?
        // For now, use the main search of the site or admin.

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $helpers = $services->get('ViewHelperManager');
        $getSearchConfig = $helpers->get('getSearchConfig');
        $searchConfig = $getSearchConfig();

        // TODO Add aliases and query args in all configs.
        $searchIndex = ['aliases' => [], 'query_args' => []];
        if ($searchConfig) {
            $searchIndex = $searchConfig->setting('index', []) + $searchIndex;
        }

        return new SearchResources(
            $services->get('Omeka\Connection'),
            $services->get('Common\EasyMeta'),
            $searchIndex
        );
    }
}
