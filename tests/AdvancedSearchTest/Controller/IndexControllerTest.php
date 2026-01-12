<?php declare(strict_types=1);

namespace AdvancedSearchTest\Controller;

require_once __DIR__ . '/SearchControllerTestCase.php';

/**
 * @group controller
 * @group needs-review
 */
class IndexControllerTest extends SearchControllerTestCase
{
    protected $site;

    public function setUp(): void
    {
        parent::setUp();

        $response = $this->api()->create('sites', [
            'o:title' => 'Test site',
            'o:slug' => 'test',
            'o:theme' => 'default',
        ]);
        $this->site = $response->getContent();

        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($this->site->id());
        $siteSettings->set('advancedsearch_configs', [$this->searchConfig->id()]);

        // Set default site for route registration during bootstrap.
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('default_site', $this->site->id());

        $this->resetApplication();
    }

    public function tearDown(): void
    {
        // Re-login as admin (public site dispatch logs out admin).
        $this->login('admin@example.com', 'root');

        // Clean up default site setting before parent cleanup.
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->delete('default_site');

        // Delete site before parent tearDown to avoid constraint issues.
        if ($this->site) {
            try {
                $this->api()->delete('sites', $this->site->id());
            } catch (\Exception $e) {
                // Ignore cleanup errors.
            }
            $this->site = null;
        }

        parent::tearDown();
    }

    public function testSearchAction(): void
    {
        // URL format: /s/{site-slug}/{search-config-slug}
        $this->dispatch('/s/test/' . $this->searchConfig->slug());
        $this->assertResponseStatusCode(200);

        $this->assertQuery('form');
    }

    public function testSearchWithParamsAction(): void
    {
        $this->dispatch('/s/test/' . $this->searchConfig->slug(), 'GET', ['q' => 'test']);
        $this->assertResponseStatusCode(200);
        $this->assertQuery('form');
    }
}
