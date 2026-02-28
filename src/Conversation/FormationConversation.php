<?php
// src/Controller/FormationConversation.php
namespace App\Controller;

use App\Repository\FormationRepository;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;

class FormationConversation extends Conversation
{
    // Remove repository from property
    public function askCategory()
    {
        // inject repository here
        $formationRepository = $this->getContainer()->get(\App\Repository\FormationRepository::class);

        $categories = $formationRepository->findAllCategories();

        $this->ask('Dans quelle catégorie ?', function ($answer) use ($formationRepository) {
            $category = $answer->getText();
            $formations = $formationRepository->findValidatedByCategory($category);
            
            if (empty($formations)) {
                $this->say("Aucune formation trouvée pour '{$category}'.");
            } else {
                foreach ($formations as $f) {
                    $this->say("📌 {$f->getTitle()} ({$f->getStartDate()->format('d/m/Y')})");
                }
            }

            $this->repeat();
        });
    }

    public function run()
    {
        $this->say("✋ Bonjour ! Je peux vous aider à trouver une formation.");
        $this->askCategory();
    }
}
