<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau, 2018-2023
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

namespace AdvancedSearch\Adapter;

use AdvancedSearch\Api\Representation\SearchEngineRepresentation;
use Laminas\ServiceManager\ServiceLocatorInterface;

abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * @var \Laminas\ServiceManager\ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation
     */
    protected $searchEngine;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $configFieldsetClass = null;

    /**
     * @var string
     */
    protected $indexerClass = null;

    /**
     * @var string
     */
    protected $querierClass = null;

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator): AdapterInterface
    {
        $this->services = $serviceLocator;
        return $this;
    }

    public function setSearchEngine(SearchEngineRepresentation $searchEngine): AdapterInterface
    {
        $this->searchEngine = $searchEngine;
        return $this;
    }

    public function getSearchEngine(): ?SearchEngineRepresentation
    {
        return $this->searchEngine;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getConfigFieldset(): ?\Laminas\Form\Fieldset
    {
        return $this->configFieldsetClass ? new $this->configFieldsetClass : null;
    }

    public function getIndexerClass(): string
    {
        return $this->indexerClass;
    }

    public function getQuerierClass(): string
    {
        return $this->querierClass;
    }

    public function getAvailableFields(): array
    {
        return [];
    }

    public function getAvailableFacetFields(): array
    {
        return [];
    }

    public function getAvailableSortFields(): array
    {
        return [];
    }

    public function getAvailableFieldsForSelect(): array
    {
        $fields = $this->getAvailableFields();
        // Manage the case when there is no label.
        return array_replace(
            array_column($fields, 'name', 'name'),
            array_filter(array_column($fields, 'label', 'name'))
        );
    }

    protected function getServiceLocator(): ServiceLocatorInterface
    {
        return $this->services;
    }
}
