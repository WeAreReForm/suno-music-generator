<?php
/**
 * Classe principale du plugin Suno Music Generator
 * 
 * Orchestrateur central qui initialise et coordonne tous les modules
 * 
 * @package SunoMusicGenerator
 * @version 1.10.5
 * @author WeAreReForm
 */

// Sécurité
if (!defined('ABSPATH')) {
    exit('Accès direct interdit.');
}

class Suno_Core {
    
    /**
     * Version du plugin
     */
    const VERSION = '1.10.5';
    
    /**
     * Modules du plugin
     */
    private $api;
    private $database;
    private $admin;
    private $shortcodes;
    private $ajax;
    private $debug;
    
    /**
     * Configuration du plugin
     */
    private $config = array();
    
    /**
     * Instance unique (Singleton)
     */
    private static $instance = null;
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Empêcher la création de multiples instances
        if (self::$instance !== null) {
            return self::$instance;
        }
        
        self::$instance = $this;
        
        // Initialisation
        $this->load_config();
        $this->init_hooks();
        $this->init_modules();
        
        // Log d'initialisation
        error_log('SUNO CORE: Initialized v' . self::VERSION);
    }
    
    /**
     * Récupération de l'instance (Singleton)
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Chargement de la configuration
     */
    private function load_config() {
        $this->config = array(
            'api_base_url' => 'https://apibox.erweima.ai',
            'max_checks' => 20,
            'timeout_minutes' => 10,
            'check_interval' => 5000, // ms
            'supported_formats' => array('mp3', 'wav'),
            'max_prompt_length' => 500,
            'default_style' => '',
            'cache_duration' => 3600, // 1 heure
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG
        );
        
        // Permettre la personnalisation via filtres
        $this->config = apply_filters('suno_plugin_config', $this->config);
    }
    
    /**
     * Initialisation des hooks WordPress
     */
    private function init_hooks() {
        // Actions principales
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Actions d'installation/mise à jour
        add_action('admin_init', array($this, 'check_version'));
        
        // Hooks de nettoyage
        add_action('wp_scheduled_delete', array($this, 'cleanup_old_data'));
        
        // Hooks de sécurité
        add_action('wp_ajax_nopriv_suno_security_check', array($this, 'security_check'));
        
        // Action personnalisée pour d'autres plugins
        do_action('suno_core_hooks_loaded', $this);
    }
    
    /**
     * Initialisation des modules
     */
    private function init_modules() {
        try {
            // Base de données (priorité haute)
            $this->database = new Suno_Database();
            
            // API (dépend de la config)
            $this->api = new Suno_Api($this->config);
            
            // Admin (backend)
            if (is_admin()) {
                $this->admin = new Suno_Admin($this->database, $this->api);
            }
            
            // Shortcodes (frontend)
            $this->shortcodes = new Suno_Shortcodes($this->database, $this->api);
            
            // AJAX (frontend + backend)
            $this->ajax = new Suno_Ajax($this->database, $this->api);
            
            // Debug (conditionnel)
            if ($this->config['debug_mode'] || current_user_can('manage_options')) {
                $this->debug = new Suno_Debug($this->database, $this->api);
            }
            
            error_log('SUNO CORE: All modules loaded successfully');
            
        } catch (Exception $e) {
            error_log('SUNO CORE ERROR: Failed to load modules - ' . $e->getMessage());
            
            // Notification admin en cas d'erreur
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error">';
                echo '<p><strong>Suno Music Generator</strong> - Erreur de chargement : ' . esc_html($e->getMessage()) . '</p>';
                echo '</div>';
            });
        }
    }
    
    /**
     * Initialisation WordPress
     */
    public function init() {
        // Vérification des capacités utilisateur si nécessaire
        $this->setup_capabilities();
        
        // Action pour d'autres plugins
        do_action('suno_plugin_loaded', $this);
    }
    
    /**
     * Enregistrement des scripts frontend
     */
    public function enqueue_scripts() {
        // CSS principal
        wp_enqueue_style(
            'suno-music-css',
            SUNO_ASSETS_URL . 'css/suno-music.css',
            array(),
            self::VERSION
        );
        
        // JavaScript principal
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'suno-music-js',
            SUNO_ASSETS_URL . 'js/suno-music.js',
            array('jquery'),
            self::VERSION,
            true
        );
        
        // Variables JavaScript
        wp_localize_script('suno-music-js', 'sunoAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('suno_music_nonce'),
            'maxChecks' => $this->config['max_checks'],
            'checkInterval' => $this->config['check_interval'],
            'version' => self::VERSION,
            'debugMode' => $this->config['debug_mode']
        ));
    }
    
    /**
     * Enregistrement des scripts admin
     */
    public function enqueue_admin_scripts($hook) {
        // Seulement sur les pages du plugin
        $plugin_pages = array('settings_page_suno-music');
        
        if (!in_array($hook, $plugin_pages)) {
            return;
        }
        
        wp_enqueue_style(
            'suno-admin-css',
            SUNO_ASSETS_URL . 'css/suno-admin.css',
            array(),
            self::VERSION
        );
        
        wp_enqueue_script(
            'suno-admin-js',
            SUNO_ASSETS_URL . 'js/suno-admin.js',
            array('jquery'),
            self::VERSION,
            true
        );
    }
    
    /**
     * Vérification de version et migrations
     */
    public function check_version() {
        $installed_version = get_option('suno_plugin_version', '0.0.0');
        
        if (version_compare($installed_version, self::VERSION, '<')) {
            $this->run_migrations($installed_version, self::VERSION);
            update_option('suno_plugin_version', self::VERSION);
            
            error_log("SUNO CORE: Updated from {$installed_version} to " . self::VERSION);
        }
    }
    
    /**
     * Exécution des migrations
     */
    private function run_migrations($from_version, $to_version) {
        // Migration vers 1.10.5 (structure modulaire)
        if (version_compare($from_version, '1.10.5', '<')) {
            // Vérifier l'intégrité de la base de données
            if ($this->database) {
                $this->database->verify_table_structure();
            }
            
            // Nettoyer les anciennes options si nécessaire
            delete_transient('suno_api_test_cache');
        }
        
        // Futures migrations ici...
        
        do_action('suno_plugin_migrated', $from_version, $to_version);
    }
    
    /**
     * Configuration des capacités utilisateur
     */
    private function setup_capabilities() {
        // Pour l'instant, utilise les capacités WordPress standard
        // Peut être étendu pour des rôles personnalisés
    }
    
    /**
     * Nettoyage des anciennes données
     */
    public function cleanup_old_data() {
        if ($this->database) {
            $this->database->cleanup_old_generations();
        }
    }
    
    /**
     * Vérification de sécurité
     */
    public function security_check() {
        wp_send_json_success(array(
            'version' => self::VERSION,
            'timestamp' => time(),
            'nonce' => wp_create_nonce('suno_security_' . time())
        ));
    }
    
    /**
     * Getters pour accéder aux modules
     */
    public function get_api() {
        return $this->api;
    }
    
    public function get_database() {
        return $this->database;
    }
    
    public function get_admin() {
        return $this->admin;
    }
    
    public function get_shortcodes() {
        return $this->shortcodes;
    }
    
    public function get_ajax() {
        return $this->ajax;
    }
    
    public function get_debug() {
        return $this->debug;
    }
    
    public function get_config($key = null) {
        if ($key === null) {
            return $this->config;
        }
        
        return isset($this->config[$key]) ? $this->config[$key] : null;
    }
    
    /**
     * Information sur le plugin
     */
    public function get_plugin_info() {
        return array(
            'version' => self::VERSION,
            'name' => 'Suno Music Generator',
            'path' => SUNO_PLUGIN_PATH,
            'url' => SUNO_PLUGIN_URL,
            'modules_count' => count(array_filter(array(
                $this->api,
                $this->database,
                $this->admin,
                $this->shortcodes,
                $this->ajax,
                $this->debug
            ))),
            'config' => $this->config
        );
    }
}