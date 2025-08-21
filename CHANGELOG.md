# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2025-08-21

### 🎉 Changement Majeur - API Officielle Suno

Cette version introduit le support de l'**API officielle de Suno** en plus de SunoAPI.org !

### ✨ Nouvelles Fonctionnalités
- **Support de l'API officielle Suno** (https://studio-api.suno.ai)
- **Double mode** : Choix entre API officielle et SunoAPI.org
- **Authentification automatique** avec email/mot de passe Suno
- **Gestion des tokens** avec cache (1 heure)
- **Support du modèle v3.5** (chirp-v3-5)
- **Récupération des crédits** du compte Suno
- **Badge API** dans l'interface pour identifier le mode utilisé

### 🔧 Améliorations Techniques
- Architecture modulaire pour supporter plusieurs APIs
- Système de fallback intelligent
- Gestion améliorée des clips multiples
- Nouvelle colonne `api_mode` dans la base de données
- Stockage des `clip_ids` pour l'API officielle
- Interface d'administration enrichie avec comparatif

### 🎨 Interface Utilisateur
- Nouveau sélecteur de mode API dans les réglages
- Affichage du mode actuel dans le formulaire
- Indicateurs visuels (badges) pour chaque génération
- Tableau comparatif des deux APIs
- Messages de statut spécifiques par API

### 📝 Configuration
- Email et mot de passe pour l'API officielle
- Clé API pour SunoAPI.org
- Bascule facile entre les deux modes

### 🔄 Migration
- Compatible avec les générations existantes
- Pas de perte de données lors de la mise à jour
- Les anciennes générations restent accessibles

## [2.2.0] - 2025-08-19

### Corrections
- Retour au format de requête qui fonctionnait avec l'ancienne version
- Optimisation de la gestion des réponses API

## [2.1.0] - 2025-08-19

### Améliorations
- Correction du problème de connexion API avec meilleure gestion cURL
- Amélioration de la robustesse des appels API

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

## Guide de Migration vers 3.0.0

### Pour les utilisateurs de SunoAPI.org
1. **Aucune action requise** - Continuez à utiliser SunoAPI.org
2. Optionnel : Basculez vers l'API officielle dans les réglages

### Pour les nouveaux utilisateurs
1. **Créez un compte sur Suno.com**
2. **Configurez vos identifiants** dans WordPress
3. **Choisissez "API Officielle"** dans les réglages
4. **Générez vos chansons** avec vos crédits Suno

### Avantages de l'API Officielle
- ✅ Utilise vos crédits Suno existants
- ✅ Support officiel et stable
- ✅ Accès aux dernières fonctionnalités
- ✅ Modèle v3.5 (meilleure qualité)
- ✅ Pas de frais supplémentaires
