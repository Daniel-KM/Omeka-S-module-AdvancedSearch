<?php declare(strict_types=1);

namespace AdvancedSearch\Api\Representation;

use AdvancedSearch\Querier\Exception\QuerierException;
use AdvancedSearch\Query;
use AdvancedSearch\Response;
use Common\Stdlib\PsrMessage;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\SiteRepresentation;

class SearchSuggesterRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o:SearchSuggester';
    }

    public function getJsonLd()
    {
        $getDateTimeJsonLd = function (?\DateTime $dateTime): ?array {
            return $dateTime
                ? [
                    '@value' => $dateTime->format('c'),
                    '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                ]
                : null;
        };
        return [
            'o:name' => $this->resource->getName(),
            'o:search_engine' => $this->searchEngine()->getReference()->jsonSerialize(),
            'o:settings' => $this->resource->getSettings(),
            'o:created' => $getDateTimeJsonLd($this->resource->getCreated()),
            'o:modified' => $getDateTimeJsonLd($this->resource->getModified()),
        ];
    }

    public function adminUrl($action = null, $canonical = false)
    {
        $url = $this->getViewHelper('Url');
        $params = [
            'action' => $action,
            'id' => $this->id(),
        ];
        $options = [
            'force_canonical' => $canonical,
        ];

        return $url('admin/search-manager/suggester-id', $params, $options);
    }

    public function siteUrl($siteSlug = null, $canonical = false)
    {
        return null;
    }

    public function name(): string
    {
        return $this->resource->getName();
    }

    public function searchEngine(): \AdvancedSearch\Api\Representation\SearchEngineRepresentation
    {
        $searchEngine = $this->resource->getEngine();
        return $this->getAdapter('search_engines')->getRepresentation($searchEngine);
    }

    public function engineAdapter(): ?\AdvancedSearch\EngineAdapter\EngineAdapterInterface
    {
        return $this->searchEngine()->engineAdapter();
    }

    public function settings(): array
    {
        return $this->resource->getSettings();
    }

    public function setting(string $name, $default = null)
    {
        $settings = $this->resource->getSettings();
        return $settings[$name] ?? $default;
    }

    public function created(): \DateTime
    {
        return $this->resource->getCreated();
    }

    public function modified(): ?\DateTime
    {
        return $this->resource->getModified();
    }

    public function getEntity(): \AdvancedSearch\Entity\SearchSuggester
    {
        return $this->resource;
    }

    /**
     * Adapted:
     * @see \AdvancedSearch\Api\Representation\SearchConfigRepresentation::suggest()
     * @see \AdvancedSearch\Api\Representation\SearchSuggesterRepresentation::suggest()
     * @see \AdvancedSearch\Form\MainSearchForm::listValuesForField()
     * @see \Reference\Mvc\Controller\Plugin\References
     */
    public function suggest(string $q, ?SiteRepresentation $site = null): Response
    {
        $query = new Query();

        $query->setQuery($q);

        $services = $this->getServiceLocator();
        $user = $services->get('Omeka\AuthenticationService')->getIdentity();
        // TODO Manage roles from modules and visibility from modules (access resources).
        $omekaRoles = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
            \Omeka\Permissions\Acl::ROLE_RESEARCHER,
        ];
        if ($user && in_array($user->getRole(), $omekaRoles)) {
            $query->setIsPublic(false);
        }

        if ($site) {
            $query->setSiteId($site->id());
            $siteSettings = $services->get('Omeka\Settings\Site');
            $searchConfigId = (int) $siteSettings->get('advancedsearch_main_config');
        } else {
            $settings = $services->get('Omeka\Settings');
            $searchConfigId = (int) $settings->get('advancedsearch_main_config');
        }

        if ($searchConfigId) {
            $api = $services->get('Omeka\ApiManager');
            try {
                /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig*/
                $searchConfig = $api->read('search_configs', ['id' => $searchConfigId])->getContent();
                $aliases = $searchConfig->subSetting('index', 'aliases', []);
                $fieldQueryArgs = $searchConfig->subSetting('index', 'query_args', []);
                $query
                    ->setAliases($aliases)
                    ->setFieldsQueryArgs($fieldQueryArgs)
                    ->setOption('remove_diacritics', (bool) $searchConfig->subSetting('q', 'remove_diacritics', false))
                    ->setOption('default_search_partial_word', (bool) $searchConfig->subSetting('q', 'default_search_partial_word', false));
            } catch (\Exception $e) {
                // No aliases.
            }
        }

        $searchEngine = $this->searchEngine();
        $searchEngineSettings = $searchEngine->settings();
        $suggesterSettings = $this->settings();

        // Build suggest options, including Solr-specific settings if present.
        $suggestOptions = [
            'suggester' => $this->resource->getId(),
            'mode_index' => $suggesterSettings['mode_index'] ?? 'start',
            'mode_search' => $suggesterSettings['mode_search'] ?? 'start',
            'length' => $suggesterSettings['length'] ?? 50,
        ];

        // Add Solr-specific options if configured.
        if (!empty($suggesterSettings['solr_suggester_name'])) {
            $suggestOptions['solr_suggester_name'] = $suggesterSettings['solr_suggester_name'];
        } elseif (!empty($suggesterSettings['solr_field'])) {
            // Auto-generate suggester name from the suggester name if field is set.
            $suggestOptions['solr_suggester_name'] = 'omeka_' . preg_replace('/[^a-z0-9_]/i', '_', strtolower($this->name()));
        }

        $query
            ->setResourceTypes($searchEngineSettings['resource_types'])
            ->setLimitPage(1, empty($suggesterSettings['limit']) ? \Omeka\Stdlib\Paginator::PER_PAGE : (int) $suggesterSettings['limit'])
            ->setSuggestOptions($suggestOptions)
            ->setSuggestFields($suggesterSettings['fields'] ?? [])
            ->setExcludedFields($suggesterSettings['excluded_fields'] ?? [])
        ;

        /** @var \AdvancedSearch\Querier\QuerierInterface $querier */
        $querier = $searchEngine
            ->querier()
            ->setQuery($query);
        try {
            return $querier->querySuggestions();
        } catch (QuerierException $e) {
            $message = new PsrMessage(
                "Query error: {message}\nQuery:{query}", // @translate
                ['message' => $e->getMessage(), 'query' => $query->jsonSerialize()]
            );
            $this->logger()->err($message->getMessage(), $message->getContext());
            $translator = $services->get('MvcTranslator');
            return (new Response)
                ->setMessage($message->setTranslator($translator));
        }
    }
}
