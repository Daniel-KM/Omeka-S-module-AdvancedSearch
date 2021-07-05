<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2017-2021
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Search\Querier;

use Laminas\Log\LoggerAwareTrait;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Search\Api\Representation\SearchIndexRepresentation;
use Search\Query;
use Search\Response;

abstract class AbstractQuerier implements QuerierInterface
{
    use LoggerAwareTrait;

    /**
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * @var SearchIndexRepresentation $index
     */
    protected $index;

    /**
     * @var Query
     */
    protected $query;

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator): QuerierInterface
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    protected function getServiceLocator(): ServiceLocatorInterface
    {
        return $this->serviceLocator;
    }

    public function setSearchIndex(SearchIndexRepresentation $index): QuerierInterface
    {
        $this->index = $index;
        return $this;
    }

    public function setQuery(Query $query): QuerierInterface
    {
        $this->query = $query;
        return $this;
    }

    abstract public function query(): Response;

    abstract public function querySuggestions(): Response;

    abstract public function getPreparedQuery();

    /**
     * Get a setting of the search index.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function getSetting(string $name, $default = null)
    {
        $settings = $this->index->settings();
        return $settings[$name] ?? $default;
    }

    /**
     * Get a setting of the search adapter.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function getAdapterSetting(string $name, $default = null)
    {
        $adapterSettings = $this->getSetting('adapter', []);
        return $adapterSettings[$name] ?? $default;
    }
}
