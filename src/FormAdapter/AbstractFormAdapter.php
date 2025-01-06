<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau, 2019-2024
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

namespace AdvancedSearch\FormAdapter;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Query;
use Omeka\Api\Representation\SiteRepresentation;

abstract class AbstractFormAdapter implements FormAdapterInterface
{
    use TraitRequest;

    /**
     * @var string|null
     */
    protected $configFormClass = null;

    /**
     * @var string|null
     */
    protected $formClass = null;

    /**
     * @var string|null
     */
    protected $formPartial = null;

    /**
     * @var string|null
     */
    protected $formPartialHeaders = null;

    /**
     * @var string
     */
    protected $label = '[Undefined]'; // @translate

    /**
     * @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation
     */
    protected $searchConfig;

    public function setSearchConfig(SearchConfigRepresentation $searchConfig): self
    {
        $this->searchConfig = $searchConfig;
        return $this;
    }

    public function getConfigFormClass(): ?string
    {
        return $this->configFormClass;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getFormClass(): ?string
    {
        return $this->formClass;
    }

    public function getFormPartialHeaders(): ?string
    {
        return $this->formPartialHeaders;
    }

    public function getFormPartial(): ?string
    {
        return $this->formPartial;
    }

    public function getForm(array $options = []): ?\Laminas\Form\Form
    {
        if (!$this->formClass || !$this->searchConfig) {
            return null;
        }
        $formElementManager = $this->searchConfig->getServiceLocator()
            ->get('FormElementManager');
        if (!$formElementManager->has($this->formClass)) {
            return null;
        }
        $options['search_config'] = $this->searchConfig;
        return $formElementManager
            ->get($this->formClass, $options)
            ->setAttribute('method', 'GET');
    }

    public function renderForm(array $options = []): string
    {
        return '';
    }

    public function toQuery(array $request, array $formSettings): Query
    {
        return new Query();
    }

    public function toResponse(array $request, ?SiteRepresentation $site = null): array
    {
        return [
            'status' => 'error',
            'message' => 'Not implemented. See MainFormAdapter.',
        ];
    }
}
