<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Doctrine\DBAL\Connection;
use Laminas\View\Helper\AbstractHelper;

/**
 * @see \BulkImport\Mvc\Controller\Plugin\Bulk
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
}
