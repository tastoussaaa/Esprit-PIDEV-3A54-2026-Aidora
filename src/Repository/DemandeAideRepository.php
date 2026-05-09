<?php

namespace App\Repository;

use App\Entity\DemandeAide;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DemandeAide>
 */
class DemandeAideRepository extends ServiceEntityRepository
{
    private const ACTIVE_LIST_LIMIT = 100;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeAide::class);
    }

    /**
     * @return list<DemandeAide>
     */
    public function findActiveByEmail(string $email): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('LOWER(d.email) = LOWER(:email)')
            ->andWhere('(d.statut IS NULL OR d.statut NOT IN (:archivedStatuses))')
            ->setParameter('email', $email)
            ->setParameter('archivedStatuses', ['TERMINÉE', 'EXPIRÉE', 'ANNULÉE'])
            ->orderBy('d.dateCreation', 'DESC')
            ->setMaxResults(self::ACTIVE_LIST_LIMIT)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<DemandeAide>
     */
    public function findArchivedByEmail(string $email): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('LOWER(d.email) = LOWER(:email)')
            ->andWhere('d.statut IN (:archivedStatuses)')
            ->setParameter('email', $email)
            ->setParameter('archivedStatuses', ['TERMINÉE', 'EXPIRÉE', 'ANNULÉE'])
            ->orderBy('d.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return DemandeAide[] Returns an array of DemandeAide objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('d.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?DemandeAide
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
