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

namespace AdvancedSearch\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class SearchConfigRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var bool
     */
    private $isFormInit = false;

    /**
     * @var \AdvancedSearch\FormAdapter\FormAdapterInterface
     */
    protected $formAdapter;

    /**
     * @var \Laminas\Form\Form|null
     */
    protected $form;

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

    public function formAdapterName(): ?string
    {
        return $this->resource->getFormAdapter();
    }

    public function formAdapter(): ?\AdvancedSearch\FormAdapter\FormAdapterInterface
    {
        if (!$this->isFormInit) {
            $this->formInit();
        }
        return $this->formAdapter;
    }

    public function form(): ?\Laminas\Form\Form
    {
        if (!$this->isFormInit) {
            $this->formInit();
        }
        return $this->form;
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

    private function formInit(): self
    {
        $this->isFormInit = true;

        $formAdapterManager = $this->getServiceLocator()->get('Search\FormAdapterManager');
        $formAdapterName = $this->formAdapterName();
        if (!$formAdapterManager->has($formAdapterName)) {
            return $this;
        }

        $this->formAdapter = $formAdapterManager->get($formAdapterName);
        $formClass = $this->formAdapter->getFormClass();
        if (empty($formClass)) {
            return $this;
        }

        $this->form = $this->getServiceLocator()
            ->get('FormElementManager')
            ->get($formClass, [
                'search_config' => $this,
            ])
            ->setAttribute('method', 'GET');

        $this->formAdapter->setForm($this->form);

        return $this;
    }
}
