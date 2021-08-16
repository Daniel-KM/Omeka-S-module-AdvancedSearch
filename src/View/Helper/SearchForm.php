<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\Form\Form;
use Laminas\View\Helper\AbstractHelper;
use AdvancedSearch\Api\Representation\SearchConfigRepresentation;

class SearchForm extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'search/search-form';

    /**
     * @var SearchConfigRepresentation
     */
    protected $searchConfig;

    /**
     * @var Form
     */
    protected $form;

    /**
     * @var string
     */
    protected $partial = '';

    /**
     * @param SearchConfigRepresentation $searchConfig
     * @param string $partial Specific partial for the search form of the page.
     * @param bool $skipFormAction Don't set form action, so use the current page.
     * @return \Search\View\Helper\SearchForm
     */
    public function __invoke(SearchConfigRepresentation $searchConfig = null, $partial = null, $skipFormAction = false): self
    {
        $this->initSearchForm($searchConfig, $partial, $skipFormAction);
        return $this;
    }

    /**
     * Prepare default search page, form and partial.
     *
     * @param SearchConfigRepresentation $searchConfig
     * @param string $partial Specific partial for the search form.
     * @param bool $skipFormAction Don't set form action, so use the current page.
     */
    protected function initSearchForm(SearchConfigRepresentation $searchConfig = null, $partial = null, $skipFormAction = false): void
    {
        $plugins = $this->getView()->getHelperPluginManager();
        $isAdmin = $plugins->get('status')->isAdminRequest();
        if (empty($searchConfig)) {
            // If it is on a search page route, use the id, else use the setting.
            $params = $plugins->get('params')->fromRoute();
            $setting = $plugins->get($isAdmin ? 'setting' : 'siteSetting');
            if ($params['controller'] === 'Search\Controller\IndexController') {
                $searchConfigId = $params['id'];
                // Check if this search page is allowed.
                if (!in_array($searchConfigId, $setting('search_configs'))) {
                    $searchConfigId = 0;
                }
            }
            if (empty($searchConfigId)) {
                $searchConfigId = $setting('search_main_page');
            }
            $this->searchConfig = $plugins->get('api')->searchOne('search_configs', ['id' => (int) $searchConfigId])->getContent();
        } else {
            $this->searchConfig = $searchConfig;
        }

        $this->form = null;
        if ($this->searchConfig) {
            $this->form = $this->searchConfig->form();
            if ($this->form && !$skipFormAction) {
                $url = $isAdmin
                    ? $this->searchConfig->adminSearchUrl()
                    : $this->searchConfig->siteUrl();
                $this->form->setAttribute('action', $url);
            }
        }

        // Reset the partial.
        $this->partial = '';

        if ($this->form) {
            $this->partial = $partial ?? '';
            if (empty($this->partial)) {
                $formAdapter = $this->searchConfig->formAdapter();
                $this->partial = $formAdapter && ($formPartial = $formAdapter->getFormPartial())
                    ? $formPartial
                    : self::PARTIAL_NAME;
            }
        }
    }

    /**
     * Get the specified search page or the default one.
     *
     * @return \Search\Api\Representation\SearchConfigRepresentation|null
     */
    public function getSearchConfig(): ?SearchConfigRepresentation
    {
        return $this->searchConfig;
    }

    /**
     * Get the form of the search page.
     *
     * @return \Laminas\Form\Form|null
     */
    public function getForm(): ?Form
    {
        return $this->form;
    }

    /**
     * Get the partial form used for this form of this page.
     *
     * @return string
     */
    public function getPartial(): string
    {
        return $this->partial;
    }

    public function __toString(): string
    {
        return $this->partial
            ? $this->getView()->partial($this->partial, ['form' => $this->form])
            : '';
    }
}
