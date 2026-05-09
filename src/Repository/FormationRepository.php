<?php

namespace App\Repository;

use App\Entity\Formation;
use App\Entity\Medecin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formation>
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    //    /**
    //     * @return Formation[] Returns an array of Formation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('f.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Formation
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * @return list<Formation>
     */
    public function findValidated(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.statut = :statut')
            ->setParameter('statut', Formation::STATUT_VALIDE)
            ->orderBy('f.startDate', 'DESC')
            ->andWhere('f.startDate >= :today')
            ->setParameter('today', new \DateTime())

            ->getQuery()
            ->getResult();
    }


    /**
     * @return list<Formation>
     */
    public function findValidatedByCategory(?string $category = null, ?string $searchTerm = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.statut = :statut')
            ->setParameter('statut', Formation::STATUT_VALIDE)
            ->andWhere('f.startDate >= :today')
            ->setParameter('today', new \DateTime())
            ->orderBy('f.startDate', 'DESC');

        if ($category) {
            $qb->andWhere('f.category = :category')
                ->setParameter('category', $category);
        }

        if ($searchTerm !== null && $searchTerm !== '') {
            $qb->andWhere('LOWER(f.title) LIKE :search OR LOWER(f.description) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($searchTerm) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Formation>
     */
    public function findVisibleForMedecin(Medecin $medecin, ?string $category = null, ?string $searchTerm = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('(f.statut = :validated OR f.medecin = :medecin)')
            ->setParameter('validated', Formation::STATUT_VALIDE)
            ->setParameter('medecin', $medecin)
            ->andWhere('f.startDate >= :today')
            ->setParameter('today', new \DateTime())
            ->orderBy('f.startDate', 'DESC');

        if ($category) {
            $qb->andWhere('f.category = :category')
                ->setParameter('category', $category);
        }

        if ($searchTerm !== null && $searchTerm !== '') {
            $qb->andWhere('LOWER(f.title) LIKE :search OR LOWER(f.description) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($searchTerm) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<string>
     */
    public function findAllCategories(): array
    {
        $result = $this->createQueryBuilder('f')
            ->select('DISTINCT f.category')
            ->getQuery()
            ->getResult();

        // Flatten array of arrays to a simple array of strings
        return array_values(array_map(
            static fn(array $categoryRow): string => (string) $categoryRow['category'],
            $result
        ));
    }
}
