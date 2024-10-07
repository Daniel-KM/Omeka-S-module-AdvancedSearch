<?php declare(strict_types=1);

namespace AdvancedSearch\Test\Querier;

use AdvancedSearch\Querier\AbstractQuerier;
use AdvancedSearch\Query;
use AdvancedSearch\Response;

class TestQuerier extends AbstractQuerier
{
    public function query(Query $query)
    {
        $response = new Response;

        $response->setTotalResults(0);
        foreach ($query->getResourceTypes() as $resourceType) {
            $response->setResourceTotalResults($resourceType, 0);
        }

        return $response;
    }

    public function querySuggestions(): Response
    {
        return new Response();
    }

    public function getPreparedQuery()
    {
        return [];
    }
}
