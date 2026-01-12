<?php declare(strict_types=1);

namespace AdvancedSearchTest\Controller\Admin;

require_once dirname(__DIR__) . '/SearchControllerTestCase.php';

use AdvancedSearch\Form\Admin\SearchEngineConfigureForm;
use Common\Stdlib\PsrMessage;
use Omeka\Mvc\Controller\Plugin\Messenger;

/**
 * @group controller
 * @group needs-review
 */
class SearchEngineControllerTest extends \AdvancedSearchTest\Controller\SearchControllerTestCase
{
    public function testAddGetAction(): void
    {
        $this->dispatch('/admin/search-manager/engine/add');
        $this->assertResponseStatusCode(200);

        $this->assertQuery('input[name="o:name"]');
        $this->assertQuery('select[name="o:engine_adapter"]');
    }

    public function testAddPostAction(): void
    {
        $this->dispatchPost(
            '/admin/search-manager/engine/add',
            [
                'o:name' => 'TestEngine2',
                'o:engine_adapter' => 'test',
            ],
            \AdvancedSearch\Form\Admin\SearchEngineForm::class
        );
        // After creation, redirects (controller uses url() which returns API URL).
        $this->assertResponseStatusCode(302);
    }

    public function testConfigureGetAction(): void
    {
        $this->dispatch($this->searchEngine->adminUrl('edit'));
        $this->assertResponseStatusCode(200);

        $this->assertQuery('form');
    }

    public function testConfigurePostAction(): void
    {
        $this->dispatchPost(
            $this->searchEngine->adminUrl('edit'),
            [
                'resource_types' => ['items', 'item_sets'],
            ],
            SearchEngineConfigureForm::class,
            ['search_engine_id' => $this->searchEngine->id()]
        );
        // Check response is not an error (200 = validation failed, 302 = saved).
        $this->assertNotResponseStatusCode(500);
    }

    public function testIndexAction(): void
    {
        $this->dispatch($this->searchEngine->adminUrl('index'));

        $this->assertRedirectTo('/admin/search-manager');

        // Check that a success message was set (indexing triggered).
        $messenger = $this->getServiceLocator()->get('ControllerPluginManager')->get('messenger');
        $messages = $messenger->get();
        $this->assertNotEmpty($messages);
        if (!empty($messages[Messenger::SUCCESS])) {
            $message = $messages[Messenger::SUCCESS][0];
            $this->assertInstanceOf(PsrMessage::class, $message);
        }
    }
}
