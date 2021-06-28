<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
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
use Search\Form\Admin\SearchIndexConfigureForm;
use Search\Form\Admin\SearchIndexForm;

class SearchIndexController extends AbstractActionController
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var SearchAdapterManager
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
        $form = $this->getForm(SearchIndexForm::class);
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
            $index = $this->api()->create('search_indexes', $formData)->getContent();
            $this->messenger()->addSuccess(new Message(
                'Search index "%s" created.', // @translate
                $index->name()
            ));
            return $this->redirect()->toUrl($index->url('edit'));
        }
        return $view;
    }

    public function editAction()
    {
        $adapterManager = $this->getSearchAdapterManager();

        $id = $this->params('id');

        /** @var \Search\Entity\SearchIndex $searchIndex */
        $searchIndex = $this->getEntityManager()->find(\Search\Entity\SearchIndex::class, $id);
        $searchIndexAdapterName = $searchIndex->getAdapter();
        if (!$adapterManager->has($searchIndexAdapterName)) {
            $this->messenger()->addError(new Message('The adapter "%s" is not available.', // @translate
                $searchIndexAdapterName
            ));
            return $this->redirect()->toRoute('admin/search', ['action' => 'browse'], true);
        }

        /** @var \Search\Adapter\AdapterInterface $adapter */
        $adapter = $adapterManager->get($searchIndexAdapterName);

        $form = $this->getForm(SearchIndexConfigureForm::class, [
            'search_index_id' => $id,
        ]);
        $adapterFieldset = $adapter->getConfigFieldset();
        if ($adapterFieldset) {
            $adapterFieldset
                ->setOption('search_index_id', $id)
                ->setName('adapter')
                ->setLabel('Adapter settings') // @translate
                ->init();
            $form->add($adapterFieldset);
        }
        $data = $searchIndex->getSettings() ?: [];
        $data['o:name'] = $searchIndex->getName();
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
            $searchIndex
                ->setName($name)
                ->setSettings($formData);
            $this->getEntityManager()->flush($searchIndex);
            $this->messenger()->addSuccess(new Message(
                'Search index "%s" successfully configured.',  // @translate
                $searchIndex->getName()
            ));
            $this->messenger()->addWarning('Don’t forget to run the indexation of the core.'); // @translate
            return $this->redirect()->toRoute('admin/search', ['action' => 'browse'], true);
        }

        return $view;
    }

    public function indexConfirmAction()
    {
        $index = $this->api()->read('search_indexes', $this->params('id'))->getContent();

        $totalJobs = $this->totalJobs(\Search\Job\Indexing::class, true);

        $view = new ViewModel([
            'resourceLabel' => 'search index',
            'resource' => $index,
            'totalJobs' => $totalJobs,
        ]);
        return $view
            ->setTerminal(true)
            ->setTemplate('search/admin/search-index/index-confirm-details');
    }

    public function indexAction()
    {
        $searchIndexId = (int) $this->params('id');
        $searchIndex = $this->api()->read('search_indexes', $searchIndexId)->getContent();

        $startResourceId = (int) $this->params()->fromPost('start_resource_id');
        $resourceNames = $this->params()->fromPost('resource_names') ?: [];
        $force = (bool) $this->params()->fromPost('force');

        $jobArgs = [];
        $jobArgs['search_index_id'] = $searchIndex->id();
        $jobArgs['start_resource_id'] = $startResourceId;
        $jobArgs['resource_names'] = $resourceNames;
        $jobArgs['force'] = $force;
        $job = $this->jobDispatcher()->dispatch(\Search\Job\Indexing::class, $jobArgs);

        $urlHelper = $this->viewHelpers()->get('url');
        $message = new Message(
            'Indexing of "%1$s" started in job %2$s#%3$d%4$s (%5$slogs%4$s)', // @translate
            $searchIndex->name(),
            sprintf('<a href="%1$s">', $urlHelper('admin/id', ['controller' => 'job', 'id' => $job->getId()])),
            $job->getId(),
            '</a>',
            sprintf('<a href="%1$s">', class_exists('Log\Stdlib\PsrMessage') ? $urlHelper('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]]) :  $urlHelper('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()]))
        );
        $message->setEscapeHtml(false);
        $this->messenger()->addSuccess($message);

        return $this->redirect()->toRoute('admin/search', ['action' => 'browse'], true);
    }

    public function deleteConfirmAction()
    {
        $response = $this->api()->read('search_indexes', $this->params('id'));
        $index = $response->getContent();

        // TODO Add a warning about the related pages, that will be deleted.

        $view = new ViewModel([
            'resourceLabel' => 'search index',
            'resource' => $index,
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
            $indexId = $this->params('id');
            $indexName = $this->api()->read('search_indexes', $indexId)->getContent()->name();
            if ($form->isValid()) {
                $this->api()->delete('search_indexes', $indexId);
                $this->messenger()->addSuccess(new Message(
                    'Search index "%s" successfully deleted', // @translate
                    $indexName
                ));
            } else {
                $this->messenger()->addError(new Message(
                    'Search index "%s" could not be deleted', // @translate
                    $indexName
                ));
            }
        }
        return $this->redirect()->toRoute('admin/search');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    protected function getSearchAdapterManager(): SearchAdapterManager
    {
        return $this->searchAdapterManager;
    }
}
