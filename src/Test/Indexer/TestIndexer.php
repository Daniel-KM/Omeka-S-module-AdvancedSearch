<?php declare(strict_types=1);

namespace Search\Test\Indexer;

use Omeka\Entity\Resource;
use Search\Indexer\AbstractIndexer;
use Search\Query;

class TestIndexer extends AbstractIndexer
{
    public function canIndex($resourceName)
    {
        return true;
    }

    public function clearIndex(Query $query = null): void
    {
    }

    public function indexResource(Resource $resource): void
    {
    }

    public function indexResources(array $resources): void
    {
    }

    public function deleteResource($resourceName, $id): void
    {
    }
}
