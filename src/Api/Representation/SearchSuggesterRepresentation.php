<?php declare(strict_types=1);

namespace AdvancedSearch\Api\Representation;

use AdvancedSearch\Querier\Exception\QuerierException;
use AdvancedSearch\Query;
use AdvancedSearch\Response;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Stdlib\Message;

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

        return $url('admin/search/suggester-id', $params, $options);
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
     */
    public function suggest(string $q, ?SiteRepresentation $site = null): Response
    {
        $query = new Query();
        $query->setQuery($q);

        $user = $this->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();
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
        }

        $engine = $this->engine();
        $engineSettings = $engine->settings();
        $suggesterSettings = $this->settings();

        $query
            ->setResources($engineSettings['resources'])
            ->setLimitPage(1, empty($suggesterSettings['limit']) ? \Omeka\Stdlib\Paginator::PER_PAGE : (int) $suggesterSettings['limit'])
            ->setSuggestOptions([
                'suggester' => $this->resource->getId(),
                'direct' => !empty($suggesterSettings['direct']),
                'mode_index' => $suggesterSettings['mode_index'] ?? 'start',
                'mode_search' => $suggesterSettings['mode_search'] ?? 'start',
                'length' => $suggesterSettings['length'] ?? 50,
            ])
            ->setSuggestFields($suggesterSettings['fields'] ?? []);

        /** @var \AdvancedSearch\Querier\QuerierInterface $querier */
        $querier = $engine
            ->querier()
            ->setQuery($query);
        try {
            return $querier->querySuggestions();
        } catch (QuerierException $e) {
            $message = new Message("Query error: %s\nQuery:%s", $e->getMessage(), json_encode($query->jsonSerialize(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); // @translate
            $this->logger()->err($message);
            return (new Response)
                ->setMessage($message);
        }
    }
}
