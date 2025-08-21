# 📦 Installation via l'interface WordPress

## 🎯 Installation simple en 3 étapes

### Étape 1 : Télécharger le plugin

**➡️ [Télécharger le plugin ZIP](https://github.com/WeAreReForm/suno-music-generator/archive/refs/heads/main.zip)**

Cliquez sur le lien ci-dessus pour télécharger la dernière version.

### Étape 2 : Installer via WordPress

1. **Connectez-vous** à votre administration WordPress
2. Allez dans **Extensions > Ajouter**
3. Cliquez sur **"Téléverser une extension"** (bouton en haut)
4. Cliquez sur **"Parcourir..."**
5. Sélectionnez le fichier **`suno-music-generator-main.zip`** téléchargé
6. Cliquez sur **"Installer maintenant"**
7. Une fois installé, cliquez sur **"Activer"**

### Étape 3 : Configuration

1. Allez dans **Réglages > Suno Music**
2. Entrez votre **clé API** de [SunoAPI.org](https://sunoapi.org)
3. Cliquez sur **"Enregistrer les modifications"**

## ✅ C'est terminé !

### 📝 Utilisation des shortcodes

Sur n'importe quelle page ou article :

```
[suno_music_form]     → Formulaire de génération
[suno_music_player]   → Playlist des chansons
[suno_test_api]       → Test de connexion (admin)
[suno_maintenance]    → Outils de maintenance (admin)
```

## 🔧 En cas de problème de versions multiples

Si vous avez des problèmes avec d'anciennes versions qui réapparaissent :

1. **Désactivez TOUS les plugins Suno** dans Extensions > Extensions installées
2. **Supprimez-les** (cliquez sur Supprimer sous chaque plugin Suno)
3. **Réinstallez** en suivant les étapes ci-dessus
4. **Utilisez l'outil de maintenance** :
   - Créez une page privée
   - Ajoutez le shortcode `[suno_maintenance]`
   - Lancez "Nettoyage des conflits de versions"

## 📱 Besoin d'aide ?

- **Documentation** : [README complet](https://github.com/WeAreReForm/suno-music-generator/blob/main/README-v2.md)
- **Support** : [Créer une issue](https://github.com/WeAreReForm/suno-music-generator/issues)
- **Version actuelle** : 2.1

## ⚠️ Important

- **Ne jamais** modifier les fichiers via FTP
- **Toujours** utiliser l'interface WordPress
- **Sauvegarder** votre clé API avant toute manipulation

---

**Plugin développé par WeAreReForm** | Version 2.1 | Août 2025
