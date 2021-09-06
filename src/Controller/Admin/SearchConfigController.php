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

        $view = new ViewModel([
            'form' => $form,
        ]);
        if (!$this->checkPostAndValidForm($form)) {
            return $view;
        }

        $formData = $form->getData();
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $response = $this->api()->create('search_configs', $formData);
        $searchConfig = $response->getContent();

        $this->messenger()->addSuccess(new Message(
            'Search page "%s" created.', // @translate
            $searchConfig->name()
        ));
        $this->manageSearchConfigOnSites(
            $searchConfig,
            $formData['manage_config_default'] ?: [],
            $formData['manage_config_availability']
        );
        if (!in_array($formData['manage_config_availability'], ['disable', 'enable'])
            && empty($formData['manage_config_default'])
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
        $id = $this->params('id');

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->api()->read('search_configs', ['id' => $id])->getContent();

        $data = $searchConfig->jsonSerialize();
        $data = json_decode(json_encode($searchConfig), true);
        $data['manage_config_default'] = $this->sitesWithSearchConfig($searchConfig);
        $data['o:engine'] = empty($data['o:engine']['o:id']) ? null : $data['o:engine']['o:id'];

        $form = $this->getForm(SearchConfigForm::class);
        $form->setData($data);

        $view = new ViewModel([
            'form' => $form,
        ]);

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
            $formData['manage_config_default'] ?: [],
            $formData['manage_config_availability']
        );

        return $this->redirect()->toRoute('admin/search');
    }

    /**
     * @fixme Simplify to use a normal search config form with integrated elements checks.
     */
    public function configureAction()
    {
        $id = $this->params('id');

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->api()->read('search_configs', $id)->getContent();

        $view = new ViewModel([
            'searchConfig' => $searchConfig,
        ]);

        $engine = $searchConfig->engine();
        $adapter = $engine ? $engine->adapter() : null;
        if (empty($adapter)) {
            $message = new Message(
                'The engine adapter "%s" is unavailable.', // @translate
                $engine->adapterLabel()
            );
            $this->messenger()->addError($message); // @translate
            return $view;
        }

        $form = $this->getConfigureForm($searchConfig);
        if (empty($form)) {
            $message = new Message(
                'This engine adapter "%s" has no config form.', // @translate
                $engine->adapterLabel()
            );
            $this->messenger()->addWarning($message); // @translate
            return $view;
        }

        $searchConfigSettings = $searchConfig->settings() ?: [];

        $formSettings = $this->prepareDataForForm($searchConfigSettings);

        $form->setData($formSettings);
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

        $params = $this->prepareDataToSave($params);

        $searchConfig = $searchConfig->getEntity();
        $searchConfig->setSettings($params);
        $this->entityManager->flush();

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
            $searchConfigName = $this->api()->read('search_configs', $id)->getContent()->name();
            if ($form->isValid()) {
                $this->api()->delete('search_configs', $this->params('id'));
                $this->messenger()->addSuccess(new Message(
                    'Search page "%s" successfully deleted', // @translate
                    $searchConfigName
                ));
            } else {
                $this->messenger()->addError(new Message(
                    'Search page "%s" could not be deleted', // @translate
                    $searchConfigName
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
        return $searchConfig->engine()
            ? $this->getForm(SearchConfigConfigureForm::class, ['search_config' => $searchConfig])
            : null;
    }

    protected function sitesWithSearchConfig(SearchConfigRepresentation $searchConfig): array
    {
        $result = [];
        $searchConfigId = $searchConfig->id();

        // Check admin.
        $adminSearchId = $this->settings()->get('advancedsearch_main_config');
        if ($adminSearchId && $adminSearchId == $searchConfigId) {
            $result[] = 'admin';
        }

        // Check all sites.
        $settings = $this->siteSettings();
        $sites = $this->api()->search('sites')->getContent();
        foreach ($sites as $site) {
            $settings->setTargetId($site->id());
            $siteSearchId = $settings->get('advancedsearch_main_config');
            if ($siteSearchId && $siteSearchId == $searchConfigId) {
                $result[] = $site->id();
            }
        }

        return $result;
    }

    /**
     * Set the config for all sites.
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
                $settings->set('advancedsearch_main_config', $searchConfigId);
                $searchConfigs = $settings->get('advancedsearch_configs', []);
                $searchConfigs[] = $searchConfigId;
                $searchConfigs = array_unique(array_filter(array_map('intval', $searchConfigs)));
                sort($searchConfigs);
                $settings->set('advancedsearch_configs', $searchConfigs);

                $message = 'The page has been set by default in admin board.'; // @translate
            } else {
                $settings->set('advancedsearch_main_config', null);
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
            $searchConfigs = $siteSettings->get('advancedsearch_configs', []);
            $current = in_array($siteId, $currentMainSearchConfigForSites);
            $new = $allSites || in_array($siteId, $newMainSearchConfigForSites);
            if ($current !== $new) {
                if ($new) {
                    $siteSettings->set('advancedsearch_main_config', $searchConfigId);
                    $searchConfigs[] = $searchConfigId;
                } else {
                    $siteSettings->set('advancedsearch_main_config', null);
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
            $siteSettings->set('advancedsearch_configs', $searchConfigs);
        }

        $this->messenger()->addSuccess($message);
    }

    /**
     * Adapt the settings for the form to be edited.
     *
     * @todo Adapt the settings via the form itself.
     * @see data/search_configs/default.php
     */
    protected function prepareDataForForm(array $settings): array
    {
        // Ok search.
        // Ok resource_fields.
        // Ok autosuggest.
        // Ok sort.
        // Ok facet.

        // Fix form.
        $settings['form']['filters'] = $settings['form']['filters'] ?? [];
        if (empty($settings['form']['filters'])) {
            return $settings;
        }
        $keyAdvancedFilter = false;
        foreach ($settings['form']['filters'] as $keyFilter => $filter) {
            if (empty($filter['type'])) {
                continue;
            }
            if ($filter['type'] === 'Advanced') {
                $keyAdvancedFilter = $keyFilter;
                break;
            }
        }
        if ($keyAdvancedFilter === false) {
            return $settings;
        }

        $advanced = $settings['form']['filters'][$keyAdvancedFilter];
        $settings['form']['advanced'] = $advanced['fields'];
        $settings['form']['max_number'] = $advanced['max_number'];
        $settings['form']['field_joiner'] = $advanced['field_joiner'];
        $settings['form']['field_operator'] = $advanced['field_operator'];
        $settings['form']['filters'][$keyAdvancedFilter] = [
            'field' => 'advanced',
            'label' => 'Filters',
            'type' => 'Advanced',
        ];

        return $settings;
    }

    /**
     * Adapt the settings from the form to be saved.
     *
     * @todo Adapt the settings via the form itself.
     * @see data/search_configs/default.php
     */
    protected function prepareDataToSave(array $params): array
    {
        unset($params['csrf']);

        $params = $this->removeAvailableFields($params);

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

        // Normalize filters.
        $inputTypes = [
            'advanced' => 'Advanced',
            'checkbox' => 'Checkbox',
            // 'date' => 'Date',
            'daterange' => 'DateRange',
            // 'daterangestartend' => 'DateRangeStartEnd',
            'hidden' => 'Hidden',
            'multicheckbox' => 'MultiCheckbox',
            'noop' => 'Noop',
            'number' => 'Number',
            // 'numberrange' => 'NumberRange',
            // 'place' => 'Place',
            'radio' => 'Radio',
            'select' => 'Select',
            'selectflat' => 'SelectFlat',
            'text' => 'Text',
        ];

        // The field "advanced" is only for display, so save it with filters.
        // TODO No more include advanced fields in filters, but still cleaning.
        $params['form']['filters'] = $params['form']['filters'] ?? [];
        $advanced = $params['form']['advanced'] ?? [];
        $keyAdvanced = false;
        foreach ($params['form']['filters'] as $keyFilter => $filter) {
            if (empty($filter['field'])) {
                unset($params['form']['filters'][$keyFilter]);
                continue;
            }
            $filterField = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $filter['type'] ?? 'Noop'));
            $params['form']['filters'][$keyFilter]['type'] = $inputTypes[$filterField] ?? ucfirst($filterField);
            if ($filter['type'] === 'Advanced') {
                if ($keyAdvanced !== false) {
                    unset($params['form']['filters'][$keyAdvanced]);
                }
                $keyAdvanced = $keyFilter;
            }
        }
        $params['form']['filters'] = array_values($params['form']['filters']);

        if ($keyAdvanced === false) {
            if (!$advanced) {
                unset(
                    $params['form']['advanced'],
                    $params['form']['max_number'],
                    $params['form']['field_joiner'],
                    $params['form']['field_operator']
                );
                return $params;
            }
            $params['form']['filters'][] = [
                'field' => 'advanced',
                'label' => $this->translate('Filters'), // @translate
                'type' => 'Advanced',
            ];
            $keyAdvanced = key(array_slice($params['form']['filters'], -1, 1, true));
        }

        $params['form']['filters'][$keyAdvanced]['fields'] = $advanced;
        $params['form']['filters'][$keyAdvanced]['max_number'] = $params['form']['max_number'] ?? 5;
        $params['form']['filters'][$keyAdvanced]['field_joiner'] = $params['form']['field_joiner'] ?? false;
        $params['form']['filters'][$keyAdvanced]['field_operator'] = $params['form']['field_operator'] ?? false;
        unset(
            $params['form']['advanced'],
            $params['form']['max_number'],
            $params['form']['field_joiner'],
            $params['form']['field_operator']
        );

        // TODO Store the final form as an array to be created via factory. https://docs.laminas.dev/laminas-form/v3/form-creation/creation-via-factory/

        return $params;
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
}
