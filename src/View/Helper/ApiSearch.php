<?php declare(strict_types=1);
namespace Search\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Search\Mvc\Controller\Plugin\ApiSearch as ApiSearchPlugin;

class ApiSearch extends AbstractHelper
{
    /**
     * @var ApiSearchPlugin
     */
    protected $apiSearch;

    /**
     * @param ApiSearchPlugin $apiSearch
     */
    public function __construct(ApiSearchPlugin $apiSearch)
    {
        $this->apiSearch = $apiSearch;
    }

    /**
     * Execute a search API request via a querier if available, else the api.
     *
     * The arguments are the same than \Omeka\View\Helper\Api::search().
     * Some features of the Omeka api may not be available.
     *
     * @see \Omeka\Api\Manager::search()
     *
     * @param string $resource
     * @param array $data
     * @return \Omeka\Api\Response
     */
    public function __invoke($resource, array $data = [])
    {
        return $this->apiSearch->__invoke($resource, $data);
    }
}
