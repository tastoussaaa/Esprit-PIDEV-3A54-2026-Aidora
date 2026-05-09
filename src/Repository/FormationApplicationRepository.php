<?php

namespace App\Repository;

use App\Entity\FormationApplication;
use App\Entity\Formation;
use App\Entity\AideSoignant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormationApplication>
 */
class FormationApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormationApplication::class);
    }

    /**
     * @return list<FormationApplication>
     */
    public function findByFormation(Formation $formation): array
    {
        return $this->createQueryBuilder('fa')
            ->andWhere('fa.formation = :formation')
            ->setParameter('formation', $formation)
            ->orderBy('fa.appliedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<FormationApplication>
     */
    public function findByAideSoignant(AideSoignant $aideSoignant): array
    {
        return $this->createQueryBuilder('fa')
            ->andWhere('fa.aideSoignant = :aideSoignant')
            ->setParameter('aideSoignant', $aideSoignant)
            ->orderBy('fa.appliedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<FormationApplication>
     */
    public function findPendingByFormation(Formation $formation): array
    {
        return $this->createQueryBuilder('fa')
            ->andWhere('fa.formation = :formation')
            ->andWhere('fa.status = :status')
            ->setParameter('formation', $formation)
            ->setParameter('status', FormationApplication::STATUS_PENDING)
            ->orderBy('fa.appliedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findExistingApplication(Formation $formation, AideSoignant $aideSoignant): ?FormationApplication
    {
        return $this->createQueryBuilder('fa')
            ->andWhere('fa.formation = :formation')
            ->andWhere('fa.aideSoignant = :aideSoignant')
            ->setParameter('formation', $formation)
            ->setParameter('aideSoignant', $aideSoignant)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
