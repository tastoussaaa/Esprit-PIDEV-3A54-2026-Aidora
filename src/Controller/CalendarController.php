<?php

namespace App\Controller;

use App\Entity\Formation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\FormationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;


final class CalendarController extends AbstractController
{
    #[Route('/formations/calendar', name: 'formations_calendar')]
    public function calendar(): Response
    {
        return $this->render('calendar/calendar.html.twig');
    }
    #[Route('/api/formations/events', name: 'api_formations_events')]
public function formationsEvents(FormationRepository $repository): JsonResponse
{
    $formations = $repository->findBy(['statut' => 'VALIDE']);

    $events = [];

    foreach ($formations as $formation) {

        $color = match($formation->getStatut()) {
            'VALIDE' => '#28a745',
            'REFUSE' => '#dc3545',
            default  => '#ffc107'
        };

        $events[] = [
            'id' => $formation->getId(),
            'title' => $formation->getTitle(),
            'start' => $formation->getStartDate()->format('Y-m-d H:i:s'),
            'end'   => $formation->getEndDate()->format('Y-m-d H:i:s'),
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'category' => $formation->getCategory(),
                'statut' => $formation->getStatut(),
            ]
        ];
    }

    return new JsonResponse($events);
}

    #[Route('/formations/{id}', name: 'formation_show')]
    public function show(Formation $formation): Response
    {
        return $this->render('formation/medecin_formation_show.html.twig', [
            'formation' => $formation,
        ]);
    }
}
