# 📋 Intégration Dompdf - Génération d'Ordonnances PDF

## ✅ Installation complétée

- **Librairie installée**: Dompdf v3.1.4
- **Service créé**: `App\Service\PdfGeneratorService`
- **Contrôleur augmenté**: `App\Controller\OrdonnanceController`

## 🎯 Routes disponibles

| Route | Méthode | Description |
|-------|---------|-------------|
| `/Ordonnance/consultation/{consultationId}/pdf` | GET | Télécharge ordonnance PDF |
| `/Ordonnance/consultation/{consultationId}/preview` | GET | Affiche ordonnance en aperçu |
| `/Ordonnance/consultation/{consultationId}/all-pdf` | GET | Télécharge toutes les ordonnances |
| `/Ordonnance/consultation/{consultationId}/all-preview` | GET | Affiche toutes les ordonnances |

## 📝 Utilisation

### Télécharger une ordonnance (download)
```
GET http://localhost:8000/Ordonnance/consultation/1/pdf
```
→ Reçoit un fichier `ordonnance_consultation_1_2026-02-20.pdf`

### Afficher en aperçu (preview)
```
GET http://localhost:8000/Ordonnance/consultation/1/preview
```
→ Affiche le PDF directement dans le navigateur

### Toutes les ordonnances (fichier unique)
```
GET http://localhost:8000/Ordonnance/consultation/1/all-pdf
```
→ Combine plusieurs ordonnances sur pages séparées (page-break)

## 🛠️ Structure du PDF généré

### En-tête
- Titre: "Ordonnance Médicale"
- Date de consultation, motif, créneaux horaires

### Informations Patient
- Nom, prénom, âge
- Email

### Informations Médecin
- Nom du docteur
- Spécialité

### Médicaments
- Nom du médicament
- Dosage
- Durée
- Instructions spéciales

### Pied de page
- Date de génération
- Mention "Ordonnance valide"

## 🎨 Styling

Le PDF utilise un CSS intégré pour :
- En-têtes bleus (#007bff)
- Fond altéré (#f8f9fa) sur sections
- Icônes emojis pour faciliter la lecture
- Bordures vertes (#28a745) pour les médicaments

## 📦 Dépendances

```json
{
  "dompdf/dompdf": "^3.1"
}
```

Included automatically:
- `dompdf/php-font-lib`
- `dompdf/php-svg-lib`
- `masterminds/html5`
- `sabberworm/php-css-parser`
- `thecodingmachine/safe`

## ✨ Prochaines étapes

- [ ] Ajouter tests unitaires pour `PdfGeneratorService`
- [ ] Intégrer [LexikJWT](https://github.com/lexik/LexikJWTAuthenticationBundle) pour sécuriser les endpoints
- [ ] Ajouter [API Platform](https://api-platform.com/) pour REST/GraphQL
- [ ] Implémenter la signature numérique des ordonnances (optionnel)
- [ ] Ajouter thumbnails/preview d'images médical dans les ordonnances
