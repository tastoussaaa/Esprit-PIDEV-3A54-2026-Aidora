<?php

namespace App\Conversation;

use App\Repository\FormationRepository;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;

class SupportConversation extends Conversation
{
    private FormationRepository $formationRepository;

    public function __construct(FormationRepository $formationRepository)
    {
        $this->formationRepository = $formationRepository;
    }

    public function run()
    {
        $this->askCategory();
    }

    private function askCategory(): void
    {$this->say("💡 Debug: conversation started.");
        $this->ask('Quelle catégorie recherchez-vous ?', function (Answer $answer) {

            $category = trim($answer->getText());

            // 🔥 Use your repository method
            $formations = $this->formationRepository
                ->findValidatedByCategory($category);

            if (empty($formations)) {
                $this->say("❌ Aucune formation validée trouvée pour : $category");
            } else {

                foreach ($formations as $formation) {

                    $message  = "📚 " . $formation->getTitle() . "\n";
                    $message .= "🏷 Catégorie : " . $formation->getCategory() . "\n";
                    $message .= "📅 " .
                        $formation->getStartDate()?->format('d/m/Y') .
                        " - " .
                        $formation->getEndDate()?->format('d/m/Y') . "\n";
                    $message .= "📝 " . $formation->getDescription();

                    $this->say($message);
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
