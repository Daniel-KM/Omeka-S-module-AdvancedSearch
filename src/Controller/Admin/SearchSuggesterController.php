<?php declare(strict_types=1);

namespace AdvancedSearch\Controller\Admin;

use AdvancedSearch\Form\Admin\SearchSuggesterForm;
use AdvancedSearch\Job\IndexSuggestions;
use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;
use Omeka\Stdlib\Message;

class SearchSuggesterController extends AbstractActionController
{
    /**
     * @var EntityManager
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

        $this->messenger()->addSuccess(new Message(
            'Search suggester "%s" created.', // @translate
            $suggester->name()
        ));

        return $this->redirect()->toUrl($suggester->adminUrl('edit'));
    }

    public function editAction()
    {
        $id = $this->params('id');

        /** @var \AdvancedSearch\Api\Representation\SearchSuggesterRepresentation $suggester */
        $suggester = $this->api()->read('search_suggesters', ['id' => $id])->getContent();
        $engine = $suggester->engine();
        $searchAdapter = $engine->adapter();
        if (!$searchAdapter) {
            $this->messenger()->addError(new Message('The search adapter for engine "%s" is not available.', // @translate
                $engine->name()
            ));
            return $this->redirect()->toRoute('admin/search', ['action' => 'browse'], true);
        }

        $data = $suggester->jsonSerialize();
        $data['o:engine'] = $engine->id();
        $isInternal = $searchAdapter instanceof \AdvancedSearch\Adapter\InternalAdapter;

        $form = $this->getForm(SearchSuggesterForm::class, [
            'add' => false,
            'engine' => $engine,
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
        $formData['o:engine'] = $engine->getEntity();

        $suggester = $this->api()
            ->update('search_suggesters', $id, $formData, [], ['isPartial' => true])
            ->getContent();

        $this->messenger()->addSuccess(new Message(
            'Suggester "%s" successfully configured.',  // @translate
            $suggester->name()
        ));

        if ($isInternal) {
            $this->messenger()->addWarning('Don’t forget to run the indexation of the suggester.'); // @translate
        }

        return $this->redirect()->toRoute('admin/search');
    }

    public function indexConfirmAction()
    {
        $suggester = $this->api()->read('search_suggesters', $this->params('id'))->getContent();

        $totalJobs = $this->totalJobs(IndexSuggestions::class, true);

        $view = new ViewModel([
            'resourceLabel' => 'search suggester',
            'resource' => $suggester,
            'totalJobs' => $totalJobs,
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
        $processMode = $this->params()->fromPost('process_mode');

        $jobArgs = [];
        $jobArgs['search_suggester_id'] = $suggester->id();
        $jobArgs['force'] = $force;
        $jobArgs['process_mode'] = $processMode;
        $dispatcher = $this->jobDispatcher();
        if ($this->params()->fromPost('foreground')) {
            $job = $dispatcher->dispatch(IndexSuggestions::class, $jobArgs, $suggester->getServiceLocator()->get('Omeka\Job\DispatchStrategy\Synchronous'));
        } else {
            $job = $dispatcher->dispatch(IndexSuggestions::class, $jobArgs);
        }

        $urlHelper = $this->viewHelpers()->get('url');
        $message = new Message(
            'Indexing suggestions of suggester "%1$s" started in job %2$s#%3$d%4$s (%5$slogs%4$s)', // @translate
            $suggester->name(),
            sprintf('<a href="%1$s">', $urlHelper('admin/id', ['controller' => 'job', 'id' => $job->getId()])),
            $job->getId(),
            '</a>',
            sprintf('<a href="%1$s">', class_exists('Log\Stdlib\PsrMessage') ? $urlHelper('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]]) : $urlHelper('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()]))
        );
        $message->setEscapeHtml(false);
        $this->messenger()->addSuccess($message);

        return $this->redirect()->toRoute('admin/search', ['action' => 'browse'], true);
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
                $this->messenger()->addSuccess(new Message(
                    'Search suggester "%s" successfully deleted', // @translate
                    $suggesterName
                ));
            } else {
                $this->messenger()->addError(new Message(
                    'Search suggester "%s" could not be deleted', // @translate
                    $suggesterName
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

        // Check if the name is single in the database.
        $params = $this->params()->fromPost();
        $id = $this->params('id');
        $name = trim($params['o:name']);

        $names = $this->api()
            ->search('search_suggesters', [], ['returnScalar' => 'name'])
            ->getContent();
        if (in_array($name, $names)) {
            if (!$id) {
                $this->messenger()->addError('The name should be unique.'); // @translate
                return false;
            }
            $suggesterId = $this->api()
                ->searchOne('search_suggesters', ['name' => $name], ['returnScalar' => 'id'])
                ->getContent();
            if ($id !== $suggesterId) {
                $this->messenger()->addError('The name should be unique.'); // @translate
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
