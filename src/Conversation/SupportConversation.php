<?php

namespace App\Conversation;

use App\Repository\FormationRepository;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;

class SupportConversation extends Conversation
{
    public function run(): void
    {
        $this->say("💡 Debug: conversation started.");
        $this->askCategory();
    }

    public function askCategory(): void
    {
        $this->ask('Quelle catégorie recherchez-vous ?', function (Answer $answer) {

            $category = trim($answer->getText());

            $bot = $this->bot;
            if (!method_exists($bot, 'getContainer')) {
                $this->say('❌ Le service de recherche est indisponible pour le moment.');
                $this->askAgain();
                return;
            }

            $container = $bot->getContainer();
            if (!method_exists($container, 'get')) {
                $this->say('❌ Le service de recherche est indisponible pour le moment.');
                $this->askAgain();
                return;
            }

            /** @var FormationRepository $repo */
            $repo = $container->get(FormationRepository::class);
            $formations = $repo->findValidatedByCategory($category);

            if (empty($formations)) {
                $this->say("❌ Aucune formation trouvée pour : $category");
            } else {
                foreach ($formations as $f) {
                    $this->say("📚 {$f->getTitle()} ({$f->getStartDate()?->format('d/m/Y')})");
                }
            }

            $this->askAgain();
        });
    }

    private function askAgain(): void
    {
        $this->ask('Voulez-vous chercher une autre catégorie ? (oui/non)', function (Answer $answer) {
            if (str_contains(strtolower($answer->getText()), 'oui')) {
                $this->askCategory();
            } else {
                $this->say('Merci ! Bonne journée 👋');
            }
        });
    }
}