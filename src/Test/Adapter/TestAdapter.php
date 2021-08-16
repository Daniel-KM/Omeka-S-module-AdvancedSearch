<?php declare(strict_types=1);

namespace AdvancedSearch\Test\Adapter;

use Laminas\Form\Fieldset;
use AdvancedSearch\Adapter\AbstractAdapter;

class TestAdapter extends AbstractAdapter
{
    public function getLabel()
    {
        return 'TestAdapter';
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
