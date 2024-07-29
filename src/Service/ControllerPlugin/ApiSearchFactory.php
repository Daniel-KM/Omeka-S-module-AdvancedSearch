<?php declare(strict_types=1);

namespace AdvancedSearch\Service\ControllerPlugin;

use AdvancedSearch\Adapter\InternalAdapter;
use AdvancedSearch\Adapter\NoopAdapter;
use AdvancedSearch\Mvc\Controller\Plugin\ApiSearch;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ApiSearchFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        $settings = $services->get('Omeka\Settings');

        $apiPage = $settings->get('advancedsearch_api_config');
        if (!$apiPage) {
            return new ApiSearch($api);
        }

        try {
            /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
            $searchConfig = $api->read('search_configs', ['id' => $apiPage])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return new ApiSearch($api);
        }

        $searchEngine = $searchConfig->engine();
        $searchAdapter = $searchEngine ? $searchEngine->adapter() : null;
        if (!$searchAdapter
            || $searchAdapter instanceof InternalAdapter
            || $searchAdapter instanceof NoopAdapter
        ) {
            return new ApiSearch($api);
        }

        return new ApiSearch(
            $api,
            $services->get('Omeka\Acl'),
            $services->get('Omeka\ApiAdapterManager'),
            $services->get('AdvancedSearch\FormAdapterManager')->get('api'),
            $services->get('Common\EasyMeta'),
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\Logger'),
            $services->get('Omeka\Paginator'),
            $searchConfig,
            $searchEngine,
            $services->get('MvcTranslator')
        );
    }
}
