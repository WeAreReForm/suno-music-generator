# 🎵 Suno Music Generator - Plugin WordPress v2.0

Un plugin WordPress puissant pour générer des chansons personnalisées avec l'intelligence artificielle via l'API SunoAPI.org.

![Version](https://img.shields.io/badge/version-2.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-red.svg)
![Status](https://img.shields.io/badge/status-stable-success.svg)

## 🎯 Nouveautés v2.0

✨ **Version 2.0 - Refonte complète** (Août 2025)
- ✅ Correction complète du système d'affichage des chansons
- ✅ Récupération fiable des URLs audio
- ✅ Nouveau design moderne avec animations
- ✅ Meilleure gestion des erreurs et des timeouts
- ✅ Système de brouillons automatiques
- ✅ Support multi-formats (MP3, MP4)
- ✅ Interface utilisateur responsive améliorée
- ✅ Système de notifications optimisé

## ✨ Fonctionnalités principales

### 🎼 Génération de musique IA
- Créez des chansons personnalisées en quelques clics
- Description libre ou guidée par style musical
- Support des paroles personnalisées
- Mode instrumental disponible

### 🎨 Styles musicaux supportés
- Pop, Rock, Jazz, Hip-Hop
- Électronique, Classique, Country
- Reggae, Blues, Folk, R&B, Metal
- Ou laissez l'IA choisir automatiquement

### 💾 Gestion des créations
- Historique complet de toutes vos générations
- Sauvegarde automatique en base de données
- Système de brouillons intelligents
- Export et téléchargement des créations

### 🔊 Lecteur intégré
- Écoute directe sur votre site WordPress
- Support des formats audio multiples
- Interface de playlist moderne
- Contrôles de lecture complets

### 📤 Partage social
- Partage direct sur Twitter et Facebook
- Copie rapide du lien de partage
- Support du Web Share API natif

## 🚀 Installation

### Prérequis
- WordPress 5.0 ou supérieur
- PHP 7.4 ou supérieur
- MySQL 5.6 ou supérieur
- Clé API de [SunoAPI.org](https://sunoapi.org)

### Étapes d'installation

1. **Téléchargez le plugin**
   ```bash
   git clone https://github.com/WeAreReForm/suno-music-generator.git
   ```

2. **Installation sur WordPress**
   - Via FTP : Uploadez le dossier dans `/wp-content/plugins/`
   - Via Admin : Allez dans Extensions > Ajouter et uploadez le ZIP

3. **Activation**
   - Allez dans `Extensions > Extensions installées`
   - Activez "Suno Music Generator"

4. **Configuration**
   - Allez dans `Réglages > Suno Music`
   - Entrez votre clé API de SunoAPI.org
   - Sauvegardez les paramètres

5. **Test de connexion**
   - Utilisez le shortcode `[suno_test_api]` sur une page
   - Vérifiez que la connexion est établie

## 🎯 Utilisation

### Shortcodes disponibles

#### Formulaire de génération
```php
[suno_music_form]
```
Affiche le formulaire complet de création de musique avec tous les paramètres.

#### Lecteur de musiques
```php
[suno_music_player]
[suno_music_player user_id="123" limit="5"]
```
Affiche les créations musicales. Paramètres optionnels :
- `user_id` : ID de l'utilisateur (par défaut : utilisateur actuel)
- `limit` : Nombre de chansons à afficher (par défaut : 10)

#### Test de connectivité
```php
[suno_test_api]
```
Teste la connexion à l'API (administrateurs uniquement).

### Exemple d'utilisation complète

1. **Créez une page "Générateur de musique"**
2. **Ajoutez le shortcode** `[suno_music_form]`
3. **Créez une page "Mes créations"**
4. **Ajoutez le shortcode** `[suno_music_player]`

### Guide de génération

1. **Description** : Décrivez votre chanson (ex: "Une ballade pop mélancolique sur l'amour perdu")
2. **Style** : Choisissez un genre musical ou laissez en automatique
3. **Titre** : Donnez un titre à votre création (optionnel)
4. **Paroles** : Ajoutez vos propres paroles ou laissez l'IA les créer
5. **Instrumental** : Cochez pour une version sans voix
6. **Générer** : Cliquez et attendez 30-60 secondes
7. **Résultat** : Écoutez, téléchargez et partagez !

## ⚙️ Configuration avancée

### Paramètres du plugin

Dans `Réglages > Suno Music` :

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| Clé API | Votre clé d'accès SunoAPI.org | Vide |
| Timeout | Durée max de génération | 180 secondes |
| Auto-save | Sauvegarde automatique des brouillons | Activé |

### Base de données

Le plugin crée automatiquement la table `wp_suno_generations` :

```sql
- id : Identifiant unique
- user_id : ID de l'utilisateur WordPress
- task_id : ID de la tâche Suno
- prompt : Description de la chanson
- style : Style musical choisi
- title : Titre de la chanson
- lyrics : Paroles personnalisées
- status : État de la génération
- audio_url : URL du fichier audio
- video_url : URL du fichier vidéo (si disponible)
- image_url : URL de la pochette
- duration : Durée en secondes
- created_at : Date de création
- completed_at : Date de fin de génération
```

## 🛠️ Développement

### Structure du projet
```
suno-music-generator/
├── suno-music-generator-v2.php  # Fichier principal v2.0
├── assets/
│   ├── suno-music-v2.js        # JavaScript v2.0
│   └── suno-music-v2.css       # Styles CSS v2.0
├── README.md                    # Documentation
└── LICENSE                      # Licence GPL-2.0
```

### API SunoAPI.org

Endpoints utilisés :
- `POST /api/generate` : Génération de musique
- `GET /api/get?ids={id}` : Récupération du statut
- `GET /api/get_limit` : Vérification des crédits

### Hooks WordPress

Actions disponibles :
- `suno_before_generation` : Avant la génération
- `suno_after_generation` : Après la génération
- `suno_generation_failed` : En cas d'échec

Filtres disponibles :
- `suno_generation_params` : Modifier les paramètres
- `suno_audio_url` : Filtrer l'URL audio
- `suno_display_result` : Personnaliser l'affichage

## 🐛 Dépannage

### Problèmes courants et solutions

#### ❌ "Clé API non configurée"
- **Solution** : Allez dans Réglages > Suno Music et ajoutez votre clé

#### ❌ "Erreur 401 : Non autorisé"
- **Cause** : Clé API invalide
- **Solution** : Vérifiez votre clé sur sunoapi.org

#### ❌ "Erreur 402 : Crédits insuffisants"
- **Cause** : Plus de crédits disponibles
- **Solution** : Rechargez votre compte SunoAPI.org

#### ❌ "La génération prend trop de temps"
- **Cause** : Serveurs surchargés
- **Solution** : Réessayez plus tard ou vérifiez dans votre playlist

#### ❌ "Audio non disponible"
- **Cause** : Problème de récupération de l'URL
- **Solution** : Mise à jour vers la v2.0 qui corrige ce problème

### Logs de débogage

Activez le mode debug WordPress :
```php
// Dans wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Les logs seront dans `/wp-content/debug.log`

## 📋 Changelog

### Version 2.0 (Août 2025) - ACTUELLE
- ✅ Refonte complète du code
- ✅ Correction du système d'affichage
- ✅ Nouvelle interface utilisateur
- ✅ Amélioration des performances
- ✅ Gestion des erreurs optimisée
- ✅ Support des brouillons automatiques

### Version 1.1
- 🔧 Tentative de correction API
- 🔧 Amélioration des tests
- ⚠️ Problèmes d'affichage persistants

### Version 1.0
- 🚀 Version initiale
- 🎵 Génération basique
- 💾 Sauvegarde en base de données

## 🤝 Contribution

Les contributions sont les bienvenues !

### Comment contribuer

1. **Fork** le projet
2. **Créez** une branche (`git checkout -b feature/NewFeature`)
3. **Committez** (`git commit -m 'Add NewFeature'`)
4. **Push** (`git push origin feature/NewFeature`)
5. **Pull Request** sur GitHub

### Guidelines
- Respectez le style de code existant
- Ajoutez des commentaires pour le code complexe
- Testez vos modifications
- Mettez à jour la documentation

## 📝 Licence

Ce projet est sous licence GPL-2.0. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 🙏 Remerciements

- [Suno AI](https://suno.ai) pour leur technologie de génération musicale
- [SunoAPI.org](https://sunoapi.org) pour l'API accessible
- La communauté WordPress pour l'écosystème
- Tous les contributeurs du projet

## 📞 Support

### Obtenir de l'aide

- 🐛 **Issues** : [GitHub Issues](https://github.com/WeAreReForm/suno-music-generator/issues)
- 💬 **Discussions** : [GitHub Discussions](https://github.com/WeAreReForm/suno-music-generator/discussions)
- 📧 **Email** : hello@wearereform.fr
- 🌐 **Site** : [parcoursmetiersbtp.fr](https://parcoursmetiersbtp.fr)

### Signaler un bug

1. Vérifiez que le bug n'est pas déjà signalé
2. Créez une issue avec :
   - Version du plugin
   - Version de WordPress
   - Description du problème
   - Étapes pour reproduire
   - Logs si disponibles

## 🚀 Roadmap

### Prochaines fonctionnalités (v2.1)
- [ ] Mode playlist avancé
- [ ] Export batch des créations
- [ ] Intégration avec WooCommerce
- [ ] Statistiques détaillées
- [ ] Mode collaboratif

### Long terme (v3.0)
- [ ] Éditeur de paroles intégré
- [ ] Remix de chansons existantes
- [ ] API REST complète
- [ ] Application mobile companion
- [ ] Intelligence artificielle locale

---

## ⭐ Si ce plugin vous est utile

N'hésitez pas à :
- ⭐ Mettre une étoile sur GitHub
- 🔄 Partager le projet
- 💬 Laisser un avis positif
- 🤝 Contribuer au développement

**Développé avec ❤️ par WeAreReForm**

*Dernière mise à jour : Août 2025 - Version 2.0*
