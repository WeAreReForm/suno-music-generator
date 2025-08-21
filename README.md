# 🎵 Suno Music Generator - Plugin WordPress v2.0

Un plugin WordPress permettant de générer des chansons personnalisées avec l'intelligence artificielle via l'API SunoAPI.org

![Version](https://img.shields.io/badge/version-2.0-green.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-red.svg)

## ✨ Nouveautés v2.0

- ✅ **Correction complète** du système d'affichage des morceaux
- ✅ **Récupération fiable** des URLs audio après génération
- ✅ **Interface améliorée** avec animations et notifications
- ✅ **Gestion des brouillons** automatique
- ✅ **Support multi-formats** (MP3, MP4)
- ✅ **Système de partage social** intégré
- ✅ **Meilleure gestion des erreurs** et des timeouts
- ✅ **Design responsive** optimisé

## 🎼 Fonctionnalités

- 🎵 **Génération de musique IA** : Créez des chansons personnalisées en quelques secondes
- 📝 **Formulaire intuitif** : Interface simple et moderne
- 🎨 **Styles musicaux variés** : Pop, Rock, Jazz, Hip-Hop, Électronique, et plus
- 📱 **100% Responsive** : Fonctionne parfaitement sur tous les appareils
- 💾 **Historique complet** : Toutes vos créations sauvegardées
- 🔊 **Lecteur intégré** : Écoutez directement sur votre site
- 📤 **Partage social** : Twitter, Facebook, et copie de lien
- 🔄 **Sauvegarde automatique** : Vos brouillons sont conservés

## 🚀 Installation

### Prérequis
- WordPress 5.0 ou supérieur
- PHP 7.4 ou supérieur
- Clé API de [SunoAPI.org](https://sunoapi.org)

### Méthode 1 : Installation via GitHub

```bash
# 1. Allez dans le dossier des plugins WordPress
cd /path/to/wordpress/wp-content/plugins/

# 2. Clonez le dépôt
git clone https://github.com/WeAreReForm/suno-music-generator.git

# 3. Activez le plugin dans WordPress Admin
```

### Méthode 2 : Installation manuelle

1. Téléchargez la dernière version depuis [GitHub](https://github.com/WeAreReForm/suno-music-generator)
2. Décompressez dans `/wp-content/plugins/`
3. Activez le plugin dans WordPress Admin

### Configuration

1. Allez dans **Réglages > Suno Music**
2. Entrez votre clé API de [SunoAPI.org](https://sunoapi.org)
3. Testez la connexion avec `[suno_test_api]`

## 🎯 Utilisation

### Shortcodes disponibles

#### 1. Formulaire de génération
```php
[suno_music_form]
```
Affiche le formulaire complet de création de musique.

#### 2. Playlist des créations
```php
[suno_music_player]
[suno_music_player user_id="123" limit="5"]
```
Affiche les créations musicales avec lecteur intégré.

#### 3. Test de l'API (admin seulement)
```php
[suno_test_api]
```
Vérifie la connexion à l'API et affiche les crédits disponibles.

### Exemple d'utilisation

1. Créez une nouvelle page WordPress
2. Ajoutez le shortcode `[suno_music_form]`
3. Publiez la page
4. Vos visiteurs peuvent maintenant :
   - Décrire leur chanson idéale
   - Choisir un style musical
   - Ajouter des paroles personnalisées
   - Générer et télécharger leur création

## ⚙️ Configuration avancée

### Base de données

Le plugin crée automatiquement la table `wp_suno_generations` avec les champs suivants :

| Champ | Type | Description |
|-------|------|-------------|
| id | INT | Identifiant unique |
| user_id | BIGINT | ID de l'utilisateur WordPress |
| task_id | VARCHAR | ID de la tâche Suno |
| prompt | TEXT | Description de la chanson |
| style | VARCHAR | Style musical choisi |
| title | VARCHAR | Titre de la chanson |
| lyrics | TEXT | Paroles personnalisées |
| status | VARCHAR | Statut de génération |
| audio_url | VARCHAR | URL du fichier audio |
| video_url | VARCHAR | URL de la vidéo (si disponible) |
| image_url | VARCHAR | URL de l'artwork |
| duration | INT | Durée en secondes |
| created_at | DATETIME | Date de création |
| completed_at | DATETIME | Date de complétion |

### Hooks WordPress

Le plugin offre plusieurs hooks pour personnalisation :

```php
// Actions AJAX
wp_ajax_generate_music
wp_ajax_check_music_status
wp_ajax_test_suno_api

// Filtres (à venir dans v2.1)
suno_music_max_prompt_length
suno_music_check_interval
suno_music_max_timeout
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
└── CHANGELOG.md                 # Historique des versions
```

### API SunoAPI.org

Ce plugin utilise l'API [SunoAPI.org](https://sunoapi.org) avec les endpoints suivants :

- `POST /api/generate` : Génération de musique
- `GET /api/get?ids={id}` : Vérification du statut
- `GET /api/get_limit` : Vérification des crédits

### Débogage

Pour activer le mode debug :

```php
// Dans wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Les logs sont disponibles dans `/wp-content/debug.log`

## 🐛 Résolution des problèmes

### "Clé API non configurée"
→ Allez dans Réglages > Suno Music et ajoutez votre clé

### "Limite de crédits atteinte"
→ Rechargez vos crédits sur [SunoAPI.org](https://sunoapi.org)

### "La génération échoue"
1. Vérifiez votre connexion internet
2. Testez avec `[suno_test_api]`
3. Vérifiez les logs WordPress
4. Contactez le support SunoAPI

### "Pas d'audio dans la playlist"
→ La v2.0 corrige ce problème. Mettez à jour le plugin.

## 📋 Changelog

### Version 2.0 (21/08/2025)
- ✅ Correction complète du système d'affichage
- ✅ Nouvelle interface utilisateur moderne
- ✅ Ajout du système de notifications
- ✅ Sauvegarde automatique des brouillons
- ✅ Partage social intégré
- ✅ Meilleure gestion des erreurs

### Version 1.1
- 🔧 Tentative de correction API
- 🔧 Amélioration des tests

### Version 1.0
- 🚀 Version initiale

## 🤝 Contribution

Les contributions sont les bienvenues !

1. Forkez le projet
2. Créez votre branche (`git checkout -b feature/AmazingFeature`)
3. Committez (`git commit -m 'Add AmazingFeature'`)
4. Pushez (`git push origin feature/AmazingFeature`)
5. Ouvrez une Pull Request

## 📝 Licence

Ce projet est sous licence GPL-2.0. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 🙏 Remerciements

- [Suno AI](https://suno.ai) pour leur technologie IA révolutionnaire
- [SunoAPI.org](https://sunoapi.org) pour l'API accessible et fiable
- La communauté WordPress pour leur support

## 📞 Support

- 🐛 **Issues** : [GitHub Issues](https://github.com/WeAreReForm/suno-music-generator/issues)
- 📧 **Email** : hello@wearereform.fr
- 🌐 **Site** : [WeAreReForm](https://wearereform.fr)

---

## 🎉 Installation rapide sur votre site

```bash
# Dans votre conteneur Docker WordPress
docker exec -it wordpress-container bash
cd /var/www/html/wp-content/plugins/
git clone https://github.com/WeAreReForm/suno-music-generator.git
```

Puis activez le plugin dans WordPress Admin !

---

⭐ **Si ce plugin vous est utile, n'hésitez pas à lui donner une étoile sur GitHub !**

🚀 **Version 2.0** - Stable et fonctionnelle - Août 2025
