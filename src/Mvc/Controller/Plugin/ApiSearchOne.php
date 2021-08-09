<?php declare(strict_types=1);
namespace Search\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Search\Mvc\Controller\Plugin\ApiSearch as ApiSearchPlugin;

class ApiSearchOne extends AbstractPlugin
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
     * The arguments are the same than \Omeka\Mvc\Controller\Plugin\Api::searchOne().
     * Some features of the Omeka api may not be available.
     *
     * @see \Omeka\Api\Manager::search()
     *
     * @param string $resource
     * @param array $data
     * @param array $options
     * @return \Omeka\Api\Response
     */
    public function __invoke($resource, array $data = [], array $options = [])
    {
        $data['limit'] = 1;
        $apiSearch = $this->apiSearch;
        $response = $apiSearch($resource, $data, $options);
        $content = $response->getContent();
        $content = is_array($content) && count($content) ? $content[0] : null;
        $response->setContent($content);
        return $response;
    }
}
