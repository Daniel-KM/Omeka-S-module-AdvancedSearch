<?php declare(strict_types=1);

namespace AdvancedSearchTest\Controller;

use CommonTest\AbstractHttpControllerTestCase;

abstract class SearchControllerTestCase extends AbstractHttpControllerTestCase
{
    protected $searchEngine;
    protected $searchConfig;

    /**
     * Override dispatch to re-register test adapter after application reset.
     *
     * CommonTest\AbstractHttpControllerTestCase::dispatch() resets the application,
     * which clears our test engine adapter registration. We need to re-register it.
     */
    public function dispatch($url, $method = null, $params = [], $isXmlHttpRequest = false)
    {
        // Reset and get fresh application (this is what parent::dispatch does first).
        $this->reset();
        $this->getApplication();

        // Re-register test adapter before dispatch.
        $this->setupTestEngineAdapter();

        // Re-authenticate (parent dispatch does this, but we need it for CSRF too).
        if ($this->requiresLogin) {
            $this->loginAsAdmin();
        }

        // Now do the actual dispatch without reset (call grandparent).
        \Laminas\Test\PHPUnit\Controller\AbstractHttpControllerTestCase::dispatch($url, $method, $params, $isXmlHttpRequest);
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->loginAsAdmin();
        $this->setupTestEngineAdapter();

        $response = $this->api()->create('search_engines', [
            'o:name' => 'TestIndex',
            'o:engine_adapter' => 'test',
            'o:settings' => [
                'resource_types' => [
                    'items',
                    'item_sets',
                ],
            ],
        ]);
        $searchEngine = $response->getContent();
        $response = $this->api()->create('search_configs', [
            'o:name' => 'TestPage',
            'o:slug' => 'testsearch',
            'o:search_engine' => [
                'o:id' => $searchEngine->id(),
            ],
            'o:form_adapter' => 'main',
            'o:settings' => [
                'request' => [],
                'q' => [],
                'index' => [],
                'form' => [],
                'results' => [],
                'facet' => [],
            ],
        ]);
        $searchConfig = $response->getContent();

        $this->searchEngine = $searchEngine;
        $this->searchConfig = $searchConfig;

        // Set global setting required for route registration.
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('advancedsearch_all_configs', [
            $searchConfig->id() => $searchConfig->slug(),
        ]);
    }

    public function tearDown(): void
    {
        // Clean up global setting.
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->delete('advancedsearch_all_configs');

        $this->api()->delete('search_configs', $this->searchConfig->id());
        $this->api()->delete('search_engines', $this->searchEngine->id());
    }

    protected function setupTestEngineAdapter(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $engineAdapterManager = $services->get('AdvancedSearch\EngineAdapterManager');
        $config = [
            'invokables' => [
                'test' => \AdvancedSearch\Test\EngineAdapter\TestEngineAdapter::class,
            ],
        ];
        $engineAdapterManager->configure($config);
    }

    protected function resetApplication(): void
    {
        // Reinitialize application and reconfigure test adapter.
        $this->reset();
        $this->setupTestEngineAdapter();
    }

    /**
     * Get the service locator (alias for getApplicationServiceLocator).
     */
    protected function getServiceLocator()
    {
        return $this->getApplicationServiceLocator();
    }

    /**
     * Get a valid CSRF token for a form.
     *
     * This must be called AFTER dispatch() has reset the application,
     * typically in a callback or by using dispatchWithCsrf().
     *
     * @param string $formClass The form class name.
     * @param array $options Form options.
     * @param string $csrfName The CSRF element name (default 'csrf').
     * @return string The CSRF token value.
     */
    protected function getCsrfToken(string $formClass, array $options = [], string $csrfName = 'csrf'): string
    {
        $forms = $this->getServiceLocator()->get('FormElementManager');
        $form = $forms->get($formClass, $options);
        return $form->get($csrfName)->getValue();
    }

    /**
     * Dispatch a POST request with automatic CSRF token injection.
     *
     * @param string $url The URL to dispatch.
     * @param array $params POST parameters (without CSRF).
     * @param string $formClass The form class to get CSRF from.
     * @param array $formOptions Form options for getting the form.
     * @param string $csrfName The CSRF element name.
     */
    protected function dispatchPost(
        string $url,
        array $params,
        string $formClass,
        array $formOptions = [],
        string $csrfName = 'csrf'
    ): void {
        // Reset and setup first.
        $this->reset();
        $this->getApplication();
        $this->setupTestEngineAdapter();

        if ($this->requiresLogin) {
            $this->loginAsAdmin();
        }

        // Get CSRF from the fresh application.
        $params[$csrfName] = $this->getCsrfToken($formClass, $formOptions, $csrfName);

        // Dispatch without reset.
        \Laminas\Test\PHPUnit\Controller\AbstractHttpControllerTestCase::dispatch($url, 'POST', $params);
    }

    /**
     * Login as admin using adapter (avoids static caching issues with Doctrine).
     */
    protected function loginAsAdmin(): void
    {
        $this->login('admin@example.com', 'root');
    }

    /**
     * Login with credentials using adapter.
     */
    protected function login(string $email, string $password): void
    {
        $services = $this->getApplicationServiceLocator();
        $auth = $services->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity($email);
        $adapter->setCredential($password);
        $auth->authenticate();
    }
}
