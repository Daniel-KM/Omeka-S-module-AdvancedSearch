<?php declare(strict_types=1);

namespace AdvancedSearchTest;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Entity\Job;

/**
 * Shared test helpers for AdvancedSearch module tests.
 */
trait AdvancedSearchTestTrait
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var array List of created resource IDs for cleanup.
     */
    protected $createdResources = [];

    /**
     * @var array List of created search config IDs for cleanup.
     */
    protected $createdSearchConfigs = [];

    /**
     * @var array List of created search engine IDs for cleanup.
     */
    protected $createdSearchEngines = [];

    /**
     * Get the API manager.
     */
    protected function api(): ApiManager
    {
        return $this->getServiceLocator()->get('Omeka\ApiManager');
    }

    /**
     * Get the service locator.
     */
    protected function getServiceLocator(): ServiceLocatorInterface
    {
        if ($this->services === null) {
            $this->services = $this->getApplication()->getServiceManager();
        }
        return $this->services;
    }

    /**
     * Get the entity manager.
     */
    protected function getEntityManager()
    {
        return $this->getServiceLocator()->get('Omeka\EntityManager');
    }

    /**
     * Login as admin user.
     */
    protected function loginAdmin(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    /**
     * Logout current user.
     */
    protected function logout(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
    }

    /**
     * Create a test item.
     *
     * @param array $data Item data with property terms as keys.
     * @return ItemRepresentation
     */
    protected function createItem(array $data): ItemRepresentation
    {
        // Convert property terms to proper format if needed.
        $itemData = [];
        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');

        foreach ($data as $term => $values) {
            // Skip non-property fields.
            if (strpos($term, ':') === false) {
                $itemData[$term] = $values;
                continue;
            }

            $propertyId = $easyMeta->propertyId($term);
            if (!$propertyId) {
                continue;
            }

            $itemData[$term] = [];
            foreach ($values as $value) {
                $valueData = [
                    'type' => $value['type'] ?? 'literal',
                    'property_id' => $propertyId,
                ];
                if (isset($value['@value'])) {
                    $valueData['@value'] = $value['@value'];
                }
                if (isset($value['@id'])) {
                    $valueData['@id'] = $value['@id'];
                }
                if (isset($value['o:label'])) {
                    $valueData['o:label'] = $value['o:label'];
                }
                $itemData[$term][] = $valueData;
            }
        }

        $response = $this->api()->create('items', $itemData);
        $item = $response->getContent();
        $this->createdResources[] = ['type' => 'items', 'id' => $item->id()];

        return $item;
    }

    /**
     * Create a search engine in the database.
     *
     * @param string $name Engine name.
     * @param string $adapter Adapter name.
     * @param array $settings Engine settings.
     * @return \AdvancedSearch\Api\Representation\SearchEngineRepresentation
     */
    protected function createSearchEngine(string $name, string $adapter, array $settings = [])
    {
        $response = $this->api()->create('search_engines', [
            'o:name' => $name,
            'o:adapter' => $adapter,
            'o:settings' => $settings,
        ]);
        $engine = $response->getContent();
        $this->createdSearchEngines[] = $engine->id();

        return $engine;
    }

    /**
     * Create a search config in the database.
     *
     * @param string $name Config name.
     * @param string $path URL path.
     * @param int $engineId Search engine ID.
     * @param array $settings Config settings.
     * @return \AdvancedSearch\Api\Representation\SearchConfigRepresentation
     */
    protected function createSearchConfig(string $name, string $path, int $engineId, array $settings = [])
    {
        $response = $this->api()->create('search_configs', [
            'o:name' => $name,
            'o:path' => $path,
            'o:engine' => ['o:id' => $engineId],
            'o:settings' => $settings,
        ]);
        $config = $response->getContent();
        $this->createdSearchConfigs[] = $config->id();

        return $config;
    }

    /**
     * Get a fixture file content.
     *
     * @param string $name Fixture filename.
     * @return string
     */
    protected function getFixture(string $name): string
    {
        $path = dirname(__DIR__) . '/fixtures/' . $name;
        if (!file_exists($path)) {
            throw new \RuntimeException("Fixture not found: $path");
        }
        return file_get_contents($path);
    }

    /**
     * Get the path to the fixtures directory.
     */
    protected function getFixturesPath(): string
    {
        return dirname(__DIR__) . '/fixtures';
    }

    /**
     * @var \Exception|null Last exception from job execution.
     */
    protected $lastJobException;

    /**
     * Run a job synchronously for testing.
     *
     * @param string $jobClass Job class name.
     * @param array $args Job arguments.
     * @param bool $expectError If true, don't rethrow exceptions (for testing error cases).
     * @return Job
     */
    protected function runJob(string $jobClass, array $args, bool $expectError = false): Job
    {
        $this->lastJobException = null;
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $auth = $services->get('Omeka\AuthenticationService');

        // Create job entity.
        $job = new Job();
        $job->setStatus(Job::STATUS_STARTING);
        $job->setClass($jobClass);
        $job->setArgs($args);
        $job->setOwner($auth->getIdentity());

        $entityManager->persist($job);
        $entityManager->flush();

        // Run job synchronously.
        $jobClass = $job->getClass();
        $jobInstance = new $jobClass($job, $services);
        $job->setStatus(Job::STATUS_IN_PROGRESS);
        $job->setStarted(new \DateTime('now'));
        $entityManager->flush();

        try {
            $jobInstance->perform();
            if ($job->getStatus() === Job::STATUS_IN_PROGRESS) {
                $job->setStatus(Job::STATUS_COMPLETED);
            }
        } catch (\Throwable $e) {
            $this->lastJobException = $e;
            $job->setStatus(Job::STATUS_ERROR);
            if (!$expectError) {
                throw $e;
            }
        }

        $job->setEnded(new \DateTime('now'));
        $entityManager->flush();

        return $job;
    }

    /**
     * Get the last exception from job execution (for debugging).
     */
    protected function getLastJobException(): ?\Exception
    {
        return $this->lastJobException;
    }

    /**
     * Clean up created resources after test.
     */
    protected function cleanupResources(): void
    {
        // Delete created items.
        foreach ($this->createdResources as $resource) {
            try {
                $this->api()->delete($resource['type'], $resource['id']);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdResources = [];

        // Delete created search configs.
        foreach ($this->createdSearchConfigs as $configId) {
            try {
                $this->api()->delete('search_configs', $configId);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdSearchConfigs = [];

        // Delete created search engines.
        foreach ($this->createdSearchEngines as $engineId) {
            try {
                $this->api()->delete('search_engines', $engineId);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdSearchEngines = [];
    }
}
