<?php declare(strict_types=1);

namespace AdvancedSearch\Entity;

use Omeka\Entity\AbstractEntity;

/**
 * Store suggestion counts per site.
 *
 * site_id = 0 represents the global admin index (all resources, including
 * private ones). Other values are actual site IDs.
 *
 * @todo Incremental update: when a resource is added/modified/deleted, update
 * the suggestion counts accordingly instead of full reindexation:
 * - On resource create: extract suggestions from values, increment totals
 * - On resource update: diff old/new values, adjust totals
 * - On resource delete: decrement totals, remove suggestions with total=0
 * - On site assignment change: move counts between sites
 *
 * @Entity
 * @Table(
 *     uniqueConstraints={
 *         @UniqueConstraint(
 *             columns={
 *                 "suggestion_id",
 *                 "site_id"
 *             }
 *         )
 *     },
 *     indexes={
 *         @Index(
 *             columns={
 *                 "site_id"
 *             }
 *         )
 *     }
 * )
 */
class SearchSuggestionSite extends AbstractEntity
{
    /**
     * @var int
     *
     * @Id
     * @Column(
     *     type="integer"
     * )
     * @GeneratedValue
     */
    protected $id;

    /**
     * @var SearchSuggestion
     *
     * @ManyToOne(
     *     targetEntity=SearchSuggestion::class,
     *     inversedBy="sites"
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $suggestion;

    /**
     * @var int
     *
     * site_id = 0 means global (all sites), otherwise the actual site ID.
     * Not a foreign key because 0 doesn't exist in site table.
     *
     * @Column(
     *     type="integer",
     *     nullable=false,
     *     options={
     *         "default": 0
     *     }
     * )
     */
    protected $siteId = 0;

    /**
     * @var int
     *
     * Total count of all resources (public + private) for admin.
     *
     * @Column(
     *     type="integer",
     *     nullable=false,
     *     options={
     *         "default": 0
     *     }
     * )
     */
    protected $total = 0;

    /**
     * @var int
     *
     * Total count of public resources only for visitors.
     *
     * @Column(
     *     type="integer",
     *     nullable=false,
     *     options={
     *         "default": 0
     *     }
     * )
     */
    protected $totalPublic = 0;

    public function getId()
    {
        return $this->id;
    }

    public function setSuggestion(SearchSuggestion $suggestion): self
    {
        $this->suggestion = $suggestion;
        return $this;
    }

    public function getSuggestion(): SearchSuggestion
    {
        return $this->suggestion;
    }

    public function setSiteId(int $siteId): self
    {
        $this->siteId = $siteId;
        return $this;
    }

    public function getSiteId(): int
    {
        return $this->siteId;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;
        return $this;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotalPublic(int $totalPublic): self
    {
        $this->totalPublic = $totalPublic;
        return $this;
    }

    public function getTotalPublic(): int
    {
        return $this->totalPublic;
    }
}
