<?php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /**
     * @return Produit[] For shop: filter by category (optional), sort by field
     */
    public function findForShop(?string $categorie = null, ?string $tri = 'nom', ?string $ordre = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('p');
        
        if ($categorie !== null && $categorie !== '') {
            $qb->andWhere('p.categorie = :cat')
                ->setParameter('cat', $categorie);
        }
        
        // Validate sort field
        $allowedSort = ['nom', 'prix', 'categorie', 'stock'];
        $sortField = in_array($tri, $allowedSort, true) ? $tri : 'prix'; // Default to 'prix' if invalid

        // Validate sort order
        $sortOrder = ($ordre === 'DESC') ? 'DESC' : 'ASC';
        
        $qb->orderBy('p.' . $sortField, $sortOrder);

        $results = $qb->getQuery()->getResult();

        // If sorting by prix, ensure numeric ordering in PHP as a fallback
        if ($sortField === 'prix') {
            usort($results, function (Produit $a, Produit $b) use ($sortOrder) {
                $av = (float) $a->getPrix();
                $bv = (float) $b->getPrix();
                if ($av === $bv) {
                    return 0;
                }
                $cmp = ($av < $bv) ? -1 : 1;
                return $sortOrder === 'ASC' ? $cmp : -$cmp;
            });
        }

        return $results;
    }

    public function findDistinctCategories(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.categorie')
            ->distinct()
            ->orderBy('p.categorie', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
        return array_values(array_filter($result));
    }

//    /**
//     * @return Produit[] Returns an array of Produit objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Produit
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
