<?php declare(strict_types=1);

namespace AdvancedSearch\Service\ViewHelper;

use AdvancedSearch\View\Helper\SearchFilters;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory for SearchFilters view helper.
 */
class SearchFiltersFactory implements FactoryInterface
{
    /**
     * Create and return the SearchFilters view helper.
     *
     * @return SearchFilters
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SearchFilters(
            $services->get('Omeka\ApiAdapterManager')->get('resources'),
            $services->get('ControllerPluginManager')->get('searchResourcesQueryBuilder')
        );
    }
}
