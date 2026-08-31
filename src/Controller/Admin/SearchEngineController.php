<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
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

use AdvancedSearch\EngineAdapter\Manager as EngineAdapterManager;
use AdvancedSearch\Entity\SearchEngine;
use AdvancedSearch\Form\Admin\SearchEngineConfigureForm;
use AdvancedSearch\Form\Admin\SearchEngineForm;
use Common\Stdlib\PsrMessage;
use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use AdvancedSearch\Api\Representation\SearchEngineRepresentation;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;

class SearchEngineController extends AbstractActionController
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \AdvancedSearch\EngineAdapter\Manager
     */
    protected $engineAdapterManager;

    public function __construct(
        EntityManager $entityManager,
        EngineAdapterManager $engineAdapterManager
    ) {
        $this->entityManager = $entityManager;
        $this->engineAdapterManager = $engineAdapterManager;
    }

    public function addAction()
    {
        $form = $this->getForm(SearchEngineForm::class);
        $view = new ViewModel([
            'form' => $form,
        ]);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if (!$form->isValid()) {
                $this->messenger()->addError('There was an error during validation'); // @translate
                return $view;
            }
            $formData = $form->getData();
            $searchEngine = $this->api()->create('search_engines', $formData)->getContent();
            $this->messenger()->addSuccess(new PsrMessage(
                'Search index "{name}" created.', // @translate
                ['name' => $searchEngine->name()]
            ));
            return $this->redirect()->toUrl($searchEngine->adminUrl('edit'));
        }
        return $view;
    }

    /**
     * Create a ready-to-use internal engine in one click, for the guided empty
     * state of the search manager. The internal adapter needs no external
     * service, so no configuration is required.
     */
    public function addInternalAction()
    {
        foreach ($this->api()->search('search_engines')->getContent() as $engine) {
            if ($engine->engineAdapterName() === 'internal') {
                $this->messenger()->addNotice('An internal search engine already exists.'); // @translate
                return $this->redirect()->toRoute('admin/search-manager');
            }
        }
        $searchEngine = $this->api()->create('search_engines', [
            'o:name' => 'Internal', // @translate
            'o:engine_adapter' => 'internal',
            'o:settings' => [
                'resource_types' => ['items', 'item_sets'],
            ],
        ])->getContent();
        $this->messenger()->addSuccess(new PsrMessage(
            'Search index "{name}" created. You can now add a search page.', // @translate
            ['name' => $searchEngine->name()]
        ));
        return $this->redirect()->toRoute('admin/search-manager');
    }

    /**
     * Create an engine from the search page form, without leaving it (json).
     *
     * The backend is "internal" or "solarium:{coreId}". The engine is created
     * with minimal settings; the api validations (single internal engine, one
     * engine per core) apply and their messages are returned on failure.
     */
    public function addQuickAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->jsonError($this->translate('Method not allowed.'), 405); // @translate
        }
        $csrf = new \Laminas\Validator\Csrf(['name' => 'quick_engine_csrf', 'timeout' => 3600]);
        if (!$csrf->isValid((string) $this->params()->fromPost('quick_engine_csrf'))) {
            return $this->jsonError($this->translate('Invalid or missing CSRF token.'), 400); // @translate
        }

        $backend = (string) $this->params()->fromPost('backend');
        $name = trim((string) $this->params()->fromPost('o:name'));
        $data = [
            'o:settings' => ['resource_types' => ['items', 'item_sets']],
        ];
        // A new Solr backend is created on the core add page (it needs a
        // connection): only the internal engine is quick-creatable here.
        if ($backend === 'internal') {
            $data['o:name'] = $name ?: 'Internal';
            $data['o:engine_adapter'] = 'internal';
        } else {
            return $this->jsonError($this->translate('Select a backend.'), 400); // @translate
        }

        try {
            $searchEngine = $this->api()->create('search_engines', $data)->getContent();
        } catch (\Omeka\Api\Exception\ValidationException $e) {
            $messages = [];
            foreach ($e->getErrorStore()->getErrors() as $errors) {
                foreach ($errors as $error) {
                    $messages[] = $this->translate((string) $error);
                }
            }
            return $this->jsonError(implode(' ', $messages) ?: $this->translate('Invalid data.'), 422); // @translate
        }

        return new JsonModel([
            'status' => 'success',
            'data' => [
                'id' => $searchEngine->id(),
                'name' => $searchEngine->name(),
            ],
        ]);
    }

    protected function jsonError(string $message, int $statusCode): JsonModel
    {
        $this->getResponse()->setStatusCode($statusCode);
        return new JsonModel([
            'status' => 'fail',
            'data' => ['message' => $message],
        ]);
    }

    public function editAction()
    {
        $id = $this->params('id');

        /**
         * @var \AdvancedSearch\Entity\SearchEngine $searchEngine
         * @var \AdvancedSearch\EngineAdapter\EngineAdapterInterface $engineAdapter
         * @var \AdvancedSearch\Form\Admin\SearchEngineConfigureForm $form
         */
        $searchEngine = $this->entityManager->find(\AdvancedSearch\Entity\SearchEngine::class, $id);
        if (!$searchEngine) {
            $this->messenger()->addError(new PsrMessage(
                'The search engine #{search_engine_id} does not exist.', // @translate
                ['search_engine_id' => $id]
            ));
            return $this->redirect()->toRoute('admin/search-manager', ['action' => 'browse'], true);
        }
        $engineAdapterName = $searchEngine->getAdapter();
        if (!$this->engineAdapterManager->has($engineAdapterName)) {
            $this->messenger()->addError(new PsrMessage(
                'The engine adapter "{name}" is not available.', // @translate
                ['name' => $engineAdapterName]
            ));
            return $this->redirect()->toRoute('admin/search-manager', ['action' => 'browse'], true);
        }

        // Passing option requires a factory to avoids the error in laminas.
        $adapter = $this->engineAdapterManager->get($engineAdapterName);
        $isAdapterInternal = $adapter instanceof \AdvancedSearch\EngineAdapter\Internal;

        $form = $this->getForm(SearchEngineConfigureForm::class, [
            'search_engine_id' => $id,
            'is_adapter_internal' => $isAdapterInternal,
        ]);

        $adapterFieldset = $adapter->getConfigFieldset();
        if ($adapterFieldset) {
            $adapterFieldset
                ->setOption('search_engine_id', $id)
                ->setName('engine_adapter')
                ->setLabel('Engine adapter settings') // @translate
                ->init();
            $form->add($adapterFieldset);
        }
        $data = $searchEngine->getSettings() ?: [];
        $data['o:name'] = $searchEngine->getName();

        $form->setData($data);

        $view = new ViewModel([
            'form' => $form,
            'searchEngineId' => (int) $id,
            'engineAdapterName' => $engineAdapterName,
        ]);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if (!$form->isValid()) {
                $this->messenger()->addError('There was an error during validation'); // @translate
                return $view;
            }

            $formData = $form->getData();
            $name = $formData['o:name'];
            unset($formData['csrf'], $formData['o:name']);
            // Keep the settings managed outside of this form, in particular the
            // solr connection of a solarium engine.
            $formData = array_replace($searchEngine->getSettings() ?: [], $formData);
            $searchEngine
                ->setName($name)
                ->setSettings($formData);
            $this->entityManager->persist($searchEngine);
            $this->entityManager->flush();
            $this->messenger()->addSuccess(new PsrMessage(
                'Search index "{name}" successfully configured.',  // @translate
                ['name' => $searchEngine->getName()]
            ));

            if ($this->isIndexingEnabled($searchEngine)) {
                $this->messenger()->addWarning('Don’t forget to run the indexation of the search engine.'); // @translate
            }
            return $this->redirect()->toRoute('admin/search-manager', ['action' => 'browse'], true);
        }

        if (!$this->isIndexingEnabled($searchEngine)) {
            $this->messenger()->addWarning('Indexing is disabled for this search engine'); // @translate
        }

        return $view;
    }

    public function indexConfirmAction()
    {
        $searchEngine = $this->api()->read('search_engines', $this->params('id'))->getContent();

        $listJobStatusesByIds = $this->listJobStatusesByIds(\AdvancedSearch\Job\IndexSearch::class, true);

        $view = new ViewModel([
            'resourceLabel' => 'search index',
            'resource' => $searchEngine,
            'listJobStatusesByIds' => $listJobStatusesByIds,
        ]);
        return $view
            ->setTerminal(true)
            ->setTemplate('advanced-search/admin/search-engine/index-confirm-details');
    }

    /**
     * Adapted:
     * @see \AdvancedSearch\Controller\Admin\SearchEngineController::indexAction()
     * @see \AdvancedSearch\Module::runJobIndexSearch()
     *
     * {@inheritDoc}
     * @see \Laminas\Mvc\Controller\AbstractActionController::indexAction()
     */
    public function indexAction()
    {
        $searchEngineId = (int) $this->params('id');
        $searchEngine = $this->api()->read('search_engines', $searchEngineId)->getContent();

        $clearIndex = (bool) $this->params()->fromPost('clear_index');
        $clearFullIndex = (bool) $this->params()->fromPost('clear_full_index');
        $startResourceId = (int) $this->params()->fromPost('start_resource_id');
        $resourcesByBatch = (int) $this->params()->fromPost('resources_by_batch');
        $sleepAfterLoop = (int) $this->params()->fromPost('sleep_after_loop');
        $resourceTypes = $this->params()->fromPost('resource_types')
            ?: $searchEngine->setting('resource_types', []);
        $resourcesLimit = (int) $this->params()->fromPost('resources_limit');
        $resourcesOffset = (int) $this->params()->fromPost('resources_offset');
        $visibility = $this->params()->fromPost('visibility');
        $visibility = in_array($visibility, ['public', 'private']) ? $visibility : null;
        $force = (bool) $this->params()->fromPost('force');

        // Do a quick check if the engine can index at least one resource type.
        $canIndex = false;
        $indexer = $searchEngine->indexer();
        foreach ($resourceTypes as $resourceType) {
            if ($indexer->canIndex($resourceType)) {
                $canIndex = true;
                break;
            }
        }
        if (!$canIndex) {
            $message = new PsrMessage(
                'The search engine "{name}" has nothing to index.', // @translate
                ['name' => $searchEngine->name()]
            );
            $this->messenger()->addWarning($message);
            return $this->redirect()->toRoute('admin/search-manager', ['action' => 'browse'], true);
        }

        $jobArgs = [];
        $jobArgs['search_engine_ids'] = [$searchEngine->id()];
        $jobArgs['clear_index'] = $clearIndex;
        $jobArgs['clear_full_index'] = $clearFullIndex;
        $jobArgs['start_resource_id'] = $startResourceId;
        $jobArgs['resources_by_batch'] = $resourcesByBatch;
        $jobArgs['sleep_after_loop'] = $sleepAfterLoop;
        $jobArgs['resource_types'] = $resourceTypes;
        $jobArgs['resources_limit'] = $resourcesLimit;
        $jobArgs['resources_offset'] = $resourcesOffset;
        $jobArgs['visibility'] = $visibility;
        $jobArgs['force'] = $force;

        // Synchronous dispatcher for quick testing purpose.
        // $job = $this->jobDispatcher()->dispatch(\AdvancedSearch\Job\IndexSearch::class, $jobArgs, $searchEngine->getServiceLocator()->get('Omeka\Job\DispatchStrategy\Synchronous'));
        $job = $this->jobDispatcher()->dispatch(\AdvancedSearch\Job\IndexSearch::class, $jobArgs);

        $urlPlugin = $this->url();
        $message = new PsrMessage(
            'Indexing of "{name}" started in job {link_job}#{job_id}{link_end} ({link_log}logs{link_end}).', // @translate
            [
                'name' => $searchEngine->name(),
                'link_job' => sprintf('<a href="%1$s">', htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
                'link_log' => class_exists('Log\Module', false)
                    ? sprintf('<a href="%1$s">', htmlspecialchars($urlPlugin->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]])))
                    : sprintf('<a href="%1$s" target="_blank" rel="noopener noreferrer">', htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()]))),
            ]
        );
        $message->setEscapeHtml(false);
        $this->messenger()->addSuccess($message);

        return $this->redirect()->toRoute('admin/search-manager', ['action' => 'browse'], true);
    }

    public function deleteConfirmAction()
    {
        $response = $this->api()->read('search_engines', $this->params('id'));
        $searchEngine = $response->getContent();

        $view = new ViewModel([
            'resourceLabel' => 'search engine',
            'resource' => $searchEngine,
            'partialPath' => 'common/delete-confirm-search-engine',
            'dependencies' => $this->searchEngineDependencies($searchEngine),
        ]);
        return $view
            ->setTerminal(true)
            ->setTemplate('common/delete-confirm-details');
    }

    /**
     * Remove deleted search configs from the settings that point to them.
     *
     * The cascade of the database removes the configs, but not the settings
     * that store their ids, so a site would keep a page that does not exist.
     */
    protected function removeSearchConfigsFromSettings(array $searchConfigIds): void
    {
        $singleKeys = [
            'advancedsearch_main_config',
            'advancedsearch_api_config',
            'advancedsearch_items_config',
            'advancedsearch_media_config',
            'advancedsearch_item_sets_config',
            'advancedsearch_items_browse_config',
            'advancedsearch_item_sets_browse_config',
        ];

        $searchConfigIds = array_map('intval', $searchConfigIds);
        $cleanedSites = [];

        /** @var \Omeka\Settings\Settings $settings */
        $settings = $this->settings();
        foreach ($singleKeys as $key) {
            if (in_array((int) $settings->get($key), $searchConfigIds, true)) {
                $settings->set($key, null);
            }
        }

        /** @var \Omeka\Settings\SiteSettings $siteSettings */
        $siteSettings = $this->siteSettings();
        foreach ($this->api()->search('sites')->getContent() as $site) {
            $siteId = $site->id();
            $cleaned = false;

            foreach ($singleKeys as $key) {
                if (in_array((int) $siteSettings->get($key, null, $siteId), $searchConfigIds, true)) {
                    $siteSettings->set($key, null, $siteId);
                    $cleaned = true;
                }
            }

            $availables = $siteSettings->get('advancedsearch_configs', [], $siteId);
            if (is_array($availables)) {
                $kept = array_values(array_diff(array_map('intval', $availables), $searchConfigIds));
                if (count($kept) !== count($availables)) {
                    $siteSettings->set('advancedsearch_configs', $kept, $siteId);
                    $cleaned = true;
                }
            }

            if ($cleaned) {
                $cleanedSites[] = $site->slug();
            }
        }

        if ($cleanedSites) {
            $this->messenger()->addWarning(new PsrMessage(
                'The deleted search pages were removed from the settings of these sites: {site_slugs}. Check the pages that used them.', // @translate
                ['site_slugs' => implode(', ', $cleanedSites)]
            ));
        }
    }

    /**
     * List what a foreign key deletes with a search engine.
     *
     * The search configs, the suggesters and the solr maps are removed by a
     * cascade of the database, so they are listed before the confirmation.
     *
     * @return array Names by type of resource.
     */
    protected function searchEngineDependencies(SearchEngineRepresentation $searchEngine): array
    {
        $engineId = $searchEngine->id();

        $result = [
            'search_configs' => [],
            'search_suggesters' => [],
            'solr_maps' => 0,
        ];

        foreach ($this->api()->search('search_configs')->getContent() as $searchConfig) {
            $configEngine = $searchConfig->searchEngine();
            if ($configEngine && $configEngine->id() === $engineId) {
                $result['search_configs'][] = sprintf('%s (/%s)', $searchConfig->name(), $searchConfig->slug());
            }
        }

        foreach ($this->api()->search('search_suggesters')->getContent() as $suggester) {
            $suggesterEngine = $suggester->searchEngine();
            if ($suggesterEngine && $suggesterEngine->id() === $engineId) {
                $result['search_suggesters'][] = $suggester->name();
            }
        }

        // The maps belong to the module SearchSolr, that may be absent.
        try {
            $result['solr_maps'] = count($this->api()->search('solr_maps', ['engine_id' => $engineId], ['returnScalar' => 'id'])->getContent());
        } catch (\Throwable $e) {
            $result['solr_maps'] = 0;
        }

        return $result;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            $searchEngineId = $this->params('id');
            $searchEngineName = $this->api()->read('search_engines', $searchEngineId)->getContent()->name();
            if ($form->isValid()) {
                // The configs are deleted by a cascade of the database, so
                // collect their ids before, to clean the settings that point
                // to them.
                $searchConfigIds = [];
                foreach ($this->api()->search('search_configs')->getContent() as $searchConfig) {
                    $configEngine = $searchConfig->searchEngine();
                    if ($configEngine && $configEngine->id() === (int) $searchEngineId) {
                        $searchConfigIds[] = $searchConfig->id();
                    }
                }

                $this->api()->delete('search_engines', $searchEngineId);
                $this->messenger()->addSuccess(new PsrMessage(
                    'Search index "{name}" successfully deleted', // @translate
                    ['name' => $searchEngineName]
                ));

                if ($searchConfigIds) {
                    $this->removeSearchConfigsFromSettings($searchConfigIds);
                }
            } else {
                $this->messenger()->addError(new PsrMessage(
                    'Search index "{name}" could not be deleted', // @translate
                    ['name' => $searchEngineName]
                ));
            }
        }
        return $this->redirect()->toRoute('admin/search-manager');
    }

    protected function isIndexingEnabled(SearchEngine $searchEngine): bool
    {
        $settings = $searchEngine->getSettings();
        return filter_var($settings['is_indexing_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }
}
