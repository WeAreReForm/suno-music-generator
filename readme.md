# 🎵 Suno Music Generator - Plugin WordPress

Un plugin WordPress permettant de générer des chansons personnalisées avec l'intelligence artificielle via l'API Suno.

![Version](https://img.shields.io/badge/version-1.1-blue.svg)
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

## 🚀 Installation

### Prérequis
- WordPress 5.0 ou supérieur
- PHP 7.4 ou supérieur
- Clé API de [SunoAPI.org](https://sunoapi.org)

### Étapes d'installation

1. **Téléchargez le plugin**
   ```bash
   git clone https://github.com/VOTRE_USERNAME/suno-music-generator.git
   ```

2. **Uploadez dans WordPress**
   - Placez le dossier dans `/wp-content/plugins/`
   - Ou uploadez le fichier ZIP via l'admin WordPress

3. **Activez le plugin**
   - Allez dans `Extensions > Extensions installées`
   - Activez "Suno Music Generator"

4. **Configurez l'API**
   - Allez dans `Réglages > Suno Music`
   - Entrez votre clé API de SunoAPI.org
   - Testez la connexion avec `[suno_test_api]`

## 🎯 Utilisation

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
Affiche les créations musicales d'un utilisateur.

#### Test de connectivité (admin uniquement)
```php
[suno_test_api]
```
Teste la connexion à l'API Suno.

### Exemple d'utilisation

1. Ajoutez `[suno_music_form]` sur une page
2. Décrivez votre chanson (ex: "Une ballade pop sur l'amour")
3. Choisissez un style musical (optionnel)
4. Ajoutez des paroles personnalisées (optionnel)
5. Cliquez sur "Générer la musique"
6. Attendez la génération (30-60 secondes)
7. Écoutez et partagez votre création !

## ⚙️ Configuration

### Paramètres du plugin

Dans `Réglages > Suno Music` :

- **Clé API** : Votre clé d'accès SunoAPI.org
- **Test de connectivité** : Vérifiez que tout fonctionne

### Base de données

Le plugin crée automatiquement la table `wp_suno_generations` pour stocker :
- Les prompts utilisateurs
- Les IDs de tâches
- Les URLs des fichiers générés
- Les métadonnées des chansons

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

### API utilisée

Ce plugin utilise [SunoAPI.org](https://sunoapi.org) pour :
- Générer des chansons avec l'IA Suno
- Récupérer les fichiers audio/vidéo
- Gérer les crédits et limitations

### Hooks WordPress

Le plugin utilise :
- `wp_ajax_generate_music` : Génération AJAX
- `wp_ajax_check_music_status` : Vérification du statut
- `admin_menu` : Page d'administration
- `wp_enqueue_scripts` : Chargement des assets

## 🐛 Dépannage

### Problèmes courants

**❌ "Clé API non configurée"**
- Allez dans Réglages > Suno Music
- Ajoutez votre clé API de sunoapi.org

**❌ "Limite de crédits atteinte"**
- Vérifiez vos crédits sur sunoapi.org
- Rechargez votre compte si nécessaire

**❌ "Impossible de se connecter à l'API"**
- Vérifiez votre connexion internet
- Testez avec `[suno_test_api]`
- Contactez le support de SunoAPI.org

### Logs de débogage

Activez `WP_DEBUG` dans `wp-config.php` pour voir les logs détaillés :
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📋 Changelog

### Version 1.1 (Actuelle)
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

- 🐛 **Issues** : [GitHub Issues](https://github.com/VOTRE_USERNAME/suno-music-generator/issues)
- 💬 **Discussions** : [GitHub Discussions](https://github.com/VOTRE_USERNAME/suno-music-generator/discussions)
- 📧 **Email** : votre-email@example.com

---

## 🚀 Développement en cours
Ce plugin est activement développé. Dernière mise à jour: [2025-07-28]

⭐ **Si ce plugin vous est utile, n'hésitez pas à lui donner une étoile sur GitHub !**