<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaService
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $baseUrl,
        private readonly string $model
    ) {
    }

    public function generatePatientHealthParagraph(string $pathologie): string
    {
        $pathologie = $this->normalizeHealthInput($pathologie);

        if ($pathologie === '') {
            return '';
        }

        $system = <<<TXT
Tu es un assistant d'information sante pour patients francophones.
Ta mission est de rediger un texte explicatif, pedagogique et utile sur la maladie indiquee.

Regles obligatoires:
- Reponds en francais.
- Reponds en JSON strict avec les cles: definition, vigilance, suivi.
- Ton empathique, clair, non alarmiste.
- Donne une courte explication de la maladie (ce que c'est, de facon generale).
- Mentionne 2 a 3 points de vigilance au quotidien.
- Explique pourquoi le suivi medical regulier est important pour ce cas.
- Encourage un suivi medical regulier.
- Interdit: diagnostic definitif, ordonnance, dose de medicament, urgence inventee.
- Ne refuse pas la demande si elle est generale.
- Pas de disclaimer vague.
TXT;
        $prompt = <<<TXT
Maladie: {$pathologie}

Format attendu (JSON uniquement):
{
  "definition": "1 a 2 phrases simples",
  "vigilance": "2 a 3 points concrets de surveillance",
  "suivi": "1 phrase sur le suivi medical"
}
TXT;
        $content = $this->generateText($system, $prompt, 360, 0.2, 4096, 'json');
        $paragraph = $this->buildParagraphFromJson($content);

        if ($paragraph !== '' && !$this->looksLikeGenericRefusal($paragraph)) {
            return $paragraph;
        }

        return $this->fallbackPatientHealthParagraph($pathologie);
    }

    public function enhanceConsultationMotif(string $motif): string
    {
        $motif = trim($motif);
        if ($motif === '') {
            return $motif;
        }

        $system = 'Tu es un assistant medical francophone. Reformule le motif en francais clair, en 1 a 2 phrases courtes, sans ajouter d information absente.';
        $content = $this->generateText($system, $motif, 180, 0.5, 2048);

        return $content !== '' ? $content : $motif;
    }

    /**
     * @param array<int, array{id:int, fullName:string, specialite:string, email?:string, disponible?:bool}> $doctors
     * @return array<int, array{id:int, fullName:string, specialite:string, email:string, disponible:bool, score:int, reason:string}>
     */
    public function findCompatibleDoctors(string $pathologie, array $doctors): array
    {
        $pathologie = $this->normalizeHealthInput($pathologie);
        if ($pathologie === '' || $doctors === []) {
            return [];
        }

        $system = <<<TXT
Tu es un assistant de tri medical.
Tu dois evaluer la compatibilite entre une maladie patient et des specialites medicales.
Reponds en JSON strict.
TXT;

        $prompt = "Maladie du patient: {$pathologie}\n\n";
        $prompt .= "Pour chaque medecin, donne un score de compatibilite de 0 a 100 et une raison concise (max 18 mots).\n";
        $prompt .= "Format JSON uniquement:\n";
        $prompt .= "{\n  \"compatibilites\": [\n";

        foreach ($doctors as $doctor) {
            $prompt .= sprintf(
                "    {\"id\": %d, \"specialite\": \"%s\"},\n",
                $doctor['id'],
                addslashes($doctor['specialite'])
            );
        }

        $prompt .= "  ]\n}\n";

        $content = $this->generateText($system, $prompt, 420, 0.1, 4096, 'json');
        $scores = $this->parseCompatibilityJson($content);

        $output = [];
        foreach ($doctors as $doctor) {
            $parsed = $scores[$doctor['id']] ?? null;
            if ($parsed === null) {
                $parsed = $this->fallbackCompatibility($pathologie, $doctor['specialite']);
            }

            $score = max(0, min(100, (int) ($parsed['score'] ?? 0)));
            $reason = trim((string) ($parsed['reason'] ?? 'Compatibilite evaluee par IA locale.'));
            if ($reason === '') {
                $reason = 'Compatibilite evaluee par IA locale.';
            }

            $ruleBoost = $this->fallbackCompatibility($pathologie, $doctor['specialite']);
            if ((int) $ruleBoost['score'] > $score) {
                $score = (int) $ruleBoost['score'];
                $reason = (string) $ruleBoost['reason'];
            }

            $output[] = [
                'id' => $doctor['id'],
                'fullName' => $doctor['fullName'],
                'specialite' => $doctor['specialite'],
                'email' => (string) ($doctor['email'] ?? ''),
                'disponible' => (bool) ($doctor['disponible'] ?? false),
                'score' => $score,
                'reason' => $reason,
            ];
        }

        usort($output, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return $output;
    }

    /**
     * @param array<int, array{id:int, fullName:string, specialite:string, email?:string, phone?:string, disponible?:bool}> $doctors
     * @return array<int, array{
     *   doctor: array{id:int, fullName:string, specialite:string, email:string, phone:?string, disponible:bool},
     *   compatibilityScore:int,
     *   reason:string
     * }>
     */
    public function getCompatibleDoctors(string $patientPathology, array $doctors): array
    {
        $evaluated = $this->findCompatibleDoctors($patientPathology, $doctors);
        $matches = [];

        foreach ($evaluated as $row) {
            $score = (int) ($row['score'] ?? 0);
            if ($score < 60) {
                continue;
            }

            $matches[] = [
                'doctor' => [
                    'id' => (int) ($row['id'] ?? 0),
                    'fullName' => (string) ($row['fullName'] ?? ''),
                    'specialite' => (string) ($row['specialite'] ?? ''),
                    'email' => (string) ($row['email'] ?? ''),
                    'phone' => isset($row['phone']) ? (string) $row['phone'] : null,
                    'disponible' => (bool) ($row['disponible'] ?? false),
                ],
                'compatibilityScore' => $score,
                'reason' => (string) ($row['reason'] ?? 'Compatibilite evaluee par IA locale.'),
            ];
        }

        usort($matches, static fn(array $a, array $b): int => $b['compatibilityScore'] <=> $a['compatibilityScore']);

        return $matches;
    }

    /**
     * @return array{score:int, reason:string, compatible:bool}
     */
    public function evaluatePathologySpecialityCompatibility(string $pathologie, string $specialite): array
    {
        $pathologie = $this->normalizeHealthInput($pathologie);
        $specialite = trim($specialite);

        if ($pathologie === '' || $specialite === '') {
            return [
                'score' => 0,
                'reason' => 'Informations insuffisantes pour evaluer la compatibilite.',
                'compatible' => false,
            ];
        }

        $system = <<<TXT
Tu es un assistant de tri medical.
Tu evalues la compatibilite entre une pathologie patient et une specialite de medecin.
Reponds en JSON strict avec les cles: score, reason.
TXT;

        $prompt = <<<TXT
Pathologie patient: {$pathologie}
Specialite medecin: {$specialite}

Regles:
- score entre 0 et 100
- reason en francais, phrase courte (max 16 mots)
- JSON uniquement:
{"score": 0, "reason": ""}
TXT;

        $content = $this->generateText($system, $prompt, 90, 0.1, 2048, 'json');
        $parsed = $this->parseSingleCompatibilityJson($content);
        $fallback = $this->fallbackCompatibility($pathologie, $specialite);

        $score = (int) max((int) ($parsed['score'] ?? 0), (int) ($fallback['score'] ?? 0));
        $score = max(0, min(100, $score));

        $reason = trim((string) ($parsed['reason'] ?? ''));
        if ($reason === '' || $score === (int) ($fallback['score'] ?? 0)) {
            $reason = (string) ($fallback['reason'] ?? 'Compatibilite evaluee par IA locale.');
        }

        return [
            'score' => $score,
            'reason' => $reason,
            'compatible' => $score >= 60,
        ];
    }

    private function generateText(
        string $system,
        string $prompt,
        int $maxTokens,
        float $temperature,
        int $numCtx = 2048,
        ?string $format = null
    ): string
    {
        try {
            $payload = [
                'model' => $this->model,
                'prompt' => $system . "\n\n" . $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => $temperature,
                    'num_predict' => $maxTokens,
                    'num_ctx' => $numCtx,
                ],
            ];

            if ($format !== null) {
                $payload['format'] = $format;
            }

            $response = $this->client->request('POST', rtrim($this->baseUrl, '/') . '/api/generate', [
                'json' => $payload,
                'timeout' => 90,
            ]);

            $data = $response->toArray(false);
            return trim((string) ($data['response'] ?? ''));
        } catch (\Throwable $e) {
            error_log('Ollama generation error: ' . $e->getMessage());
            return '';
        }
    }

    private function fallbackPatientHealthParagraph(string $pathologie): string
    {
        $normalized = mb_strtolower($pathologie);

        if (str_contains($normalized, 'diabete type 1') || str_contains($normalized, 'diabète type 1')) {
            return "Le diabete de type 1 est une maladie chronique dans laquelle le corps ne produit plus suffisamment d'insuline, ce qui demande une surveillance quotidienne de la glycemie. Les points importants sont l'equilibre entre alimentation, activite physique et traitement, ainsi que la reconnaissance rapide des signes d'hypoglycemie ou d'hyperglycemie. Un suivi regulier avec l'equipe soignante aide a ajuster les habitudes et a prevenir les complications sur le long terme.";
        }

        if (str_contains($normalized, 'diabete type 2') || str_contains($normalized, 'diabète type 2')) {
            return "Le diabete de type 2 correspond a une mauvaise utilisation du sucre par l'organisme, souvent progressive, qui peut rester silencieuse au debut. Il est utile de surveiller la glycemie, le poids, l'alimentation et l'activite physique pour mieux stabiliser la maladie au quotidien. Un suivi medical regulier permet d'adapter la prise en charge et de reduire le risque de complications cardiovasculaires, renales ou visuelles.";
        }

        if (str_contains($normalized, 'hypertension')) {
            return "L'hypertension arterielle signifie que la pression du sang dans les arteres est trop elevee de facon durable, parfois sans symptomes visibles. Au quotidien, il est important de surveiller la tension, limiter le sel, maintenir une activite physique adaptee et etre attentif aux maux de tete inhabituels ou aux vertiges. Un suivi medical regulier est essentiel pour proteger le coeur, les reins et le cerveau.";
        }

        if (str_contains($normalized, 'asthme')) {
            return "L'asthme est une inflammation chronique des bronches qui peut provoquer une respiration sifflante, de la toux et une sensation d'oppression thoracique. Il est important d'identifier les facteurs declenchants, de surveiller la frequence des crises et de garder une bonne technique d'inhalation si un traitement est prescrit. Un suivi medical regulier permet de mieux controler la maladie et d'eviter les exacerbations.";
        }

        return sprintf(
            'Vous avez indique "%s". Cette information est importante pour mieux adapter votre suivi et aider les professionnels de sante a comprendre votre situation. Pensez a decrire l evolution de votre etat, vos symptomes principaux et tout changement recent lors de vos prochains echanges medicaux.',
            $pathologie
        );
    }

    private function buildParagraphFromJson(string $content): string
    {
        if ($content === '') {
            return '';
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return '';
        }

        $definition = trim((string) ($decoded['definition'] ?? ''));
        $vigilance = trim((string) ($decoded['vigilance'] ?? ''));
        $suivi = trim((string) ($decoded['suivi'] ?? ''));

        $parts = array_values(array_filter([$definition, $vigilance, $suivi], static fn(string $v): bool => $v !== ''));

        return trim(implode(' ', $parts));
    }

    /**
     * @return array<int, array{score:int, reason:string}>
     */
    private function parseCompatibilityJson(string $content): array
    {
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = $decoded['compatibilites'] ?? null;
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }

            $id = (int) $row['id'];
            $out[$id] = [
                'score' => (int) ($row['score'] ?? 0),
                'reason' => (string) ($row['reason'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array{score:int, reason:string}|null
     */
    private function parseSingleCompatibilityJson(string $content): ?array
    {
        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }

        if (!array_key_exists('score', $decoded)) {
            return null;
        }

        return [
            'score' => (int) ($decoded['score'] ?? 0),
            'reason' => trim((string) ($decoded['reason'] ?? '')),
        ];
    }

    /**
     * @return array{score:int, reason:string}
     */
    private function fallbackCompatibility(string $pathologie, string $specialite): array
    {
        $p = $this->normalizeForMatch($pathologie);
        $s = $this->normalizeForMatch($specialite);

        // Strong direct match: if pathology and specialty text overlap, keep doctor.
        if ($p !== '' && $s !== '' && (str_contains($s, $p) || str_contains($p, $s))) {
            return [
                'score' => 96,
                'reason' => 'Correspondance directe entre la pathologie et la specialite du medecin.',
            ];
        }

        $rules = [
            [['autisme', 'tsa', 'spectre autistique'], ['autisme', 'psychiatre', 'pedopsychiatre', 'neurologue', 'pediatre'], 92, 'Adapte au suivi comportemental et psychologique.'],
            [['diabete', 'glycemie'], ['endocrinologue', 'nutritionniste', 'generaliste', 'diabetologue', 'medecine interne'], 92, 'Specialite coherente avec le suivi metabolique du diabete.'],
            [['alzheimer', 'demence', 'trouble cognitif'], ['neurologue', 'geriatre', 'psychiatre'], 91, 'Specialite adaptee aux troubles cognitifs et neurologiques.'],
            [['hypertension', 'tension', 'cardiaque'], ['cardiologue', 'generaliste', 'medecine interne'], 90, 'Specialite ciblee pour le suivi cardiovasculaire.'],
            [['asthme', 'respiratoire', 'bronche', 'poumon'], ['pneumologue', 'generaliste', 'allergologue'], 90, 'Specialite adaptee au suivi respiratoire.'],
            [['arthrose', 'os', 'articulation', 'rhumat'], ['rhumatologue', 'orthopediste'], 88, 'Specialite utile pour la prise en charge osteo-articulaire.'],
            [['depression', 'anxiete', 'psychique'], ['psychiatre', 'psychologue', 'neurologue'], 88, 'Specialite pertinente pour le suivi psychologique et neurologique.'],
        ];

        foreach ($rules as [$keywordsP, $keywordsS, $score, $reason]) {
            $matchP = false;
            foreach ($keywordsP as $k) {
                if (str_contains($p, $k)) {
                    $matchP = true;
                    break;
                }
            }
            if (!$matchP) {
                continue;
            }

            foreach ($keywordsS as $k) {
                if (str_contains($s, $k)) {
                    return [
                        'score' => $score,
                        'reason' => $reason,
                    ];
                }
            }
        }

        if (str_contains($s, 'medecine interne') || str_contains($s, 'generaliste') || str_contains($s, 'general')) {
            return [
                'score' => 70,
                'reason' => 'Specialite polyvalente utile pour un premier suivi.',
            ];
        }

        return [
            'score' => 45,
            'reason' => 'Compatibilite possible mais moins ciblee.',
        ];
    }

    private function normalizeForMatch(string $value): string
    {
        $v = mb_strtolower(trim($value));
        $v = strtr($v, [
            '�' => 'a', '�' => 'a', '�' => 'a', '�' => 'a',
            '�' => 'c',
            '�' => 'e', '�' => 'e', '�' => 'e', '�' => 'e',
            '�' => 'i', '�' => 'i', '�' => 'i', '�' => 'i',
            '�' => 'o', '�' => 'o', '�' => 'o', '�' => 'o',
            '�' => 'u', '�' => 'u', '�' => 'u', '�' => 'u',
            '�' => 'y', '�' => 'n',
        ]);
        $v = preg_replace('/\s+/', ' ', $v) ?? $v;

        return $v;
    }
    private function normalizeHealthInput(string $input): string
    {
        $value = trim($input);
        $value = preg_replace('/^maladie\\s+ou\\s+pathologie\\s*/i', '', $value) ?? $value;
        $value = preg_replace('/\\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function looksLikeGenericRefusal(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));
        $signals = [
            'je suis desole',
            'je ne peux pas',
            'consulter un professionnel',
            'site web fiable',
            'je ne suis pas en mesure',
        ];

        foreach ($signals as $signal) {
            if (str_contains($normalized, $signal)) {
                return true;
            }
        }

        return false;
    }
}

