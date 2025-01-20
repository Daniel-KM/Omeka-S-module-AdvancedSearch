<?php declare(strict_types=1);

namespace AdvancedSearch\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class ListJobStatusesByIds extends AbstractPlugin
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager = null)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Get the list of jobs statuses according to their status or owner.
     *
     * @param string $class Job class to filter
     * @param bool|array $statusesOrProcessing If true, running jobs. If false, ended jobs.
     * If array, list of statuses to check.
     * @param int $ownerId
     * @param int $excludeJobId
     * @return array List of job statuses by id.
     */
    public function __invoke(
        $class = null,
        $statusesOrProcessing = [],
        ?int $ownerId = null,
        ?int $excludeJobId = null
    ): array {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();
        $result = $qb
            ->select('job.id', 'job.status')
            ->from(\Omeka\Entity\Job::class, 'job');
        if ($class) {
            $qb
                ->andWhere($qb->expr()->eq('job.class', ':class'))
                ->setParameter('class', $class);
        }
        if (!is_array($statusesOrProcessing)) {
            if ($statusesOrProcessing) {
                $statusesOrProcessing = [
                    \Omeka\Entity\Job::STATUS_STARTING,
                    \Omeka\Entity\Job::STATUS_STOPPING,
                    \Omeka\Entity\Job::STATUS_IN_PROGRESS,
                ];
            } else {
                $statusesOrProcessing = [
                    \Omeka\Entity\Job::STATUS_COMPLETED,
                    \Omeka\Entity\Job::STATUS_STOPPED,
                    \Omeka\Entity\Job::STATUS_ERROR,
                ];
            }
        }
        if ($statusesOrProcessing) {
            $qb
                ->andWhere($expr->in('job.status', ':quote'))
                ->setParameter('quote', $statusesOrProcessing, Connection::PARAM_STR_ARRAY);
        }
        if ($ownerId) {
            $qb
                ->andWhere($expr->eq('job.owner_id', ':owner'))
                ->setParameter('owner', $ownerId);
        }
        if ($excludeJobId) {
            $qb
                ->andWhere($expr->neq('job.id', ':job_id'))
                ->setParameter('job_id', $excludeJobId);
        }
        $result = $qb
            ->getQuery()
            ->getScalarResult();
        return array_column($result, 'status', 'id');
    }
}
