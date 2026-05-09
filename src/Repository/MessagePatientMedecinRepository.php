<?php

namespace App\Repository;

use App\Entity\Medecin;
use App\Entity\MessagePatientMedecin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessagePatientMedecin>
 */
class MessagePatientMedecinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessagePatientMedecin::class);
    }

    /**
     * @return MessagePatientMedecin[]
     */
    public function findByMedecinOrdered(Medecin $medecin): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
