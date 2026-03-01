<?php

namespace App\Controller;

use App\Repository\FormationRepository;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Web\WebDriver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ChatbotTestController extends AbstractController
{
    #[Route('/chat/frame', name: 'chat_frame')]
    public function chatFrame(): Response
    {
        return $this->render('chat_bot/frame.html.twig');
    }

    #[Route('/botman/chat', name: 'botman_chat', methods: ['GET', 'POST'])]
    public function handle(Request $request, FormationRepository $formationRepository): Response
    {
        DriverManager::loadDriver(WebDriver::class);

        $botman = BotManFactory::create([]);

        $botman->hears('hi', function (BotMan $bot) {
            $bot->reply('Hello 👋');
        });

        $botman->hears('hi(.*)', function (BotMan $bot) {
            $bot->reply('Hello, I am a chatBot!');
        });

        $botman->hears('salut(.*)', function (BotMan $bot) {
            $bot->reply('Salut, je suis un chatBot!');
        });

        $botman->hears('formation {title}', function (BotMan $bot, $title) use ($formationRepository) {
            $formation = $formationRepository->findOneBy(['title' => $title]);

            if ($formation) {
                $bot->reply("📚 Formation trouvée : " . $formation->getTitle() . " - " . $formation->getDescription());
            } else {
                $bot->reply("❌ Aucune formation trouvée avec ce titre.");
            }
        });

        $botman->fallback(function (BotMan $bot) {
            $bot->reply("Je ne comprends pas votre demande.");
        });

        // ✅ Capture BotMan's output instead of letting it echo directly
        ob_start();
        $botman->listen();
        $content = ob_get_clean();

        return new Response($content, 200, [
            'Content-Type' => 'application/json',
        ]);
    }
}