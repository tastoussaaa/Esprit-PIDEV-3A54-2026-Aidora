<?php

namespace App\Repository;

use App\Entity\Avis;
use App\Entity\AideSoignant;
use App\Entity\Medecin;
use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Avis>
 */
class AvisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avis::class);
    }

    public function hasPatientRatedMedecin(Patient $patient, Medecin $medecin): bool
    {
        return $this->findOneBy(['patient' => $patient, 'medecin' => $medecin]) instanceof Avis;
    }

    public function hasPatientRatedAide(Patient $patient, AideSoignant $aide): bool
    {
        return $this->findOneBy(['patient' => $patient, 'aideSoignant' => $aide]) instanceof Avis;
    }

    /**
     * @param list<int> $medecinIds
     * @return array<int, array{avg:float,count:int}>
     */
    public function getMedecinStatsMap(array $medecinIds): array
    {
        if ($medecinIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.medecin) AS medecinId, AVG(a.rating) AS avgRating, COUNT(a.id) AS totalReviews')
            ->andWhere('a.medecin IN (:ids)')
            ->setParameter('ids', $medecinIds)
            ->groupBy('a.medecin')
            ->getQuery()
            ->getArrayResult();

        $stats = [];
        foreach ($rows as $row) {
            $id = (int) $row['medecinId'];
            $stats[$id] = [
                'avg' => round((float) $row['avgRating'], 1),
                'count' => (int) $row['totalReviews'],
            ];
        }

        return $stats;
    }

    /**
     * @param list<int> $medecinIds
     * @return array<int, int>
     */
    public function getPatientMedecinRatingsMap(Patient $patient, array $medecinIds): array
    {
        if ($medecinIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.medecin) AS medecinId, a.rating AS rating')
            ->andWhere('a.patient = :patient')
            ->andWhere('a.medecin IN (:ids)')
            ->setParameter('patient', $patient)
            ->setParameter('ids', $medecinIds)
            ->getQuery()
            ->getArrayResult();

        $ratings = [];
        foreach ($rows as $row) {
            $ratings[(int) $row['medecinId']] = (int) $row['rating'];
        }

        return $ratings;
    }

    /**
     * @param list<int> $aideIds
     * @return array<int, array{avg:float,count:int}>
     */
    public function getAideStatsMap(array $aideIds): array
    {
        if ($aideIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.aideSoignant) AS aideId, AVG(a.rating) AS avgRating, COUNT(a.id) AS totalReviews')
            ->andWhere('a.aideSoignant IN (:ids)')
            ->setParameter('ids', $aideIds)
            ->groupBy('a.aideSoignant')
            ->getQuery()
            ->getArrayResult();

        $stats = [];
        foreach ($rows as $row) {
            $id = (int) $row['aideId'];
            $stats[$id] = [
                'avg' => round((float) $row['avgRating'], 1),
                'count' => (int) $row['totalReviews'],
            ];
        }

        return $stats;
    }

    /**
     * @param list<int> $aideIds
     * @return array<int, int>
     */
    public function getPatientAideRatingsMap(Patient $patient, array $aideIds): array
    {
        if ($aideIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.aideSoignant) AS aideId, a.rating AS rating')
            ->andWhere('a.patient = :patient')
            ->andWhere('a.aideSoignant IN (:ids)')
            ->setParameter('patient', $patient)
            ->setParameter('ids', $aideIds)
            ->getQuery()
            ->getArrayResult();

        $ratings = [];
        foreach ($rows as $row) {
            $ratings[(int) $row['aideId']] = (int) $row['rating'];
        }

        return $ratings;
    }
}
