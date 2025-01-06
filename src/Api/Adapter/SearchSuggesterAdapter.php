<?php declare(strict_types=1);

namespace AdvancedSearch\Api\Adapter;

use AdvancedSearch\Entity\SearchEngine;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class SearchSuggesterAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'name' => 'name',
        'engine' => 'engine',
        'search_engine' => 'engine',
        'created' => 'created',
        'modified' => 'modified',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'name' => 'name',
        'engine' => 'engine',
        'search_engine' => 'engine',
        'settings' => 'settings',
        'created' => 'created',
        'modified' => 'modified',
    ];

    public function getResourceName()
    {
        return 'search_suggesters';
    }

    public function getRepresentationClass()
    {
        return \AdvancedSearch\Api\Representation\SearchSuggesterRepresentation::class;
    }

    public function getEntityClass()
    {
        return \AdvancedSearch\Entity\SearchSuggester::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $expr = $qb->expr();

        if (isset($query['engine_id'])) {
            $searchEngineAlias = $this->createAlias();
            // The join avoids to find a config without engine.
            $qb->innerJoin(
                SearchEngine::class,
                $searchEngineAlias,
                \Doctrine\ORM\Query\Expr\Join::WITH,
                $expr->andX(
                    $expr->eq($searchEngineAlias . '.id', 'omeka_root.engine'),
                    $expr->in(
                        $searchEngineAlias . '.id',
                        $this->createNamedParameter($qb, $query['engine_id'])
                    )
                )
            );
        }

        if (isset($query['name'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.name',
                $this->createNamedParameter($qb, $query['name']))
            );
        }
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        if ($this->shouldHydrate($request, 'o:name')) {
            $entity->setName($request->getValue('o:name'));
        }
        // The related engine cannot be modified once created.
        if ($request->getOperation() === Request::CREATE
            && $this->shouldHydrate($request, 'o:search_engine')
        ) {
            $searchEngine = $request->getValue('o:search_engine');
            if (is_array($searchEngine)) {
                $searchEngine = $this->getAdapter('search_engines')->findEntity($searchEngine['o:id'] ?? 0);
            } elseif (is_numeric($searchEngine)) {
                $searchEngine = $this->getAdapter('search_engines')->findEntity((int) $searchEngine);
            }
            $entity->setEngine($searchEngine);
        }
        if ($this->shouldHydrate($request, 'o:settings')) {
            $entity->setSettings($request->getValue('o:settings') ?? []);
        }
        $this->updateTimestamps($request, $entity);
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore): void
    {
        if (!$entity->getName()) {
            $errorStore->addError('o:name', 'The name cannot be empty.'); // @translate
        }
    }
}
