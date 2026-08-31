<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau, 2018-2026
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

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Form\Admin\SearchConfigConfigureForm;
use AdvancedSearch\Form\Admin\SearchConfigFacetFieldset;
use AdvancedSearch\Form\Admin\SearchConfigFilterFieldset;
use AdvancedSearch\Form\Admin\SearchConfigForm;
use AdvancedSearch\Stdlib\SearchResources;
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

    public function __construct(
        EntityManager $entityManager,
        FormElementManager $formElementManager
    ) {
        $this->entityManager = $entityManager;
        $this->formElementManager = $formElementManager;
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

        $this->messenger()->addSuccess((new PsrMessage(
            'Search page {name} created.', // @translate
            ['name' => $searchConfig->link($searchConfig->name(), 'edit')]
        ))->setEscapeHtml(false));
        $this->manageSearchConfigSettings(
            $searchConfig,
            $formData['manage_config_availability'] ?: [],
            $formData['manage_config_default'] ?: []
        );
        if (!in_array($formData['manage_config_availability'], ['disable', 'enable'])
            && in_array($formData['manage_config_default'], ['', 'let'])
        ) {
            $this->messenger()->addWarning('You can enable this page in your site settings or in admin settings.'); // @translate
        }

        $this->recommendSolrSyncMaps($searchConfig);

        return $this->redirect()->toUrl($searchConfig->adminUrl('edit'));
    }

    public function editAction()
    {
        $id = $this->params('id');

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->api()->read('search_configs', ['id' => $id])->getContent();

        $view = new ViewModel([
            'searchConfig' => $searchConfig,
        ]);

        $searchEngine = $searchConfig->searchEngine();
        $engineAdapter = $searchEngine ? $searchEngine->engineAdapter() : null;
        // Adapter of the engine before save, to warn when it changes.
        $previousAdapterClass = $engineAdapter ? get_class($engineAdapter) : null;
        if (empty($engineAdapter)) {
            $message = new PsrMessage(
                'The engine adapter "{label}" is unavailable.', // @translate
                ['label' => $searchEngine ? $searchEngine->engineAdapterLabel() : '']
            );
            $this->messenger()->addError($message);
            return $view;
        }

        $form = $this->getConfigureForm($searchConfig);
        if (empty($form)) {
            $message = new PsrMessage(
                'This engine adapter "{label}" has no config form.', // @translate
                ['label' => $searchEngine->engineAdapterLabel()]
            );
            $this->messenger()->addWarning($message);
            return $view;
        }
        $form->setFormElementManager($this->formElementManager);

        $scalar = json_decode(json_encode($searchConfig), true);
        $data = $this->prepareDataForForm($searchConfig->settings() ?: []);
        $data['settings'] = [
            'o:name' => $scalar['o:name'] ?? '',
            'o:slug' => $scalar['o:slug'] ?? '',
            'o:search_engine' => empty($scalar['o:search_engine']['o:id']) ? null : $scalar['o:search_engine']['o:id'],
            'o:form_adapter' => $scalar['o:form_adapter'] ?? null,
        ];
        $defaults = $this->sitesWithSearchConfigAsDefault($searchConfig);
        $adminDefault = in_array('admin', $defaults, true);
        $data['sites'] = [
            'manage_config_default' => array_values(array_diff($defaults, ['admin'])),
            'manage_config_availability' => $this->sitesWithSearchConfigAsAvailable($searchConfig),
            'manage_config_default_admin' => $adminDefault ? '1' : '0',
        ];

        $form->setData($data);
        $view->setVariable('form', $form);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $params = $this->getRequest()->getPost()->toArray();
        $params = $this->removeUselessFields($params);

        if (!$this->checkPostAndValidForm($form, $params)) {
            return $view;
        }

        $params = $form->getData();

        $settingsPart = $params['settings'] ?? [];
        $sitesPart = $params['sites'] ?? [];
        unset($params['settings'], $params['sites']);

        $settings = $this->prepareDataToSave($params);

        $this->validateFilters($searchConfig, $settings);

        // Validate facets.
        if (($settings['facet']['mode'] ?? 'button') === 'button'
            && ($settings['facet']['display_submit'] ?? 'none') === 'none'
        ) {
            $this->messenger()->addWarning(new PsrMessage(
                'The mode for facets is "Button", but the button "Apply facets" is hidden, so it should be added in the theme.' // @translate
            ));
        }

        $scalarData = [
            'o:name' => $settingsPart['o:name'] ?? $searchConfig->name(),
            'o:slug' => $settingsPart['o:slug'] ?? $searchConfig->slug(),
            'o:search_engine' => $settingsPart['o:search_engine'] ?? null,
            'o:form_adapter' => $settingsPart['o:form_adapter'] ?? null,
        ];
        $searchConfig = $this->api()
            ->update('search_configs', $id, $scalarData, [], ['isPartial' => true])
            ->getContent();

        // Adapt field references and suggester when the engine changed, so the
        // configuration stays consistent with the new engine's naming.
        $newAdapter = $searchConfig->searchEngine()
            ? $searchConfig->searchEngine()->engineAdapter()
            : null;
        $newAdapterClass = $newAdapter ? get_class($newAdapter) : null;
        $engineChanged = $previousAdapterClass && $newAdapterClass
            && $previousAdapterClass !== $newAdapterClass;
        $adaptReport = $engineChanged
            ? $this->adaptConfigToEngine($settings, $searchEngine, $searchConfig->searchEngine())
            : [];

        $searchConfigEntity = $searchConfig->getEntity();
        $searchConfigEntity->setSettings($settings);
        $this->entityManager->flush();

        $defaults = $sitesPart['manage_config_default'] ?? [];
        if (!empty($sitesPart['manage_config_default_admin'])) {
            $defaults[] = 'admin';
        }
        $this->manageSearchConfigSettings(
            $searchConfig,
            $sitesPart['manage_config_availability'] ?? [],
            $defaults
        );

        $this->messenger()->addSuccess((new PsrMessage(
            'Search page {name} saved.', // @translate
            ['name' => $searchConfig->link($searchConfig->name(), 'edit')]
        ))->setEscapeHtml(false));

        // Report the engine change and its automatic adaptation.
        if ($engineChanged) {
            $this->warnEngineChanged($adaptReport);
        }

        // For a Solr engine (including after a switch to Solr), recommend
        // syncing maps for the new fields, then reindexing.
        $this->recommendSolrSyncMaps($searchConfig);

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

    public function copyAction()
    {
        $id = $this->params('id');

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->api()->read('search_configs', ['id' => $id])->getContent();

        // Find a unique name and slug.
        $baseName = $searchConfig->name();
        $baseSlug = $searchConfig->slug();

        // Get all existing slugs.
        $slugs = $this->api()
            ->search('search_configs', [], ['returnScalar' => 'slug'])
            ->getContent();

        // Generate unique name and slug with increment.
        $newName = sprintf($this->translate('%s (copy)'), $baseName);
        $newSlug = $this->generateUniqueSlug($baseSlug, $slugs);

        // Prepare data for the new search config.
        $data = [
            'o:name' => $newName,
            'o:slug' => $newSlug,
            'o:search_engine' => $searchConfig->searchEngine() ? $searchConfig->searchEngine()->id() : null,
            'o:form_adapter' => $searchConfig->formAdapterName(),
            'o:settings' => $searchConfig->settings(),
        ];

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $newSearchConfig */
        $response = $this->api()->create('search_configs', $data);
        $newSearchConfig = $response->getContent();

        $this->messenger()->addSuccess((new PsrMessage(
            'Search page "{name}" successfully copied as "{new_name}".', // @translate
            ['name' => $searchConfig->name(), 'new_name' => $newSearchConfig->name()]
        )));

        return $this->redirect()->toUrl($newSearchConfig->adminUrl('edit'));
    }

    /**
     * Generate a unique slug based on existing slugs.
     *
     * @param string $baseSlug The base slug to use.
     * @param array $existingSlugs List of existing slugs.
     * @return string A unique slug.
     */
    protected function generateUniqueSlug(string $baseSlug, array $existingSlugs): string
    {
        // Remove any existing numeric suffix like "_2", "_3", etc.
        $cleanSlug = preg_replace('/_\d+$/', '', $baseSlug);

        $counter = 2;
        $newSlug = $cleanSlug . '_' . $counter;

        while (in_array($newSlug, $existingSlugs)) {
            $counter++;
            $newSlug = $cleanSlug . '_' . $counter;
        }

        return $newSlug;
    }

    protected function checkPostAndValidForm($form, ?array $params = null): bool
    {
        if (!$this->getRequest()->isPost()) {
            return false;
        }

        if ($params === null) {
            $params = $this->params()->fromPost();
        }
        $id = (int) $this->params('id');
        // Slug may be at root (add form) or nested under "settings" (edit
        // form).
        $slug = $params['settings']['o:slug'] ?? $params['o:slug'] ?? '';

        $slugs = $this->api()
            ->search('search_configs', [], ['returnScalar' => 'slug'])
            ->getContent();
        if (in_array($slug, $slugs)) {
            if (!$id) {
                $this->messenger()->addError('The slug should be unique.'); // @translate
                return false;
            }
            try {
                $searchConfigId = (int) $this->api()->read('search_configs', ['slug' => $slug])->getContent()->id();
            } catch (\Throwable $e) {
                $searchConfigId = null;
            }
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
     * Adapt a config to a new engine: rename field references and reset a
     * suggester that no longer belongs to the engine.
     *
     * The reliable translation is Solr → internal: a raw Solr index name is
     * mapped back to its property term (understood by every engine) via the
     * previous core maps. A list of Solr fields for one alias becomes the list
     * of aggregated properties, and vice versa. The other direction only checks
     * that the target Solr core can index the property (else it is reported as
     * unresolved, to be fixed with "Sync maps"). Settings are passed by
     * reference; the returned report lists what was renamed, what stays
     * unresolved, and whether the suggester was reset.
     */
    protected function adaptConfigToEngine(
        array &$settings,
        ?\AdvancedSearch\Api\Representation\SearchEngineRepresentation $previousEngine,
        ?\AdvancedSearch\Api\Representation\SearchEngineRepresentation $newEngine
    ): array {
        $report = ['renamed' => [], 'review' => [], 'unresolved' => [], 'suggester_reset' => false];

        $solrFieldToProperty = $this->solrFieldToProperty($previousEngine);
        $newSolrProperties = $this->solrSourceProperties($newEngine);

        $adapt = function ($field) use ($solrFieldToProperty, $newSolrProperties, &$report) {
            if (!is_string($field) || $field === '') {
                return $field;
            }
            // Solr index name → its source. Only remap to a plain property term
            // (prefix:local), understood by every engine. A structured source
            // such as "item_set/o:id" or "resource_class/o:term" differs
            // between engines, so it is reported for manual review, not renamed
            // blindly.
            if ($solrFieldToProperty !== null && isset($solrFieldToProperty[$field])) {
                $source = $solrFieldToProperty[$field];
                if ($source !== '' && $source !== $field) {
                    if ($this->isPropertyTerm($source)) {
                        $report['renamed'][$field] = $source;
                        $field = $source;
                    } else {
                        $report['review'][$field] = $source;
                        return $field;
                    }
                }
            }
            // A property term the target Solr core cannot index yet.
            if ($newSolrProperties !== null
                && $this->isPropertyTerm($field)
                && !isset($newSolrProperties[$field])
            ) {
                $report['unresolved'][$field] = true;
            }
            return $field;
        };

        foreach ($settings['index']['aliases'] ?? [] as $name => $alias) {
            if (!empty($alias['fields']) && is_array($alias['fields'])) {
                $settings['index']['aliases'][$name]['fields'] = array_values(array_unique(array_map($adapt, $alias['fields'])));
            }
        }
        foreach ($settings['facet']['facets'] ?? [] as $name => $facet) {
            if (!empty($facet['field'])) {
                $settings['facet']['facets'][$name]['field'] = $adapt($facet['field']);
            }
        }

        // Reset a suggester that does not belong to the new engine.
        $suggesterId = (int) ($settings['q']['suggester'] ?? 0);
        if ($suggesterId && $newEngine) {
            $owned = $this->api()->search('search_suggesters', [
                'id' => $suggesterId,
                'engine_id' => $newEngine->id(),
            ], ['returnScalar' => 'id'])->getContent();
            if (!$owned) {
                $settings['q']['suggester'] = null;
                $report['suggester_reset'] = true;
            }
        }

        return $report;
    }

    /**
     * Map of Solr index name => property term for a Solr engine core, or null
     * when the engine is not Solr.
     */
    protected function solrFieldToProperty(?\AdvancedSearch\Api\Representation\SearchEngineRepresentation $engine): ?array
    {
        // A core is a facet of its solarium engine: the core id is the
        // engine id.
        $coreId = $engine && $engine->engineAdapterName() === 'solarium' ? $engine->id() : null;
        if (!$coreId) {
            return null;
        }
        try {
            $maps = $this->api()->search('solr_maps', ['engine_id' => $coreId])->getContent();
        } catch (\Exception $e) {
            return null;
        }
        $result = [];
        foreach ($maps as $map) {
            $result[$map->fieldName()] = $map->source();
        }
        return $result;
    }

    /**
     * Set of property terms a Solr engine core can index (source and property
     * aliases), or null when the engine is not Solr.
     */
    protected function solrSourceProperties(?\AdvancedSearch\Api\Representation\SearchEngineRepresentation $engine): ?array
    {
        $coreId = $engine && $engine->engineAdapterName() === 'solarium' ? $engine->id() : null;
        if (!$coreId) {
            return null;
        }
        try {
            $maps = $this->api()->search('solr_maps', ['engine_id' => $coreId])->getContent();
        } catch (\Exception $e) {
            return [];
        }
        $result = [];
        foreach ($maps as $map) {
            $source = $map->source();
            if ($this->isPropertyTerm($source)) {
                $result[$source] = true;
            }
            $alias = (string) $map->alias();
            if ($this->isPropertyTerm($alias)) {
                $result[$alias] = true;
            }
        }
        return $result;
    }

    /**
     * Emit the messenger warnings after an engine change adaptation.
     */
    protected function warnEngineChanged(array $report): void
    {
        $messenger = $this->messenger();
        $messenger->addWarning(new PsrMessage(
            'The search engine type changed. Field names use different conventions per engine (for example "dcterms:subject" versus "dcterms_subject_ss"); the configuration was adapted automatically, but please review facets, sorts and filters.' // @translate
        ));
        if (!empty($report['renamed'])) {
            $messenger->addNotice(new PsrMessage(
                'Fields renamed to their property term: {list}.', // @translate
                ['list' => implode(', ', array_map(fn ($k, $v) => $k . ' → ' . $v, array_keys($report['renamed']), $report['renamed']))]
            ));
        }
        if (!empty($report['review'])) {
            $messenger->addWarning(new PsrMessage(
                'These special Solr fields have no direct equivalent in the other engine and need manual review: {list}.', // @translate
                ['list' => implode(', ', array_map(fn ($k, $v) => $k . ' (' . $v . ')', array_keys($report['review']), $report['review']))]
            ));
        }
        if (!empty($report['unresolved'])) {
            $messenger->addWarning(new PsrMessage(
                'These fields have no Solr map and return nothing until you align the maps on the core: {list}.', // @translate
                ['list' => implode(', ', array_keys($report['unresolved']))]
            ));
        }
        if (!empty($report['suggester_reset'])) {
            $messenger->addWarning(new PsrMessage(
                'The autocompletion suggester did not belong to the new engine and was reset; select a suggester of the new engine.' // @translate
            ));
        }
    }

    /**
     * Whether a value is a plain property term "prefix:local" (e.g.
     * "dcterms:subject"), excluding structured sources like "item_set/o:id".
     */
    protected function isPropertyTerm(string $value): bool
    {
        return (bool) preg_match('~^[a-zA-Z][\w-]*:[a-zA-Z][\w-]*$~', $value);
    }

    /**
     * For a Solr config, recommend syncing the core maps then reindexing, so
     * the fields used by the config are indexed.
     *
     * A one-shot session flag: the search manager page, loaded after the
     * redirect, displays the recommendation in a sidebar with the sync and
     * reindex actions.
     */
    protected function recommendSolrSyncMaps(SearchConfigRepresentation $searchConfig): void
    {
        $searchEngine = $searchConfig->searchEngine();
        if (!$searchEngine
            || !($searchEngine->engineAdapter() instanceof \SearchSolr\EngineAdapter\Solarium)
        ) {
            return;
        }
        // A core is a facet of its solarium engine: the core id is the
        // engine id.
        $session = new \Laminas\Session\Container('AdvancedSearch');
        $session->recommendSync = [
            'solr_core_id' => $searchEngine->id(),
            'search_engine_id' => $searchEngine->id(),
            'search_config_name' => $searchConfig->name(),
        ];
    }

    /**
     * Check if the configuration should use simple or visual form and get it.
     */
    protected function getConfigureForm(SearchConfigRepresentation $searchConfig): ?\AdvancedSearch\Form\Admin\SearchConfigConfigureForm
    {
        $searchEngine = $searchConfig->searchEngine();
        if (!$searchEngine) {
            return null;
        }
        // Only the suggesters of the config engine are valid for it.
        $suggesters = [];
        foreach ($this->api()->search('search_suggesters', ['engine_id' => $searchEngine->id()])->getContent() as $suggester) {
            $suggesters[$suggester->id()] = $suggester->name();
        }
        return $this->getForm(SearchConfigConfigureForm::class, [
            'search_config' => $searchConfig,
            'suggesters' => $suggesters,
        ]);
    }

    protected function sitesWithSearchConfigAsDefault(SearchConfigRepresentation $searchConfig): array
    {
        $result = [];
        $searchConfigId = $searchConfig->id();

        // Check admin.
        $adminSearchId = (int) $this->settings()->get('advancedsearch_main_config');
        if ($adminSearchId && $adminSearchId === $searchConfigId) {
            $result[] = 'admin';
        }

        // Check all sites.
        $siteSettings = $this->siteSettings();
        $sites = $this->api()->search('sites')->getContent();
        foreach ($sites as $site) {
            $siteId = $site->id();
            $siteSearchId = (int) $siteSettings->get('advancedsearch_main_config', null, $siteId);
            if ($siteSearchId && $siteSearchId === $searchConfigId) {
                $result[] = $siteId;
            }
        }

        return $result;
    }

    protected function sitesWithSearchConfigAsAvailable(SearchConfigRepresentation $searchConfig): array
    {
        $result = [];
        $searchConfigId = $searchConfig->id();

        $siteSettings = $this->siteSettings();
        $sites = $this->api()->search('sites')->getContent();
        foreach ($sites as $site) {
            $siteId = $site->id();
            $searchConfigIdsForSite = $siteSettings->get('advancedsearch_configs', [], $siteId);
            if (in_array($searchConfigId, $searchConfigIdsForSite)) {
                $result[] = $siteId;
            }
        }

        return $result;
    }

    /**
     * Set the search config for admin and sites.
     */
    protected function manageSearchConfigSettings(
        SearchConfigRepresentation $searchConfig,
        array $searchConfigSiteAvailabilities,
        array $searchConfigSiteDefaults
    ): void {
        $searchConfigId = $searchConfig->id();
        $searchConfigSiteDefaultsCurrent = $this->sitesWithSearchConfigAsDefault($searchConfig);

        // Check default config first in order to add it as available config.

        // Manage admin settings.
        $settings = $this->settings();

        $current = in_array('admin', $searchConfigSiteDefaultsCurrent);
        $new = in_array('admin', $searchConfigSiteDefaults);
        if ($current !== $new) {
            if ($new) {
                $settings->set('advancedsearch_main_config', $searchConfigId);
                $message = 'The page has been set by default in admin board.'; // @translate
            } else {
                $settings->set('advancedsearch_main_config', null);
                $message = 'The page has been unset in admin board.'; // @translate
            }
            $this->messenger()->addSuccess($message);
        }

        // Manage site settings.
        /** @var \Omeka\Settings\SiteSettings $siteSettings */
        $siteSettings = $this->siteSettings();

        $allDefaults = [];
        $allAvailables = [];
        $defaultForAllSitesAdded = in_array('all', $searchConfigSiteDefaults);
        $defaultForAllSitesRemoved = in_array('none', $searchConfigSiteDefaults);
        $availabilityForAllSitesEnabled = in_array('enable', $searchConfigSiteAvailabilities);
        $availabilityForAllSitesDisabled = in_array('disable', $searchConfigSiteAvailabilities);

        /** @var \Omeka\Api\Representation\SiteRepresentation[] $sites */
        $sites = $this->api()->search('sites')->getContent();
        foreach ($sites as $site) {
            $siteId = $site->id();
            $prevDefaultForSite = (int) $siteSettings->get('advancedsearch_main_config', null, $siteId);
            $setDefaultForSite = $defaultForAllSitesAdded
                || in_array($siteId, $searchConfigSiteDefaults);
            if ($setDefaultForSite) {
                $siteSettings->set('advancedsearch_main_config', $searchConfigId, $siteId);
            } elseif ($defaultForAllSitesRemoved || $prevDefaultForSite === $searchConfigId) {
                $siteSettings->set('advancedsearch_main_config', null, $siteId);
            }
            if ($siteSettings->get('advancedsearch_main_config', null, $siteId) === $searchConfigId) {
                $allDefaults[] = $site->slug();
            }

            $searchConfigIdsForSite = $siteSettings->get('advancedsearch_configs', [], $siteId);
            $prevAvailableForSite = in_array($searchConfigId, $searchConfigIdsForSite);
            $setAvailableForSite = $setDefaultForSite
                || $availabilityForAllSitesEnabled
                || in_array($siteId, $searchConfigSiteAvailabilities);
            if ($setAvailableForSite) {
                $searchConfigIdsForSite[] = $searchConfigId;
            } elseif ($availabilityForAllSitesDisabled || $prevAvailableForSite) {
                $searchConfigIdsForSite = array_diff($searchConfigIdsForSite, [$searchConfigId]);
            }
            $searchConfigIdsForSite = array_unique(array_filter(array_map('intval', $searchConfigIdsForSite)));
            sort($searchConfigIdsForSite);
            $siteSettings->set('advancedsearch_configs', $searchConfigIdsForSite, $siteId);
            if (in_array($searchConfigId, $searchConfigIdsForSite)) {
                $allAvailables[] = $site->slug();
            }
        }

        if ($allDefaults) {
            $this->messenger()->addSuccess(new PsrMessage(
                'This search config is the default one in sites: {site_slugs}.', // @translate
                ['site_slugs' => implode(', ', $allDefaults)]
            ));
        } else {
            $this->messenger()->addSuccess(new PsrMessage(
                'This search config is not used as default in any site.' // @translate
            ));
        }

        if ($allAvailables) {
            $this->messenger()->addSuccess(new PsrMessage(
                'This search config is available in sites: {site_slugs}.', // @translate
                ['site_slugs' => implode(', ', $allAvailables)]
            ));
        } else {
            $this->messenger()->addSuccess(new PsrMessage(
                'This search config is not available in any site.' // @translate
            ));
        }
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

        // Legacy: the settings of the advanced filter were a separate section.
        if (!empty($settings['form']['advanced']) && isset($settings['form']['filters']['advanced'])) {
            $settings['form']['filters']['advanced'] += $settings['form']['advanced'];
        }
        unset($settings['form']['advanced']);

        foreach ($settings['form']['filters'] ?? [] as $key => $fieldset) {
            // The name of filters are used as key and should be unique.
            $settings['form']['filters'][$key]['name'] = $key;
            // The promoted options and attributes are fields of the form;
            // the key in the textarea takes precedence over the field.
            foreach (SearchConfigFilterFieldset::PROMOTED_OPTIONS as $k) {
                if (isset($fieldset['options'][$k])) {
                    $settings['form']['filters'][$key][$k] = $fieldset['options'][$k];
                    unset($settings['form']['filters'][$key]['options'][$k]);
                }
            }
            foreach (SearchConfigFilterFieldset::PROMOTED_ATTRIBUTES as $k) {
                if (isset($fieldset['attributes'][$k])) {
                    $settings['form']['filters'][$key][$k] = $fieldset['attributes'][$k];
                    unset($settings['form']['filters'][$key]['attributes'][$k]);
                }
            }
            // The checkbox "first digits" is checked by default.
            $settings['form']['filters'][$key]['first_digits'] = ($settings['form']['filters'][$key]['first_digits'] ?? true) === true
                || in_array($settings['form']['filters'][$key]['first_digits'], [1, '1', 'true'], true);
            // The variants of the select are a type with two options in the
            // form: multiple choices and layout of the values.
            $type = $fieldset['type'] ?? '';
            if (in_array($type, SearchConfigFilterFieldset::TYPES_SELECT, true)) {
                $settings['form']['filters'][$key]['type'] = 'Select';
                $settings['form']['filters'][$key]['multiple'] = strpos($type, 'Multi') === 0;
                $settings['form']['filters'][$key]['value_layout'] = substr($type, -4) === 'Flat' ? 'flat' : (substr($type, -5) === 'Group' ? 'group' : '');
            }
            // The elements of a row of the advanced filter are a multi
            // checkbox in the form and booleans in the settings.
            if (($fieldset['type'] ?? '') === 'Advanced') {
                $elements = [];
                foreach (['field_joiner' => 'joiner', 'field_joiner_not' => 'joiner_not', 'field_operator' => 'operator', 'field_value_autosuggest' => 'autosuggest'] as $k => $v) {
                    if (!empty($fieldset[$k])) {
                        $elements[] = $v;
                    }
                }
                $settings['form']['filters'][$key]['field_elements'] = $elements;
            }
            // Set specific types options.
            $type = $settings['form']['filters'][$key]['type'] ?? '';
            if ($type && !isset($filterTypes[$type])) {
                $settings['form']['filters'][$key]['type'] = 'Specific';
                $settings['form']['filters'][$key]['options'] = ['type' => $type]
                    + ($settings['form']['filters'][$key]['options'] ?? []);
            }
        }
        $settings['form']['filters'] = array_values($settings['form']['filters'] ?? []);

        // The sort selector is stored with the name and the label, edited as
        // pairs "name => label".
        $sortList = [];
        foreach ($settings['results']['sort_list'] ?? [] as $name => $sort) {
            $sortList[$name] = is_array($sort) ? (string) ($sort['label'] ?? '') : (string) $sort;
        }
        $settings['results']['sort_list'] = $sortList;

        // The properties to display may be a simple list (no labels) or a
        // map "term => label" for the form.
        $properties = $settings['results']['properties'] ?? [];
        if ($properties && array_is_list($properties)) {
            $settings['results']['properties'] = array_fill_keys($properties, '');
        }

        $facetInputs = [
            'field',
            'field_end',
            'label',
            'type',
            'language_site',
            'languages',
            'order',
            'limit',
            'state',
            'paginate',
            'more',
            'per_page',
            'display_count',
            'as_link',
            'thesaurus',
            'min',
            'max',
            'step',
            'first_digits',
            'options',
            'attributes',
            // Slider scale settings: dedicated form fields, must not be moved
            // into "options" (which is the free-form IniTextarea).
            'scale_mode',
            'scale_breakpoints',
            'scale_show_ticks',
            // Boolean buckets filter: dedicated form field.
            'boolean_filter',
            // Value labels: dedicated form fields, must not be moved into the
            // free-form "options" IniTextarea (would create a duplicate entry).
            'value_labels',
            'value_labels_table',
        ];
        $settings['facet']['mode'] = in_array($settings['facet']['mode'] ?? null, ['button', 'link', 'js']) ? $settings['facet']['mode'] : 'button';
        foreach ($settings['facet']['facets'] ?? [] as $key => $facet) {
            // The bounds are fields of the form; the key in the attributes
            // takes precedence over the field.
            foreach (['min', 'max', 'step'] as $k) {
                if (isset($facet['attributes'][$k])) {
                    $facet[$k] = $facet['attributes'][$k];
                    unset($facet['attributes'][$k]);
                }
            }
            // The checkbox "first digits" is checked by default.
            $facet['first_digits'] = ($facet['first_digits'] ?? $facet['integer'] ?? true) === true
                || in_array($facet['first_digits'] ?? $facet['integer'] ?? null, [1, '1', 'true'], true);
            unset($facet['integer']);
            // Remove the mode of each facet to simplify config: it is stored
            // in each facet to simplify theming, but it is a global option.
            unset($facet['mode']);
            // Simplify some values too (integer and boolean).
            if (isset($facet['display_count'])) {
                $facet['display_count'] = (bool) $facet['display_count'];
            }
            foreach (['limit', 'more', 'per_page', 'min', 'max'] as $k) {
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

        // The properties to display are stored as a simple list when no
        // custom label is set.
        $properties = $params['results']['properties'] ?? [];
        if (is_array($properties)) {
            $properties = array_filter($properties, fn ($v, $k) => trim((string) $k) !== '', ARRAY_FILTER_USE_BOTH);
            $params['results']['properties'] = array_filter(array_map('trim', $properties), 'strlen')
                ? array_map('trim', $properties)
                : array_keys($properties);
        }

        $params = $this->removeUselessFields($params);

        if (isset($params['search']['default_query'])) {
            $params['search']['default_query'] = trim($params['search']['default_query'] ?? '', "? \t\n\r\0\x0B");
        }

        if (isset($params['search']['default_query_post'])) {
            $params['search']['default_query_post'] = trim($params['search']['default_query_post'] ?? '', "? \t\n\r\0\x0B");
        }

        // Normalize hidden query filters from the legacy URL format (e.g.
        // property[N][property|type|text]) to the flat format consumable by
        // InternalQuerier::filterQueryAny(). Stored once at save-time so the
        // runtime path stays simple.
        if (!empty($params['request']['hidden_query_filters']) && is_array($params['request']['hidden_query_filters'])) {
            $params['request']['hidden_query_filters'] = SearchResources::normalizeHiddenQueryFilters($params['request']['hidden_query_filters']);
        }

        // Set name as key and move all specific types to options. The name is
        // optional: it is derived from the field below when empty.
        $filters = [];
        $j = 0;
        foreach ($params['form']['filters'] ?? [] as $filter) {
            $name = trim($filter['name'] ?? '');
            unset($filter['name']);
            if (empty($filter['field']) && !$name) {
                continue;
            }
            $type = $filter['type'] ?? '';
            if ($type === 'Specific') {
                $filter['type'] = $filter['options']['type'] ?? '';
                unset($filter['options']['type']);
            }
            // A numeric key is replaced by the field below.
            $filters[$name !== '' ? $name : $j++] = $filter;
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

            // The type is a key of the list of types, else a specific one.
            $type = $filter['type'] ?? '';
            $type = isset($filterTypes[$type]) ? $type : ucfirst($type);

            // Key is always "advanced" for advanced filters, so no duplicate.
            // Its settings are stored with the filter.
            if ($type === 'Advanced') {
                $name = 'advanced';
                $filter = [
                    'field' => 'advanced',
                    'label' => $filter['label'] ?? '',
                    'type' => 'Advanced',
                ] + $filter;
                $filter['default_number'] = isset($filter['default_number']) ? (int) $filter['default_number'] : 1;
                $filter['max_number'] = isset($filter['max_number']) ? (int) $filter['max_number'] : 10;
                $elements = isset($filter['field_elements']) ? (array) $filter['field_elements'] : [];
                unset($filter['field_elements']);
                $filter['field_joiner'] = in_array('joiner', $elements);
                $filter['field_joiner_not'] = in_array('joiner_not', $elements);
                $filter['field_operator'] = in_array('operator', $elements);
                $filter['field_operators'] = isset($filter['field_operators']) ? (array) $filter['field_operators'] : [];
                $filter['field_value_autosuggest'] = in_array('autosuggest', $elements);
                // Move the fields as last key for end user.
                $advancedFields = $filter['fields'] ?? [];
                unset($filter['fields']);
                $filter['fields'] = $advancedFields;
            } elseif ($type === '') {
                unset($filter['type']);
            }

            // The manual list of values has its own field.
            if (!empty($filter['options']['value_options']) && empty($filter['values'])) {
                $filter['values'] = $this->normalizeManualValues($filter['options']['value_options']);
                unset($filter['options']['value_options']);
            }

            // The settings of the groups the type does not use are removed.
            $filter = $this->cleanSettingsByType($filter, $type, [
                [SearchConfigFilterFieldset::TYPES_LIST, SearchConfigFilterFieldset::SETTINGS_LIST],
                [SearchConfigFilterFieldset::TYPES_RANGE, SearchConfigFilterFieldset::SETTINGS_RANGE],
                [SearchConfigFilterFieldset::TYPES_SLIDER, SearchConfigFilterFieldset::SETTINGS_SLIDER],
                [['Advanced'], SearchConfigFilterFieldset::SETTINGS_ADVANCED],
                [['text'], SearchConfigFilterFieldset::SETTINGS_TEXT],
                [['Hidden'], SearchConfigFilterFieldset::SETTINGS_HIDDEN],
                [['Checkbox', 'HasValue'], array_unique(array_merge(SearchConfigFilterFieldset::SETTINGS_CHECKBOX, SearchConfigFilterFieldset::SETTINGS_HAS_VALUE))],
                [['Thesaurus'], SearchConfigFilterFieldset::SETTINGS_THESAURUS],
                [['Number', 'Range', 'RangeDouble'], SearchConfigFilterFieldset::SETTINGS_NUMBER],
            ]);
            if ($type === 'HasValue') {
                unset($filter['unchecked_value']);
            } elseif ($type === 'Checkbox') {
                unset($filter['query_type'], $filter['value_label']);
            }

            // The promoted fields are stored as options or attributes of the
            // filter; a key set in the textarea takes precedence.
            foreach (SearchConfigFilterFieldset::PROMOTED_OPTIONS as $k) {
                $v = $filter[$k] ?? null;
                unset($filter[$k]);
                if ($k === 'first_digits') {
                    // Enabled by default: store only when disabled.
                    if (in_array($type, ['Number', 'Range', 'RangeDouble'], true)
                        && !$v
                        && !isset($filter['options'][$k])
                    ) {
                        $filter['options'][$k] = false;
                    }
                    continue;
                }
                if ($k === 'autosuggest' || $k === 'thesaurus') {
                    $v = $v ? ($k === 'autosuggest' ? true : (int) $v) : null;
                }
                if ($v !== null && $v !== '' && !isset($filter['options'][$k])) {
                    $filter['options'][$k] = $v;
                }
            }
            foreach (SearchConfigFilterFieldset::PROMOTED_ATTRIBUTES as $k) {
                $v = $filter[$k] ?? null;
                unset($filter[$k]);
                if ($v !== null && $v !== '' && !isset($filter['attributes'][$k])) {
                    $filter['attributes'][$k] = $v;
                }
            }

            // The type Select is recomposed with its two options.
            if ($type === 'Select') {
                $layout = $filter['value_layout'] ?? '';
                $filter['type'] = (empty($filter['multiple']) ? '' : 'Multi')
                    . 'Select'
                    . ($layout === 'flat' ? 'Flat' : ($layout === 'group' ? 'Group' : ''));
            }
            unset($filter['multiple'], $filter['value_layout']);

            foreach ($filter as $k => $v) {
                if ($v === null || $v === '' || $v === []) {
                    unset($filter[$k]);
                }
            }

            $name = is_numeric($name) ? $field : $name;
            // Name is no more forcet to lower case, only slugified.
            $name = $this->slugify($name);
            if ($name !== 'advanced' && isset($filters[$name])) {
                $name .= '_' . ++$i;
            }

            $filters[$name] = $filter;
        }

        $params['form']['filters'] = $filters;
        // The settings of the advanced filter are stored with it now.
        unset($params['form']['advanced']);

        // The sort selector is a list of pairs "name => label" in the form,
        // stored with the name and the label.
        $sortList = [];
        foreach ($params['results']['sort_list'] ?? [] as $name => $sort) {
            if (is_array($sort)) {
                // Legacy fieldsets of the collection.
                if (!empty($sort['name'])) {
                    $sortList[$sort['name']] = $sort;
                }
                continue;
            }
            $name = trim((string) $name);
            if ($name !== '') {
                $sortList[$name] = ['name' => $name, 'label' => trim((string) $sort)];
            }
        }
        $params['results']['sort_list'] = $sortList;

        // Three possible modes: button, link, checkbox js as link.
        $facetMode = in_array($params['facet']['mode'] ?? null, ['button', 'link', 'js']) ? $params['facet']['mode'] : 'button';
        $warnLanguage = false;
        $facets = [];
        $i = 0;
        foreach ($params['facet']['facets'] ?? [] as $name => $facet) {
            if (empty($facet['field'])) {
                unset($params['facet']['facets'][$name]);
                continue;
            }
            $field = $facet['field'];
            // Check the name: it should be the field name, except in case of a
            // duplicate, normally never in real use cases.
            // Use field as name: the standard form does not allow to set a
            // specific name, unlike filters.
            // TODO Manage use of duplicated facets with a name suffixed with an index.
            // Name is no more forcet to lower case, only slugified.
            $name = $this->slugify($field);
            if (isset($facets[$name])) {
                $name .= '_' . ++$i;
            }
            // There can be only one mode for all facets, so add the mode to
            // each facet to simplify theme.
            $facet['mode'] = $facetMode;
            // Move specific settings to the root of the array. Keys with
            // dedicated form fields must not be overridden by stale entries
            // coming from the "options" IniTextarea.
            $reservedKeys = [
                'boolean_filter',
                'field_end',
                'paginate',
                'language_site',
                'languages',
                'thesaurus',
                'min',
                'max',
                'step',
                'first_digits',
                'scale_mode',
                'scale_breakpoints',
                'scale_show_ticks',
                'value_labels',
                'value_labels_table',
            ];
            foreach ($facet['options'] as $k => $v) {
                if (in_array($k, $reservedKeys, true) && array_key_exists($k, $facet)) {
                    continue;
                }
                $facet[$k] = $v;
            }
            unset($facet['options']);
            // The settings of the groups the type does not use are removed.
            $facet = $this->cleanSettingsByType($facet, $facet['type'] ?? 'Checkbox', [
                [SearchConfigFacetFieldset::TYPES_LIST, SearchConfigFacetFieldset::SETTINGS_LIST],
                [SearchConfigFacetFieldset::TYPES_VALUES, SearchConfigFacetFieldset::SETTINGS_VALUES],
                [SearchConfigFacetFieldset::TYPES_LINK, SearchConfigFacetFieldset::SETTINGS_LINK],
                [SearchConfigFacetFieldset::TYPES_SLIDER, SearchConfigFacetFieldset::SETTINGS_SLIDER],
                [SearchConfigFacetFieldset::TYPES_BOUNDS, SearchConfigFacetFieldset::SETTINGS_BOUNDS],
                [['Thesaurus'], SearchConfigFacetFieldset::SETTINGS_THESAURUS],
            ]);
            // The option "first digits" is enabled by default: store only
            // when disabled.
            if (array_key_exists('first_digits', $facet)) {
                if ($facet['first_digits'] && $facet['first_digits'] !== '0') {
                    unset($facet['first_digits']);
                } else {
                    $facet['first_digits'] = false;
                }
            }
            if (isset($facet['thesaurus'])) {
                $facet['thesaurus'] = (int) $facet['thesaurus'] ?: null;
            }
            // Simplify some values (empty string, integer and boolean).
            foreach (['display_count', 'paginate', 'as_link'] as $k) {
                if (isset($facet[$k])) {
                    $facet[$k] = (bool) $facet[$k];
                }
            }
            foreach (['limit', 'more', 'per_page', 'min', 'max'] as $k) {
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
     * Warn if filters and advanced fields match existing engine index.
     */
    protected function validateFilters(SearchConfigRepresentation $searchConfig, array $params): void
    {
        $engine = $searchConfig->searchEngine();
        if (!$engine) {
            return;
        }

        $engineAdapter = $engine->engineAdapter();
        if (!$engineAdapter) {
            return;
        }

        $availableFields = $engineAdapter->getAvailableFields();

        // Check standard filters.

        $fields = $params['form']['filters'] ?? [];
        if (empty($fields)) {
            return;
        }

        // Manage the exception for "advanced".
        $advanced = $fields['advanced'] ?? null;
        unset($fields['advanced']);

        if (count($fields)) {
            // Don't use the key, but the key field in each value, because the key
            // may not be an index.
            $fields = array_column($fields, 'field', 'field');
            $result = array_intersect_key($fields, $availableFields);

            if (!count($result)) {
                $this->messenger()->addError(
                    'The indexes of the filters are not present in the search engine.' // @translate
                );
                return;
            } elseif (count($result) !== count($fields)) {
                $this->messenger()->addError(new PsrMessage(
                    'Some indexes of the filters are not present in the search engine: {list}', // @translate
                    ['list' => implode(', ', array_keys(array_diff_key($fields, $availableFields)))]
                ));
                return;
            }
        }

        // Check advanced filters too.

        if (empty($advanced)) {
            return;
        }

        $fieldsAdvanced = $advanced['fields'] ?? [];
        if (!$fieldsAdvanced) {
            $this->messenger()->addError(
                'The list of fields of the advanced filters is not configured.' // @translate
            );
            return;
        }

        $result = array_intersect_key($fieldsAdvanced, $availableFields);

        if (!count($result)) {
            $this->messenger()->addError(
                'The indexes of the advanced filters are not present in the search engine.' // @translate
            );
            return;
        } elseif (count($result) !== count($fieldsAdvanced)) {
            $this->messenger()->addError(new PsrMessage(
                'Some indexes of the advanced filters are not present in the search engine: {list}', // @translate
                ['list' => implode(', ', array_keys(array_diff_key($fieldsAdvanced, $availableFields)))]
            ));
            return;
        }
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
     * Remove empty params except labels and params starting with "available_".
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
            'results' => 'sort_list',
            'facet' => 'facets',
        ];
        foreach ($collections as $mainName => $name) {
            $list = $params[$mainName][$name] ?? [];
            // The list may be an unparsed textarea (raw post).
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $key => $data) {
                if (is_array($data)) {
                    unset($data['minus'], $data['plus'], $data['up'], $data['down']);
                    $params[$mainName][$name][$key] = $data;
                }
            }
        }

        return $params;
    }

    /**
     * Transform the given string into a valid URL slug.
     *
     * Unlike site slug slugify, replace forbidden characters with "_", and
     * don't start with a number. The ":" is allowed too (like terms).
     * Furthermore, the slug case is kept.
     *
     * @see \Omeka\Api\Adapter\SiteSlugTrait::slugify()
     * @see \AdvancedSearch\Controller\Admin\SearchConfigController::slugify()
     * @see \BlockPlus\Module::slugify()
     */
    /**
     * Remove the settings of the groups that the type does not use.
     *
     * @param array $groups List of [types, settings]: the settings are kept
     * only when the type is one of the types.
     */
    /**
     * Normalize the legacy manual list of values into an array "value => label".
     *
     * The legacy option "value_options" was a list of values, without label:
     * only the values were used, whatever the keys.
     */
    protected function normalizeManualValues($values): array
    {
        $result = [];
        foreach ((array) $values as $value) {
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value !== '') {
                $result[$value] = '';
            }
        }
        return $result;
    }

    protected function cleanSettingsByType(array $settings, string $type, array $groups): array
    {
        foreach ($groups as [$types, $keys]) {
            if (!in_array($type, $types, true)) {
                foreach ($keys as $key) {
                    unset($settings[$key]);
                }
            }
        }
        return $settings;
    }

    protected function slugify($input): string
    {
        if (extension_loaded('intl')) {
            static $transliterator;
            $transliterator ??= \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
            $slug = (string) $transliterator->transliterate((string) $input);
        } elseif (extension_loaded('iconv')) {
            $slug = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $input);
        } else {
            $slug = (string) $input;
        }
        // Don't lowercase string here.
        // $slug = mb_strtolower($slug, 'UTF-8');
        $slug = preg_replace('/[^a-zA-Z0-9_:]+/u', '_', $slug);
        $slug = preg_replace('/^\d+$/', '_', $slug);
        $slug = preg_replace('/_{2,}/', '_', $slug);
        $slug = preg_replace('/_*$/', '', $slug);
        return $slug;
    }
}
