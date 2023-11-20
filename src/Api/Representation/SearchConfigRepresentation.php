<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2017-2023
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

use Omeka\Api\Representation\AbstractEntityRepresentation;

class SearchConfigRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o:SearchConfig';
    }

    public function getJsonLd()
    {
        $modified = $this->resource->getModified();
        return [
            'o:name' => $this->resource->getName(),
            'o:path' => $this->resource->getPath(),
            'o:engine' => $this->engine()->getReference(),
            // TODO Don't use "o:form" for the form adapter.
            'o:form' => $this->resource->getFormAdapter(),
            'o:settings' => $this->resource->getSettings(),
            'o:created' => $this->getDateTime($this->resource->getCreated()),
            'o:modified' => $modified ? $this->getDateTime($modified) : null,
        ];
    }

    public function adminUrl($action = null, $canonical = false)
    {
        $url = $this->getViewHelper('Url');
        $params = [
            'action' => $action,
            'id' => $this->id(),
        ];
        $options = [
            'force_canonical' => $canonical,
        ];

        return $url('admin/search/config-id', $params, $options);
    }

    /**
     * Url of the real search page.
     */
    public function adminSearchUrl($canonical = false): string
    {
        $url = $this->getViewHelper('Url');
        $options = [
            'force_canonical' => $canonical,
        ];
        return $url('search-admin-page-' . $this->id(), [], $options);
    }

    public function siteUrl($siteSlug = null, $canonical = false)
    {
        if (!$siteSlug) {
            $siteSlug = $this->getServiceLocator()->get('Application')
                ->getMvcEvent()->getRouteMatch()->getParam('site-slug');
        }
        $params = [
            'site-slug' => $siteSlug,
        ];
        $options = [
            'force_canonical' => $canonical,
        ];
        $url = $this->getViewHelper('Url');
        // The urls use "search-page-" to simplify migration.
        return $url('search-page-' . $this->id(), $params, $options);
    }

    public function name(): string
    {
        return $this->resource->getName();
    }

    public function path(): ?string
    {
        return $this->resource->getPath();
    }

    public function engine(): ?\AdvancedSearch\Api\Representation\SearchEngineRepresentation
    {
        $searchEngine = $this->resource->getEngine();
        return $searchEngine
            ? $this->getAdapter('search_engines')->getRepresentation($searchEngine)
            : null;
    }

    public function searchAdapter(): ?\AdvancedSearch\Adapter\AdapterInterface
    {
        $engine = $this->engine();
        if ($engine) {
            $adapter = $engine->adapter();
            return $adapter
                ? $adapter->setSearchEngine($engine)
                : null;
        }
        return null;
    }

    public function formAdapterName(): ?string
    {
        return $this->resource->getFormAdapter();
    }

    public function formAdapter(): ?\AdvancedSearch\FormAdapter\FormAdapterInterface
    {
        $formAdapterName = $this->formAdapterName();
        if (!$formAdapterName) {
            return null;
        }

        $formAdapterManager = $this->getServiceLocator()->get('Search\FormAdapterManager');
        if (!$formAdapterManager->has($formAdapterName)) {
            return null;
        }

        /** @var \AdvancedSearch\FormAdapter\FormAdapterInterface $formAdapter */
        $formAdapter = $formAdapterManager->get($formAdapterName);
        return $formAdapter
            ->setSearchConfig($this);
    }

    /**
     * Get the form from the adapter.
     *
     * @uses \AdvancedSearch\FormAdapter\FormAdapterInterface::getForm()
     */
    public function form(): ?\Laminas\Form\Form
    {
        $formAdapter = $this->formAdapter();
        return $formAdapter
            ? $formAdapter->getForm()
            : null;
    }

    /**
     * Render the form.
     *
     * @param array $options
     *   - template (string): Use a specific template instead of the default one.
     *   This is the template of the form, not the main template of the search page.
     *   - skip_form_action (bool): Don't set form action, so use the current page.
     *   - skip_partial_headers (bool): Skip partial headers.
     *   Other options are passed to the partial.
     *
     * @uses \AdvancedSearch\FormAdapter\FormAdapterInterface::renderForm()
     */
    public function renderForm(array $options = []): string
    {
        $formAdapter = $this->formAdapter();
        return $formAdapter
            ? $formAdapter->renderForm($options)
            : '';
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

    public function subSubSetting(string $mainName, string $name, string $subName, $default = null)
    {
        return $this->resource->getSettings()[$mainName][$name][$subName] ?? $default;
    }

    public function created(): \DateTime
    {
        return $this->resource->getCreated();
    }

    public function modified(): ?\DateTime
    {
        return $this->resource->getModified();
    }

    public function getEntity(): \AdvancedSearch\Entity\SearchConfig
    {
        return $this->resource;
    }
}
