<?php declare(strict_types=1);

namespace Search\Test\Adapter;

use Laminas\Form\Fieldset;
use Search\Adapter\AbstractAdapter;

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
        return \Search\Test\Indexer\TestIndexer::class;
    }

    public function getQuerierClass()
    {
        return \Search\Test\Querier\TestQuerier::class;
    }
}
