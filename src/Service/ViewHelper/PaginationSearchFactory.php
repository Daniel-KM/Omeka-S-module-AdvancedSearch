<?php declare(strict_types=1);

namespace AdvancedSearch\Service\ViewHelper;

use AdvancedSearch\View\Helper\PaginationSearch;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory for the pagination view helper via advanced search.
 */
class PaginationSearchFactory implements FactoryInterface
{
    /**
     * Create and return the pagination view helper via advanced search.
     *
     * @return \AdvancedSearch\View\Helper\PaginationSearch
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new PaginationSearch(
            $services->get('Omeka\Paginator')
        );
    }
}
