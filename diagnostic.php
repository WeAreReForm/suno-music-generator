<?php
/**
 * Script de diagnostic pour Suno Music Generator v2.0
 * Uploadez ce fichier à la racine de WordPress et accédez-y via navigateur
 */

// Chargement de WordPress
require_once('wp-load.php');

// Vérifications
$checks = array();

// 1. Version PHP
$checks['PHP Version'] = array(
    'required' => '7.4+',
    'current' => PHP_VERSION,
    'status' => version_compare(PHP_VERSION, '7.4', '>=') ? '✅' : '❌'
);

// 2. Plugin installé
$plugin_path = WP_PLUGIN_DIR . '/suno-music-generator/';
$checks['Plugin Directory'] = array(
    'path' => $plugin_path,
    'exists' => file_exists($plugin_path) ? '✅' : '❌'
);

// 3. Fichier principal v2
$main_file = $plugin_path . 'suno-music-generator-v2.php';
$checks['Main File v2'] = array(
    'file' => 'suno-music-generator-v2.php',
    'exists' => file_exists($main_file) ? '✅' : '❌'
);

// 4. Assets
$checks['JavaScript v2'] = array(
    'file' => 'assets/suno-music-v2.js',
    'exists' => file_exists($plugin_path . 'assets/suno-music-v2.js') ? '✅' : '❌'
);

$checks['CSS v2'] = array(
    'file' => 'assets/suno-music-v2.css',
    'exists' => file_exists($plugin_path . 'assets/suno-music-v2.css') ? '✅' : '❌'
);

// 5. Plugin actif
$active_plugins = get_option('active_plugins');
$is_active = false;
foreach ($active_plugins as $plugin) {
    if (strpos($plugin, 'suno-music-generator') !== false) {
        $is_active = true;
        $active_file = $plugin;
        break;
    }
}

$checks['Plugin Active'] = array(
    'status' => $is_active ? '✅' : '❌',
    'file' => $active_file ?? 'Non trouvé'
);

// 6. Clé API
$api_key = get_option('suno_api_key', '');
$checks['API Key'] = array(
    'configured' => !empty($api_key) ? '✅' : '❌',
    'length' => strlen($api_key)
);

// 7. Table base de données
global $wpdb;
$table_name = $wpdb->prefix . 'suno_generations';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
$checks['Database Table'] = array(
    'name' => $table_name,
    'exists' => $table_exists ? '✅' : '❌'
);

// 8. Test API (si clé configurée)
if (!empty($api_key)) {
    $response = wp_remote_get('https://api.sunoapi.org/api/get_limit', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 10
    ));
    
    $api_status = '❌ Erreur';
    if (!is_wp_error($response)) {
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code === 200) {
            $api_status = '✅ Connecté';
        } else {
            $api_status = '❌ Code: ' . $status_code;
        }
    }
    
    $checks['API Connection'] = array(
        'status' => $api_status
    );
}

// Affichage
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnostic Suno Music Generator v2.0</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #6366f1;
            padding-bottom: 10px;
        }
        .check-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .check-name {
            font-weight: bold;
            color: #555;
        }
        .check-value {
            margin-left: 20px;
            color: #333;
        }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .fix-section {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            padding: 20px;
            margin-top: 30px;
            border-radius: 5px;
        }
        .fix-section h2 {
            color: #92400e;
            margin-top: 0;
        }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <h1>🔍 Diagnostic Suno Music Generator v2.0</h1>
    
    <?php foreach ($checks as $name => $check): ?>
    <div class="check-item">
        <span class="check-name"><?php echo $name; ?>:</span>
        <div class="check-value">
            <?php foreach ($check as $key => $value): ?>
                <div><?php echo $key; ?>: <?php echo $value; ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    
    <div class="fix-section">
        <h2>🔧 Actions recommandées</h2>
        
        <?php if (!$is_active): ?>
        <p>⚠️ <strong>Le plugin n'est pas actif !</strong></p>
        <p>Activez-le dans Extensions > Extensions installées</p>
        <?php endif; ?>
        
        <?php if (!file_exists($main_file)): ?>
        <p>⚠️ <strong>Fichier principal v2 manquant !</strong></p>
        <p>Réinstallez le plugin depuis GitHub :</p>
        <code>git clone https://github.com/WeAreReForm/suno-music-generator.git</code>
        <?php endif; ?>
        
        <?php if (empty($api_key)): ?>
        <p>⚠️ <strong>Clé API non configurée !</strong></p>
        <p>Allez dans Réglages > Suno Music et ajoutez votre clé API</p>
        <?php endif; ?>
        
        <?php if (!$table_exists): ?>
        <p>⚠️ <strong>Table de base de données manquante !</strong></p>
        <p>Désactivez et réactivez le plugin pour créer la table</p>
        <?php endif; ?>
        
        <?php if ($is_active && file_exists($main_file) && !empty($api_key) && $table_exists): ?>
        <p class="success">✅ <strong>Tout semble correctement configuré !</strong></p>
        <p>Si vous avez toujours des erreurs, vérifiez :</p>
        <ul>
            <li>Les permissions des fichiers (644 pour PHP, 755 pour dossiers)</li>
            <li>Le cache WordPress (videz-le)</li>
            <li>Les logs d'erreur PHP</li>
        </ul>
        <?php endif; ?>
    </div>
    
    <div style="margin-top: 30px; padding: 20px; background: #e0f2fe; border-radius: 5px;">
        <h3>📝 Informations système</h3>
        <p>WordPress: <?php echo get_bloginfo('version'); ?></p>
        <p>PHP: <?php echo PHP_VERSION; ?></p>
        <p>Serveur: <?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
        <p>URL du site: <?php echo get_site_url(); ?></p>
    </div>
</body>
</html>
