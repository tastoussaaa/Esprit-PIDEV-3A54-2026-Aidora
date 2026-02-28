# 🤖 Documentation IA - Analyse de Risque HuggingFace

## Vue d'ensemble

Le système d'analyse de risque combine maintenant :
- **70% IA** (HuggingFace Mistral-7B-Instruct)
- **30% Scoring déterministe** (algorithme classique)

**Fallback automatique** : Si l'IA n'est pas disponible, le système utilise 100% le scoring déterministe.

---

## Configuration

### 1. Obtenir une clé API HuggingFace

1. Créer un compte sur https://huggingface.co
2. Aller dans **Settings → Access Tokens**
3. Créer un nouveau token (permissions: Read)
4. Copier la clé

### 2. Configurer `.env`

```bash
HUGGINGFACE_API_KEY=hf_xxxxxxxxxxxxxxxxxxxxxxxxxx
```

**Pour désactiver l'IA** : Laisser la valeur vide
```bash
HUGGINGFACE_API_KEY=
```

---

## Exemple de requête envoyée au modèle HuggingFace

### Prompt structuré

```
[INST]
Tu es un expert médical spécialisé dans l'évaluation des besoins en soins à domicile.

Analyse la description suivante d'une demande de soins et détermine :
1. Le niveau de risque médical (faible, moyen, élevé)
2. Un score de risque entre 0 et 100
3. La pathologie probable ou situation médicale
4. Une justification courte de ton analyse

Description du patient :
"Patient diabétique type 2, mobilité réduite suite à AVC récent. 
Nécessite aide pour toilette et prise de repas. Isolement social."

IMPORTANT : Réponds UNIQUEMENT avec un objet JSON valide au format suivant, sans texte avant ou après :
{
  "niveau_risque": "faible|moyen|élevé",
  "score_risque": 0-100,
  "pathologie_probable": "description courte",
  "justification": "explication en 1-2 phrases"
}
[/INST]
```

### Body HTTP complet

```json
{
  "inputs": "[INST]...[/INST]",
  "parameters": {
    "max_new_tokens": 500,
    "temperature": 0.3,
    "top_p": 0.9,
    "do_sample": true,
    "return_full_text": false
  }
}
```

### Headers

```
Authorization: Bearer hf_xxxxxxxxxxxxxxxxxxxxxxxxxx
Content-Type: application/json
```

---

## Exemple de réponse IA (mockée pour tests)

### Cas 1 : Risque élevé

```json
{
  "niveau_risque": "élevé",
  "score_risque": 85,
  "pathologie_probable": "Diabète type 2 avec séquelles d'AVC",
  "justification": "Risque élevé de complications métaboliques et de chutes. Isolement social augmente le risque de décompensation."
}
```

### Cas 2 : Risque moyen

```json
{
  "niveau_risque": "moyen",
  "score_risque": 55,
  "pathologie_probable": "Arthrose avec difficultés de mobilité",
  "justification": "Besoin d'assistance quotidienne stable. Suivi régulier nécessaire mais pas d'urgence vitale."
}
```

### Cas 3 : Risque faible

```json
{
  "niveau_risque": "faible",
  "score_risque": 25,
  "pathologie_probable": "Convalescence post-opératoire simple",
  "justification": "Situation temporaire avec amélioration progressive attendue. Autonomie partielle préservée."
}
```

---

## Exemple de réponse API complète

### Endpoint `/api/demandes/{id}/risk`

#### Avec IA disponible

```json
{
  "demandeId": 42,
  "scoreDeterministe": 45,
  "scoreIa": 85,
  "scoreFinal": 73,
  "level": "HIGH",
  "factors": [
    "Urgence élevée",
    "Date de début dépassée",
    "IA: Diabète type 2 avec séquelles d'AVC"
  ],
  "justificationIa": "Risque élevé de complications métaboliques et de chutes. Isolement social augmente le risque de décompensation.",
  "pathologieProbable": "Diabète type 2 avec séquelles d'AVC"
}
```

**Calcul :** `scoreFinal = (85 * 0.7) + (45 * 0.3) = 59.5 + 13.5 = 73`

#### Sans IA (fallback) 

```json
{
  "demandeId": 42,
  "scoreDeterministe": 45,
  "scoreIa": null,
  "scoreFinal": 45,
  "level": "MEDIUM",
  "factors": [
    "Urgence élevée",
    "Date de début dépassée"
  ],
  "justificationIa": null,
  "pathologieProbable": null
}
```

---

## Gestion d'erreurs

### Erreurs gérées silencieusement (pas d'exception)

1. **Clé API manquante** → Fallback déterministe
2. **Timeout API (>15s)** → Fallback déterministe
3. **Erreur HTTP (500, 503)** → Fallback déterministe
4. **JSON invalide** → Fallback déterministe
5. **Champs manquants** → Fallback déterministe

### Logs

Tous les échecs sont loggés pour monitoring :

```php
$this->logger->warning('HuggingFace API returned non-200 status', [
    'status_code' => $statusCode,
]);
```

---

## Tests

### Test avec mock (sans vraie API)

Créer un mock dans votre test :

```php
$mockAiService = $this->createMock(AiRiskAnalysisService::class);
$mockAiService->method('analyzeDemandeText')
    ->willReturn([
        'niveau_risque' => 'élevé',
        'score_risque' => 85,
        'pathologie_probable' => 'Diabète type 2',
        'justification' => 'Risque élevé de complications'
    ]);
```

### Test avec vraie API

1. Configurer `HUGGINGFACE_API_KEY` dans `.env.test`
2. Créer une demande avec description réelle
3. Appeler `/api/demandes/{id}/risk`
4. Vérifier que `scoreIa` n'est pas null

---

## Performances

- **Timeout**: 15 secondes max
- **Latence moyenne**: 2-5 secondes
- **Cache**: Non implémenté (à ajouter si besoin)
- **Rate limits HuggingFace**: ~1000 requêtes/jour (compte gratuit)

---

## Améliorations futures

1. **Cache Redis** : Mettre en cache les résultats IA pour descriptions identiques
2. **Retry logic** : Réessayer 1 fois en cas d'échec temporaire
3. **A/B Testing** : Comparer précision IA vs déterministe
4. **Fine-tuning** : Entraîner un modèle spécifique au domaine médical
5. **Batch processing** : Analyser plusieurs demandes en parallèle

---

## Sécurité

✅ **Jamais d'exception bloquante** : Le système continue même si l'IA échoue
✅ **Validation des données** : Score clamped 0-100, niveau validé
✅ **Clé API sécurisée** : Stockée dans `.env`, jamais versionné
✅ **Timeout strict** : Pas de blocage infini

---

## Support

- **HuggingFace Docs**: https://huggingface.co/docs/api-inference
- **Mistral-7B**: https://huggingface.co/mistralai/Mistral-7B-Instruct-v0.1
- **Symfony HttpClient**: https://symfony.com/doc/current/http_client.html
