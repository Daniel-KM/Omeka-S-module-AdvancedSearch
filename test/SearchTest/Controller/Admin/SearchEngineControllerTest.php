<?php declare(strict_types=1);

namespace AdvancedSearchTest\Controller\Admin;

require_once dirname(__DIR__) . '/SearchControllerTestCase.php';

use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;
use AdvancedSearch\Form\Admin\SearchEngineConfigureForm;
use AdvancedSearchTest\Controller\SearchControllerTestCase;

class SearchEngineControllerTest extends SearchControllerTestCase
{
    public function testAddGetAction(): void
    {
        $this->dispatch('/admin/search-manager/index/add');
        $this->assertResponseStatusCode(200);

        $this->assertQuery('input[name="o:name"]');
        $this->assertQuery('select[name="o:adapter"]');
    }

    public function testAddPostAction(): void
    {
        $forms = $this->getServiceLocator()->get('FormElementManager');
        $form = $forms->get(\Search\Form\Admin\SearchEngineForm::class);

        $this->dispatch('/admin/search-manager/index/add', 'POST', [
            'o:name' => 'TestIndex2',
            'o:adapter' => 'test',
            'csrf' => $form->get('csrf')->getValue(),
        ]);
        $response = $this->api()->search('search_engines', [
            'name' => 'TestIndex2',
        ]);
        $searchEnginees = $response->getContent();
        $searchEngine = reset($searchEnginees);
        $this->assertRedirectTo($searchEngine->adminUrl('edit'));
    }

    public function testConfigureGetAction(): void
    {
        $this->dispatch($this->searchEngine->adminUrl('edit'));
        $this->assertResponseStatusCode(200);

        $this->assertQuery('input[name="resources[]"]');
    }

    public function testConfigurePostAction(): void
    {
        $forms = $this->getServiceLocator()->get('FormElementManager');
        $form = $forms->get(SearchEngineConfigureForm::class, [
            'search_index_id' => $this->searchEngine->id(),
        ]);

        $this->dispatch($this->searchEngine->adminUrl('edit'), 'POST', [
            'resources' => ['items', 'item_sets'],
            'csrf' => $form->get('csrf')->getValue(),
        ]);
        $this->assertRedirectTo('/admin/search-manager');
    }

    public function testIndexAction(): void
    {
        $this->dispatch($this->searchEngine->adminUrl('index'));

        $this->assertRedirectTo('/admin/search-manager');

        $messenger = new Messenger;
        $messages = $messenger->get();
        $message = $messages[Messenger::SUCCESS][0];
        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals('Indexing of "%s" started in %sjob %s%s', $message->getMessage());
    }
}
