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
        $engine = $suggester->engine();
        $searchAdapter = $engine->adapter();
        if (!$searchAdapter) {
            $this->messenger()->addError(new PsrMessage(
                'The search adapter for engine "{name}" is not available.', // @translate
                ['name' => $engine->name()]
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

        $this->messenger()->addSuccess(new PsrMessage(
            'Suggester "{name}" successfully configured.',  // @translate
            ['name' => $suggester->name()]
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
        $message = new PsrMessage(
            'Indexing suggestions of suggester "{name}" started in job {link_job}#{job_id}{link_end} ({link_log}logs{link_end}).', // @translate
            [
                'name' => $suggester->name(),
                'link_job' => sprintf('<a href="%1$s">', $urlHelper('admin/id', ['controller' => 'job', 'id' => $job->getId()])),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
                'link_log' => sprintf('<a href="%1$s">', class_exists('Log\Module') ? $urlHelper('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]]) : $urlHelper('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])),
            ]
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
        return $this->redirect()->toRoute('admin/search');
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
            $suggesterId = (int) $this->api()
                ->searchOne('search_suggesters', ['name' => $name], ['returnScalar' => 'id'])
                ->getContent();
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
