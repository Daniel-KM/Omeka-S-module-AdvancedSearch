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

namespace Search\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class SearchPageRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var \Search\FormAdapter\FormAdapterInterface
     */
    protected $formAdapter;

    public function getJsonLdType()
    {
        return 'o:SearchPage';
    }

    public function getJsonLd()
    {
        $entity = $this->resource;
        return [
            'o:name' => $entity->getName(),
            'o:path' => $entity->getPath(),
            'o:index_id' => $entity->getIndex()->getId(),
            'o:form' => $entity->getFormAdapter(),
            'o:settings' => $entity->getSettings(),
            'o:created' => $this->getDateTime($entity->getCreated()),
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

        return $url('admin/search/page-id', $params, $options);
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

    public function index(): ?\Search\Api\Representation\SearchIndexRepresentation
    {
        $searchIndex = $this->resource->getIndex();
        return $searchIndex
            ? $this->getAdapter('search_indexes')->getRepresentation($searchIndex)
            : null;
    }

    public function formAdapterName(): ?string
    {
        return $this->resource->getFormAdapter();
    }

    public function formAdapter(): ?\Search\FormAdapter\FormAdapterInterface
    {
        if (!$this->formAdapter) {
            $formAdapterManager = $this->getServiceLocator()->get('Search\FormAdapterManager');
            $formAdapterName = $this->formAdapterName();
            if ($formAdapterManager->has($formAdapterName)) {
                $this->formAdapter = $formAdapterManager->get($formAdapterName);
            }
        }

        return $this->formAdapter;
    }

    public function form(): ?\Laminas\Form\Form
    {
        $formAdapter = $this->formAdapter();
        if (empty($formAdapter)) {
            return null;
        }

        $formClass = $formAdapter->getFormClass();
        if (empty($formClass)) {
            return null;
        }

        return $this->getServiceLocator()
            ->get('FormElementManager')
            ->get($formClass, [
                'search_page' => $this,
            ])
            ->setAttribute('method', 'GET');
    }

    public function settings(): array
    {
        return $this->resource->getSettings();
    }

    public function setting(string $name, $default = null)
    {
        $settings = $this->resource->getSettings();
        return $settings[$name] ?? $default;
    }

    public function created(): \DateTime
    {
        return $this->resource->getCreated();
    }

    public function getEntity(): \Search\Entity\SearchPage
    {
        return $this->resource;
    }
}
