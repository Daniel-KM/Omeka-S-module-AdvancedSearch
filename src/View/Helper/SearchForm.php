<?php

namespace Search\View\Helper;

use Search\Api\Representation\SearchPageRepresentation;
use Zend\Form\Form;
use Zend\View\Helper\AbstractHelper;

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
    protected $partial;

    /**
     * @param SearchPageRepresentation $searchPage
     * @param string $partial Specific partial for the search form of the page.
     * @return \Search\View\Helper\SearchForm
     */
    public function __invoke(SearchPageRepresentation $searchPage = null, $partial = null)
    {
        $this->initSearchForm($searchPage, $partial);
        return $this;
    }

    /**
     *Prepare default search page, form and partial.
     *
     * @param SearchPageRepresentation $searchPage
     * @param string $partial Specific partial for the search form.
     */
    protected function initSearchForm(SearchPageRepresentation $searchPage = null, $partial = null)
    {
        $view = $this->getView();
        $isAdmin = $view->status()->isAdminRequest();
        if (empty($searchPage)) {
            // If it is on a search page route, use the id, else use the setting.
            $params = $view->params()->fromRoute();
            $setting = $isAdmin
                ? $view->getHelperPluginManager()->get('setting')
                : $view->getHelperPluginManager()->get('siteSetting');
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
            $this->searchPage = $view->api()->searchOne('search_pages', ['id' => (int) $searchPageId])->getContent();
        } else {
            $this->searchPage = $searchPage;
        }

        $this->form = null;
        if ($this->searchPage) {
            $this->form = $this->searchPage->form();
            if ($this->form) {
                $url = $isAdmin
                    ? $this->searchPage->adminSearchUrl()
                    : $this->searchPage->url();
                $this->form->setAttribute('action', $url);
            }
        }

        $this->partial = null;
        if ($this->form) {
            $this->partial = $partial;
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
    public function getSearchPage()
    {
        return $this->searchPage;
    }

    /**
     * Get the form of the search page.
     *
     * @return \Zend\Form\Form|null
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * Get the partial form used for this form of this page.
     *
     * @return string
     */
    public function getPartial()
    {
        return $this->partial;
    }

    public function __toString()
    {
        return $this->partial
            ? $this->getView()->partial($this->partial, ['form' => $this->form])
            : '';
    }
}
