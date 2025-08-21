# 🎵 Suno Music Generator v5.0

[![Version](https://img.shields.io/badge/version-5.0.0-blue.svg)](https://github.com/WeAreReForm/suno-music-generator)
[![WordPress](https://img.shields.io/badge/WordPress-5.0+-green.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0-red.svg)](LICENSE)

Un plugin WordPress professionnel pour générer des chansons personnalisées avec l'intelligence artificielle via l'API Suno.

## ✨ Nouveautés v5.0

- 🚀 **Architecture refonte complète** : Code modulaire et optimisé
- 🎨 **Interface utilisateur moderne** : Design responsive et animations fluides
- 📊 **Tableau de bord avancé** : Statistiques et gestion centralisée
- 🎼 **Galerie publique** : Partagez vos créations avec la communauté
- 👤 **Profils utilisateurs** : Historique et playlists personnelles
- 🔌 **REST API** : Intégration avec d'autres applications
- 🌍 **Multilingue** : Support de plusieurs langues
- ⚡ **Performance améliorée** : Cache et optimisations

## 🎯 Fonctionnalités principales

### Génération de musique
- Création de chansons personnalisées avec l'IA Suno
- Support de multiples styles musicaux
- Génération avec ou sans paroles
- Mode instrumental disponible
- Tags et métadonnées personnalisables

### Interface utilisateur
- Formulaire de génération intuitif
- Barre de progression en temps réel
- Lecteur audio intégré
- Galerie de créations
- Système de likes et partage

### Gestion avancée
- Tableau de bord administrateur
- Historique complet des générations
- Statistiques d'utilisation
- Gestion des limites quotidiennes
- Export des données

## 📦 Installation

### Prérequis
- WordPress 5.0 ou supérieur
- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Clé API de [SunoAPI.org](https://sunoapi.org)

### Installation automatique (recommandée)

1. Téléchargez la dernière version depuis [GitHub](https://github.com/WeAreReForm/suno-music-generator/releases)
2. Dans WordPress, allez dans `Extensions > Ajouter`
3. Cliquez sur `Téléverser une extension`
4. Sélectionnez le fichier ZIP téléchargé
5. Activez le plugin

### Installation manuelle

```bash
# Cloner le repository
cd wp-content/plugins/
git clone https://github.com/WeAreReForm/suno-music-generator.git

# Activer dans WordPress Admin
```

## ⚙️ Configuration

### Configuration initiale

1. **Obtenir une clé API**
   - Inscrivez-vous sur [SunoAPI.org](https://sunoapi.org)
   - Récupérez votre clé API dans votre dashboard
   - Ajoutez des crédits si nécessaire

2. **Configurer le plugin**
   - Allez dans `Réglages > Suno Music`
   - Entrez votre clé API
   - Configurez les options selon vos besoins

3. **Créer les pages**
   - Le plugin crée automatiquement 3 pages
   - Vous pouvez les personnaliser ou en créer d'autres

## 🎮 Utilisation

### Shortcodes disponibles

#### Générateur principal
```php
[suno_music_generator]
```
Options :
- `styles="pop,rock,jazz"` : Styles disponibles
- `show_history="true"` : Afficher l'historique
- `max_prompt="500"` : Longueur max du prompt
- `max_lyrics="3000"` : Longueur max des paroles

#### Galerie publique
```php
[suno_gallery limit="12" orderby="created_at" order="DESC"]
```

#### Ma musique
```php
[suno_my_music limit="20" show_stats="true"]
```

#### Lecteur individuel
```php
[suno_player id="123" autoplay="false"]
```

#### Test API (admin seulement)
```php
[suno_test_api]
```

### Exemple d'utilisation

1. Ajoutez le shortcode sur une page :
```php
[suno_music_generator]
```

2. Les utilisateurs peuvent :
   - Décrire leur chanson idéale
   - Choisir un style musical
   - Ajouter des paroles personnalisées
   - Générer et écouter le résultat

## 🔌 API REST

Le plugin expose une API REST pour l'intégration :

### Endpoints

```
POST /wp-json/suno/v1/generate
GET  /wp-json/suno/v1/status/{task_id}
GET  /wp-json/suno/v1/gallery
```

### Exemple d'utilisation

```javascript
// Générer une chanson
fetch('/wp-json/suno/v1/generate', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        prompt: 'Une chanson joyeuse sur l\'été',
        style: 'pop'
    })
});
```

## 🗂️ Structure du projet

```
suno-music-generator/
├── suno-music-generator.php    # Fichier principal
├── assets/
│   ├── css/
│   │   ├── suno-music.css     # Styles frontend
│   │   └── suno-admin.css     # Styles admin
│   ├── js/
│   │   ├── suno-music.js      # Scripts frontend
│   │   └── suno-admin.js      # Scripts admin
│   └── images/                 # Images et icônes
├── includes/
│   ├── admin/                  # Pages admin
│   ├── ajax/                   # Handlers AJAX
│   └── shortcodes/             # Templates shortcodes
├── languages/                  # Fichiers de traduction
└── README.md                   # Documentation
```

## 🚀 Déploiement

### Sur un VPS OVH avec Docker

```yaml
# docker-compose.yml
version: '3'
services:
  wordpress:
    image: wordpress:latest
    volumes:
      - ./plugins/suno-music-generator:/var/www/html/wp-content/plugins/suno-music-generator
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_NAME: wordpress
      WORDPRESS_DB_USER: root
      WORDPRESS_DB_PASSWORD: password
```

### Configuration Nginx recommandée

```nginx
location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
    fastcgi_read_timeout 300;
}
```

## 🐛 Dépannage

### Problèmes courants

**Clé API non reconnue**
- Vérifiez que la clé est correctement saisie
- Testez avec le shortcode `[suno_test_api]`

**Génération qui ne démarre pas**
- Vérifiez vos crédits sur SunoAPI.org
- Consultez les logs WordPress

**Timeout pendant la génération**
- Augmentez `max_execution_time` dans PHP
- Augmentez `fastcgi_read_timeout` dans Nginx

### Logs et debug

Activez le mode debug dans `wp-config.php` :
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## 📊 Base de données

Le plugin crée 3 tables :
- `wp_suno_generations` : Historique des générations
- `wp_suno_likes` : Système de likes
- `wp_suno_playlists` : Playlists utilisateurs

## 🔄 Mises à jour

Le plugin vérifie automatiquement les mises à jour sur GitHub.

Pour mettre à jour manuellement :
```bash
cd wp-content/plugins/suno-music-generator
git pull origin main
```

## 🤝 Contribution

Les contributions sont les bienvenues !

1. Fork le projet
2. Créez une branche (`git checkout -b feature/AmazingFeature`)
3. Committez (`git commit -m 'Add AmazingFeature'`)
4. Push (`git push origin feature/AmazingFeature`)
5. Ouvrez une Pull Request

## 📋 Changelog

### Version 5.0.0 (2025-08-21)
- ✨ Refonte complète de l'architecture
- 🎨 Nouvelle interface utilisateur
- 📊 Ajout du tableau de bord admin
- 🔌 Implémentation de l'API REST
- 🌍 Support multilingue
- ⚡ Optimisations de performance

### Version 2.2.0
- Correction de bugs
- Amélioration de la stabilité

### Version 1.0.0
- Version initiale

## 📝 Licence

Ce projet est sous licence GPL-2.0+. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 🙏 Remerciements

- [Suno AI](https://suno.ai) pour leur technologie IA
- [SunoAPI.org](https://sunoapi.org) pour l'API accessible
- [WeAreReForm](https://wearereform.fr) pour le développement
- La communauté WordPress

## 📞 Support

- 🐛 **Issues** : [GitHub Issues](https://github.com/WeAreReForm/suno-music-generator/issues)
- 📧 **Email** : hello@wearereform.fr
- 🌐 **Site** : [parcoursmetiersbtp.fr](https://parcoursmetiersbtp.fr)

---

⭐ **Si ce plugin vous est utile, n'hésitez pas à lui donner une étoile sur GitHub !**

🚀 **Version 5.0.0** - Développé avec ❤️ par [WeAreReForm](https://wearereform.fr)
