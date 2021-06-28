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

        $settings = $searchPage->settings() ?: [];
        $settings = $this->prepareSettingsForSimpleForm($searchPage, $settings);

        $form->setData($settings);
        $view->setVariable('form', $form);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $params = $this->extractSimpleFields($searchPage);

        // TODO Check simple fields with normal way.
        $form->setData($params);
        if (!$form->isValid()) {
            $messages = $form->getMessages();
            if (isset($messages['csrf'])) {
                $this->messenger()->addError('Invalid or missing CSRF token'); // @translate
            } else {
                $this->messenger()->addError('There was an error during validation'); // @translate
            }
            // return $view;
        }

        // TODO Why the fieldset "form" is removed from the params? Add an intermediate fieldset? Check if it is still the case.
        $formParams = $params['form'] ?? [];
        // TODO Check simple fields.
        $checkedParams = $form->getData();
        $formParams['fields_order'] = $checkedParams['form']['fields_order'] ?? null;
        $params['form'] = $formParams;

        unset($params['csrf']);
        unset($params['available_filters']);
        unset($params['available_fields_order']);
        unset($params['form']['available_filters']);
        unset($params['form']['available_fields_order']);

        $params['default_query'] = trim($params['default_query'], "? \t\n\r\0\x0B");

        // TODO Should be checked in form.
        if (empty($params['facet_languages'])) {
            $params['facet_languages'] = [];
        } elseif (!is_array($params['facet_languages'])) {
            $params['facet_languages'] = strlen(trim($params['facet_languages']))
                ? array_unique(array_map('trim', explode('|', $params['facet_languages'])))
                : [];
        }

        // Add a warning because it may be a hard to understand issue.
        if (!empty($params['facet_languages']) && !in_array('', $params['facet_languages'])) {
            $this->messenger()->addWarning(
                'Note that you didn’t set "||", so all values without language will be removed.' // @translate
            );
        }

        // Sort facets and sort fields to simplify next load.
        foreach (['facets', 'sort_fields'] as $type) {
            if (empty($params[$type])) {
                continue;
            }
            // Sort enabled first, then available, else sort by weight.
            uasort($params[$type], [$this, 'sortByEnabledFirst']);
        }

        // TODO Like languages, fieldset data should be checked in form fieldset.
        $formAdapter = $searchPage->formAdapter();
        if ($formAdapter) {
            $configFormClass = $formAdapter->getConfigFormClass();
            if (isset($configFormClass)) {
                $fieldset = $searchPage->getServiceLocator()->get('FormElementManager')
                    ->get($configFormClass, [
                        'search_page' => $searchPage,
                    ]);
                if (method_exists($fieldset, 'processInputFilters')) {
                    $params = $fieldset->processInputFilters($params);
                }
            }
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

    /**
     * Convert settings into strings in ordeer to manage many fields inside form.
     */
    protected function prepareSettingsForSimpleForm(SearchPageRepresentation $searchPage, $settings): array
    {
        $index = $searchPage->index();
        $adapter = $index->adapter();

        $data = '';
        $fields = empty($settings['facets']) ? [] : $settings['facets'];
        foreach ($fields as $name => $field) {
            if (!empty($field['enabled'])) {
                $data .= $name . ' | ' . $field['display']['label'] . "\n";
            }
        }
        $settings['facets'] = $data;

        $data = '';
        $fields = $adapter->getAvailableFacetFields($index);
        foreach ($fields as $name => $field) {
            $data .= $name . ' | ' . $field['label'] . "\n";
        }
        $settings['available_facets'] = $data;

        $data = '';
        $fields = empty($settings['sort_fields']) ? [] : $settings['sort_fields'];
        foreach ($fields as $name => $field) {
            if (!empty($field['enabled'])) {
                $data .= $name . ' | ' . $field['display']['label'] . "\n";
            }
        }
        $settings['sort_fields'] = $data;

        $data = '';
        $fields = $adapter->getAvailableSortFields($index);
        foreach ($fields as $name => $field) {
            $data .= $name . ' | ' . $field['label'] . "\n";
        }
        $settings['available_sort_fields'] = $data;

        return $settings;
    }

    protected function extractSimpleFields(SearchPageRepresentation $searchPage): array
    {
        $index = $searchPage->index();
        $adapter = $index->adapter();

        $params = $this->getRequest()->getPost()->toArray();
        unset($params['fieldsets']);
        unset($params['form_class']);
        unset($params['available_facets']);
        unset($params['available_sort_fields']);

        $fields = $adapter->getAvailableFacetFields($index);

        $data = $params['facets'] ?: '';
        unset($params['facets']);
        $data = $this->stringToList($data);
        foreach ($data as $key => $value) {
            list($term, $label) = array_map('trim', explode('|', $value . '|'));
            if (isset($fields[$term])) {
                $params['facets'][$term] = [
                    'enabled' => true,
                    'weight' => $key + 1,
                    'display' => [
                        'label' => $label ?: $term,
                    ],
                ];
            }
        }

        $fields = $adapter->getAvailableSortFields($index);

        $data = $params['sort_fields'] ?: '';
        unset($params['sort_fields']);
        $data = $this->stringToList($data);
        foreach ($data as $key => $value) {
            list($term, $label) = array_map('trim', explode('|', $value . '|'));
            if (isset($fields[$term])) {
                $params['sort_fields'][$term] = [
                    'enabled' => true,
                    'weight' => $key + 1,
                    'display' => [
                        'label' => $label ?: $term,
                    ],
                ];
            }
        }

        return $params;
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

    /**
     * Compare fields to be sorted, with enabled fields first, and by weight.
     *
     * @param array $a First value
     * @param array $b Second value
     * @return int -1, 0, 1.
     */
    protected function sortByEnabledFirst($a, $b): int
    {
        // Sort by availability.
        if (isset($a['enabled']) && isset($b['enabled'])) {
            if ($a['enabled'] > $b['enabled']) {
                return -1;
            } elseif ($a['enabled'] < $b['enabled']) {
                return 1;
            }
        } elseif (isset($a['enabled'])) {
            return -1;
        } elseif (isset($b['enabled'])) {
            return 1;
        }

        // In other cases, sort by weight.
        if (isset($a['weight']) && isset($b['weight'])) {
            return $a['weight'] == $b['weight']
                ? 0
                : ($a['weight'] < $b['weight'] ? -1 : 1);
        } elseif (isset($a['weight'])) {
            return -1;
        } elseif (isset($b['weight'])) {
            return 1;
        }
        return 0;
    }

    /**
     * Get each line of a string separately.
     */
    protected function stringToList($string): array
    {
        return array_filter(array_map('trim', explode("\n", $this->fixEndOfLine($string))));
    }

    /**
     * Clean the text area from end of lines.
     *
     * This method fixes Windows and Apple copy/paste from a textarea input.
     */
    protected function fixEndOfLine($string): string
    {
        return str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], (string) $string);
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
