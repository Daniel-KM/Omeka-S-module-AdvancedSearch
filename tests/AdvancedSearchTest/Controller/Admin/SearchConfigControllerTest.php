<?php declare(strict_types=1);

namespace AdvancedSearchTest\Controller\Admin;

require_once dirname(__DIR__) . '/SearchControllerTestCase.php';

/**
 * @group controller
 * @group needs-review
 */
class SearchConfigControllerTest extends \AdvancedSearchTest\Controller\SearchControllerTestCase
{
    public function testAddGetAction(): void
    {
        $this->dispatch('/admin/search-manager/config/add');
        $this->assertResponseStatusCode(200);

        $this->assertQuery('input[name="o:name"]');
        $this->assertQuery('input[name="o:slug"]');
        $this->assertQuery('form#search-config-edit-form');
    }

    public function testAddPostAction(): void
    {
        $this->dispatchPost(
            '/admin/search-manager/config/add',
            [
                'o:name' => 'TestPage2',
                'o:slug' => 'search/test2',
                'o:search_engine' => $this->searchEngine->id(),
                'o:form_adapter' => 'main',
            ],
            \AdvancedSearch\Form\Admin\SearchConfigForm::class
        );
        // Check redirect (may go to configure page or show validation error).
        $this->assertResponseStatusCode(302);
    }

    public function testConfigureGetAction(): void
    {
        $this->dispatch($this->searchConfig->adminUrl('configure'));
        $this->assertResponseStatusCode(200);

        // Check form exists.
        $this->assertQuery('form');
    }

    public function testConfigurePostAction(): void
    {
        $url = '/admin/search-manager/config/' . $this->searchConfig->id() . '/configure';
        $this->dispatchPost(
            $url,
            [],
            \AdvancedSearch\Form\Admin\SearchConfigConfigureForm::class,
            ['search_config' => $this->searchConfig]
        );
        // Check response is not an error (200 = validation failed, 302 = saved).
        $this->assertNotResponseStatusCode(500);
    }
}
