<?php
// src/Controller/BotManController.php
namespace App\Controller;

use App\Repository\FormationRepository;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BotManController extends AbstractController
{
    private $formationRepository;

    public function __construct(FormationRepository $formationRepository)
    {
        $this->formationRepository = $formationRepository;
    }

    #[Route('/botman', name: 'botman', methods: ['GET', 'POST'])]
    public function handle(Request $request): Response
    {
        // 1️⃣ Load the Web Driver
        DriverManager::loadDriver(\BotMan\Drivers\Web\WebDriver::class);

        // 2️⃣ Create BotMan instance directly
        $config = []; // Optional: add config here if needed
        $botman = BotManFactory::create($config);

        // 3️⃣ Handle messages
        $botman->hears('.*', function (BotMan $bot) {
            $bot->startConversation(new \App\Controller\FormationConversation($this->formationRepository));
        });

        $botman->listen();

        return new Response();
    }
}
