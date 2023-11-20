<?php declare(strict_types=1);

namespace AdvancedSearch\Adapter;

/**
 * This adapter is not registered in manager, so only for internal purpose.
 */
class NoopAdapter extends AbstractAdapter
{
    protected $label = 'No operation (noop)'; // @translate

    protected $configFieldsetClass = null;

    protected $indexerClass = \AdvancedSearch\Indexer\NoopIndexer::class;

    protected $querierClass = \AdvancedSearch\Querier\NoopQuerier::class;
}
