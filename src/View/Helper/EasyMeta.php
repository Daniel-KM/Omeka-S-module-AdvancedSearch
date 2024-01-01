<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Doctrine\DBAL\Connection;
use Laminas\View\Helper\AbstractHelper;

/**
 * @see \AdvancedSearch\View\Helper\EasyMeta
 * @see \Annotate\View\Helper\EasyMeta
 *
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
    protected static $propertyIdsByTerms;

    /**
     * @var array
     */
    protected static $propertyIdsByTermsAndIds;

    /**
     * @var array
     */
    protected static $propertyLabelsByTerms;

    /**
     * @var array
     */
    protected static $propertyLabelsByTermsAndIds;

    /**
     * @var array
     */
    protected static $resourceClassIdsByTerms;

    /**
     * @var array
     */
    protected static $resourceClassIdsByTermsAndIds;

    /**
     * @var array
     */
    protected static $resourceClassLabelsByTerms;

    /**
     * @var array
     */
    protected static $resourceClassLabelsByTermsAndIds;

    /**
     * @var array
     */
    protected static $resourceTemplateIdsByLabels;

    /**
     * @var array
     */
    protected static $resourceTemplateIdsByLabelsAndIds;

    /**
     * @var array
     */
    protected static $resourceTemplateLabelsByLabelsAndIds;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Get a property id by JSON-LD term or by numeric id.
     *
     * @param int|string|null $termOrId A id or a term.
     * @return int|null The property id matching term or id.
     */
    public function propertyId($termOrId): ?int
    {
        if (is_null(static::$propertyIdsByTermsAndIds)) {
            $this->initProperties();
        }
        return static::$propertyIdsByTermsAndIds[$termOrId] ?? null;
    }

    /**
     * Get property ids by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return int[] The property ids matching terms or ids, or all properties
     * by terms. When the input contains terms and ids matching the same
     * properties, they are all returned.
     */
    public function propertyIds($termsOrIds = null): array
    {
        if (is_null(static::$propertyIdsByTermsAndIds)) {
            $this->initProperties();
        }
        if (is_null($termsOrIds)) {
            return static::$propertyIdsByTerms;
        }
        if (is_scalar($termsOrIds)) {
            $termsOrIds = [$termsOrIds];
        }
        return array_intersect_key(static::$propertyIdsByTermsAndIds, array_flip($termsOrIds));
    }

    /**
     * Get a property term by JSON-LD term or by numeric id.
     *
     * @param int|string|null $termOrId A id or a term.
     * @return string|null The property term matching term or id.
     */
    public function propertyTerm($termOrId): ?string
    {
        if (is_null(static::$propertyIdsByTermsAndIds)) {
            $this->initProperties();
        }
        if (!isset(static::$propertyIdsByTermsAndIds[$termOrId])) {
            return null;
        }
        return is_numeric($termOrId)
            ? array_search($termOrId, static::$propertyIdsByTerms)
            : $termOrId;
    }

    /**
     * Get property terms by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return string[] The property terms matching terms or ids, or all
     * properties by ids. When the input contains terms and ids matching the
     * same properties, they are all returned.
     */
    public function propertyTerms($termsOrIds = null): array
    {
        if (is_null(static::$propertyIdsByTermsAndIds)) {
            $this->initProperties();
        }
        if (is_null($termsOrIds)) {
            return array_flip(static::$propertyIdsByTerms);
        }
        if (is_scalar($termsOrIds)) {
            $termsOrIds = [$termsOrIds];
        }
        // TODO Keep original order.
        return array_intersect_key(static::$propertyIdsByTermsAndIds, array_flip($termsOrIds));
    }

    /**
     * Get a property label by JSON-LD term or by numeric id.
     *
     * @param int|string|null $termOrId A id or a term.
     * @return string|null The property label matching term or id. The label is
     * not translated.
     */
    public function propertyLabel($termOrId): ?string
    {
        return static::$propertyLabelsByTermsAndIds[$termOrId] ?? null;
    }

    /**
     * Get property labels by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return string[] The property labels matching terms or ids, or all
     * properties labels by terms. When the input contains terms and ids
     * matching the same properties, they are all returned. Labels are not
     * translated.
     */
    public function propertyLabels($termsOrIds = null): array
    {
        if (is_null($termsOrIds)) {
            if (is_null(static::$propertyLabelsByTerms)) {
                $this->initProperties();
            }
            return static::$propertyLabelsByTerms;
        }
        if (is_scalar($termsOrIds)) {
            $termsOrIds = [$termsOrIds];
        }
        return array_intersect_key(static::$propertyLabelsByTermsAndIds, array_flip($termsOrIds));
    }

    /**
     * Get a resource class id by JSON-LD term or by numeric id.
     *
     * @param int|string|null $termOrId A id or a term.
     * @return int|null The resource class id matching term or id.
     */
    public function resourceClassId($termOrId): ?int
    {
        if (is_null(static::$resourceClassIdsByTermsAndIds)) {
            $this->initResourceClasses();
        }
        return static::$resourceClassIdsByTermsAndIds[$termOrId] ?? null;
    }

    /**
     * Get resource class ids by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return int[] The resource class ids matching terms or ids, or all
     * resource classes by terms. When the input contains terms and ids matching
     * the same resource classes, they are all returned.
     */
    public function resourceClassIds($termsOrIds = null): array
    {
        if (is_null(static::$resourceClassIdsByTermsAndIds)) {
            $this->initResourceClasses();
        }
        if (is_null($termsOrIds)) {
            return static::$resourceClassIdsByTerms;
        }
        if (is_scalar($termsOrIds)) {
            $termsOrIds = [$termsOrIds];
        }
        return array_intersect_key(static::$resourceClassIdsByTermsAndIds, array_flip($termsOrIds));
    }

    /**
     * Get a resource class term by JSON-LD term or by numeric id.
     *
     * @param int|string|null $termOrId A id or a term.
     * @return string|null The resource class term matching term or id.
     */
    public function resourceClassTerm($termOrId): ?string
    {
        if (is_null(static::$resourceClassIdsByTermsAndIds)) {
            $this->initResourceClasses();
        }
        if (!isset(static::$resourceClassIdsByTermsAndIds[$termOrId])) {
            return null;
        }
        return is_numeric($termOrId)
            ? array_search($termOrId, static::$resourceClassIdsByTerms)
            : $termOrId;
    }

    /**
     * Get resource class terms by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return string[] The resource class terms matching terms or ids, or all
     * resource classes by ids.
     */
    public function resourceClassTerms($termsOrIds = null): array
    {
        if (is_null(static::$resourceClassIdsByTermsAndIds)) {
            $this->initResourceClasses();
        }
        if (is_null($termsOrIds)) {
            return array_flip(static::$resourceClassIdsByTerms);
        }
        if (is_scalar($termsOrIds)) {
            $termsOrIds = [$termsOrIds];
        }
        // TODO Keep original order.
        return array_intersect_key(static::$resourceClassIdsByTermsAndIds, array_flip($termsOrIds));
    }

    /**
     * Get a resource class label by JSON-LD term or by numeric id.
     *
     * @param int|string|null $termOrId A id or a term.
     * @return string|null The resource class label matching term or id. The
     * label is not translated
     */
    public function resourceClassLabel($termOrId): ?string
    {
        return static::$resourceClassLabelsByTermsAndIds[$termOrId] ?? null;
    }

    /**
     * Get resource class labels by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return string[] The resource class labels matching terms or ids, or all
     * resource class labels by terms. When the input contains terms and ids
     * matching the same resource classes, they are all returned. Labels are not
     * translated.
     */
    public function resourceClassLabels($termsOrIds = null): array
    {
        if (is_null($termsOrIds)) {
            if (is_null(static::$resourceClassLabelsByTerms)) {
                $this->initResourceClasses();
            }
            return static::$resourceClassLabelsByTerms;
        }
        if (is_scalar($termsOrIds)) {
            $termsOrIds = [$termsOrIds];
        }
        $terms = $this->resourceClassTerms($termsOrIds);
        return array_intersect_key(static::$resourceClassLabelsByTermsAndIds, array_flip($terms));
    }

    /**
     * Get a resource template id by label or by numeric id.
     *
     * @param int|string|null $labelOrId A id or a label.
     * @return int|null The property id matching term or id.
     */
    public function resourceTemplateId($labelOrId): ?int
    {
        if (is_null(static::$resourceTemplateIdsByLabelsAndIds)) {
            $this->initResourceTemplates();
        }
        return static::$resourceTemplateIdsByLabelsAndIds[$labelOrId] ?? null;
    }

    /**
     * Get one or more resource template ids by labels or by numeric ids.
     *
     * @param array|int|string|null $labelsOrIds One or multiple ids or labels.
     * @return string[] The resource template ids matching labels or ids, or all
     * resource templates by labels. When the input contains labels and ids
     * matching the same templates, they are all returned.
     */
    public function resourceTemplateIds($labelsOrIds = null): array
    {
        if (is_null(static::$resourceTemplateIdsByLabelsAndIds)) {
            $this->initResourceTemplates();
        }
        if (is_null($labelsOrIds)) {
            return static::$resourceTemplateByLabels;
        }
        if (is_scalar($labelsOrIds)) {
            $labelsOrIds = [$labelsOrIds];
        }
        return array_intersect_key(static::$resourceTemplateIdsByLabelsAndIds, array_flip($labelsOrIds));
    }

    /**
     * Get a resource template label by label or by numeric id.
     *
     * @param int|string|null $labelOrId A id or a label.
     * @return string|null The resource template label matching label or id.
     */
    public function resourceTemplateLabel($labelOrId): ?string
    {
        return static::$resourceTemplateIdsByLabelsAndIds[$labelOrId] ?? null;
    }

    /**
     * Get one or more resource template labels by labels or by numeric ids.
     *
     * @param array|int|string|null $labelsOrIds One or multiple ids or labels.
     * @return string[] The resource template labels matching labels or ids, or
     * all resource templates labels. When the input contains labels and ids
     * matching the same templates, they are all returned.
     */
    public function resourceTemplateLabels($labelsOrIds = null): array
    {
        if (is_null(static::$resourceTemplateIdsByLabelsAndIds)) {
            $this->initResourceTemplates();
        }
        if (is_null($labelsOrIds)) {
            return array_flip(static::$resourceTemplateIdsByLabels);
        }
        if (is_scalar($labelsOrIds)) {
            $labelsOrIds = [$labelsOrIds];
        }
        // TODO Keep original order.
        return array_intersect_key(static::$resourceTemplateLabelsByLabelsAndIds, array_flip($labelsOrIds));
    }

    protected function initProperties(): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select(
                'DISTINCT CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                'property.id AS id',
                'property.label AS label',
                // Required with only_full_group_by.
                'vocabulary.id'
            )
            ->from('property', 'property')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
            ->orderBy('vocabulary.id', 'asc')
            ->addOrderBy('property.id', 'asc')
        ;
        $result = $this->connection->executeQuery($qb)->fetchAllAssociative();
        static::$propertyIdsByTerms = array_map('intval', array_column($result, 'id', 'term'));
        static::$propertyIdsByTermsAndIds = static::$propertyIdsByTerms + array_combine(static::$propertyIdsByTerms, static::$propertyIdsByTerms);
        static::$propertyLabelsByTerms = array_column($result, 'label', 'term');
        static::$propertyLabelsByTermsAndIds = static::$propertyLabelsByTerms + array_column($result, 'label', 'id');
    }

    protected function initResourceClasses(): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select(
                'DISTINCT CONCAT(vocabulary.prefix, ":", resource_class.local_name) AS term',
                'resource_class.id AS id',
                'resource_class.label AS label',
                // Required with only_full_group_by.
                'vocabulary.id'
            )
            ->from('resource_class', 'resource_class')
            ->innerJoin('resource_class', 'vocabulary', 'vocabulary', 'resource_class.vocabulary_id = vocabulary.id')
            ->orderBy('vocabulary.id', 'asc')
            ->addOrderBy('resource_class.id', 'asc')
        ;
        $result = $this->connection->executeQuery($qb)->fetchAllAssociative();
        static::$resourceClassIdsByTerms = array_map('intval', array_column($result, 'id', 'term'));
        static::$resourceClassIdsByTermsAndIds = static::$resourceClassIdsByTerms + array_combine(static::$resourceClassIdsByTerms, static::$resourceClassIdsByTerms);
        static::$resourceClassLabelsByTerms = array_column($result, 'label', 'term');
        static::$resourceClassLabelsByTermsAndIds = static::$resourceClassLabelsByTerms + array_column($result, 'label', 'id');
    }

    protected function initResourceTemplates(): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select(
                'resource_template.label AS label',
                'resource_template.id AS id'
            )
            ->from('resource_template', 'resource_template')
            ->orderBy('resource_template.label', 'asc')
        ;
        $result = $this->connection->executeQuery($qb)->fetchAllKeyValue();
        static::$resourceTemplateIdsByLabels = array_map('intval', $result);
        static::$resourceTemplateIdsByLabelsAndIds = static::$resourceTemplateIdsByLabels + array_combine(static::$resourceTemplateIdsByLabels, static::$resourceTemplateIdsByLabels);
        static::$resourceTemplateLabelsByLabelsAndIds = array_combine(array_keys(static::$resourceTemplateIdsByLabels), array_keys(static::$resourceTemplateIdsByLabels)) + array_flip(static::$resourceTemplateIdsByLabels);
    }
}
