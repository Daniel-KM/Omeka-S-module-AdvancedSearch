<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2020-2024
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

namespace AdvancedSearch\Api\Representation;

use AdvancedSearch\EngineAdapter\EngineAdapterInterface;
use AdvancedSearch\Indexer\NoopIndexer;
use AdvancedSearch\Querier\NoopQuerier;
use Omeka\Api\Representation\AbstractEntityRepresentation;

class SearchEngineRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o:SearchEngine';
    }

    public function getJsonLd()
    {
        $modified = $this->resource->getModified();
        return [
            'o:name' => $this->resource->getName(),
            'o:engine_adapter' => $this->resource->getEngineAdapter(),
            'o:settings' => $this->resource->getSettings(),
            'o:created' => $this->getDateTime($this->resource->getCreated()),
            'o:modified' => $modified ? $this->getDateTime($modified) : null,
        ];
    }

    public function adminUrl($action = null, $canonical = false): string
    {
        $url = $this->getViewHelper('Url');
        $params = [
            'action' => $action,
            'id' => $this->id(),
        ];
        $options = [
            'force_canonical' => $canonical,
        ];

        return $url('admin/search-manager/engine-id', $params, $options);
    }

    public function name(): string
    {
        return $this->resource->getName();
    }

    public function cleanName(): string
    {
        return strtolower(str_replace('__', '_',
            preg_replace('/[^a-zA-Z0-9]/', '_', $this->resource->getName())
        ));
    }

    /**
     * Get unique short name of this index.
     *
     * The short name is used in Solr module to create a unique id, that should
     * be 32 letters max in order to be sorted (39rhjw-Z-item_sets/7654321:fr_FR),
     * it should be less than two letters, so don't create too much indexes.
     */
    public function shortName(): string
    {
        return base_convert((string) $this->id(), 10, 36);
    }

    public function engineAdapter(): ?EngineAdapterInterface
    {
        $name = $this->resource->getAdapter();
        $engineAdapterManager = $this->getServiceLocator()->get('AdvancedSearch\EngineAdapterManager');
        return $engineAdapterManager->has($name)
            ? $engineAdapterManager->get($name)->setSearchEngine($this)
            : null;
    }

    public function engineAdapterName(): ?string
    {
        return $this->resource->getAdapter();
    }

    public function settings(): array
    {
        return $this->resource->getSettings();
    }

    public function setting(string $name, $default = null)
    {
        return $this->resource->getSettings()[$name] ?? $default;
    }

    public function subSetting(string $mainName, string $name, $default = null)
    {
        return $this->resource->getSettings()[$mainName][$name] ?? $default;
    }

    public function settingEngineAdapter(string $name, $default = null)
    {
        return $this->resource->getSettings()['engine_adapter'][$name] ?? $default;
    }

    public function created(): \DateTime
    {
        return $this->resource->getCreated();
    }

    public function modified(): ?\DateTime
    {
        return $this->resource->getModified();
    }

    public function getEntity(): \AdvancedSearch\Entity\SearchEngine
    {
        return $this->resource;
    }

    public function engineAdapterLabel(): string
    {
        $engineAdapter = $this->engineAdapter();
        if (!$engineAdapter) {
            $translator = $this->getServiceLocator()->get('MvcTranslator');
            return sprintf($translator->translate('[Missing engine adapter "%s"]'), // @translate
                $this->resource->getAdapter()
            );
        }
        return $engineAdapter->getLabel();
    }

    /**
     * @return NoopIndexer is returned when the indexer is not available.
     */
    public function indexer(): \AdvancedSearch\Indexer\IndexerInterface
    {
        $services = $this->getServiceLocator();
        $engineAdapter = $this->engineAdapter();
        if ($engineAdapter) {
            $indexerClass = $engineAdapter->getIndexerClass() ?: NoopIndexer::class;
        } else {
            $indexerClass = NoopIndexer::class;
        }

        /** @var \AdvancedSearch\Indexer\IndexerInterface $indexer */
        $indexer = new $indexerClass;
        return $indexer
            ->setServiceLocator($services)
            ->setSearchEngine($this)
            ->setLogger($services->get('Omeka\Logger'));
    }

    /**
     * @return NoopQuerier is returned when the querier is not available.
     */
    public function querier(): \AdvancedSearch\Querier\QuerierInterface
    {
        $services = $this->getServiceLocator();
        $engineAdapter = $this->engineAdapter();
        if ($engineAdapter) {
            $querierClass = $engineAdapter->getQuerierClass() ?: \AdvancedSearch\Querier\NoopQuerier::class;
        } else {
            $querierClass = \AdvancedSearch\Querier\NoopQuerier::class;
        }

        /** @var \AdvancedSearch\Querier\QuerierInterface $querier */
        $querier = new $querierClass;
        return $querier
            ->setServiceLocator($services)
            ->setSearchEngine($this)
            ->setLogger($services->get('Omeka\Logger'));
    }
}
