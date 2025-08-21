<?php
/**
 * Fichier de réactivation d'urgence pour Suno Music Generator
 * 
 * SI VOS SHORTCODES NE FONCTIONNENT PLUS :
 * 1. Uploadez ce fichier à la racine de WordPress
 * 2. Accédez à : https://votre-site.fr/suno-emergency-fix.php
 * 3. Supprimez ce fichier après utilisation
 */

// Chargement de WordPress
require_once('wp-load.php');

if (!current_user_can('manage_options')) {
    die('Accès réservé aux administrateurs');
}

// Début de la réparation
?>
<!DOCTYPE html>
<html>
<head>
    <title>Réparation d'urgence - Suno Music Generator</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 { color: #10b981; }
        .success { 
            background: #dcfce7; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0;
            border: 2px solid #10b981;
        }
        .error { 
            background: #fee2e2; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0;
            border: 2px solid #ef4444;
        }
        .info {
            background: #dbeafe;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 2px solid #3b82f6;
        }
        code {
            background: #e5e7eb;
            padding: 4px 8px;
            border-radius: 3px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #6366f1;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>🔧 Réparation d'urgence Suno Music Generator</h1>

<?php

// 1. Sauvegarder la clé API
$api_key = get_option('suno_api_key', '');
echo '<div class="info">🔑 Clé API ' . (!empty($api_key) ? 'sauvegardée' : 'non trouvée') . '</div>';

// 2. Rechercher le plugin dans le système
$plugin_dir = WP_PLUGIN_DIR . '/';
$found_plugins = array();

// Chercher tous les dossiers suno
$directories = glob($plugin_dir . '*suno*', GLOB_ONLYDIR);
foreach ($directories as $dir) {
    $plugin_name = basename($dir);
    
    // Chercher les fichiers PHP principaux possibles
    $possible_files = array(
        'suno-music-generator-v2.php',
        'suno-music-generator.php',
        'index.php',
        $plugin_name . '.php'
    );
    
    foreach ($possible_files as $file) {
        $full_path = $dir . '/' . $file;
        if (file_exists($full_path)) {
            $found_plugins[] = array(
                'dir' => $plugin_name,
                'file' => $file,
                'path' => $plugin_name . '/' . $file
            );
            break;
        }
    }
}

if (empty($found_plugins)) {
    echo '<div class="error">❌ Aucun plugin Suno trouvé ! Vous devez le réinstaller.</div>';
    echo '<a href="https://github.com/WeAreReForm/suno-music-generator/archive/refs/heads/main.zip" class="button">Télécharger le plugin</a>';
} else {
    echo '<div class="info">📁 Plugin(s) trouvé(s) :</div>';
    echo '<ul>';
    foreach ($found_plugins as $plugin) {
        echo '<li>Dossier : <code>' . $plugin['dir'] . '</code> | Fichier : <code>' . $plugin['file'] . '</code></li>';
    }
    echo '</ul>';
    
    // 3. Désactiver tous les plugins Suno
    $active_plugins = get_option('active_plugins', array());
    $cleaned_plugins = array();
    
    foreach ($active_plugins as $active) {
        if (strpos($active, 'suno') === false) {
            $cleaned_plugins[] = $active;
        }
    }
    
    // 4. Activer le bon plugin (v2)
    $activated = false;
    foreach ($found_plugins as $plugin) {
        if ($plugin['file'] === 'suno-music-generator-v2.php') {
            $cleaned_plugins[] = $plugin['path'];
            $activated = true;
            echo '<div class="success">✅ Plugin v2 activé : <code>' . $plugin['path'] . '</code></div>';
            break;
        }
    }
    
    // Si pas de v2, activer le premier trouvé
    if (!$activated && !empty($found_plugins)) {
        $cleaned_plugins[] = $found_plugins[0]['path'];
        echo '<div class="success">✅ Plugin activé : <code>' . $found_plugins[0]['path'] . '</code></div>';
    }
    
    // 5. Mettre à jour les plugins actifs
    update_option('active_plugins', $cleaned_plugins);
    
    // 6. Restaurer la clé API
    if (!empty($api_key)) {
        update_option('suno_api_key', $api_key);
        echo '<div class="success">✅ Clé API restaurée</div>';
    }
    
    // 7. Vider le cache
    wp_cache_flush();
    echo '<div class="success">✅ Cache vidé</div>';
    
    // 8. Créer/vérifier la table
    global $wpdb;
    $table_name = $wpdb->prefix . 'suno_generations';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    
    if (!$table_exists) {
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT 0,
            task_id varchar(255) NOT NULL,
            prompt text NOT NULL,
            style varchar(255) DEFAULT '',
            title varchar(255) DEFAULT '',
            lyrics text DEFAULT '',
            status varchar(50) DEFAULT 'pending',
            audio_url varchar(500) DEFAULT '',
            video_url varchar(500) DEFAULT '',
            image_url varchar(500) DEFAULT '',
            duration int DEFAULT 0,
            model_name varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        echo '<div class="success">✅ Table de base de données créée</div>';
    } else {
        echo '<div class="success">✅ Table de base de données OK</div>';
    }
}

// 9. Test des shortcodes
echo '<div class="info">';
echo '<h3>Test des shortcodes :</h3>';

// Vérifier si les shortcodes sont enregistrés
global $shortcode_tags;
$suno_shortcodes = array('suno_music_form', 'suno_music_player', 'suno_test_api', 'suno_maintenance');
$working = array();
$missing = array();

foreach ($suno_shortcodes as $shortcode) {
    if (isset($shortcode_tags[$shortcode])) {
        $working[] = $shortcode;
    } else {
        $missing[] = $shortcode;
    }
}

if (!empty($working)) {
    echo '<p>✅ Shortcodes actifs : <code>[' . implode(']</code>, <code>[', $working) . ']</code></p>';
}

if (!empty($missing)) {
    echo '<p>❌ Shortcodes manquants : <code>[' . implode(']</code>, <code>[', $missing) . ']</code></p>';
    echo '<p>Le plugin n\'est peut-être pas complètement chargé. Essayez de :</p>';
    echo '<ol>';
    echo '<li>Aller dans Extensions > Extensions installées</li>';
    echo '<li>Désactiver puis réactiver Suno Music Generator</li>';
    echo '</ol>';
}

echo '</div>';

?>

    <div class="info" style="background: #fef3c7; border-color: #f59e0b;">
        <h3>⚠️ Actions finales :</h3>
        <ol>
            <li>Allez dans <a href="/wp-admin/plugins.php">Extensions > Extensions installées</a></li>
            <li>Vérifiez que "Suno Music Generator" est <strong>Activé</strong></li>
            <li>Si non, cliquez sur "Activer"</li>
            <li>Testez un shortcode sur une page</li>
            <li><strong>SUPPRIMEZ CE FICHIER</strong> : <code>suno-emergency-fix.php</code></li>
        </ol>
    </div>
    
    <div style="margin-top: 30px; text-align: center;">
        <a href="/wp-admin/plugins.php" class="button">→ Aller aux Extensions</a>
        <a href="/wp-admin/options-general.php?page=suno-music" class="button" style="background: #10b981;">→ Réglages Suno Music</a>
    </div>
    
    <div class="error" style="margin-top: 30px;">
        <strong>🔒 SÉCURITÉ : Supprimez ce fichier après utilisation !</strong><br>
        <code>rm <?php echo $_SERVER['DOCUMENT_ROOT']; ?>/suno-emergency-fix.php</code>
    </div>

</body>
</html>