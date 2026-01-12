<?php declare(strict_types=1);

namespace AdvancedSearch\Test\Querier;

use AdvancedSearch\Querier\AbstractQuerier;
use AdvancedSearch\Query;
use AdvancedSearch\Response;

class TestQuerier extends AbstractQuerier
{
    public function query(): Response
    {
        $response = new Response;

        $response->setTotalResults(0);
        foreach ($this->query->getResourceTypes() as $resourceType) {
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
    public function queryValues(string $field): array
    {
        return [];
    }

    public function queryAllResourceIds(?string $resourceType = null, bool $byResourceType = false): array
    {
        return [];
    }
}
