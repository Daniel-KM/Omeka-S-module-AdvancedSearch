<?php declare(strict_types=1);

namespace AdvancedSearch\Entity;

use Omeka\Entity\AbstractEntity;

/**
 * @Entity
 * @Table(
 *     indexes={
 *         @Index(
 *             name="search_text_idx",
 *             columns={
 *                 "text",
 *                 "suggester_id"
 *             }
 *         ),
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
     *     inversedBy="suggests"
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
     * @var int
     *
     * @Column(
     *     type="integer",
     *     nullable=false
     * )
     */
    protected $totalAll = 0;

    /**
     * @var int
     *
     * @Column(
     *     type="integer",
     *     nullable=false
     * )
     */
    protected $totalPublic = 0;

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

    public function setTotalAll(int $totalAll): self
    {
        $this->totalAll = $totalAll;
        return $this;
    }

    public function getTotalAll(): int
    {
        return $this->totalAll;
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
