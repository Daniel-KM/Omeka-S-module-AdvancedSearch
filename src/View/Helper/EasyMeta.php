<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Doctrine\DBAL\Connection;
use Laminas\View\Helper\AbstractHelper;

/**
 * @see \BulkImport\Mvc\Controller\Plugin\Bulk
 * @see \Reference\Mvc\Controller\Plugin\References
 */
class EasyMeta extends AbstractHelper
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var array
     */
    static protected $propertiesByTerms;

    /**
     * @var array
     */
    static protected $propertiesByTermsAndIds;

    /**
     * @var array
     */
    static protected $propertiesLabels;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Get one or more property ids by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return int[]|int|null The property ids matching terms or ids, or all
     * properties by terms.
     */
    public function propertyIds($termsOrIds = null)
    {
        if (is_null(static::$propertiesByTermsAndIds)) {
            $qb = $this->connection->createQueryBuilder();
            $qb
                ->select(
                    'DISTINCT CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                    'property.id AS id',
                    // Required with only_full_group_by.
                    'vocabulary.id'
                )
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('property.id', 'asc')
            ;
            static::$propertiesByTerms = array_map('intval', $this->connection->executeQuery($qb)->fetchAllKeyValue());
            static::$propertiesByTermsAndIds = array_replace(static::$propertiesByTerms, array_combine(static::$propertiesByTerms, static::$propertiesByTerms));
        }

        if (is_null($termsOrIds)) {
            return static::$propertiesByTerms;
        }

        return is_scalar($termsOrIds)
            ? static::$propertiesByTermsAndIds[$termsOrIds] ?? null
            : array_intersect_key(array_flip($termsOrIds), static::$propertiesByTermsAndIds);
    }

    /**
     * Get one or more property terms by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return string[]|string|null The property terms matching terms or ids, or
     * all properties by ids.
     */
    public function propertyTerms($termsOrIds = null)
    {
        if (is_null(static::$propertiesByTerms)) {
            $this->propertyIds();
        }

        if (is_null($termsOrIds)) {
            return array_flip(static::$propertiesByTerms);
        }

        return is_scalar($termsOrIds)
            ? (array_search($termsOrIds, static::$propertiesByTermsAndIds) ?: null)
            // TODO Keep original order.
            : array_flip(array_intersect_key(static::$propertiesByTermsAndIds, array_fill_keys($termsOrIds, null)));
    }

    /**
     * Get one or more property labels by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return string[]|string|null The property labels matching terms or ids, or
     * all properties by ids. Labels are not translated.
     */
    public function propertyLabels($termsOrIds = null)
    {
        if (is_null(static::$propertiesLabels)) {
            $qb = $this->connection->createQueryBuilder();
            $qb
                ->select(
                    'DISTINCT property.id AS id',
                    'property.label AS label',
                    // Required with only_full_group_by.
                    'vocabulary.id'
                )
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('property.id', 'asc')
            ;
            static::$propertiesLabels = $this->connection->executeQuery($qb)->fetchAllKeyValue();
        }

        if (is_null($termsOrIds)) {
            return static::$propertiesLabels;
        }

        $ids = $this->propertyIds($termsOrIds);
        if (empty($ids)) {
            return $ids;
        }

        if (is_scalar($ids)) {
            return static::$propertiesLabels[$ids] ?? null;
        }

        // TODO Keep original order.
        return array_intersect_key(static::$propertiesLabels, array_fill_keys($ids, null));
    }
}
