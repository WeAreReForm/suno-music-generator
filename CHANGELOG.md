# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-08-19

### 🎉 Nouveautés
- Support multi-endpoints pour une meilleure fiabilité de connexion
- Système de fallback automatique entre plusieurs APIs
- Support de différents formats de requêtes API
- Interface d'administration complètement révisée
- Nouveau système de débogage avancé

### 🔧 Améliorations
- Extraction des URLs améliorée avec support de multiples formats de réponse
- Meilleure gestion des erreurs avec messages explicites
- Optimisation des performances de vérification du statut
- Ajout de la configuration de l'endpoint par défaut
- Amélioration de la compatibilité avec différentes versions de l'API

### 🐛 Corrections
- Correction des problèmes de connexion avec l'API
- Résolution des erreurs de timeout
- Correction de l'extraction des task_id dans différents formats
- Amélioration de la gestion des réponses vides

### 📝 Documentation
- README complètement mis à jour
- Ajout d'exemples de configuration
- Documentation des nouveaux shortcodes
- Guide de dépannage amélioré

## [1.7.0] - 2025-08-19

### Améliorations
- Timeout étendu à 5 minutes pour les générations longues
- Sauvegarde des réponses API pour débogage
- Nouveaux shortcodes de débogage
- Compteur de vérifications visible

## [1.1.0] - 2025-07-28

### Corrections
- Correction de la compatibilité avec SunoAPI.org
- Amélioration du système de test API
- Interface utilisateur améliorée
- Gestion d'erreurs optimisée

## [1.0.0] - 2025-07-28

### Version initiale
- 🎵 Génération de musique basique avec l'API Suno
- 📝 Formulaire de création intuitif
- 💾 Sauvegarde en base de données
- 🎨 Interface responsive
- 🔊 Lecteur audio intégré
- 📤 Options de partage social

---

## Formats de version

### Majeure (X.0.0)
- Changements incompatibles avec les versions précédentes
- Refonte majeure de l'architecture
- Nouvelles fonctionnalités majeures

### Mineure (0.X.0)
- Nouvelles fonctionnalités compatibles
- Améliorations significatives
- Nouveaux shortcodes ou options

### Patch (0.0.X)
- Corrections de bugs
- Améliorations mineures
- Mises à jour de sécurité
