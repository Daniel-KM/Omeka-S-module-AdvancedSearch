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

namespace AdvancedSearch\Controller\Admin;

use AdvancedSearch\Adapter\Manager as SearchAdapterManager;
use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Form\Admin\SearchConfigConfigureForm;
use AdvancedSearch\Form\Admin\SearchConfigForm;
use AdvancedSearch\FormAdapter\Manager as SearchFormAdapterManager;
use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;
use Omeka\Stdlib\Message;

class SearchConfigController extends AbstractActionController
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
        $form = $this->getForm(SearchConfigForm::class);

        $view = new ViewModel;
        $view->setVariable('form', $form);
        if (!$this->checkPostAndValidForm($form)) {
            return $view;
        }

        $formData = $form->getData();
        $response = $this->api()->create('search_configs', $formData);
        $searchConfig = $response->getContent();

        $this->messenger()->addSuccess(new Message(
            'Search page "%s" created.', // @translate
            $searchConfig->name()
        ));
        $this->manageSearchConfigOnSites(
            $searchConfig,
            $formData['manage_page_default'] ?: [],
            $formData['manage_page_availability']
        );
        if (!in_array($formData['manage_page_availability'], ['disable', 'enable'])
            && empty($formData['manage_page_default'])
        ) {
            $this->messenger()->addWarning('You can enable this page in your site settings or in admin settings.'); // @translate
        }

        if ($searchConfig->formAdapter() instanceof \AdvancedSearch\FormAdapter\ApiFormAdapter) {
            $this->messenger()->addWarning(
                'The api adapter should be selected in the main settings.' // @translate
            );
        }

        return $this->redirect()->toUrl($searchConfig->url('configure'));
    }

    public function editAction()
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $page */
        $id = $this->params('id');
        $page = $this->api()->read('search_configs', ['id' => $id])->getContent();

        $data = $page->jsonSerialize();
        $data['manage_page_default'] = $this->sitesWithSearchConfig($page);

        $form = $this->getForm(SearchConfigForm::class);
        $form->setData($data);

        $view = new ViewModel;
        $view->setVariable('form', $form);

        if (!$this->checkPostAndValidForm($form)) {
            return $view;
        }

        $formData = $form->getData();
        $searchConfig = $this->api()
            ->update('search_configs', $id, $formData, [], ['isPartial' => true])
            ->getContent();

        $this->messenger()->addSuccess(new Message(
            'Search page "%s" saved.', // @translate
            $searchConfig->name()
        ));

        $this->manageSearchConfigOnSites(
            $searchConfig,
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

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->api()->read('search_configs', $id)->getContent();

        $view = new ViewModel([
            'searchConfig' => $searchConfig,
        ]);

        $index = $searchConfig->index();
        $adapter = $index ? $index->adapter() : null;
        if (empty($adapter)) {
            $message = new Message(
                'The index adapter "%s" is unavailable.', // @translate
                $index->adapterLabel()
            );
            $this->messenger()->addError($message); // @translate
            return $view;
        }

        $form = $this->getConfigureForm($searchConfig);
        if (empty($form)) {
            $message = new Message(
                'This index adapter "%s" has no config form.', // @translate
                $index->adapterLabel()
            );
            $this->messenger()->addWarning($message); // @translate
            return $view;
        }

        $searchConfigSettings = $searchConfig->settings() ?: [];

        $form->setData($searchConfigSettings);
        $view->setVariable('form', $form);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $params = $this->getRequest()->getPost()->toArray();
        $params = $this->removeAvailableFields($params);

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
        $params = $this->removeAvailableFields($params);
        unset($params['csrf']);
        if (isset($params['search']['default_query'])) {
            $params['search']['default_query'] = trim($params['search']['default_query'] ?? '', "? \t\n\r\0\x0B");
        }

        // Add a warning because it may be a hard to understand issue.
        if (isset($params['facet']['languages'])) {
            $params['facet']['languages'] = array_unique(array_map('trim', $params['facet']['languages']));
            if (!empty($params['facet']['languages']) && !in_array('', $params['facet']['languages'])) {
                $this->messenger()->addWarning(
                    'Note that you didn’t set a trailing "|", so all values without language will be removed.' // @translate
                );
            }
        }

        $page = $searchConfig->getEntity();
        $page->setSettings($params);
        $entityManager->flush();

        $this->messenger()->addSuccess(new Message(
            'Configuration "%s" saved.', // @translate
            $searchConfig->getName()
        ));

        return $this->redirect()->toRoute('admin/search');
    }

    public function deleteConfirmAction()
    {
        $id = $this->params('id');
        $searchConfig = $this->api()->read('search_configs', $id)->getContent();

        $view = new ViewModel([
            'resourceLabel' => 'search page',
            'resource' => $searchConfig,
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
            $pageName = $this->api()->read('search_configs', $id)->getContent()->name();
            if ($form->isValid()) {
                $this->api()->delete('search_configs', $this->params('id'));
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
            ->search('search_configs', [], ['returnScalar' => 'path'])
            ->getContent();
        if (in_array($path, $paths)) {
            if (!$id) {
                $this->messenger()->addError('The path should be unique.'); // @translate
                return false;
            }
            $searchConfigId = $this->api()
                ->searchOne('search_configs', ['path' => $path], ['returnScalar' => 'id'])
                ->getContent();
            if ($id !== $searchConfigId) {
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
    protected function getConfigureForm(SearchConfigRepresentation $searchConfig): ?\AdvancedSearch\Form\Admin\SearchConfigConfigureForm
    {
        return $searchConfig->index()
            ? $this->getForm(SearchConfigConfigureForm::class, ['advancedsearch_config' => $searchConfig])
            : null;
    }

    protected function sitesWithSearchConfig(SearchConfigRepresentation $searchConfig): array
    {
        $result = [];
        $searchConfigId = $searchConfig->id();

        // Check admin.
        $adminSearchId = $this->settings()->get('advancedsearch_main_page');
        if ($adminSearchId && $adminSearchId == $searchConfigId) {
            $result[] = 'admin';
        }

        // Check all sites.
        $settings = $this->siteSettings();
        $sites = $this->api()->search('sites')->getContent();
        foreach ($sites as $site) {
            $settings->setTargetId($site->id());
            $siteSearchId = $settings->get('advancedsearch_main_page');
            if ($siteSearchId && $siteSearchId == $searchConfigId) {
                $result[] = $site->id();
            }
        }

        return $result;
    }

    /**
     * Remove all params starting with "available_".
     */
    protected function removeAvailableFields(array $params): array
    {
        foreach ($params as $name => $values) {
            if (substr($name, 0, 10) === 'available_') {
                unset($params[$name]);
            } elseif (is_array($values)) {
                foreach (array_keys($values) as $subName) {
                    if (substr($subName, 0, 10) === 'available_') {
                        unset($params[$name][$subName]);
                    }
                }
            }
        }
        return $params;
    }

    /**
     * Config the page for all sites.
     */
    protected function manageSearchConfigOnSites(
        SearchConfigRepresentation $searchConfig,
        array $newMainSearchConfigForSites,
        $availability
    ): void {
        $searchConfigId = $searchConfig->id();
        $currentMainSearchConfigForSites = $this->sitesWithSearchConfig($searchConfig);

        // Manage admin settings.
        $current = in_array('admin', $currentMainSearchConfigForSites);
        $new = in_array('admin', $newMainSearchConfigForSites);
        if ($current !== $new) {
            $settings = $this->settings();
            if ($new) {
                $settings->set('advancedsearch_main_page', $searchConfigId);
                $searchConfigs = $settings->get('search_configs', []);
                $searchConfigs[] = $searchConfigId;
                $searchConfigs = array_unique(array_filter(array_map('intval', $searchConfigs)));
                sort($searchConfigs);
                $settings->set('search_configs', $searchConfigs);

                $message = 'The page has been set by default in admin board.'; // @translate
            } else {
                $settings->set('advancedsearch_main_page', null);
                $message = 'The page has been unset in admin board.'; // @translate
            }
            $this->messenger()->addSuccess($message);
        }

        $allSites = in_array('all', $newMainSearchConfigForSites);
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
            $searchConfigs = $siteSettings->get('search_configs', []);
            $current = in_array($siteId, $currentMainSearchConfigForSites);
            $new = $allSites || in_array($siteId, $newMainSearchConfigForSites);
            if ($current !== $new) {
                if ($new) {
                    $siteSettings->set('advancedsearch_main_page', $searchConfigId);
                    $searchConfigs[] = $searchConfigId;
                } else {
                    $siteSettings->set('advancedsearch_main_page', null);
                }
            }

            if ($new || $available) {
                $searchConfigs[] = $searchConfigId;
            } else {
                $key = array_search($searchConfigId, $searchConfigs);
                if ($key === false) {
                    continue;
                }
                unset($searchConfigs[$key]);
            }
            $searchConfigs = array_unique(array_filter(array_map('intval', $searchConfigs)));
            sort($searchConfigs);
            $siteSettings->set('search_configs', $searchConfigs);
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
