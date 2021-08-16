<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau, 2018-2021
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

interface AdapterInterface
{
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator): AdapterInterface;

    /**
     * Get the name of the adapter.
     */
    public function getLabel(): string;

    /**
     * Return the form used to managed the config of the adapter, if any.
     */
    public function getConfigFieldset(): ?\Laminas\Form\Fieldset;

    /**
     * Get the fully qualified name of the indexer class used by this adapter.
     */
    public function getIndexerClass(): string;

    /**
     * Get the fully qualified name of the querier class used by this adapter.
     */
    public function getQuerierClass(): string;

    /**
     * Get the available fields.
     *
     * The available fields are used for filters.
     *
     * @param SearchEngineRepresentation $engine
     * @return array Associative array with field name as key and an array with
     * field name and field label as value.
     */
    public function getAvailableFields(SearchEngineRepresentation $engine): array;

    /**
     * Get the available sort fields.
     *
     * @param SearchEngineRepresentation $engine
     * @return array Associative array with sort name as key and an array with
     * sort name and sort label as value.
     */
    public function getAvailableSortFields(SearchEngineRepresentation $engine): array;

    /**
     * Get the available facet fields.
     *
     * @param SearchEngineRepresentation $engine
     * @return array Associative array with facet name as key and an array with
     * facet name and facet label as value.
     */
    public function getAvailableFacetFields(SearchEngineRepresentation $engine): array;
}
