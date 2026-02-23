<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    public function findByDemandeur($user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.demandeur = :user')
            ->setParameter('user', $user)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find commandes for a user with optional filtering and sorting
     */
    public function findForUser($user, ?string $statut = null, ?string $tri = 'dateCommande', ?string $ordre = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.demandeur = :user')
            ->setParameter('user', $user);
        
        if ($statut !== null && $statut !== '') {
            $qb->andWhere('c.statut = :statut')
                ->setParameter('statut', $statut);
        }
        
        // Validate sort field
        $allowedSort = ['dateCommande', 'montantTotal', 'statut'];
        $sortField = in_array($tri, $allowedSort, true) ? $tri : 'dateCommande';
        
        // Validate sort order
        $sortOrder = ($ordre === 'ASC') ? 'ASC' : 'DESC';
        
        $qb->orderBy('c.' . $sortField, $sortOrder);

        $results = $qb->getQuery()->getResult();

        // If sorting by montantTotal ensure numeric ordering in PHP as a fallback
        if ($sortField === 'montantTotal') {
            usort($results, function ($a, $b) use ($sortOrder) {
                $compute = function ($c) {
                    $mt = $c->getMontantTotal();
                    if ($mt !== null) return (float) $mt;
                    $prod = $c->getProduit();
                    $prix = $prod ? $prod->getPrix() : 0;
                    $q = $c->getQuantite() ?? 0;
                    return (float) ($prix * $q);
                };
                $av = $compute($a);
                $bv = $compute($b);
                if ($av === $bv) return 0;
                $cmp = ($av < $bv) ? -1 : 1;
                return $sortOrder === 'ASC' ? $cmp : -$cmp;
            });
        }

        return $results;
    }
    
    /**
     * Get all distinct statuts
     */
    public function findDistinctStatuts(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select('c.statut')
            ->distinct()
            ->orderBy('c.statut', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
        return array_values(array_filter($result));
    }
}
