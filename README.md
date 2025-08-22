# 🎵 Suno Music Generator - Plugin WordPress

Un plugin WordPress permettant de générer des chansons personnalisées avec l'intelligence artificielle via l'API Suno.

![Version](https://img.shields.io/badge/version-1.2-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-red.svg)

## ✨ Fonctionnalités

- 🎼 **Génération de musique IA** : Créez des chansons personnalisées avec Suno
- 📝 **Formulaire intuitif** : Interface simple pour décrire votre chanson
- 🎨 **Styles musicaux variés** : Pop, Rock, Jazz, Hip-Hop, et plus
- 📱 **Responsive** : Fonctionne sur desktop, tablette et mobile
- 💾 **Historique** : Sauvegarde de toutes vos créations
- 🔊 **Lecteur intégré** : Écoutez vos créations directement sur votre site
- 📤 **Partage social** : Partagez facilement sur les réseaux sociaux
- ⚙️ **Interface admin complète** : Page de réglages et historique

## 🚀 Installation

### Prérequis
- WordPress 5.0 ou supérieur
- PHP 7.4 ou supérieur
- Clé API de [SunoAPI.org](https://sunoapi.org)

### Étapes d'installation

1. **Téléchargez le plugin**
   ```bash
   git clone https://github.com/WeAreReForm/suno-music-generator.git
   ```

2. **Uploadez dans WordPress**
   - Placez le dossier dans `/wp-content/plugins/`
   - Ou uploadez le fichier ZIP via l'admin WordPress

3. **Activez le plugin**
   - Allez dans `Extensions > Extensions installées`
   - Activez "Suno Music Generator"

4. **Configurez l'API**
   - Allez dans `Suno Music > Réglages`
   - Entrez votre clé API de SunoAPI.org
   - Testez la connexion

## 🎯 Utilisation

### Configuration initiale

1. **Obtenez une clé API**
   - Inscrivez-vous sur [SunoAPI.org](https://sunoapi.org)
   - Copiez votre clé API

2. **Configurez le plugin**
   - Menu admin : `Suno Music > Réglages`
   - Collez votre clé API
   - Configurez les options (accès public, limites, etc.)

### Shortcodes disponibles

#### Formulaire de génération
```php
[suno_music_form]
```
Affiche le formulaire complet de création de musique.

#### Lecteur de musiques
```php
[suno_music_player]
[suno_music_player user_id="123" limit="5"]
```
Affiche les créations musicales. Paramètres optionnels :
- `user_id` : ID de l'utilisateur (défaut : utilisateur actuel)
- `limit` : Nombre de résultats (défaut : 10)

#### Test de connectivité (admin uniquement)
```php
[suno_test_api]
```
Teste la connexion à l'API Suno.

### Créer une page de génération

1. **Créez une nouvelle page** dans WordPress
2. **Ajoutez le shortcode** `[suno_music_form]`
3. **Publiez la page**
4. **C'est prêt !** Les visiteurs peuvent maintenant générer de la musique

### Exemple de page complète

```html
[suno_music_form]

<h2>Mes créations récentes</h2>
[suno_music_player limit="5"]
```

## ⚙️ Administration

### Menu Suno Music

Le plugin ajoute un menu principal dans l'admin WordPress avec :

- **Dashboard** : Vue d'ensemble et guide d'utilisation
- **Réglages** : Configuration de l'API et options
- **Historique** : Liste de toutes les générations

### Options disponibles

- **Clé API** : Votre clé d'accès SunoAPI.org
- **Accès public** : Permettre aux visiteurs non connectés
- **Limite par utilisateur** : Nombre max de générations par jour

## 🛠️ Développement

### Structure du projet
```
suno-music-generator/
├── suno-music-generator.php    # Fichier principal du plugin
├── assets/
│   ├── suno-music.js          # Interface JavaScript
│   └── suno-music.css         # Styles CSS
└── README.md                  # Documentation
```

### Base de données

Le plugin crée automatiquement la table `wp_suno_generations` pour stocker :
- Les prompts utilisateurs
- Les IDs de tâches
- Les URLs des fichiers générés
- Les métadonnées des chansons

### Hooks WordPress

Le plugin utilise :
- `wp_ajax_generate_music` : Génération AJAX
- `wp_ajax_check_music_status` : Vérification du statut
- `admin_menu` : Pages d'administration
- `wp_enqueue_scripts` : Chargement des assets

## 🐛 Dépannage

### Problèmes courants

**❌ "Clé API non configurée"**
- Allez dans `Suno Music > Réglages`
- Ajoutez votre clé API de sunoapi.org

**❌ "Limite de crédits atteinte"**
- Vérifiez vos crédits sur sunoapi.org
- Rechargez votre compte si nécessaire

**❌ "Impossible de se connecter à l'API"**
- Vérifiez votre connexion internet
- Testez avec `[suno_test_api]`
- Vérifiez que votre clé API est valide

### Logs de débogage

Activez `WP_DEBUG` dans `wp-config.php` pour voir les logs détaillés :
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📋 Changelog

### Version 1.2 (Actuelle)
- ✅ Interface admin complète avec menu et sous-menus
- ✅ Page de réglages pour configurer la clé API
- ✅ Historique des générations
- ✅ Suppression de la création automatique de pages
- ✅ Correction de tous les shortcodes
- ✅ Test API amélioré

### Version 1.1
- ✅ Correction de la compatibilité SunoAPI.org
- ✅ Amélioration du système de test API
- ✅ Interface utilisateur améliorée
- ✅ Gestion d'erreurs optimisée

### Version 1.0
- 🚀 Version initiale
- 🎵 Génération de musique basique
- 📝 Formulaire de création
- 💾 Sauvegarde en base de données

## 🤝 Contribution

Les contributions sont les bienvenues ! 

1. Forkez le projet
2. Créez une branche feature (`git checkout -b feature/AmazingFeature`)
3. Committez vos changements (`git commit -m 'Add AmazingFeature'`)
4. Pushez sur la branche (`git push origin feature/AmazingFeature`)
5. Ouvrez une Pull Request

## 📝 Licence

Ce projet est sous licence GPL-2.0. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 🙏 Remerciements

- [Suno](https://suno.ai) pour leur incroyable technologie IA
- [SunoAPI.org](https://sunoapi.org) pour l'API accessible
- La communauté WordPress pour l'écosystème

## 📞 Support

- 🐛 **Issues** : [GitHub Issues](https://github.com/WeAreReForm/suno-music-generator/issues)
- 💬 **Discussions** : [GitHub Discussions](https://github.com/WeAreReForm/suno-music-generator/discussions)
- 📧 **Email** : contact@wearereform.com

---

## 🚀 Développement en cours
Ce plugin est activement développé. Dernière mise à jour: 2025-08-22

⭐ **Si ce plugin vous est utile, n'hésitez pas à lui donner une étoile sur GitHub !**