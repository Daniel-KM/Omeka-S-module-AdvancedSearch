<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau, 2018-2024
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
use AdvancedSearch\Form\Admin\SearchConfigFilterFieldset;
use AdvancedSearch\Form\Admin\SearchConfigForm;
use AdvancedSearch\FormAdapter\Manager as SearchFormAdapterManager;
use Common\Stdlib\PsrMessage;
use Doctrine\ORM\EntityManager;
use Laminas\Form\FormElementManager;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;

class SearchConfigController extends AbstractActionController
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Laminas\Form\FormElementManager;
     */
    protected $formElementManager;

    /**
     * @var \AdvancedSearch\Adapter\Manager
     */
    protected $searchAdapterManager;

    /**
     * @var \AdvancedSearch\FormAdapter\Manager
     */
    protected $searchFormAdapterManager;

    public function __construct(
        EntityManager $entityManager,
        FormElementManager $formElementManager,
        SearchAdapterManager $searchAdapterManager,
        SearchFormAdapterManager $searchFormAdapterManager
    ) {
        $this->entityManager = $entityManager;
        $this->formElementManager = $formElementManager;
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

        $this->messenger()->addSuccess(new PsrMessage(
            'Search page "{name}" created.', // @translate
            ['name' => $searchConfig->name()]
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

        // $data = $searchConfig->jsonSerialize();
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

        $this->messenger()->addSuccess(new PsrMessage(
            'Search page "{name}" saved.', // @translate
            ['name' => $searchConfig->name()]
        ));

        $this->manageSearchConfigOnSites(
            $searchConfig,
            $formData['manage_config_default'] ?: [],
            $formData['manage_config_availability']
        );

        return $this->redirect()->toRoute('admin/search-manager');
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
            $message = new PsrMessage(
                'The engine adapter "{label}" is unavailable.', // @translate
                ['label' => $engine->adapterLabel()]
            );
            $this->messenger()->addError($message); // @translate
            return $view;
        }

        $form = $this->getConfigureForm($searchConfig);
        $form->setFormElementManager($this->formElementManager);
        if (empty($form)) {
            $message = new PsrMessage(
                'This engine adapter "{label}" has no config form.', // @translate
                ['label' => $engine->adapterLabel()]
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
        $params = $this->removeUselessFields($params);

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

        $this->messenger()->addSuccess(new PsrMessage(
            'Configuration "{name}" saved.', // @translate
            ['name' => $searchConfig->getName()]
        ));

        return $this->redirect()->toRoute('admin/search-manager');
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
                $this->messenger()->addSuccess(new PsrMessage(
                    'Search page "{name}" successfully deleted', // @translate
                    ['name' => $searchConfigName]
                ));
            } else {
                $this->messenger()->addError(new PsrMessage(
                    'Search page "{name}" could not be deleted', // @translate
                    ['name' => $searchConfigName]
                ));
            }
        }
        return $this->redirect()->toRoute('admin/search-manager');
    }

    protected function checkPostAndValidForm($form): bool
    {
        if (!$this->getRequest()->isPost()) {
            return false;
        }

        // Check if the name of the slug is single in the database.
        $params = $this->params()->fromPost();
        $id = (int) $this->params('id');
        $slug = $params['o:slug'];

        $slugs = $this->api()
            ->search('search_configs', [], ['returnScalar' => 'slug'])
            ->getContent();
        if (in_array($slug, $slugs)) {
            if (!$id) {
                $this->messenger()->addError('The slug should be unique.'); // @translate
                return false;
            }
            $searchConfigId = (int) $this->api()
                ->searchOne('search_configs', ['slug' => $slug], ['returnScalar' => 'id'])
                ->getContent();
            if ($id !== $searchConfigId) {
                $this->messenger()->addError('The slug should be unique.'); // @translate
                return false;
            }
        }

        if (strpos($slug, 'https:') === 0 || strpos($slug, 'http:') === 0) {
            $this->messenger()->addError('The slug should be relative to the root of the site, like "search".'); // @translate
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
     *
     * @see data/search_configs/default.php
     */
    protected function prepareDataForForm(array $settings): array
    {
        $filterTypes = $this->getFormFilterTypes();

        foreach ($settings['form']['filters'] ?? [] as $key => $fieldset) {
            // The name of filters are used as key and should be unique.
            $settings['form']['filters'][$key]['name'] = $key;
            // Set specific types options.
            $type = $fieldset['type'] ?? '';
            if ($type && !isset($filterTypes[$type])) {
                $settings['form']['filters'][$key]['type'] = 'Specific';
                $settings['form']['filters'][$key]['options'] = ['type' => $type]
                    + ($settings['form']['filters'][$key]['options'] ?? []);
            }
        }
        $settings['form']['filters'] = array_values($settings['form']['filters'] ?? []);

        $facetInputs = [
            'field',
            'label',
            'type',
            'order',
            'limit',
            'state',
            'more',
            'display_count',
        ];
        $settings['facet']['mode'] = in_array($settings['facet']['mode'] ?? null, ['link', 'js']) ? $settings['facet']['mode'] : 'button';
        foreach ($settings['facet']['facets'] ?? [] as $key => $facet) {
            // Remove the mode of each facet to simplify config.
            unset($facet['mode']);
            // Simplify some values too (integer and boolean).
            if (isset($facet['display_count'])) {
                $facet['display_count'] = (bool) $facet['display_count'];
            }
            foreach (['limit', 'more', 'min', 'max'] as $k) {
                if (isset($facet[$k])) {
                    if ($facet[$k] === '') {
                        unset($facet[$k]);
                    } else {
                        $facet[$k] = (int) $facet[$k];
                    }
                }
            }
            // Move specific settings to options.
            foreach ($facet as $k => $v) {
                if (!in_array($k, $facetInputs)) {
                    $facet['options'][$k] = $v;
                }
            }
            $settings['facet']['facets'][$key] = $facet;
        }

        return $settings;
    }

    /**
     * Adapt the settings from the form to be saved.
     *
     * @todo Adapt the settings via the form itself.
     * @see data/search_configs/default.php
     *
     * @todo Store the final form as an array to be created via factory. https://docs.laminas.dev/laminas-form/v3/form-creation/creation-via-factory/
     */
    protected function prepareDataToSave(array $params): array
    {
        unset($params['csrf']);

        $params = $this->removeUselessFields($params);

        if (isset($params['search']['default_query'])) {
            $params['search']['default_query'] = trim($params['search']['default_query'] ?? '', "? \t\n\r\0\x0B");
        }

        if (isset($params['search']['default_query_post'])) {
            $params['search']['default_query_post'] = trim($params['search']['default_query_post'] ?? '', "? \t\n\r\0\x0B");
        }

        // Set name as key and move all specific types to options.
        $filters = [];
        foreach ($params['form']['filters'] ?? [] as $filter) {
            $name = trim($filter['name'] ?? '');
            if ($name) {
                unset($filter['name']);
                $type = $filter['type'] ?? '';
                if ($type === 'Specific') {
                    $filter['type'] = $filter['options']['type'] ?? '';
                    unset($filter['options']['type']);
                }
                $filters[$name] = $filter;
            }
        }
        $params['form']['filters'] = $filters;

        // Normalize filters.
        $filterTypes = $this->getFormFilterTypes();

        $filters = [];
        $i = 0;
        foreach ($params['form']['filters'] ?? [] as $name => $filter) {
            if (empty($filter['field'])) {
                continue;
            }

            $field = $filter['field'];

            $type = $filter['type'] ?? '';
            $type = $filterTypes[$type] ?? ucfirst($type);

            // Key is always "advanced" for advanced filters, so no duplicate.
            if ($type === 'Advanced') {
                $name = 'advanced';
                $filter = [
                    'field' => 'advanced',
                    'label' => $filter['label'] ?? '',
                    'type' => 'Advanced',
                ] + $filter;
            } elseif ($type === '') {
                unset($filter['type']);
            }

            foreach ($filter as $k => $v) {
                if ($v === null || $v === '' || $v === []) {
                    unset($filter[$k]);
                }
            }

            $name = is_numeric($name) ? $field : $name;
            $name = $this->slugify($name);
            if ($name !== 'advanced' && isset($filters[$name])) {
                $name .= '_' . ++$i;
            }

            $filters[$name] = $filter;
        }

        $advanced = $params['form']['advanced'] ?? [];
        if ($advanced) {
            // Normalize some keys.
            $advanced['default_number'] = isset($advanced['default_number']) ? (int) $advanced['default_number'] : 1;
            $advanced['max_number'] = isset($advanced['max_number']) ? (int) $advanced['max_number'] : 10;
            $advanced['field_joiner'] = isset($advanced['field_joiner']) ? !empty($advanced['field_joiner']) : true;
            $advanced['field_joiner_not'] = isset($advanced['field_joiner_not']) ? !empty($advanced['field_joiner_not']) : true;
            $advanced['field_operator'] = isset($advanced['field_operator']) ? !empty($advanced['field_operator']) : true;
            $advanced['field_operators'] = isset($advanced['field_operators']) ? (array) $advanced['field_operators'] : [];
            // Move advanced fields as last key for end user.
            $advancedFields = $advanced['fields'] ?? [];
            unset($advanced['fields']);
            $advanced['fields'] = $advancedFields;
        }

        $params['form']['filters'] = $filters;
        $params['form']['advanced'] = $advanced;

        $sortList = [];
        foreach ($params['display']['sort_list'] ?? [] as $sort) {
            if (!empty($sort['name'])) {
                $sortList[$sort['name']] = $sort;
            }
        }
        $params['display']['sort_list'] = $sortList;

        $facetMode = in_array($params['facet']['mode'] ?? null, ['link', 'js']) ? $params['facet']['mode'] : 'button';
        $warnLanguage = false;
        $facets = [];
        $i = 0;
        foreach ($params['facet']['facets'] ?? [] as $name => $facet) {
            if (empty($facet['field'])) {
                unset($params['facet']['facets'][$name]);
                continue;
            }
            $field = $facet['field'];
            $name = is_numeric($name) ? $field : $name;
            $name = $this->slugify($name);
            if (isset($facets[$name])) {
                $name .= '_' . ++$i;
            }
            // Add the mode to each facet to simplify theme.
            $facet['mode'] = $facetMode;
            // Move specific settings to the root of the array.
            foreach ($facet['options'] as $k => $v) {
                $facet[$k] = $v;
            }
            unset($facet['options']);
            // Simplify some values (empty string, integer and boolean).
            if (isset($facet['display_count'])) {
                $facet['display_count'] = (bool) $facet['display_count'];
            }
            foreach (['limit', 'more', 'min', 'max'] as $k) {
                if (isset($facet[$k])) {
                    if ($facet[$k] === '') {
                        unset($facet[$k]);
                    } else {
                        $facet[$k] = (int) $facet[$k];
                    }
                }
            }
            foreach ($facet as $k => $v) {
                if ($v === null || $v === '' || $v === []) {
                    unset($facet[$k]);
                }
            }
            // Add a warning for languages of facets because it may be a hard to
            // understand issue.
            if (!empty($facet['languages'])) {
                if (is_string($facet['languages'])) {
                    $facet['languages'] = explode('|', $facet['languages']);
                }
                $facet['languages'] = array_values(array_unique(array_map('trim', $facet['languages'])));
                if (!empty($facet['languages']) && !in_array('', $facet['languages'])) {
                    $warnLanguage = true;
                }
            }
            foreach ($facet as $k => $v) {
                if ($v === null || $v === '' || $v === []) {
                    unset($facet[$k]);
                }
            }
            // TODO Explode array options ("|" and "," are supported) early or keep user input?
            $facets[$name] = $facet;
        }
        $params['facet']['facets'] = $facets;

        if ($warnLanguage) {
            $this->messenger()->addWarning(
                'Note that you didn’t set an empty language for some facets, so all values without language will be skipped in the facet.' // @translate
            );
        }

        return $params;
    }

    /**
     * Get the list of filter types.
     */
    protected function getFormFilterTypes(): array
    {
        // Some types are specific and set as option.
        $fieldset = $this->getForm(SearchConfigFilterFieldset::class);
        $types = $fieldset->get('type')->getOption('value_options');
        $types += $types['modules']['options'];
        unset($types['modules']);
        return $types;
    }

    /**
     * Remove empty params and all params starting with "available_".
     */
    protected function removeUselessFields(array $params): array
    {
        foreach ($params as $k => $v) {
            if (strpos($k, 'label') !== false) {
                continue;
            }
            if ($v === null || $v === '' || $v === []) {
                unset($params[$k]);
            } elseif (is_array($v)) {
                foreach ($v as $kk => $vv) {
                    if (strpos($kk, 'label') !== false) {
                        continue;
                    }
                    if ($vv === null || $vv === '' || $vv === []) {
                        unset($params[$k][$kk]);
                    }
                }
            }
        }

        $removeNames = ['minus', 'plus', 'up', 'down'];
        foreach ($params as $name => $values) {
            if (in_array($name, $removeNames)
                || substr($name, 0, 10) === 'available_'
            ) {
                unset($params[$name]);
            } elseif (is_array($values)) {
                foreach (array_keys($values) as $subName) {
                    if (in_array($subName, $removeNames)
                        || substr($subName, 0, 10) === 'available_'
                    ) {
                        unset($params[$name][$subName]);
                    }
                }
            }
        }

        $collections = [
            'form' => 'filters',
            'display' => 'sort_list',
            'facet' => 'facets',
        ];
        foreach ($collections as $mainName => $name) {
            foreach ($params[$mainName][$name] ?? [] as $key => $data) {
                unset($data['minus'], $data['plus'], $data['up'], $data['down']);
                $params[$mainName][$name][$key] = $data;
            }
        }

        return $params;
    }

    /**
     * Transform the given string into a valid URL slug.
     *
     * Unlike site slug slugify, replace with "_" and don't start with a number.
     *
     * @see \Omeka\Api\Adapter\SiteSlugTrait::slugify()
     * @see \AdvancedSearch\Controller\Admin\SearchConfigController::slugify()
     * @see \BlockPlus\Module::slugify()
     */
    protected function slugify($input): string
    {
        if (extension_loaded('intl')) {
            $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
            $slug = $transliterator->transliterate((string) $input);
        } elseif (extension_loaded('iconv')) {
            $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $input);
        } else {
            $slug = (string) $input;
        }
        $slug = mb_strtolower((string) $slug, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9_]+/u', '_', $slug);
        $slug = preg_replace('/^\d+$/', '_', $slug);
        $slug = preg_replace('/_{2,}/', '_', $slug);
        $slug = preg_replace('/_*$/', '', $slug);
        return $slug;
    }
}
