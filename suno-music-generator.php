<?php
/**
 * Plugin Name: Suno Music Generator
 * Plugin URI: https://github.com/WeAreReForm/suno-music-generator
 * Description: Générateur de musique IA avec Suno via formulaire WordPress - Architecture modulaire
 * Version: 1.10.5
 * Author: WeAreReForm
 * Author URI: https://github.com/WeAreReForm
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: suno-music-generator
 * Domain Path: /languages/
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
 */

// Sécurité - Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit('Accès direct interdit.');
}

/**
 * Constantes du plugin
 */
define('SUNO_PLUGIN_VERSION', '1.10.5');
define('SUNO_PLUGIN_FILE', __FILE__);
define('SUNO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SUNO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SUNO_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SUNO_INCLUDES_PATH', SUNO_PLUGIN_PATH . 'includes/');
define('SUNO_ASSETS_URL', SUNO_PLUGIN_URL . 'assets/');
define('SUNO_TEMPLATES_PATH', SUNO_PLUGIN_PATH . 'templates/');

/**
 * Autoloader simple pour les classes du plugin
 */
spl_autoload_register(function ($class_name) {
    // Préfixe des classes du plugin
    if (strpos($class_name, 'Suno_') !== 0) {
        return;
    }
    
    // Conversion du nom de classe en nom de fichier
    $class_file = strtolower(str_replace('_', '-', $class_name));
    $file_path = SUNO_INCLUDES_PATH . 'class-' . $class_file . '.php';
    
    if (file_exists($file_path)) {
        require_once $file_path;
    }
});

/**
 * Vérification des prérequis système
 */
function suno_check_requirements() {
    $errors = array();
    
    // Vérification PHP
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        $errors[] = sprintf(
            __('Ce plugin nécessite PHP 7.4 ou supérieur. Version actuelle : %s', 'suno-music-generator'),
            PHP_VERSION
        );
    }
    
    // Vérification WordPress
    global $wp_version;
    if (version_compare($wp_version, '5.0', '<')) {
        $errors[] = sprintf(
            __('Ce plugin nécessite WordPress 5.0 ou supérieur. Version actuelle : %s', 'suno-music-generator'),
            $wp_version
        );
    }
    
    // Vérification extensions PHP
    $required_extensions = array('curl', 'json');
    foreach ($required_extensions as $extension) {
        if (!extension_loaded($extension)) {
            $errors[] = sprintf(
                __('Extension PHP manquante : %s', 'suno-music-generator'),
                $extension
            );
        }
    }
    
    return $errors;
}

/**
 * Affichage des erreurs de prérequis
 */
function suno_requirements_notice() {
    $errors = suno_check_requirements();
    if (!empty($errors)) {
        echo '<div class="notice notice-error">';
        echo '<p><strong>Suno Music Generator</strong> - Erreurs de prérequis :</p>';
        echo '<ul>';
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
}

/**
 * Initialisation du plugin
 */
function suno_init_plugin() {
    // Vérifier les prérequis
    $errors = suno_check_requirements();
    if (!empty($errors)) {
        add_action('admin_notices', 'suno_requirements_notice');
        return;
    }
    
    // Charger la classe principale
    if (class_exists('Suno_Core')) {
        $suno_plugin = new Suno_Core();
        
        // Hook global pour accéder à l'instance
        $GLOBALS['suno_music_generator'] = $suno_plugin;
        
        // Logs d'initialisation
        error_log('=== SUNO MUSIC GENERATOR v' . SUNO_PLUGIN_VERSION . ' INITIALIZED (MODULAR) ===');
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error">';
            echo '<p><strong>Suno Music Generator</strong> - Erreur : Classe principale introuvable.</p>';
            echo '</div>';
        });
    }
}

/**
 * Hooks d'activation/désactivation
 */
register_activation_hook(__FILE__, function() {
    error_log('SUNO PLUGIN ACTIVATED - v' . SUNO_PLUGIN_VERSION);
    
    // Créer les tables si nécessaire
    if (class_exists('Suno_Database')) {
        $database = new Suno_Database();
        $database->create_tables();
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Option pour indiquer la première activation
    add_option('suno_plugin_activated', time());
});

register_deactivation_hook(__FILE__, function() {
    error_log('SUNO PLUGIN DEACTIVATED - v' . SUNO_PLUGIN_VERSION);
    
    // Flush rewrite rules
    flush_rewrite_rules();
});

/**
 * Hook de désinstallation (si nécessaire)
 */
register_uninstall_hook(__FILE__, function() {
    // Note: La désinstallation complète pourrait être optionnelle
    // pour préserver les données utilisateur
    error_log('SUNO PLUGIN UNINSTALLED - v' . SUNO_PLUGIN_VERSION);
});

/**
 * Fonction d'accès global à l'instance du plugin
 */
function suno_music_generator() {
    return isset($GLOBALS['suno_music_generator']) ? $GLOBALS['suno_music_generator'] : null;
}

/**
 * Chargement des traductions
 */
add_action('plugins_loaded', function() {
    load_plugin_textdomain(
        'suno-music-generator',
        false,
        dirname(SUNO_PLUGIN_BASENAME) . '/languages/'
    );
});

/**
 * Initialisation après le chargement de WordPress
 */
add_action('plugins_loaded', 'suno_init_plugin');

/**
 * Ajout de liens dans la liste des plugins
 */
add_filter('plugin_action_links_' . SUNO_PLUGIN_BASENAME, function($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=suno-music') . '">' . __('Réglages', 'suno-music-generator') . '</a>';
    $github_link = '<a href="https://github.com/WeAreReForm/suno-music-generator" target="_blank">' . __('GitHub', 'suno-music-generator') . '</a>';
    
    array_unshift($links, $settings_link);
    $links[] = $github_link;
    
    return $links;
});

/**
 * Ajout de métadonnées dans la liste des plugins
 */
add_filter('plugin_row_meta', function($links, $file) {
    if ($file === SUNO_PLUGIN_BASENAME) {
        $links[] = '<a href="https://github.com/WeAreReForm/suno-music-generator/issues" target="_blank">' . __('Support', 'suno-music-generator') . '</a>';
        $links[] = '<a href="https://github.com/WeAreReForm/suno-music-generator/blob/main/docs/API.md" target="_blank">' . __('Documentation', 'suno-music-generator') . '</a>';
    }
    return $links;
}, 10, 2);

// Hook de compatibilité pour les anciens appels
if (!function_exists('suno_get_instance')) {
    function suno_get_instance() {
        return suno_music_generator();
    }
}