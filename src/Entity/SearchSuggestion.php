<?php declare(strict_types=1);

namespace AdvancedSearch\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Omeka\Entity\AbstractEntity;

/**
 * @Entity
 * @Table(
 *     uniqueConstraints={
 *         @UniqueConstraint(
 *             columns={
 *                 "suggester_id",
 *                 "text"
 *             }
 *         )
 *     },
 *     indexes={
 *         @Index(
 *             columns={
 *                 "text"
 *             },
 *             flags={
 *                 "fulltext"
 *             }
 *         )
 *     }
 * )
 */
class SearchSuggestion extends AbstractEntity
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
     * @var SearchSuggester
     *
     * @ManyToOne(
     *     targetEntity=SearchSuggester::class,
     *     inversedBy="suggestions"
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $suggester;

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=190,
     *     nullable=false
     * )
     */
    protected $text;

    /**
     * @var Collection|SearchSuggestionSite[]
     *
     * @OneToMany(
     *     targetEntity=SearchSuggestionSite::class,
     *     mappedBy="suggestion",
     *     orphanRemoval=true,
     *     cascade={
     *         "persist",
     *         "remove"
     *     },
     *     indexBy="siteId"
     * )
     */
    protected $sites;

    public function __construct()
    {
        $this->sites = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setSuggester(SearchSuggester $suggester): self
    {
        $this->suggester = $suggester;
        return $this;
    }

    public function getSuggester(): SearchSuggester
    {
        return $this->suggester;
    }

    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getSites(): Collection
    {
        return $this->sites;
    }
}
