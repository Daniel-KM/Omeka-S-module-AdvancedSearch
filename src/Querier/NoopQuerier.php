<?php

namespace Search\Querier;

use Search\Api\Representation\SearchIndexRepresentation;
use Search\Query;
use Search\Response;
use Laminas\Log\LoggerAwareTrait;
use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * Querier that doesn't answer anything.
 *
 * It is used to avoid a crash when a module dependency is missing.
 */
class NoopQuerier implements QuerierInterface
{
    use LoggerAwareTrait;

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        return $this;
    }

    public function setSearchIndex(SearchIndexRepresentation $index)
    {
        return $this;
    }

    public function setQuery(Query $query)
    {
        return $this;
    }

    public function query()
    {
        return new Response;
    }

    public function getPreparedQuery()
    {
        return null;
    }
}
