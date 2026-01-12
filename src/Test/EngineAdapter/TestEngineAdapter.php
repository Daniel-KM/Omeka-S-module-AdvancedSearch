<?php declare(strict_types=1);

namespace AdvancedSearch\Test\EngineAdapter;

use AdvancedSearch\EngineAdapter\AbstractEngineAdapter;
use Laminas\Form\Fieldset;

class TestEngineAdapter extends AbstractEngineAdapter
{
    public function getLabel(): string
    {
        return 'TestEngineAdapter';
    }

    public function getConfigFieldset(): ?Fieldset
    {
        return new Fieldset;
    }

    public function getIndexerClass(): string
    {
        return \AdvancedSearch\Test\Indexer\TestIndexer::class;
    }

    public function getQuerierClass(): string
    {
        return \AdvancedSearch\Test\Querier\TestQuerier::class;
    }
}
