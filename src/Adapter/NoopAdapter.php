<?php declare(strict_types=1);

namespace AdvancedSearch\Adapter;

/**
 * This adapter is not registered in manager, so only for internal purpose.
 */
class NoopAdapter extends AbstractAdapter
{
    public function getLabel(): string
    {
        return 'Noop'; // @translate
    }

    public function getConfigFieldset(): ?\Laminas\Form\Fieldset
    {
        return null;
    }

    public function getIndexerClass(): string
    {
        return \AdvancedSearch\Indexer\NoopIndexer::class;
    }

    public function getQuerierClass(): string
    {
        return \AdvancedSearch\Querier\NoopQuerier::class;
    }
}
