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

namespace Search\Controller\Admin;

use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;
use Omeka\Stdlib\Message;
use Search\Adapter\Manager as SearchAdapterManager;
use Search\Api\Representation\SearchPageRepresentation;
use Search\Form\Admin\SearchPageConfigureForm;
use Search\Form\Admin\SearchPageForm;
use Search\FormAdapter\Manager as SearchFormAdapterManager;

class SearchPageController extends AbstractActionController
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var SearchAdapterManager
     */
    protected $searchAdapterManager;

    /**
     * @var SearchFormAdapterManager
     */
    protected $searchFormAdapterManager;

    public function __construct(
        EntityManager $entityManager,
        SearchAdapterManager $searchAdapterManager,
        SearchFormAdapterManager $searchFormAdapterManager
    ) {
        $this->entityManager = $entityManager;
        $this->searchAdapterManager = $searchAdapterManager;
        $this->searchFormAdapterManager = $searchFormAdapterManager;
    }

    public function addAction()
    {
        $form = $this->getForm(SearchPageForm::class);

        $view = new ViewModel;
        $view->setVariable('form', $form);
        if (!$this->checkPostAndValidForm($form)) {
            return $view;
        }

        $formData = $form->getData();
        $response = $this->api()->create('search_pages', $formData);
        $searchPage = $response->getContent();

        $this->messenger()->addSuccess(new Message(
            'Search page "%s" created.', // @translate
            $searchPage->name()
        ));
        $this->manageSearchPageOnSites(
            $searchPage,
            $formData['manage_page_default'] ?: [],
            $formData['manage_page_availability']
        );
        if (!in_array($formData['manage_page_availability'], ['disable', 'enable'])
            && empty($formData['manage_page_default'])
        ) {
            $this->messenger()->addWarning('You can enable this page in your site settings or in admin settings.'); // @translate
        }

        if ($searchPage->formAdapter() instanceof \Search\FormAdapter\ApiFormAdapter) {
            $this->messenger()->addWarning(
                'The api adapter should be selected in the main settings.' // @translate
            );
        }

        return $this->redirect()->toUrl($searchPage->url('configure'));
    }

    public function editAction()
    {
        /** @var \Search\Api\Representation\SearchPageRepresentation $page */
        $id = $this->params('id');
        $page = $this->api()->read('search_pages', ['id' => $id])->getContent();

        $data = $page->jsonSerialize();
        $data['manage_page_default'] = $this->sitesWithSearchPage($page);

        $form = $this->getForm(SearchPageForm::class);
        $form->setData($data);

        $view = new ViewModel;
        $view->setVariable('form', $form);

        if (!$this->checkPostAndValidForm($form)) {
            return $view;
        }

        $formData = $form->getData();
        $searchPage = $this->api()
            ->update('search_pages', $id, $formData, [], ['isPartial' => true])
            ->getContent();

        $this->messenger()->addSuccess(new Message(
            'Search page "%s" saved.', // @translate
            $searchPage->name()
        ));

        $this->manageSearchPageOnSites(
            $searchPage,
            $formData['manage_page_default'] ?: [],
            $formData['manage_page_availability']
        );

        return $this->redirect()->toRoute('admin/search');
    }

    /**
     * @fixme Simplify to use a normal search config form with integrated elements checks.
     */
    public function configureAction()
    {
        $entityManager = $this->getEntityManager();

        $id = $this->params('id');

        /** @var \Search\Api\Representation\SearchPageRepresentation $searchPage */
        $searchPage = $this->api()->read('search_pages', $id)->getContent();

        $view = new ViewModel([
            'searchPage' => $searchPage,
        ]);

        $index = $searchPage->index();
        $adapter = $index ? $index->adapter() : null;
        if (empty($adapter)) {
            $message = new Message(
                'The index adapter "%s" is unavailable.', // @translate
                $index->adapterLabel()
            );
            $this->messenger()->addError($message); // @translate
            return $view;
        }

        $form = $this->getConfigureForm($searchPage);
        if (empty($form)) {
            $message = new Message(
                'This index adapter "%s" has no config form.', // @translate
                $index->adapterLabel()
            );
            $this->messenger()->addWarning($message); // @translate
            return $view;
        }

        $searchPageSettings = $searchPage->settings() ?: [];

        $form->setData($searchPageSettings);
        $view->setVariable('form', $form);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $params = $this->getRequest()->getPost()->toArray();

        unset(
            $params['form']['available_filters'],
            $params['form']['available_fields_order'],
            $params['sort']['available_sort_fields'],
            $params['facet']['available_facets']
        );

        // TODO Check simple fields with normal way.
        $form->setData($params);
        if (!$form->isValid()) {
            $messages = $form->getMessages();
            if (isset($messages['csrf'])) {
                $this->messenger()->addError('Invalid or missing CSRF token'); // @translate
            } else {
                $this->messenger()->addError('There was an error during validation'); // @translate
            }
            return $view;
        }

        $params = $form->getData();
        unset(
            $params['csrf'],
            $params['form']['available_filters'],
            $params['form']['available_fields_order'],
            $params['sort']['available_sort_fields'],
            $params['facet']['available_facets']
        );

        $params['search']['default_query'] = trim($params['search']['default_query'], "? \t\n\r\0\x0B");

        // Add a warning because it may be a hard to understand issue.
        $params['facet']['languages'] = array_unique(array_map('trim', $params['facet']['languages'] ?? []));
        if (!empty($params['facet']['languages']) && !in_array('', $params['facet']['languages'])) {
            $this->messenger()->addWarning(
                'Note that you didn’t set "||", so all values without language will be removed.' // @translate
            );
        }

        $page = $searchPage->getEntity();
        $page->setSettings($params);
        $entityManager->flush();

        $this->messenger()->addSuccess(new Message(
            'Configuration saved for page "%s".', // @translate
            $searchPage->name()
        ));

        return $this->redirect()->toRoute('admin/search');
    }

    public function deleteConfirmAction()
    {
        $id = $this->params('id');
        $searchPage = $this->api()->read('search_pages', $id)->getContent();

        $view = new ViewModel([
            'resourceLabel' => 'search page',
            'resource' => $searchPage,
        ]);
        return $view
            ->setTerminal(true)
            ->setTemplate('common/delete-confirm-details');
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            $id = $this->params('id');
            $pageName = $this->api()->read('search_pages', $id)->getContent()->name();
            if ($form->isValid()) {
                $this->api()->delete('search_pages', $this->params('id'));
                $this->messenger()->addSuccess(new Message(
                    'Search page "%s" successfully deleted', // @translate
                    $pageName
                ));
            } else {
                $this->messenger()->addError(new Message(
                    'Search page "%s" could not be deleted', // @translate
                    $pageName
                ));
            }
        }
        return $this->redirect()->toRoute('admin/search');
    }

    protected function checkPostAndValidForm($form): bool
    {
        if (!$this->getRequest()->isPost()) {
            return false;
        }

        // Check if the name of the path is single in the database.
        $params = $this->params()->fromPost();
        $id = $this->params('id');
        $path = $params['o:path'];

        $paths = $this->api()
            ->search('search_pages', [], ['returnScalar' => 'path'])
            ->getContent();
        if (in_array($path, $paths)) {
            if (!$id) {
                $this->messenger()->addError('The path should be unique.'); // @translate
                return false;
            }
            $searchPageId = $this->api()
                ->searchOne('search_pages', ['path' => $path], ['returnScalar' => 'id'])
                ->getContent();
            if ($id !== $searchPageId) {
                $this->messenger()->addError('The path should be unique.'); // @translate
                return false;
            }
        }

        if (strpos($path, 'https:') === 0 || strpos($path, 'http:') === 0) {
            $this->messenger()->addError('The path should be relative to the root of the site, like "search".'); // @translate
            return false;
        }

        $form->setData($params);
        if ($form->isValid()) {
            return true;
        }

        $messages = $form->getMessages();
        if (isset($messages['csrf'])) {
            $this->messenger()->addError('Invalid or missing CSRF token'); // @translate
        } else {
            $this->messenger()->addError('There was an error during validation'); // @translate
        }
        return false;
    }

    /**
     * Check if the configuration should use simple or visual form and get it.
     */
    protected function getConfigureForm(SearchPageRepresentation $searchPage): ?\Search\Form\Admin\SearchPageConfigureForm
    {
        return $searchPage->index()
            ? $this->getForm(SearchPageConfigureForm::class, ['search_page' => $searchPage])
            : null;
    }

    protected function sitesWithSearchPage(SearchPageRepresentation $searchPage): array
    {
        $result = [];

        // Check admin.
        $adminSearchId = $this->settings()->get('search_main_page');
        if ($adminSearchId) {
            $result[] = 'admin';
        }

        // Check all sites.
        $searchPageId = $searchPage->id();
        $settings = $this->siteSettings();
        $sites = $this->api()->search('sites')->getContent();
        foreach ($sites as $site) {
            $settings->setTargetId($site->id());
            if ($settings->get('search_main_page') == $searchPageId) {
                $result[] = $site->id();
            }
        }

        return $result;
    }

    /**
     * Config the page for all sites.
     */
    protected function manageSearchPageOnSites(
        SearchPageRepresentation $searchPage,
        array $newMainSearchPageForSites,
        $availability
    ): void {
        $searchPageId = $searchPage->id();
        $currentMainSearchPageForSites = $this->sitesWithSearchPage($searchPage);

        // Manage admin settings.
        $current = in_array('admin', $currentMainSearchPageForSites);
        $new = in_array('admin', $newMainSearchPageForSites);
        if ($current !== $new) {
            $settings = $this->settings();
            if ($new) {
                $settings->set('search_main_page', $searchPageId);
                $searchPages = $settings->get('search_pages', []);
                $searchPages[] = $searchPageId;
                $searchPages = array_unique(array_filter(array_map('intval', $searchPages)));
                sort($searchPages);
                $settings->set('search_pages', $searchPages);

                $message = 'The page has been set by default in admin board.'; // @translate
            } else {
                $settings->set('search_main_page', null);
                $message = 'The page has been unset in admin board.'; // @translate
            }
            $this->messenger()->addSuccess($message);
        }

        $allSites = in_array('all', $newMainSearchPageForSites);
        switch ($availability) {
            case 'disable':
                $available = false;
                $message = 'The page has been disabled in all specified sites.'; // @translate
                break;
            case 'enable':
                $available = true;
                $message = 'The page has been made available in all specified sites.'; // @translate
                break;
            default:
                $available = null;
                $message = 'The availability of pages of sites was let unmodified.'; // @translate
        }

        // Manage site settings.
        $siteSettings = $this->siteSettings();
        $sites = $this->api()->search('sites')->getContent();
        foreach ($sites as $site) {
            $siteId = $site->id();
            $siteSettings->setTargetId($siteId);
            $searchPages = $siteSettings->get('search_pages', []);
            $current = in_array($siteId, $currentMainSearchPageForSites);
            $new = $allSites || in_array($siteId, $newMainSearchPageForSites);
            if ($current !== $new) {
                if ($new) {
                    $siteSettings->set('search_main_page', $searchPageId);
                    $searchPages[] = $searchPageId;
                } else {
                    $siteSettings->set('search_main_page', null);
                }
            }

            if ($new || $available) {
                $searchPages[] = $searchPageId;
            } else {
                $key = array_search($searchPageId, $searchPages);
                if ($key === false) {
                    continue;
                }
                unset($searchPages[$key]);
            }
            $searchPages = array_unique(array_filter(array_map('intval', $searchPages)));
            sort($searchPages);
            $siteSettings->set('search_pages', $searchPages);
        }

        $this->messenger()->addSuccess($message);
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    protected function getSearchAdapterManager(): SearchAdapterManager
    {
        return $this->searchAdapterManager;
    }

    protected function getSearchFormAdapterManager(): SearchFormAdapterManager
    {
        return $this->searchFormAdapterManager;
    }
}
