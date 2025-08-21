<?php
/**
 * Script de nettoyage complet pour Suno Music Generator
 * ATTENTION : Ce script va nettoyer toutes les traces de l'ancien plugin
 * 
 * Utilisation :
 * 1. Uploadez ce fichier à la racine de WordPress
 * 2. Accédez à : https://votre-site.fr/cleanup-suno.php
 * 3. Suivez les instructions
 * 4. SUPPRIMEZ ce fichier après utilisation
 */

// Sécurité
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Nettoyage Suno Music Generator</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 50px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            h1 { color: #ef4444; }
            .warning {
                background: #fee2e2;
                border: 2px solid #ef4444;
                padding: 20px;
                border-radius: 5px;
                margin: 20px 0;
            }
            .button {
                display: inline-block;
                padding: 12px 24px;
                background: #ef4444;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 10px 5px;
            }
            .button.safe {
                background: #10b981;
            }
            code {
                background: #e5e7eb;
                padding: 2px 6px;
                border-radius: 3px;
            }
        </style>
    </head>
    <body>
        <h1>⚠️ Nettoyage Suno Music Generator</h1>
        
        <div class="warning">
            <h2>ATTENTION !</h2>
            <p>Ce script va :</p>
            <ul>
                <li>Désactiver TOUTES les versions du plugin Suno</li>
                <li>Nettoyer les options WordPress</li>
                <li>Supprimer les transients</li>
                <li>Conserver la table des générations</li>
                <li>Conserver votre clé API</li>
            </ul>
        </div>
        
        <h3>Êtes-vous sûr de vouloir continuer ?</h3>
        
        <a href="?confirm=yes" class="button">⚠️ OUI, nettoyer maintenant</a>
        <a href="/" class="button safe">❌ NON, annuler</a>
        
        <div style="margin-top: 40px; padding: 20px; background: #e0f2fe; border-radius: 5px;">
            <h3>📝 Après le nettoyage :</h3>
            <ol>
                <li>Supprimez TOUS les dossiers suno-music-generator*</li>
                <li>Installez la version fraîche depuis GitHub</li>
                <li>Réactivez le plugin dans WordPress</li>
            </ol>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Chargement de WordPress
require_once('wp-load.php');

// Début du nettoyage
$results = array();

// 1. Récupération de la clé API actuelle (pour la sauvegarder)
$saved_api_key = get_option('suno_api_key', '');
$results['API Key Saved'] = !empty($saved_api_key) ? '✅ Clé API sauvegardée' : '⚠️ Pas de clé API trouvée';

// 2. Désactivation de TOUTES les versions du plugin
$active_plugins = get_option('active_plugins', array());
$original_count = count($active_plugins);
$cleaned_plugins = array();

foreach ($active_plugins as $plugin) {
    // Retirer toutes les versions de suno-music-generator
    if (strpos($plugin, 'suno-music-generator') === false) {
        $cleaned_plugins[] = $plugin;
    }
}

update_option('active_plugins', $cleaned_plugins);
$removed_count = $original_count - count($cleaned_plugins);
$results['Plugins Deactivated'] = "✅ $removed_count plugin(s) Suno désactivé(s)";

// 3. Nettoyage des options WordPress
$options_to_clean = array(
    'suno_music_generator_version',
    'suno_music_generator_db_version',
    'suno_music_settings',
    'suno_music_cache',
    '_transient_suno_*',
    '_site_transient_suno_*'
);

$cleaned_options = 0;
global $wpdb;

// Supprimer les options spécifiques
foreach ($options_to_clean as $option) {
    if (strpos($option, '*') !== false) {
        // Pour les patterns avec wildcard
        $pattern = str_replace('*', '%', $option);
        $query = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $pattern
        );
        $wpdb->query($query);
    } else {
        // Pour les options exactes
        if (delete_option($option)) {
            $cleaned_options++;
        }
    }
}

// Nettoyer aussi les transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_suno_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_suno_%'");

$results['Options Cleaned'] = "✅ Options WordPress nettoyées";

// 4. Vérification des dossiers de plugin
$plugin_dir = WP_PLUGIN_DIR . '/';
$suno_folders = array();

if ($handle = opendir($plugin_dir)) {
    while (false !== ($entry = readdir($handle))) {
        if (strpos($entry, 'suno-music-generator') !== false) {
            $suno_folders[] = $entry;
        }
    }
    closedir($handle);
}

$results['Plugin Folders Found'] = count($suno_folders) > 0 
    ? "⚠️ " . count($suno_folders) . " dossier(s) trouvé(s): " . implode(', ', $suno_folders)
    : "✅ Aucun dossier Suno trouvé";

