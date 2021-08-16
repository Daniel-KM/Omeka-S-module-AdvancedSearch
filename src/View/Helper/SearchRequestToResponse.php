<?php declare(strict_types=1);
namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\SiteRepresentation;
use AdvancedSearch\Api\Representation\SearchPageRepresentation;
use AdvancedSearch\Mvc\Controller\Plugin\SearchRequestToResponse as SearchRequestToResponsePlugin;

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
     * @uses \Search\Mvc\Controller\Plugin\SearchRequestToResponse
     *
     * @param array $request Validated request.
     * @param SearchPageRepresentation $searchPage
     * @param SiteRepresentation $site
     * @return array Result with a status, data, and message if error.
     */
    public function __invoke(
        array $request,
        SearchPageRepresentation $searchPage,
        SiteRepresentation $site = null
    ) {
        $searchPlugin = $this->searchRequestToResponse;
        return $searchPlugin($request, $searchPage, $site);
    }
}
