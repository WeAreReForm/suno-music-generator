# 🎵 Suno Music Generator - Plugin WordPress

Un plugin WordPress avancé permettant de générer des chansons personnalisées avec l'intelligence artificielle via l'API Suno, avec protection anti-boucle et système de sécurité intégré.

![Version](https://img.shields.io/badge/version-1.10.4-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-red.svg)
![Security](https://img.shields.io/badge/security-anti--loop-brightgreen.svg)

## ✨ Fonctionnalités

### 🎼 Génération musicale IA
- **Génération de musique IA** : Créez des chansons personnalisées avec Suno
- **Modèle V3.5** : Utilise la dernière version de l'IA Suno
- **Mode custom et automatique** : Contrôle total ou génération automatique
- **Version instrumentale** : Option pour créer des versions sans voix

### 🛡️ Protections et sécurité (v1.10.4)
- **Protection anti-boucle** : Évite les consommations infinies de crédits API
- **Limite de vérifications** : Maximum 20 contrôles par génération
- **Auto-completion** : Finalisation automatique après 10 minutes
- **Test API sécurisé** : Test de connectivité sans consommer de crédits

### 📱 Interface utilisateur
- **Formulaire intuitif** : Interface simple et claire
- **Styles musicaux variés** : Pop, Rock, Jazz, Hip-Hop, Electronic, et plus
- **Design responsive** : Fonctionne sur desktop, tablette et mobile
- **Feedback temps réel** : Affichage du statut avec compteur de protection

### 💾 Gestion des données
- **Historique complet** : Sauvegarde de toutes vos créations
- **Lecteur intégré** : Écoutez vos créations directement sur votre site
- **Statuts détaillés** : Suivi précis de chaque génération
- **Base de données optimisée** : Structure efficace avec indexation

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
   - Allez dans `Réglages > Suno Music`
   - Entrez votre clé API de SunoAPI.org
   - Testez la connexion avec `[suno_test_api]`

## 🎯 Utilisation

### Shortcodes disponibles

#### 🎼 Formulaire de génération
```php
[suno_music_form]
```
Affiche le formulaire complet de création de musique avec protection anti-boucle.

**Fonctionnalités :**
- Description de la chanson (obligatoire)
- Choix du style musical
- Titre personnalisé (optionnel)
- Paroles personnalisées (optionnel)
- Option version instrumentale
- Protection contre les boucles infinies

#### 🎵 Lecteur de musiques
```php
[suno_music_player]
[suno_music_player user_id="123" limit="5"]
```
Affiche les créations musicales avec lecteur audio intégré.

**Paramètres :**
- `user_id` : ID utilisateur spécifique (défaut: utilisateur actuel)
- `limit` : Nombre de créations à afficher (défaut: 10)

#### 🔧 Outils d'administration (admin uniquement)

##### Test API sécurisé
```php
[suno_test_api]
```
Teste la connexion à l'API Suno **SANS consommer de crédits**.

##### Informations de debug
```php
[suno_debug]
```
Affiche les statistiques de la base de données et l'état du système.

##### Test de fonctionnement
```php
[suno_test_shortcode]
```
Vérification rapide que le plugin fonctionne correctement.

##### Gestion de la base de données
```php
[suno_clear_database]
```
Interface sécurisée pour vider la base de données (avec confirmation).

### Exemple d'utilisation complète

1. **Préparation**
   - Ajoutez `[suno_music_form]` sur une page
   - Testez d'abord avec `[suno_test_api]` (admin)

2. **Création**
   - Décrivez votre chanson (ex: "Une ballade pop sur l'amour d'été")
   - Choisissez un style musical (optionnel)
   - Ajoutez des paroles personnalisées (optionnel)
   - Cochez "Version instrumentale" si désiré

3. **Génération**
   - Cliquez sur "Générer la musique"
   - Suivez le statut avec le compteur de protection (max 20 vérifications)
   - Attendez la génération (30-60 secondes généralement)

4. **Écoute et partage**
   - Écoutez votre création avec le lecteur intégré
   - Affichez toutes vos créations avec `[suno_music_player]`

## ⚙️ Configuration

### Page d'administration

Dans `Réglages > Suno Music` :

- **Clé API** : Votre clé d'accès SunoAPI.org
- **Statut de protection** : Informations sur la version v1.10.4
- **Test de connectivité** : Vérification sans consommation de crédits
- **Shortcodes disponibles** : Liste complète des codes disponibles

### Base de données

Le plugin crée automatiquement la table `wp_suno_generations` avec les champs :

| Champ | Type | Description |
|-------|------|-------------|
| `id` | mediumint(9) | ID unique auto-incrémenté |
| `user_id` | bigint(20) | ID de l'utilisateur WordPress |
| `task_id` | varchar(255) | ID de tâche Suno (indexé) |
| `prompt` | text | Description de la chanson |
| `style` | varchar(255) | Style musical choisi |
| `title` | varchar(255) | Titre personnalisé |
| `lyrics` | text | Paroles personnalisées |
| `status` | varchar(50) | État : pending/completed |
| `audio_url` | varchar(500) | URL du fichier audio |
| `video_url` | varchar(500) | URL du fichier vidéo |
| `image_url` | varchar(500) | URL de l'image |
| `duration` | int | Durée en secondes |
| `created_at` | datetime | Date de création |
| `completed_at` | datetime | Date de finalisation |

## 🛠️ Développement

### Structure du projet
```
suno-music-generator/
├── suno-music-generator.php    # Fichier principal (33.9KB)
├── assets/
│   ├── suno-music.js          # Interface JavaScript (12.2KB)
│   └── suno-music.css         # Styles CSS (9.8KB)
├── README.md                  # Documentation complète
└── .gitignore                 # Fichiers à ignorer
```

### API et endpoints

Ce plugin utilise [SunoAPI.org](https://sunoapi.org) avec :

**Endpoint de génération :**
```
POST https://apibox.erweima.ai/api/v1/generate
```

**Endpoint de test (nouveau) :**
```
GET https://apibox.erweima.ai/api/v1/get_limit
```

**Paramètres supportés :**
- `prompt` : Description de la chanson
- `customMode` : Mode personnalisé (true/false)
- `instrumental` : Version instrumentale (true/false)
- `model` : Version du modèle ("V3_5")
- `style` : Style musical
- `title` : Titre personnalisé
- `lyric` : Paroles personnalisées

### Hooks WordPress utilisés

- `init` : Initialisation du plugin
- `wp_enqueue_scripts` : Chargement des assets
- `wp_ajax_generate_music` : Génération AJAX
- `wp_ajax_check_music_status` : Vérification du statut
- `wp_ajax_suno_callback` : Callback de l'API
- `admin_menu` : Page d'administration
- `register_activation_hook` : Création des tables

### Sécurité et protection

**Protection anti-boucle (v1.10.4) :**
- Limite stricte de 20 vérifications par génération
- Auto-completion après 10 minutes
- Logging détaillé des actions
- Test API sans consommation de crédits

**Sécurité WordPress :**
- Vérification des nonces AJAX
- Sanitisation de tous les inputs
- Échappement des outputs
- Contrôle des permissions administrateur

## 🐛 Dépannage

### Problèmes courants

**❌ "Clé API non configurée"**
- Allez dans `Réglages > Suno Music`
- Ajoutez votre clé API de sunoapi.org
- Testez avec `[suno_test_api]`

**❌ "Protection anti-boucle activée"**
- ✅ **C'EST NORMAL** : Protection de vos crédits
- La génération se termine automatiquement après 20 vérifications
- Attendez quelques minutes et vérifiez le résultat

**❌ "Limite de crédits atteinte"**
- Vérifiez vos crédits sur sunoapi.org
- Rechargez votre compte si nécessaire

**❌ "Impossible de se connecter à l'API"**
- Vérifiez votre connexion internet
- Testez avec `[suno_test_api]` (sans consommer de crédits)
- Vérifiez que votre clé API est valide

### Outils de diagnostic

**Diagnostic complet :**
```php
[suno_debug]  // Statistiques de la base de données
```

**Test sans risque :**
```php
[suno_test_api]  // Test SANS consommer de crédits
```

**Vérification du plugin :**
```php
[suno_test_shortcode]  // Test de fonctionnement
```

### Logs de débogage

Activez `WP_DEBUG` dans `wp-config.php` :
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Les logs incluent :
- Actions de génération
- Statuts des vérifications API
- Déclenchements de la protection anti-boucle
- Erreurs de connexion

## 📋 Changelog

### Version 1.10.4 (Actuelle) - 2025-07-31
- 🛡️ **MAJEUR** : Protection anti-boucle infinie
- ✅ Limite de 20 vérifications par génération
- ⏰ Auto-completion après 10 minutes
- 🔒 Test API sécurisé sans consommation de crédits
- 📊 Compteur de protection dans l'interface
- 🐛 Correction de la consommation excessive de crédits

### Version 1.9 - 2025-07-28
- 🧪 Ajout des fonctions de test et debug
- 📋 Insertion de tests en base de données
- 🔄 Amélioration du système de callback

### Version 1.4 - 2025-07-28
- 🔗 Nouveaux endpoints API
- 🛠️ Optimisation de la connectivité

### Version 1.1 - 2025-07-28
- ✅ Documentation ajoutée
- 📖 Section développement en cours

### Version 1.0 - 2025-07-28
- 🚀 Version initiale
- 🎵 Génération de musique basique
- 📝 Formulaire de création
- 💾 Sauvegarde en base de données

## 🤝 Contribution

Les contributions sont les bienvenues ! 

### Comment contribuer

1. **Forkez le projet**
2. **Créez une branche feature**
   ```bash
   git checkout -b feature/AmazingFeature
   ```
3. **Committez vos changements**
   ```bash
   git commit -m 'Add: AmazingFeature'
   ```
4. **Pushez sur la branche**
   ```bash
   git push origin feature/AmazingFeature
   ```
5. **Ouvrez une Pull Request**

### Guidelines de développement

- Respectez les standards WordPress
- Testez toutes les modifications
- Documentez les nouvelles fonctionnalités
- Maintenez la compatibilité avec la protection anti-boucle

## 📝 Licence

Ce projet est sous licence GPL-2.0. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 🙏 Remerciements

- [Suno](https://suno.ai) pour leur incroyable technologie IA
- [SunoAPI.org](https://sunoapi.org) pour l'API accessible et fiable
- La communauté WordPress pour l'écosystème
- Les testeurs pour leurs retours sur la protection anti-boucle

## 📞 Support

- 🐛 **Issues** : [GitHub Issues](https://github.com/WeAreReForm/suno-music-generator/issues)
- 💬 **Discussions** : [GitHub Discussions](https://github.com/WeAreReForm/suno-music-generator/discussions)
- 📧 **Email** : hello@wearereform.fr

---

## 🚀 Statut du développement

**Version actuelle :** v1.10.4 (Protection anti-boucle)  
**Statut :** ✅ Stable et sécurisé  
**Dernière mise à jour :** 2025-08-01  

### 🎯 Prochaines fonctionnalités
- Interface d'administration avancée
- Gestion des quotas utilisateurs
- Export des créations
- Intégration réseaux sociaux

⭐ **Si ce plugin vous est utile, n'hésitez pas à lui donner une étoile sur GitHub !**

---

*Plugin développé avec ❤️ par [WeAreReForm](https://github.com/WeAreReForm) pour la communauté WordPress*