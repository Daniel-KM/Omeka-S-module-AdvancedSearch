<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
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
     * @var \AdvancedSearch\Adapter\Manager
     */
    protected $searchAdapterManager;

    public function __construct(
        EntityManager $entityManager,
        SearchAdapterManager $searchAdapterManager
    ) {
        $this->entityManager = $entityManager;
        $this->searchAdapterManager = $searchAdapterManager;
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
            $engine = $this->api()->create('search_engines', $formData)->getContent();
            $this->messenger()->addSuccess(new PsrMessage(
                'Search index "{name}" created.', // @translate
                ['name' => $engine->name()]
            ));
            return $this->redirect()->toUrl($engine->url('edit'));
        }
        return $view;
    }

    public function editAction()
    {
        $id = $this->params('id');

        /**
         * @var \AdvancedSearch\Entity\SearchEngine $searchEngine
         * @var \AdvancedSearch\Adapter\AdapterInterface $adapter
         * @var \AdvancedSearch\Form\Admin\SearchEngineConfigureForm $form
         */
        $searchEngine = $this->entityManager->find(\AdvancedSearch\Entity\SearchEngine::class, $id);
        $searchEngineAdapterName = $searchEngine->getAdapter();
        if (!$this->searchAdapterManager->has($searchEngineAdapterName)) {
            $this->messenger()->addError(new PsrMessage(
                'The search adapter "{name}" is not available.', // @translate
                ['name' => $searchEngineAdapterName]
            ));
            return $this->redirect()->toRoute('admin/search-manager', ['action' => 'browse'], true);
        }

        // Passing option requires a factory to avoids the error in laminas.
        $adapter = $this->searchAdapterManager->get($searchEngineAdapterName);
        $form = $this->getForm(SearchEngineConfigureForm::class, [
            'search_engine_id' => $id,
        ]);

        $adapterFieldset = $adapter->getConfigFieldset();
        if ($adapterFieldset) {
            $adapterFieldset
                ->setOption('search_engine_id', $id)
                ->setName('adapter')
                ->setLabel('Adapter settings') // @translate
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
        $engine = $this->api()->read('search_engines', $this->params('id'))->getContent();

        $totalJobs = $this->totalJobs(\AdvancedSearch\Job\IndexSearch::class, true);

        $view = new ViewModel([
            'resourceLabel' => 'search index',
            'resource' => $engine,
            'totalJobs' => $totalJobs,
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

        $startResourceId = (int) $this->params()->fromPost('start_resource_id');
        $resourceTypes = $this->params()->fromPost('resource_types') ?: [];
        $force = (bool) $this->params()->fromPost('force');

        $jobArgs = [];
        $jobArgs['search_engine_id'] = $searchEngine->id();
        $jobArgs['start_resource_id'] = $startResourceId;
        $jobArgs['resource_types'] = $resourceTypes;
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
        $engine = $response->getContent();

        // TODO Add a warning about the related configs, that will be deleted.

        $view = new ViewModel([
            'resourceLabel' => 'search engine',
            'resource' => $engine,
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
            $engineId = $this->params('id');
            $engineName = $this->api()->read('search_engines', $engineId)->getContent()->name();
            if ($form->isValid()) {
                $this->api()->delete('search_engines', $engineId);
                $this->messenger()->addSuccess(new PsrMessage(
                    'Search index "{name}" successfully deleted', // @translate
                    ['name' => $engineName]
                ));
            } else {
                $this->messenger()->addError(new PsrMessage(
                    'Search index "{name}" could not be deleted', // @translate
                    ['name' => $engineName]
                ));
            }
        }
        return $this->redirect()->toRoute('admin/search-manager');
    }
}
