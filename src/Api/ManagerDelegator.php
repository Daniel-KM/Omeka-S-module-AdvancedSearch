<?php declare(strict_types=1);

namespace AdvancedSearch\Api;

use Laminas\Mvc\Controller\PluginManager as ControllerPluginManager;
use Omeka\Api\Adapter\AdapterInterface;
use Omeka\Api\Manager;
use Omeka\Api\Request;
use Omeka\Api\Response;

/**
 * API manager decorator.
 *
 * Decorates the Omeka API Manager to intercept search requests with
 * `index=true` option and route them through the external search engine
 * (e.g., Solr) for better performance.
 *
 * This decorator extends Manager to satisfy type hints but delegates all
 * operations to the wrapped original instance. Only `search()` has special
 * handling for the `index` option.
 *
 * Note: We must extend Manager (not just wrap it) because Omeka uses concrete
 * class type hints rather than interfaces. This ensures compatibility with
 * existing code that expects `Omeka\Api\Manager`.
 *
 * Delegate all other methods to the original Manager.
 *
 * Note: We must explicitly override every public method because:
 * 1. We extend Manager to satisfy type hints (no interface in Omeka core)
 * 2. We don't call parent::__construct() to avoid duplicating dependencies
 * 3. Therefore parent's properties ($adapterManager, $acl, etc.) are null
 * 4. Calling parent methods directly would fail
 *
 * If Omeka core added an ApiManagerInterface, we could use __call() instead
 * and only override search().
 * @todo Do a pull request to create an interface to the api manager.
 */
class ManagerDelegator extends Manager
{
    /**
     * The original Manager instance to delegate to.
     *
     * @var Manager
     */
    protected $delegate;

    /**
     * @var ControllerPluginManager
     */
    protected $controllerPlugins;

    /**
     * @param Manager $delegate The original API Manager instance
     */
    public function __construct(Manager $delegate)
    {
        // Don't call parent constructor - we delegate everything to $delegate.
        $this->delegate = $delegate;
    }

    /**
     * Execute a search API request.
     *
     * When `index` option is set to true (in data or options), the search
     * is routed through the external search engine (e.g., Solr) instead of
     * the database for better performance.
     *
     * @param string $resource
     * @param array $data
     * @param array $options
     * @return Response
     */
    public function search($resource, array $data = [], array $options = [])
    {
        // Check for index search request.
        // The `index` option can be in data (from query string) or options.
        if (!empty($options['index']) || !empty($data['index'])) {
            return $this->searchViaIndex($resource, $data, $options);
        }

        // Standard search via delegate.
        return $this->delegate->search($resource, $data, $options);
    }

    /**
     * Route search through external index (e.g., Solr).
     */
    protected function searchViaIndex(string $resource, array $data, array $options): Response
    {
        // ApiSearch is set static to avoid a loop during init of Api Manager.
        /** @var \AdvancedSearch\Mvc\Controller\Plugin\ApiSearch $apiSearch */
        static $apiSearch;

        if ($apiSearch === null) {
            $apiSearch = $this->controllerPlugins->get('apiSearch');
        }

        return $apiSearch($resource, $data, $options);
    }

    // Delegate all other methods to the original Manager.

    /**
     * @see \Omeka\Api\Manager::create()
     */
    public function create($resource, array $data = [], $fileData = [], array $options = [])
    {
        return $this->delegate->create($resource, $data, $fileData, $options);
    }

    /**
     * @see \Omeka\Api\Manager::batchCreate()
     */
    public function batchCreate($resource, array $data = [], $fileData = [], array $options = [])
    {
        return $this->delegate->batchCreate($resource, $data, $fileData, $options);
    }

    /**
     * @see \Omeka\Api\Manager::read()
     */
    public function read($resource, $id, array $data = [], array $options = [])
    {
        return $this->delegate->read($resource, $id, $data, $options);
    }

    /**
     * @see \Omeka\Api\Manager::update()
     */
    public function update($resource, $id, array $data = [], array $fileData = [], array $options = [])
    {
        return $this->delegate->update($resource, $id, $data, $fileData, $options);
    }

    /**
     * @see \Omeka\Api\Manager::batchUpdate()
     */
    public function batchUpdate($resource, array $ids, array $data = [], array $options = [])
    {
        return $this->delegate->batchUpdate($resource, $ids, $data, $options);
    }

    /**
     * @see \Omeka\Api\Manager::delete()
     */
    public function delete($resource, $id, array $data = [], array $options = [])
    {
        return $this->delegate->delete($resource, $id, $data, $options);
    }

    /**
     * @see \Omeka\Api\Manager::batchDelete()
     */
    public function batchDelete($resource, array $ids, array $data = [], array $options = [])
    {
        return $this->delegate->batchDelete($resource, $ids, $data, $options);
    }

    /**
     * @see \Omeka\Api\Manager::execute()
     */
    public function execute(Request $request)
    {
        return $this->delegate->execute($request);
    }

    /**
     * @see \Omeka\Api\Manager::initialize()
     */
    public function initialize(AdapterInterface $adapter, Request $request)
    {
        return $this->delegate->initialize($adapter, $request);
    }

    /**
     * @see \Omeka\Api\Manager::finalize()
     */
    public function finalize(AdapterInterface $adapter, Request $request, Response $response)
    {
        return $this->delegate->finalize($adapter, $request, $response);
    }

    public function setControllerPlugins(ControllerPluginManager $controllerPlugins): self
    {
        $this->controllerPlugins = $controllerPlugins;
        return $this;
    }
}
