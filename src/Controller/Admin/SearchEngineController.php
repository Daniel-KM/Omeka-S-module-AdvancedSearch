<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2018-2025
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
use AdvancedSearch\Form\Admin\SearchEngineConfigureForm;
use AdvancedSearch\Form\Admin\SearchEngineForm;
use Common\Stdlib\PsrMessage;
use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\AbstractActionController;
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
            return $this->redirect()->toUrl($searchEngine->url('edit'));
        }
        return $view;
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
        $form = $this->getForm(SearchEngineConfigureForm::class, [
            'search_engine_id' => $id,
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
            $searchEngine
                ->setName($name)
                ->setSettings($formData);
            $this->entityManager->persist($searchEngine);
            $this->entityManager->flush();
            $this->messenger()->addSuccess(new PsrMessage(
                'Search index "{name}" successfully configured.',  // @translate
                ['name' => $searchEngine->getName()]
            ));
            $this->messenger()->addWarning('Don’t forget to run the indexation of the search engine.'); // @translate
            return $this->redirect()->toRoute('admin/search-manager', ['action' => 'browse'], true);
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
        $startResourceId = (int) $this->params()->fromPost('start_resource_id');
        $resourcesByBatch = (int) $this->params()->fromPost('resources_by_batch');
        $sleepAfterLoop = (int) $this->params()->fromPost('sleep_after_loop');
        $resourceTypes = $this->params()->fromPost('resource_types') ?: [];
        $visibility = $this->params()->fromPost('visibility');
        $visibility = in_array($visibility, ['public', 'private']) ? $visibility : null;
        $force = (bool) $this->params()->fromPost('force');

        $jobArgs = [];
        $jobArgs['search_engine_id'] = $searchEngine->id();
        $jobArgs['clear_index'] = $clearIndex;
        $jobArgs['start_resource_id'] = $startResourceId;
        $jobArgs['resources_by_batch'] = $resourcesByBatch;
        $jobArgs['sleep_after_loop'] = $sleepAfterLoop;
        $jobArgs['resource_types'] = $resourceTypes;
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
                'link_job' => sprintf('<a href="%1$s">', $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()])),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
                'link_log' => sprintf('<a href="%1$s">', class_exists('Log\Module', false)
                    ? $urlPlugin->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]])
                    : $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])
                ),
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

        // TODO Add a warning about the related configs, that will be deleted.

        $view = new ViewModel([
            'resourceLabel' => 'search engine',
            'resource' => $searchEngine,
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
            $searchEngineId = $this->params('id');
            $searchEngineName = $this->api()->read('search_engines', $searchEngineId)->getContent()->name();
            if ($form->isValid()) {
                $this->api()->delete('search_engines', $searchEngineId);
                $this->messenger()->addSuccess(new PsrMessage(
                    'Search index "{name}" successfully deleted', // @translate
                    ['name' => $searchEngineName]
                ));
            } else {
                $this->messenger()->addError(new PsrMessage(
                    'Search index "{name}" could not be deleted', // @translate
                    ['name' => $searchEngineName]
                ));
            }
        }
        return $this->redirect()->toRoute('admin/search-manager');
    }
}
