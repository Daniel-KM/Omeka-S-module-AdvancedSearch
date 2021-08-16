<?php declare(strict_types=1);
namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Mvc\Controller\Plugin\AdvancedSearchRequestToResponse as SearchRequestToResponsePlugin;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\SiteRepresentation;

class SearchRequestToResponse extends AbstractHelper
{
    /**
     * @var SearchRequestToResponsePlugin
     */
    protected $searchRequestToResponse;

    /**
     * @param SearchRequestToResponsePlugin $references
     */
    public function __construct(SearchRequestToResponsePlugin $searchRequestToResponse)
    {
        $this->searchRequestToResponse = $searchRequestToResponse;
    }

    /**
     * Get response from a search request.
     *
     * @uses \AdvancedSearch\Mvc\Controller\Plugin\AdvancedSearchRequestToResponse
     *
     * @param array $request Validated request.
     * @param SearchConfigRepresentation $searchConfig
     * @param SiteRepresentation $site
     * @return array Result with a status, data, and message if error.
     */
    public function __invoke(
        array $request,
        SearchConfigRepresentation $searchConfig,
        SiteRepresentation $site = null
    ) {
        $searchPlugin = $this->searchRequestToResponse;
        return $searchPlugin($request, $searchConfig, $site);
    }
}