// 5. Nettoyage du cache WordPress
wp_cache_flush();
$results['Cache Cleared'] = "✅ Cache WordPress vidé";

// 6. Nettoyage des rewrites
flush_rewrite_rules();
$results['Rewrite Rules'] = "✅ Règles de réécriture réinitialisées";

// 7. Restauration de la clé API
if (!empty($saved_api_key)) {
    update_option('suno_api_key', $saved_api_key);
    $results['API Key Restored'] = "✅ Clé API restaurée";
}

// 8. Vérification de la table de base de données
$table_name = $wpdb->prefix . 'suno_generations';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
$results['Database Table'] = $table_exists 
    ? "✅ Table conservée avec " . $wpdb->get_var("SELECT COUNT(*) FROM $table_name") . " enregistrements"
    : "⚠️ Table non trouvée";

// Affichage des résultats
?>
<!DOCTYPE html>
<html>
<head>
    <title>Nettoyage terminé - Suno Music Generator</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 { 
            color: #10b981;
            border-bottom: 2px solid #10b981;
            padding-bottom: 10px;
        }
        .result-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { color: #10b981; }
        .warning { color: #f59e0b; }
        .error { color: #ef4444; }
        .next-steps {
            background: #f0fdf4;
            border: 2px solid #10b981;
            padding: 20px;
            margin-top: 30px;
            border-radius: 5px;
        }
        code {
            background: #e5e7eb;
            padding: 4px 8px;
            border-radius: 3px;
            display: block;
            margin: 10px 0;
            font-size: 14px;
        }
        .important {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h1>✅ Nettoyage terminé avec succès !</h1>
    
    <h2>📊 Résultats du nettoyage :</h2>
    <?php foreach ($results as $key => $value): ?>
    <div class="result-item">
        <strong><?php echo $key; ?>:</strong> <?php echo $value; ?>
    </div>
    <?php endforeach; ?>
    
    <?php if (!empty($saved_api_key)): ?>
    <div class="important">
        <h3>🔑 Votre clé API a été sauvegardée !</h3>
        <p>Clé : <?php echo substr($saved_api_key, 0, 10); ?>...<?php echo substr($saved_api_key, -4); ?></p>
        <p>Elle sera automatiquement restaurée lors de la réinstallation.</p>
    </div>
    <?php endif; ?>
    
    <div class="next-steps">
        <h2>📝 Prochaines étapes (IMPORTANT) :</h2>
        
        <h3>1. Supprimez TOUS les anciens dossiers :</h3>
        <code>
cd <?php echo WP_PLUGIN_DIR; ?>/<br>
rm -rf suno-music-generator*
        </code>
        
        <h3>2. Installez la version fraîche :</h3>
        <code>
git clone https://github.com/WeAreReForm/suno-music-generator.git
        </code>
        
        <h3>3. Définissez les permissions :</h3>
        <code>
chmod -R 755 suno-music-generator<br>
chmod 644 suno-music-generator/*.php
        </code>
        
        <h3>4. Dans WordPress Admin :</h3>
        <ul>
            <li>Allez dans <strong>Extensions > Extensions installées</strong></li>
            <li>Cherchez <strong>"Suno Music Generator"</strong></li>
            <li>Cliquez sur <strong>"Activer"</strong></li>
            <li>Vérifiez dans <strong>Réglages > Suno Music</strong> que votre clé API est présente</li>
        </ul>
        
        <h3>5. Testez avec :</h3>
        <code>[suno_test_api]</code>
    </div>
    
    <div class="important">
        <h3>⚠️ SÉCURITÉ :</h3>
        <p><strong>SUPPRIMEZ CE FICHIER MAINTENANT !</strong></p>
        <code>rm <?php echo $_SERVER['DOCUMENT_ROOT']; ?>/cleanup-suno.php</code>
    </div>
    
    <div style="margin-top: 30px; text-align: center;">
        <p>
            <a href="/wp-admin/plugins.php" style="padding: 12px 24px; background: #6366f1; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">
                → Aller aux Extensions WordPress
            </a>
        </p>
    </div>
</body>
</html>

<?php
// Log pour debug
error_log('Suno Music Generator Cleanup completed at ' . date('Y-m-d H:i:s'));
error_log('API Key saved: ' . (!empty($saved_api_key) ? 'Yes' : 'No'));
error_log('Plugins deactivated: ' . $removed_count);
error_log('Folders found: ' . implode(', ', $suno_folders));
?>