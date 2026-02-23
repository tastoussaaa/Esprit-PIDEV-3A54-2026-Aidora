<?php

namespace App\Service;

use App\DTO\Api\AideMatchOutputDto;
use App\DTO\Api\AideReliabilityOutputDto;
use App\DTO\Api\CalendarSlotOutputDto;
use App\DTO\Api\DemandeAideOutputDto;
use App\DTO\Api\DemandeRiskOutputDto;
use App\DTO\Api\MissionOutputDto;
use App\DTO\Api\RiskAlertOutputDto;
use App\Entity\AideSoignant;
use App\Entity\DemandeAide;
use App\Entity\Mission;

class ApiDtoMapper
{
    public function mapMission(Mission $mission): MissionOutputDto
    {
        return new MissionOutputDto(
            id: (int) $mission->getId(),
            title: $mission->getTitreM(),
            status: $mission->getStatutMission(),
            finalStatus: $mission->getFinalStatus(),
            startAt: $mission->getDateDebut(),
            endAt: $mission->getDateFin(),
            prixFinal: $mission->getPrixFinal(),
            demandeId: $mission->getDemandeAide()?->getId(),
            aideSoignantId: $mission->getAideSoignant()?->getId(),
        );
    }

    public function mapDemande(DemandeAide $demande): DemandeAideOutputDto
    {
        return new DemandeAideOutputDto(
            id: (int) $demande->getId(),
            title: $demande->getTitreD(),
            typeDemande: $demande->getTypeDemande(),
            typePatient: $demande->getTypePatient(),
            status: $demande->getStatut(),
            budgetMax: $demande->getBudgetMax(),
            dateDebutSouhaitee: $demande->getDateDebutSouhaitee(),
            dateFinSouhaitee: $demande->getDateFinSouhaitee(),
            urgencyScore: $demande->getUrgencyScore(),
            aideChoisieId: $demande->getAideChoisie()?->getId(),
        );
    }

    public function mapAideMatch(AideSoignant $aide, int $score, bool $available): AideMatchOutputDto
    {
        return new AideMatchOutputDto(
            id: (int) $aide->getId(),
            nom: $aide->getNom(),
            prenom: $aide->getPrenom(),
            sexe: $aide->getSexe(),
            villeIntervention: $aide->getVilleIntervention(),
            rayonInterventionKm: $aide->getRayonInterventionKm(),
            tarifMin: $aide->getTarifMin(),
            niveauExperience: $aide->getNiveauExperience(),
            available: $available,
            score: $score,
        );
    }

    /**
     * @param array{score:int,completedMissions:int,cancelledOrExpiredMissions:int,suspiciousCheckouts:int,anomalies:list<string>} $data
     */
    public function mapAideReliability(int $aideId, array $data): AideReliabilityOutputDto
    {
        return new AideReliabilityOutputDto(
            aideId: $aideId,
            score: (int) $data['score'],
            completedMissions: (int) $data['completedMissions'],
            cancelledOrExpiredMissions: (int) $data['cancelledOrExpiredMissions'],
            suspiciousCheckouts: (int) $data['suspiciousCheckouts'],
            anomalies: $data['anomalies'],
        );
    }

    /**
     * @param array{score_deterministe:int,score_ia:?int,score_final:int,level:string,factors:list<string>,justification_ia:?string,pathologie_probable:?string} $data
     */
    public function mapDemandeRisk(int $demandeId, array $data): DemandeRiskOutputDto
    {
        return new DemandeRiskOutputDto(
            demandeId: $demandeId,
            scoreDeterministe: (int) $data['score_deterministe'],
            scoreIa: $data['score_ia'],
            scoreFinal: (int) $data['score_final'],
            level: (string) $data['level'],
            factors: $data['factors'],
            justificationIa: $data['justification_ia'],
            pathologieProbable: $data['pathologie_probable'],
        );
    }

    /**
     * @param array{type:string,severity:string,message:string,aideId:?int,demandeId:?int,missionId:?int} $data
     */
    public function mapRiskAlert(array $data): RiskAlertOutputDto
    {
        return new RiskAlertOutputDto(
            type: $data['type'],
            severity: $data['severity'],
            message: $data['message'],
            aideId: $data['aideId'],
            demandeId: $data['demandeId'],
            missionId: $data['missionId'],
        );
    }

    public function mapCalendarSlot(
        int $missionId,
        int $aideId,
        string $aideName,
        ?string $status,
        ?\DateTimeInterface $startAt,
        ?\DateTimeInterface $endAt,
        string $color,
    ): CalendarSlotOutputDto {
        return new CalendarSlotOutputDto(
            missionId: $missionId,
            aideId: $aideId,
            aideName: $aideName,
            status: $status,
            startAt: $startAt,
            endAt: $endAt,
            color: $color,
        );
    }
}
