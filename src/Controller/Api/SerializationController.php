<?php

namespace App\Controller\Api;

use App\Repository\DemandeAideRepository;
use App\Repository\MissionRepository;
use App\Service\ApiDtoMapper;
use App\Service\CalendarAvailabilityService;
use App\Service\MatchingEngineService;
use App\Service\RiskSupervisionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;
use App\Repository\AideSoignantRepository;

#[Route('/api/serialize', name: 'api_serialize_')]
class SerializationController extends AbstractController
{
    #[Route('/mission/{id}', name: 'mission', methods: ['GET'])]
    public function mission(
        int $id,
        MissionRepository $missionRepository,
        ApiDtoMapper $mapper,
        SerializerInterface $serializer,
    ): JsonResponse {
        try {
            $mission = $missionRepository->find($id);
            if (!$mission) {
                throw new NotFoundHttpException('Mission introuvable.');
            }

            $dto = $mapper->mapMission($mission);
            $json = $serializer->serialize($dto, 'json', [
                'groups' => ['mission:read'],
            ]);

            return JsonResponse::fromJsonString($json);
        } catch (\Throwable $exception) {
            return $this->jsonError($exception, $serializer);
        }
    }

    #[Route('/demande/{id}', name: 'demande', methods: ['GET'])]
    public function demande(
        int $id,
        DemandeAideRepository $demandeAideRepository,
        ApiDtoMapper $mapper,
        SerializerInterface $serializer,
    ): JsonResponse {
        try {
            $demande = $demandeAideRepository->find($id);
            if (!$demande) {
                throw new NotFoundHttpException('Demande introuvable.');
            }

            $dto = $mapper->mapDemande($demande);
            $json = $serializer->serialize($dto, 'json', [
                'groups' => ['demande:read'],
                'datetime_format' => \DateTimeInterface::ATOM,
            ]);

            return JsonResponse::fromJsonString($json);
        } catch (\Throwable $exception) {
            return $this->jsonError($exception, $serializer);
        }
    }

    #[Route('/demande/{id}/top-aides', name: 'demande_top_aides', methods: ['GET'])]
    public function demandeTopAides(
        int $id,
        DemandeAideRepository $demandeAideRepository,
        MatchingEngineService $matchingEngine,
        ApiDtoMapper $mapper,
        SerializerInterface $serializer,
    ): JsonResponse {
        try {
            $demande = $demandeAideRepository->find($id);
            if (!$demande) {
                throw new NotFoundHttpException('Demande introuvable.');
            }

            $topAides = $matchingEngine->getTopAidesForDemande($demande, 5);
            $payload = array_map(function (array $item) use ($mapper) {
                return $mapper->mapAideMatch(
                    $item['aide'],
                    (int) $item['score'],
                    (bool) $item['available']
                );
            }, $topAides);

            $json = $serializer->serialize($payload, 'json', [
                'groups' => ['aide_match:read'],
            ]);

            return JsonResponse::fromJsonString($json);
        } catch (\Throwable $exception) {
            return $this->jsonError($exception, $serializer);
        }
    }

    #[Route('/demande/{id}/calendar-slots', name: 'demande_calendar_slots', methods: ['GET'])]
    public function demandeCalendarSlots(
        int $id,
        DemandeAideRepository $demandeAideRepository,
        CalendarAvailabilityService $calendarAvailability,
        ApiDtoMapper $mapper,
        SerializerInterface $serializer,
    ): JsonResponse {
        try {
            $demande = $demandeAideRepository->find($id);
            if (!$demande) {
                throw new NotFoundHttpException('Demande introuvable.');
            }

            $slots = $calendarAvailability->getBusySlotsForDemande($demande, 10);
            $payload = [];

            foreach ($slots as $slot) {
                $mission = $slot['mission'];
                $aide = $slot['aide'];
                $status = (string) ($mission->getStatutMission() ?? 'N/A');

                $color = match ($status) {
                    'ACCEPTÉE' => '#ef4444',
                    'EN_ATTENTE' => '#f59e0b',
                    default => '#64748b',
                };

                $payload[] = $mapper->mapCalendarSlot(
                    missionId: (int) $mission->getId(),
                    aideId: (int) $aide->getId(),
                    aideName: trim(sprintf('%s %s', (string) $aide->getPrenom(), (string) $aide->getNom())),
                    status: $status,
                    startAt: $mission->getDateDebut(),
                    endAt: $mission->getDateFin(),
                    color: $color,
                );
            }

            $json = $serializer->serialize($payload, 'json', [
                'groups' => ['calendar_slot:read'],
                'datetime_format' => \DateTimeInterface::ATOM,
            ]);

            return JsonResponse::fromJsonString($json);
        } catch (\Throwable $exception) {
            return $this->jsonError($exception, $serializer);
        }
    }

    #[Route('/risk/aide/{id}', name: 'risk_aide', methods: ['GET'])]
    public function aideRisk(
        int $id,
        AideSoignantRepository $aideSoignantRepository,
        RiskSupervisionService $riskSupervision,
        ApiDtoMapper $mapper,
        SerializerInterface $serializer,
    ): JsonResponse {
        try {
            $aide = $aideSoignantRepository->find($id);
            if (!$aide) {
                throw new NotFoundHttpException('Aide-soignant introuvable.');
            }

            $riskData = $riskSupervision->computeAideReliability($aide);
            $dto = $mapper->mapAideReliability((int) $aide->getId(), $riskData);

            $json = $serializer->serialize($dto, 'json', [
                'groups' => ['aide_risk:read'],
            ]);

            return JsonResponse::fromJsonString($json);
        } catch (\Throwable $exception) {
            return $this->jsonError($exception, $serializer);
        }
    }

    #[Route('/risk/demande/{id}', name: 'risk_demande', methods: ['GET'])]
    public function demandeRisk(
        int $id,
        DemandeAideRepository $demandeAideRepository,
        RiskSupervisionService $riskSupervision,
        ApiDtoMapper $mapper,
        SerializerInterface $serializer,
    ): JsonResponse {
        try {
            $demande = $demandeAideRepository->find($id);
            if (!$demande) {
                throw new NotFoundHttpException('Demande introuvable.');
            }

            $riskData = $riskSupervision->computeDemandeRisk($demande);
            $dto = $mapper->mapDemandeRisk((int) $demande->getId(), $riskData);

            $json = $serializer->serialize($dto, 'json', [
                'groups' => ['demande_risk:read'],
            ]);

            return JsonResponse::fromJsonString($json);
        } catch (\Throwable $exception) {
            return $this->jsonError($exception, $serializer);
        }
    }

    #[Route('/risk/alerts', name: 'risk_alerts', methods: ['GET'])]
    public function riskAlerts(
        RiskSupervisionService $riskSupervision,
        ApiDtoMapper $mapper,
        SerializerInterface $serializer,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $alerts = $riskSupervision->buildAdminAlerts(20);
            $payload = array_map(fn (array $item) => $mapper->mapRiskAlert($item), $alerts);

            $json = $serializer->serialize($payload, 'json', [
                'groups' => ['risk_alert:read'],
            ]);

            return JsonResponse::fromJsonString($json);
        } catch (\Throwable $exception) {
            return $this->jsonError($exception, $serializer);
        }
    }

    private function jsonError(\Throwable $exception, SerializerInterface $serializer): JsonResponse
    {
        $statusCode = match (true) {
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $exception instanceof AccessDeniedException => 403,
            default => 500,
        };

        $json = $serializer->serialize($exception, 'json', [
            'groups' => ['error:read'],
        ]);

        return JsonResponse::fromJsonString($json, $statusCode);
    }
}
