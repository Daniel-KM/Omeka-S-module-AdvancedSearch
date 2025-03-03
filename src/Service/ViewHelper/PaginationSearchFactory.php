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
        // Warning: The service Paginator is not a shared service: each instance
        // is a new one. Furthermore, the delegator SitePaginatorFactory is not
        // declared in the main config and only used in Omeka MvcListeners().

        // Here, the Paginator used in helper Pagination() is needed, not a new
        // one.

        /** @var \Omeka\View\Helper\Pagination $pagination */
        $pagination = $services->get('ViewHelperManager')->get('pagination');
        $paginator = $pagination->getPaginator();

        return new PaginationSearch(
            // $services->get('Omeka\Paginator')
            $paginator
        );
    }
}
