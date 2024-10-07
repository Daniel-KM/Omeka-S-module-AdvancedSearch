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
        $modified = $this->resource->getModified();
        return [
            'o:name' => $this->resource->getName(),
            'o:engine' => $this->engine()->getReference(),
            'o:settings' => $this->resource->getSettings(),
            'o:created' => $this->getDateTime($this->resource->getCreated()),
            'o:modified' => $modified ? $this->getDateTime($modified) : null,
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

    public function engine(): \AdvancedSearch\Api\Representation\SearchEngineRepresentation
    {
        $searchEngine = $this->resource->getEngine();
        return $this->getAdapter('search_engines')->getRepresentation($searchEngine);
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
     * @todo Remove site (but manage direct query).
     * @todo Manage direct query here? Remove it?
     *
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
                $query
                    ->setAliases($aliases)
                    ->setOption('remove_diacritics', (bool) $searchConfig->subSetting('q', 'remove_diacritics', false))
                    ->setOption('default_search_partial_word', (bool) $searchConfig->subSetting('q', 'default_search_partial_word', false));
            } catch (\Exception $e) {
                // No aliases.
            }
        }

        $engine = $this->engine();
        $engineSettings = $engine->settings();
        $suggesterSettings = $this->settings();

        $query
            ->setResourceTypes($engineSettings['resource_types'])
            ->setLimitPage(1, empty($suggesterSettings['limit']) ? \Omeka\Stdlib\Paginator::PER_PAGE : (int) $suggesterSettings['limit'])
            ->setSuggestOptions([
                'suggester' => $this->resource->getId(),
                'direct' => !empty($suggesterSettings['direct']),
                'mode_index' => $suggesterSettings['mode_index'] ?? 'start',
                'mode_search' => $suggesterSettings['mode_search'] ?? 'start',
                'length' => $suggesterSettings['length'] ?? 50,
            ])
            ->setSuggestFields($suggesterSettings['fields'] ?? [])
            ->setExcludedFields($suggesterSettings['excluded_fields'] ?? [])
        ;

        /** @var \AdvancedSearch\Querier\QuerierInterface $querier */
        $querier = $engine
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
