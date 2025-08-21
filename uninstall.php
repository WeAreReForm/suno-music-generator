<?php
/**
 * Désinstallation du plugin Suno Music Generator
 */

// Sécurité
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Supprimer les options
delete_option('suno_api_key_v5');
delete_option('suno_plugin_version');

// Supprimer la table
global $wpdb;
$table_name = $wpdb->prefix . 'suno_music';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Nettoyer les transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_%suno%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_site_transient_%suno%'");