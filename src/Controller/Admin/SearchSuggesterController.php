<?php declare(strict_types=1);

namespace AdvancedSearch\Controller\Admin;

use AdvancedSearch\Form\Admin\SearchSuggesterForm;
use AdvancedSearch\Job\IndexSuggestions;
use Common\Stdlib\PsrMessage;
use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;

class SearchSuggesterController extends AbstractActionController
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    public function __construct(
        EntityManager $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    public function addAction()
    {
        $form = $this->getForm(SearchSuggesterForm::class, [
            'add' => true,
        ]);

        $view = new ViewModel;
        $view->setVariable('form', $form);
        if (!$this->checkPostAndValidForm($form)) {
            return $view;
        }

        $formData = $form->getData();
        $response = $this->api()->create('search_suggesters', $formData);
        /** @var \AdvancedSearch\Api\Representation\SearchSuggesterRepresentation $suggester */
        $suggester = $response->getContent();

        $this->messenger()->addSuccess(new PsrMessage(
            'Search suggester "{name}" created.', // @translate
            ['name' => $suggester->name()]
        ));

        return $this->redirect()->toUrl($suggester->adminUrl('edit'));
    }

    public function editAction()
    {
        $id = $this->params('id');

        /** @var \AdvancedSearch\Api\Representation\SearchSuggesterRepresentation $suggester */
        $suggester = $this->api()->read('search_suggesters', ['id' => $id])->getContent();
        $searchEngine = $suggester->searchEngine();
        $engineAdapter = $searchEngine->engineAdapter();
        if (!$engineAdapter) {
            $this->messenger()->addError(new PsrMessage(
                'The search adapter for engine "{name}" is not available.', // @translate
                ['name' => $searchEngine->name()]
            ));
            return $this->redirect()->toRoute('admin/search-manager', ['action' => 'browse'], true);
        }

        $data = $suggester->jsonSerialize();
        $data['o:search_engine'] = $searchEngine->id();
        $isInternal = $engineAdapter instanceof \AdvancedSearch\EngineAdapter\Internal;

        $form = $this->getForm(SearchSuggesterForm::class, [
            'add' => false,
            'search_engine' => $searchEngine,
            'is_internal' => $isInternal,
        ]);
        $form->setData($data);

        $view = new ViewModel([
            'form' => $form,
        ]);

        if (!$this->checkPostAndValidForm($form)) {
            return $view;
        }

        $formData = $form->getData();

        // The engine cannot be modified.
        $formData['o:search_engine'] = $searchEngine->getEntity();

        $suggester = $this->api()
            ->update('search_suggesters', $id, $formData, [], ['isPartial' => true])
            ->getContent();

        $this->messenger()->addSuccess(new PsrMessage(
            'Suggester "{name}" successfully configured.',  // @translate
            ['name' => $suggester->name()]
        ));

        if ($isInternal) {
            $this->messenger()->addWarning('Don’t forget to run the indexation of the suggester.'); // @translate
        }

        // Trigger event for other modules to handle post-save actions.
        // For example, SearchSolr can create the Solr suggester here.
        $this->getEventManager()->trigger('advancedsearch.suggester.save', $this, [
            'suggester' => $suggester,
            'search_engine' => $searchEngine,
            'engine_adapter' => $engineAdapter,
            'messenger' => $this->messenger(),
        ]);

        return $this->redirect()->toRoute('admin/search-manager');
    }

    public function indexConfirmAction()
    {
        $suggester = $this->api()->read('search_suggesters', $this->params('id'))->getContent();

        $listJobStatusesByIds = $this->listJobStatusesByIds(IndexSuggestions::class, true);

        $view = new ViewModel([
            'resourceLabel' => 'search suggester',
            'resource' => $suggester,
            'listJobStatusesByIds' => $listJobStatusesByIds,
        ]);
        return $view
            ->setTerminal(true)
            ->setTemplate('advanced-search/admin/search-suggester/index-confirm-details');
    }

    public function indexAction()
    {
        /** @var \AdvancedSearch\Api\Representation\SearchSuggesterRepresentation $suggester */
        $suggesterId = (int) $this->params('id');
        $suggester = $this->api()->read('search_suggesters', $suggesterId)->getContent();

        $force = (bool) $this->params()->fromPost('force');

        $jobArgs = [];
        $jobArgs['search_suggester_id'] = $suggester->id();
        $jobArgs['force'] = $force;
        $job = $this->jobDispatcher()->dispatch(IndexSuggestions::class, $jobArgs);

        $urlPlugin = $this->url();
        $message = new PsrMessage(
            'Indexing suggestions of suggester "{name}" started in job {link_job}#{job_id}{link_end} ({link_log}logs{link_end}).', // @translate
            [
                'name' => $suggester->name(),
                'link_job' => sprintf('<a href="%1$s">', $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()])),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
                'link_log' => class_exists('Log\Module', false)
                    ? sprintf('<a href="%1$s">', $urlPlugin->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]]))
                    : sprintf('<a href="%1$s" target="_blank">', $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])),
            ]
        );
        $message->setEscapeHtml(false);
        $this->messenger()->addSuccess($message);

        return $this->redirect()->toRoute('admin/search-manager', ['action' => 'browse'], true);
    }

    public function deleteConfirmAction()
    {
        $id = $this->params('id');
        $suggester = $this->api()->read('search_suggesters', $id)->getContent();

        $view = new ViewModel([
            'resourceLabel' => 'search suggester',
            'resource' => $suggester,
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
            $suggesterName = $this->api()->read('search_suggesters', $id)->getContent()->name();
            if ($form->isValid()) {
                $this->api()->delete('search_suggesters', $this->params('id'));
                $this->messenger()->addSuccess(new PsrMessage(
                    'Search suggester "{name}" successfully deleted', // @translate
                    ['name' => $suggesterName]
                ));
            } else {
                $this->messenger()->addError(new PsrMessage(
                    'Search suggester "{name}" could not be deleted', // @translate
                    ['name' => $suggesterName]
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

        // Check if the name is single in the database.
        $params = $this->params()->fromPost();
        $id = (int) $this->params('id');
        $name = trim($params['o:name']);

        $names = $this->api()
            ->search('search_suggesters', [], ['returnScalar' => 'name'])
            ->getContent();
        if (in_array($name, $names)) {
            if (!$id) {
                $this->messenger()->addError(new PsrMessage(
                    'The name should be unique.' // @translate
                ));
                return false;
            }
            try {
                $suggesterId = (int) $this->api()->read('search_suggesters', ['name' => $name])->getContent()->id();
            } catch (\Exception $e) {
                $suggesterId = null;
            }
            if ($id !== $suggesterId) {
                $this->messenger()->addError(new PsrMessage(
                    'The name should be unique.' // @translate
                ));
                return false;
            }
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
}
