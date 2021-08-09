<?php declare(strict_types=1);

namespace Search\View\Helper;

use Laminas\Form\Form;
use Laminas\View\Helper\AbstractHelper;
use Search\Api\Representation\SearchPageRepresentation;

class SearchForm extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'search/search-form';

    /**
     * @var SearchPageRepresentation
     */
    protected $searchPage;

    /**
     * @var Form
     */
    protected $form;

    /**
     * @var string
     */
    protected $partial = '';

    /**
     * @param SearchPageRepresentation $searchPage
     * @param string $partial Specific partial for the search form of the page.
     * @param bool $skipFormAction Don't set form action, so use the current page.
     * @return \Search\View\Helper\SearchForm
     */
    public function __invoke(SearchPageRepresentation $searchPage = null, $partial = null, $skipFormAction = false): self
    {
        $this->initSearchForm($searchPage, $partial, $skipFormAction);
        return $this;
    }

    /**
     * Prepare default search page, form and partial.
     *
     * @param SearchPageRepresentation $searchPage
     * @param string $partial Specific partial for the search form.
     * @param bool $skipFormAction Don't set form action, so use the current page.
     */
    protected function initSearchForm(SearchPageRepresentation $searchPage = null, $partial = null, $skipFormAction = false): void
    {
        $plugins = $this->getView()->getHelperPluginManager();
        $isAdmin = $plugins->get('status')->isAdminRequest();
        if (empty($searchPage)) {
            // If it is on a search page route, use the id, else use the setting.
            $params = $plugins->get('params')->fromRoute();
            $setting = $plugins->get($isAdmin ? 'setting' : 'siteSetting');
            if ($params['controller'] === 'Search\Controller\IndexController') {
                $searchPageId = $params['id'];
                // Check if this search page is allowed.
                if (!in_array($searchPageId, $setting('search_pages'))) {
                    $searchPageId = 0;
                }
            }
            if (empty($searchPageId)) {
                $searchPageId = $setting('search_main_page');
            }
            $this->searchPage = $plugins->get('api')->searchOne('search_pages', ['id' => (int) $searchPageId])->getContent();
        } else {
            $this->searchPage = $searchPage;
        }

        $this->form = null;
        if ($this->searchPage) {
            $this->form = $this->searchPage->form();
            if ($this->form && !$skipFormAction) {
                $url = $isAdmin
                    ? $this->searchPage->adminSearchUrl()
                    : $this->searchPage->siteUrl();
                $this->form->setAttribute('action', $url);
            }
        }

        // Reset the partial.
        $this->partial = '';

        if ($this->form) {
            $this->partial = $partial ?? '';
            if (empty($this->partial)) {
                $formAdapter = $this->searchPage->formAdapter();
                $this->partial = $formAdapter && ($formPartial = $formAdapter->getFormPartial())
                    ? $formPartial
                    : self::PARTIAL_NAME;
            }
        }
    }

    /**
     * Get the specified search page or the default one.
     *
     * @return \Search\Api\Representation\SearchPageRepresentation|null
     */
    public function getSearchPage(): ?SearchPageRepresentation
    {
        return $this->searchPage;
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
