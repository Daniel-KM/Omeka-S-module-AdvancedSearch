<?php declare(strict_types=1);

namespace AdvancedSearch\Test\EngineAdapter;

use AdvancedSearch\EngineAdapter\AbstractEngineAdapter;
use Laminas\Form\Fieldset;

class TestEnginerAdapter extends AbstractEngineAdapter
{
    public function getLabel()
    {
        return 'TestEngineAdapter';
    }

    public function getConfigFieldset()
    {
        return new Fieldset;
    }

    public function getIndexerClass()
    {
        return \AdvancedSearch\Test\Indexer\TestIndexer::class;
    }

    public function getQuerierClass()
    {
        return \AdvancedSearch\Test\Querier\TestQuerier::class;
    }
}
