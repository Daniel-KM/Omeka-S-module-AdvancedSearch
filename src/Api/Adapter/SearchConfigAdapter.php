<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2017-2026
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace AdvancedSearch\Api\Adapter;

use AdvancedSearch\Entity\SearchEngine;
use Common\Stdlib\PsrMessage;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class SearchConfigAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'name' => 'name',
        'slug' => 'slug',
        'engine' => 'engine',
        'search_engine' => 'engine',
        'form_adapter' => 'formAdapter',
        'created' => 'created',
        'modified' => 'modified',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'name' => 'name',
        'slug' => 'slug',
        'engine' => 'engine',
        'search_engine' => 'engine',
        'form_adapter' => 'formAdapter',
        'settings' => 'settings',
        'created' => 'created',
        'modified' => 'modified',
    ];

    public function getResourceName()
    {
        return 'search_configs';
    }

    public function getRepresentationClass()
    {
        return \AdvancedSearch\Api\Representation\SearchConfigRepresentation::class;
    }

    public function getEntityClass()
    {
        return \AdvancedSearch\Entity\SearchConfig::class;
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

        if (isset($query['slug'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.slug',
                $this->createNamedParameter($qb, $query['slug']))
            );
        }

        if (isset($query['form'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.formAdapter',
                $this->createNamedParameter($qb, $query['form']))
            );
        }

        if (isset($query['path'])) {
            $this->getServiceLocator()->get('Omeka\Logger')->warn(
                'A query for search config uses the argument path, that was replaced by "slug". You should update your query. An exception will be thrown in a future version.' // @translate
            );
            $qb->andWhere($expr->eq(
                'omeka_root.slug',
                $this->createNamedParameter($qb, $query['path']))
            );
        }
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        /** @var \AdvancedSearch\Entity\SearchConfig $entity */

        $entityManager = $this->getEntityManager();

        if ($this->shouldHydrate($request, 'o:name')) {
            $entity->setName($request->getValue('o:name'));
        }
        if ($this->shouldHydrate($request, 'o:slug')) {
            $entity->setSlug($request->getValue('o:slug'));
        }
        if ($this->shouldHydrate($request, 'o:search_engine')) {
            $searchEngine = $request->getValue('o:search_engine');
            if (is_array($searchEngine)) {
                $searchEngine = empty($searchEngine['o:id']) ? null : $entityManager->find(\AdvancedSearch\Entity\SearchEngine::class, $searchEngine['o:id']);
            } elseif (is_numeric($searchEngine)) {
                $searchEngine = $entityManager->find(\AdvancedSearch\Entity\SearchEngine::class, (int) $searchEngine);
            } else {
                $searchEngine = null;
            }
            $entity->setEngine($searchEngine);
        }
        if ($this->shouldHydrate($request, 'o:form_adapter')) {
            $entity->setFormAdapter($request->getValue('o:form_adapter'));
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

        $slug = $entity->getSlug();
        if (!$this->isUnique($entity, ['slug' => $slug])) {
            $errorStore->addError('o:slug', new PsrMessage(
                'The slug "{search_slug}" is already taken.', // @translate
                ['search_slug' => $slug]
            ));
        }
    }
}
