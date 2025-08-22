# 🎵 Suno Music Generator - Plugin WordPress

Un plugin WordPress permettant de générer des chansons personnalisées avec l'intelligence artificielle via l'API Suno.

![Version](https://img.shields.io/badge/version-1.3-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-red.svg)

## ✨ Fonctionnalités

- 🎼 **Génération de musique IA** : Créez des chansons personnalisées avec Suno
- 📝 **Formulaire intuitif** : Interface moderne inspirée de Suno
- 🎨 **15 styles musicaux** : Pop, Rock, Jazz, Hip-Hop, Metal, R&B et plus
- 📱 **Design responsive** : Fonctionne sur desktop, tablette et mobile
- 💾 **Historique complet** : Sauvegarde de toutes vos créations
- 🔊 **Lecteur intégré** : Écoutez vos créations directement
- 📤 **Partage social** : Twitter, Facebook, copier le lien
- ⚙️ **Interface admin complète** : Dashboard, réglages et historique
- 🎯 **Sauvegarde automatique** : Ne perdez jamais votre travail
- 🌙 **Thème sombre moderne** : Design inspiré de Suno

## 🚀 Installation

### Prérequis
- WordPress 5.0 ou supérieur
- PHP 7.4 ou supérieur
- Clé API de [SunoAPI.com](https://sunoapi.com)

### Installation rapide

1. **Téléchargez la dernière version**
   - [Télécharger le ZIP](https://github.com/WeAreReForm/suno-music-generator/archive/refs/heads/main.zip)

2. **Installez dans WordPress**
   - Admin WordPress > Extensions > Ajouter
   - Téléverser une extension > Choisir le fichier ZIP
   - Activer le plugin

3. **Configurez votre clé API**
   - Menu `Suno Music > Réglages`
   - Entrez votre clé API de SunoAPI.com
   - Testez la connexion

## 🎯 Configuration

### 1️⃣ Obtenir une clé API

1. Inscrivez-vous sur [SunoAPI.com](https://sunoapi.com)
2. Obtenez votre clé API dans le dashboard
3. Ajoutez des crédits si nécessaire

### 2️⃣ Configurer le plugin

Dans WordPress admin > `Suno Music > Réglages` :

- **Clé API** : Collez votre clé API
- **Accès public** : Autoriser les visiteurs non connectés
- **Limite par utilisateur** : Nombre max de générations/jour

### 3️⃣ Créer vos pages

Créez une nouvelle page et ajoutez le shortcode :

```
[suno_music_form]
```

## 📌 Shortcodes

### Formulaire de génération
```
[suno_music_form]
```
Affiche le formulaire complet avec :
- Zone de description
- Sélecteur de style musical
- Titre personnalisé
- Paroles optionnelles
- Mode instrumental

### Lecteur de musiques
```
[suno_music_player]
[suno_music_player user_id="123" limit="5"]
```
Paramètres :
- `user_id` : ID utilisateur (optionnel)
- `limit` : Nombre de résultats (défaut: 10)

### Test API (admin uniquement)
```
[suno_test_api]
```
Vérifie la connexion à l'API et affiche les crédits disponibles.

## 🎨 Styles musicaux disponibles

- Pop
- Rock
- Électronique
- Hip-Hop
- Jazz
- Classique
- Country
- Reggae
- Blues
- Folk
- Metal
- R&B
- Disco
- Funk

## 💡 Exemples d'utilisation

### Page simple
```html
[suno_music_form]
```

### Page complète avec historique
```html
<h2>Créer votre musique</h2>
[suno_music_form]

<h2>Mes créations récentes</h2>
[suno_music_player limit="5"]
```

### Page avec instructions
```html
<h2>Studio de création musicale IA</h2>
<p>Décrivez la chanson de vos rêves et laissez l'IA la créer !</p>

[suno_music_form]

<hr>

<h3>Besoin d'inspiration ?</h3>
<p>Essayez : "Une chanson pop énergique sur l'été et la liberté"</p>
```

## 🛠️ Interface d'administration

### Dashboard principal
- Guide d'utilisation
- Shortcodes disponibles
- Test de connexion API

### Page de réglages
- Configuration de la clé API
- Options d'accès
- Limites utilisateurs
- Test API en temps réel

### Historique
- Liste complète des générations
- Statut de chaque création
- Liens d'écoute directs

## 🔧 Résolution des problèmes

### ❌ Erreur HTTP 404 sur le test API

**Solution :**
1. Vérifiez que votre clé API est correcte
2. Assurez-vous d'avoir des crédits sur SunoAPI.com
3. Le format de clé doit être : `sk-xxxxxxxxxxxx`

### ❌ Le formulaire ne génère pas de musique

**Vérifications :**
1. Clé API configurée dans les réglages
2. Crédits disponibles sur votre compte
3. Description de chanson fournie
4. Connexion internet stable

### ❌ CSS minimal / Design cassé

**Solution :**
- Videz le cache de votre navigateur
- Désactivez les plugins de cache WordPress
- Vérifiez que les fichiers CSS sont bien chargés

## 📊 Structure de la base de données

Table `wp_suno_generations` :
```sql
- id : Identifiant unique
- user_id : ID de l'utilisateur
- task_id : ID de la tâche Suno
- clip_ids : IDs des clips générés
- prompt : Description de la chanson
- style : Style musical
- title : Titre
- lyrics : Paroles
- status : Statut (pending/completed)
- audio_url : URL du fichier audio
- video_url : URL de la vidéo
- image_url : URL de l'artwork
- created_at : Date de création
- completed_at : Date de complétion
```

## 📋 Changelog

### Version 1.3 (2025-08-22) - ACTUELLE
- ✅ **API corrigée** : URL api.sunoapi.com
- ✅ **Headers API** : Utilisation de `api-key` au lieu de `Authorization`
- ✅ **Système clip_ids** : Gestion correcte des IDs de génération
- ✅ **Design moderne** : Interface inspirée de Suno (thème sombre)
- ✅ **CSS amélioré** : Animations et transitions fluides
- ✅ **JavaScript optimisé** : Meilleure gestion des erreurs
- ✅ **Sauvegarde automatique** : LocalStorage pour les brouillons
- ✅ **Suggestions de prompts** : 10 idées prédéfinies

### Version 1.2
- ✅ Interface admin complète
- ✅ Page de réglages fonctionnelle
- ✅ Historique des générations
- ✅ Suppression de la création auto de pages

### Version 1.1
- ✅ Compatibilité SunoAPI.org
- ✅ Test API amélioré
- ✅ Gestion d'erreurs

### Version 1.0
- 🚀 Version initiale

## 🔒 Sécurité

- Nonces WordPress pour toutes les requêtes AJAX
- Sanitization des entrées utilisateur
- Échappement des sorties HTML
- Validation côté serveur
- Protection CSRF

## 🚀 Roadmap

- [ ] Mode galerie publique
- [ ] Système de votes/likes
- [ ] Export MP3 avec métadonnées
- [ ] Intégration WooCommerce
- [ ] API REST WordPress
- [ ] Multilingue (WPML/Polylang)
- [ ] Mode clair/sombre
- [ ] Webhooks pour notifications

## 🤝 Contribution

Les contributions sont les bienvenues !

1. Forkez le projet
2. Créez une branche (`git checkout -b feature/NouvelleFonction`)
3. Committez (`git commit -m 'Ajout NouvelleFonction'`)
4. Push (`git push origin feature/NouvelleFonction`)
5. Ouvrez une Pull Request

## 📝 Licence

GPL-2.0 - Voir [LICENSE](LICENSE)

## 🙏 Remerciements

- [Suno AI](https://suno.ai) - Technologie IA
- [SunoAPI.com](https://sunoapi.com) - API accessible
- [WeAreReForm](https://wearereform.com) - Développement
- Communauté WordPress

## 📞 Support

- 🐛 [GitHub Issues](https://github.com/WeAreReForm/suno-music-generator/issues)
- 💬 [Discussions](https://github.com/WeAreReForm/suno-music-generator/discussions)
- 📧 contact@wearereform.com
- 🌐 [parcoursmetiersbtp.fr](https://parcoursmetiersbtp.fr)

---

⭐ **Si ce plugin vous aide, merci de mettre une étoile sur GitHub !**

🚀 **Version 1.3** - Dernière mise à jour : 22/08/2025