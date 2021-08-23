<?php declare(strict_types=1);

namespace AdvancedSearch\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Omeka\Entity\AbstractEntity;

/**
 * @Entity
 * @HasLifecycleCallbacks
 */
class SearchSuggester extends AbstractEntity
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
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=190
     * )
     */
    protected $name;

    /**
     * @var SearchEngine
     *
     * @ManyToOne(
     *     targetEntity=SearchEngine::class,
     *     inversedBy="searchSuggesters"
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $engine;

    /**
     * @var array
     *
     * @Column(
     *     type="json",
     *     nullable=true
     * )
     */
    protected $settings = [];

    /**
     * @var DateTime
     *
     * @Column(
     *     type="datetime"
     * )
     */
    protected $created;

    /**
     * @var DateTime
     *
     * @Column(
     *     type="datetime",
     *     nullable=true
     * )
     */
    protected $modified;

    /**
     * @OneToMany(
     *     targetEntity=SearchSuggestion::class,
     *     mappedBy="suggester",
     *     orphanRemoval=true,
     *     cascade={"persist", "remove"}
     * )
     */
    protected $suggestions;

    public function __construct()
    {
        parent::__construct();
        $this->suggests = new ArrayCollection;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setEngine(SearchEngine $engine): self
    {
        $this->engine = $engine;
        return $this;
    }

    public function getEngine(): SearchEngine
    {
        return $this->engine;
    }

    public function setSettings(?array $settings): self
    {
        $this->settings = $settings;
        return $this;
    }

    public function getSettings(): array
    {
        return $this->settings ?? [];
    }

    public function setCreated(DateTime $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function setModified(?DateTime $dateTime): self
    {
        $this->modified = $dateTime;
        return $this;
    }

    public function getModified(): ?DateTime
    {
        return $this->modified;
    }

    public function getSuggestions(): ArrayCollection
    {
        return $this->suggestions;
    }

    /**
     * @PrePersist
     */
    public function prePersist(LifecycleEventArgs $eventArgs): self
    {
        $this->created = new DateTime('now');
        return $this;
    }

    /**
     * @PreUpdate
     */
    public function preUpdate(PreUpdateEventArgs $eventArgs): self
    {
        $this->modified = new DateTime('now');
        return $this;
    }
}
