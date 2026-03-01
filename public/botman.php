<?php

use App\Kernel;
use App\Repository\FormationRepository;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Web\WebDriver;

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    (new Symfony\Component\Dotenv\Dotenv())->loadEnv(__DIR__ . '/../.env');
}

// ✅ Boot Symfony kernel just to get the container
$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool)($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

// ✅ Get your repository directly
/** @var FormationRepository $formationRepository */
$formationRepository = $kernel->getContainer()->get(FormationRepository::class);

// ── BotMan ───────────────────────────────────────────────────────────────────
DriverManager::loadDriver(WebDriver::class);
$botman = BotManFactory::create([]);

$botman->hears('formations disponibles', function (BotMan $bot) use ($formationRepository) {
    $formations = $formationRepository->findValidated();
    if (empty($formations)) {
        $bot->reply("Aucune formation disponible.");
        return;
    }
    $list = implode("\n", array_map(fn($f) => "📚 " . $f->getTitle(), $formations));
    $bot->reply("Formations disponibles :\n" . $list);
});

$botman->hears('formation {title}', function (BotMan $bot, string $title) use ($formationRepository) {
    $formation = $formationRepository->findOneBy(['title' => $title]);
    if ($formation) {
        $bot->reply(
            "📚 *" . $formation->getTitle() . "*\n" .
            "📂 " . $formation->getCategory() . "\n" .
            "📅 Du " . $formation->getStartDate()->format('d/m/Y') . " au " . $formation->getEndDate()->format('d/m/Y') . "\n" .
            "📝 " . strip_tags($formation->getDescription())
        );
    } else {
        $bot->reply("❌ Aucune formation trouvée.");
    }
});

$botman->fallback(function (BotMan $bot) {
    $bot->reply("Je ne comprends pas 🤔");
});

$botman->listen();