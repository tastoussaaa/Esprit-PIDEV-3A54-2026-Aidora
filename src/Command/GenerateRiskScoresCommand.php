<?php
namespace App\Command;

use App\Service\RiskScoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:generate-risk-scores', description: 'Generate sample risk scores (5 examples)')]
class GenerateRiskScoresCommand extends Command
{
    public function __construct(private RiskScoringService $riskService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $samples = [
            ['age' => 28, 'symptom' => 3, 'chronic' => 0, 'ai' => 0.05],
            ['age' => 62, 'symptom' => 7, 'chronic' => 2, 'ai' => 0.65],
            ['age' => 45, 'symptom' => 5, 'chronic' => 1, 'ai' => 0.4],
            ['age' => 78, 'symptom' => 8, 'chronic' => 3, 'ai' => 0.9],
            ['age' => 55, 'symptom' => 6, 'chronic' => 2, 'ai' => 0.55],
        ];

        $i = 1;
        foreach ($samples as $s) {
            $res = $this->riskService->calculate($s['age'], $s['symptom'], $s['chronic'], $s['ai']);
            $output->writeln(sprintf("Patient %d — Risk Score: %d/100 (%s)", $i, $res['score'], $res['level']));
            $output->writeln(sprintf("  Breakdown: Age=%d, Symptom=%d, AI=%d", $res['breakdown']['ageWeight'], $res['breakdown']['symptomWeight'], $res['breakdown']['aiWeight']));
            $i++;
        }

        return Command::SUCCESS;
    }
}
