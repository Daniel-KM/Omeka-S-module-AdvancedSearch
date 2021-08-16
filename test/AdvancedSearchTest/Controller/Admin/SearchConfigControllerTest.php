<?php declare(strict_types=1);

namespace AdvancedSearchTest\Controller\Admin;

require_once dirname(__DIR__) . '/SearchControllerTestCase.php';

class SearchConfigControllerTest extends SearchControllerTestCase
{
    public function testAddGetAction(): void
    {
        $this->dispatch('/admin/search-manager/config/add');
        $this->assertResponseStatusCode(200);

        $this->assertQuery('input[name="o:name"]');
        $this->assertQuery('input[name="o:path"]');
        $this->assertQuery('select[name="o:engine_id"]');
        $this->assertQuery('select[name="o:form"]');
    }

    public function testAddPostAction(): void
    {
        $forms = $this->getServiceLocator()->get('FormElementManager');
        $form = $forms->get(\AdvancedSearch\Form\Admin\SearchConfigForm::class);

        $this->dispatch('/admin/search-manager/config/add', 'POST', [
            'o:name' => 'TestPage [testAddPostAction]',
            'o:path' => 'search/test2',
            'o:engine_id' => $this->searchEngine->id(),
            'o:form' => 'basic',
            'manage_config_default' => '0',
            'manage_config_availability' => 'let',
            'csrf' => $form->get('csrf')->getValue(),
        ]);
        $this->assertResponseStatusCode(302);
        $response = $this->api()->search('search_configs', [
            'name' => 'TestPage [testAddPostAction]',
        ]);
        $searchConfigs = $response->getContent();
        $this->assertNotEmpty($searchConfigs);
        $searchConfig = reset($searchConfigs);
        $this->assertRedirectTo($searchConfig->adminUrl('configure'));
    }

    public function testConfigureGetAction(): void
    {
        $this->dispatch($this->searchConfig->adminUrl('configure'));
        $this->assertResponseStatusCode(200);

        $this->assertQueryContentContains('h2', 'Facets');
        $this->assertQueryContentContains('h2', 'Sort fields');
    }

    public function testConfigurePostAction(): void
    {
        $forms = $this->getServiceLocator()->get('FormElementManager');
        $form = $forms->get(\AdvancedSearch\Form\Admin\SearchConfigConfigureForm::class, [
            'search_config' => $this->searchConfig,
        ]);

        $url = '/admin/search-manager/config/' . $this->searchConfig->id() . '/configure';
        $this->dispatch($url, 'POST', [
            'facet_limit' => '10',
            'csrf' => $form->get('csrf')->getValue(),
        ]);
        $this->assertRedirectTo("/admin/search-manager");
    }
}
