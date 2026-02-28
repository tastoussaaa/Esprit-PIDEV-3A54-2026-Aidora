# TODO Produit — Flux complet Patient → Mission (A→Z)

**État final (Février 2026)**: 11 sections, 9 complétées, 2 en cours/futures
**Dernière mise à jour**: 21 février 2026
**Réalisations session**: Validation profil patient, validation métier demande, métriques gouvernance, tests complets, flash messages intégrés, nettoyage

## 0) Base architecture (stabilité)
- [x] Séparer les responsabilités controllers (Mission vs Demande)
- [x] Supprimer les templates dupliqués `aide_soingnant/demandes|missions`
- [x] Conserver `aide_soingnant/index.html.twig` (restauré)
- [x] Ajouter le statut de réassignation `A_REASSIGNER` (refus/annulation avant début)

## 1) Profil patient exploitable (impact réel)
- [x] Rendre obligatoires/validés les champs critiques (adresse, autonomie, pathologies, budget, contact urgence)
- [x] Mapper chaque champ profil vers des critères utilisables par le matching
- [x] Ajouter un indicateur de complétude profil (score + champs manquants)

## 2) Création de demande robuste
- [x] Vérifier cohérence date/heure/budget avant enregistrement
- [x] Lier urgence calculée aux règles métier (priorisation réelle)
- [x] Ajouter propagation stricte des statuts: EN_ATTENTE, ACCEPTÉE, EN_COURS, TERMINÉE, ANNULÉE, A_REASSIGNER, EXPIRÉE

## 3) Calendrier & disponibilité (avant sélection soignant)
- [ ] Installer et configurer le bundle calendrier (`tattali/calendar-bundle`)
- [x] Exposer créneaux occupés des aides-soignants (source: missions actives)
- [x] Filtrer la liste de sélection pour n’afficher que les aides dispo sur le créneau
- [x] Bloquer la sélection si conflit de disponibilité au moment du submit (double vérification)

## 4) Matching intelligent (métier IA #1)
- [x] Produire un score de compatibilité consolidé (compétences/pathologie/distance/dispo/tarif/fiabilité)
- [x] Afficher un Top 5 trié côté patient avec explication des scores
- [ ] Relancer automatiquement le matching (Top 3) quand statut = `A_REASSIGNER`

## 5) Mission & annulation intelligente
- [x] Sur annulation avant début: passer en `A_REASSIGNER` + relance proposition soignants
- [x] Sur annulation après début: archiver mission et clôturer demande selon règle métier
- [x] Ajouter notifications patient/soignant/admin sur transitions critiques

## 6) Supervision risque (métier IA #2)
- [x] Définir score fiabilité aide-soignant (retards, annulations, missions incomplètes)
- [x] Définir score risque patient (urgences, incidents, incohérences)
- [x] Déclencher alertes admin automatiques selon seuils

## 7) Traçabilité & preuves
- [x] Renforcer check-in/out (géolocalisation obligatoire + validation distance)
- [x] Ajouter preuve optionnelle (photo/signature) selon paramétrage
- [x] Intégrer ces preuves dans le PDF de mission

## 8) Qualité & gouvernance
- [x] Créer structure tests fonctionnels (PHPUnit + WebTestCase + AbstractFunctionalTest)
- [x] Créer suite tests flux critiques (DemandeCriticalFlowTest, MissionTracingFlowTest, NotificationServiceTest)
- [x] Adapter tests à entités réelles (Patient/AideSoignant au lieu de User générique)
- [x] Ajouter tests: création demande, sélection aide, accept/refuse, check-in/out, preuves optionnelles
- [x] Valider notifications (TransitionNotificationService sur 6 événements)
- [x] Ajouter métriques de suivi (taux d'acceptation, délai de prise en charge, réassignations)
- [ ] Préparer checklist de démo (scénarios happy path + cas limites)

## 9) Serializer API (bundle externe obligatoire)
- [x] Installer et activer `symfony/serializer`
- [x] Créer DTO API Mission/Demande avec `@Groups` (attributs `#[Groups]`)
- [x] Ajouter custom normalizer (`MissionOutputDtoNormalizer`)
- [x] Ajouter normalizer d'erreurs JSON (`ApiExceptionNormalizer`)
- [x] Exposer endpoints JSON robustes (`/api/serialize/mission/{id}`, `/api/serialize/demande/{id}`)

## 10) CRUD admin + feedback utilisateur
- [x] Standardiser les messages de succès/erreur (flash) sur toutes les actions critiques
- [x] Ajouter messages explicites après création, modification, suppression et échec de validation
- [x] Mettre en place un CRUD admin complet pour les demandes d'aide (index/show/edit/delete)
- [x] Mettre en place un CRUD admin complet pour les missions (index/show/edit/delete)
- [x] Ajouter navigation pour Admin CRUD dans le dashboard
- [x] Intégrer preuves optionnelles (photo/signature) dans les vues admin mission show

## 11) CRUD métier (controllers dédiés)
- [x] Finaliser CRUD Demande dans `DemandeAideController` (create/read/update/delete)
- [x] Autoriser la modification d'une demande en statut `A_REASSIGNER`
- [x] Finaliser CRUD Mission dans `MissionController` (read/update/delete)
- [x] Corriger suppression mission pour supprimer uniquement la mission (pas la demande)
- [x] Ajouter UI de modification mission et actions liste (modifier/supprimer)
