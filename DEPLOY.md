# 📦 Instructions de déploiement - Version 2.0

## 🚀 Installation rapide sur WordPress

### Étape 1 : Téléchargement

```bash
# Option A : Via Git (recommandé)
cd /votre-wordpress/wp-content/plugins/
git clone https://github.com/WeAreReForm/suno-music-generator.git

# Option B : Téléchargement ZIP
# Téléchargez depuis : https://github.com/WeAreReForm/suno-music-generator/archive/refs/heads/main.zip
```

### Étape 2 : Activation du plugin

1. **Connectez-vous à WordPress Admin**
2. **Allez dans Extensions > Extensions installées**
3. **Désactivez l'ancienne version** (si elle existe)
4. **Activez "Suno Music Generator"**

⚠️ **IMPORTANT** : Le fichier principal est maintenant `suno-music-generator-v2.php`

### Étape 3 : Configuration

1. **Allez dans Réglages > Suno Music**
2. **Entrez votre clé API** de [SunoAPI.org](https://sunoapi.org)
3. **Sauvegardez**

### Étape 4 : Test de connexion

Créez une page test et ajoutez :
```
[suno_test_api]
```

Vous devriez voir :
- ✅ Connexion réussie
- ✅ Crédits disponibles

### Étape 5 : Utilisation

#### Sur une page "Créer une chanson" :
```
[suno_music_form]
```

#### Sur une page "Mes créations" :
```
[suno_music_player]
```

## 🔧 Résolution des problèmes

### Si l'ancien plugin est encore actif :

```bash
# 1. Renommez l'ancien dossier
mv suno-music-generator suno-music-generator-old

# 2. Clonez la nouvelle version
git clone https://github.com/WeAreReForm/suno-music-generator.git

# 3. Activez dans WordPress
```

### Si les styles ne s'affichent pas :

1. **Videz le cache WordPress**
2. **Videz le cache navigateur** (Ctrl+F5)
3. **Vérifiez les permissions** des fichiers (644 pour les fichiers, 755 pour les dossiers)

### Si la génération échoue :

1. **Vérifiez votre clé API** dans Réglages > Suno Music
2. **Vérifiez vos crédits** sur [SunoAPI.org](https://sunoapi.org)
3. **Testez avec** `[suno_test_api]`

## ✅ Vérification finale

Votre plugin fonctionne correctement si :

- ✅ Le formulaire s'affiche avec le bon design
- ✅ La génération démarre après soumission
- ✅ Une barre de progression apparaît
- ✅ La chanson s'affiche après génération
- ✅ Le lecteur audio fonctionne
- ✅ Le téléchargement est possible
- ✅ Les chansons apparaissent dans la playlist

## 📞 Support

En cas de problème :

1. **Vérifiez les logs WordPress** : `/wp-content/debug.log`
2. **Créez une issue** : [GitHub Issues](https://github.com/WeAreReForm/suno-music-generator/issues)
3. **Contactez-nous** : hello@wearereform.fr

## 🎉 Nouveautés v2.0

- ✅ **Correction du bug d'affichage** dans la playlist
- ✅ **Interface moderne** avec animations
- ✅ **Brouillons automatiques** sauvegardés
- ✅ **Notifications améliorées**
- ✅ **Support mobile** optimisé

---

**Version 2.0 - Déployée le 21 août 2025**
**Développé par WeAreReForm**
