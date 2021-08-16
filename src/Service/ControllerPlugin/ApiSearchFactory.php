<?php declare(strict_types=1);
namespace AdvancedSearch\Service\ControllerPlugin;

use AdvancedSearch\Mvc\Controller\Plugin\ApiSearch;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ApiSearchFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');

        $settings = $services->get('Omeka\Settings');
        $apiPage = $settings->get('advancedsearch_api_page');
        if ($apiPage) {
            try {
                /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
                $searchConfig = $api->read('search_configs', ['id' => $apiPage])->getContent();
                $engine = $searchConfig->engine();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
            }
            if ($engine) {
                $adapterManager = $services->get('Omeka\ApiAdapterManager');
                $formAdapter = $services->get('Search\FormAdapterManager')->get('api');
                $acl = $services->get('Omeka\Acl');
                $logger = $services->get('Omeka\Logger');
                $translator = $services->get('MvcTranslator');
                $entityManager = $services->get('Omeka\EntityManager');
                $paginator = $services->get('Omeka\Paginator');
                return new ApiSearch(
                    $api,
                    $searchConfig,
                    $engine,
                    $adapterManager,
                    $formAdapter,
                    $acl,
                    $logger,
                    $translator,
                    $entityManager,
                    $paginator
                );
            }
        }
        return new ApiSearch($api);
    }
}
