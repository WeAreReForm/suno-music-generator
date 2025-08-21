<?php
/**
 * Plugin Name: Suno Music Generator
 * Plugin URI: https://github.com/WeAreReForm/suno-music-generator
 * Description: Générateur de musique IA professionnel avec Suno API
 * Version: 5.0.0
 * Author: WeAreReForm
 * Author URI: https://parcoursmetiersbtp.fr
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: suno-music-generator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Sécurité : Bloquer l'accès direct
if (!defined('ABSPATH')) {
    exit('Accès direct interdit');
}

// Définir les constantes du plugin
define('SUNO_VERSION', '5.0.0');
define('SUNO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SUNO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SUNO_PLUGIN_FILE', __FILE__);
define('SUNO_API_BASE_URL', 'https://api.sunoapi.org');

/**
 * Classe principale du plugin Suno Music Generator
 * 
 * @since 5.0.0
 */
final class SunoMusicGeneratorV5 {
    
    /**
     * Instance unique du plugin
     * @var SunoMusicGeneratorV5
     */
    private static $instance = null;
    
    /**
     * Clé API
     * @var string
     */
    private $api_key;
    
    /**
     * Configuration du plugin
     * @var array
     */
    private $config = [
        'max_prompt_length' => 500,
        'max_lyrics_length' => 3000,
        'cache_duration' => 3600,
        'polling_interval' => 3000,
        'max_polling_attempts' => 40
    ];
    
    /**
     * Obtenir l'instance unique du plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur privé pour singleton
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialisation des hooks WordPress
     */
    private function init_hooks() {
        // Hooks d'activation/désactivation
        register_activation_hook(SUNO_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(SUNO_PLUGIN_FILE, [$this, 'deactivate']);
        
        // Hooks d'initialisation
        add_action('init', [$this, 'init']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        
        // Scripts et styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        
        // Shortcodes
        add_action('init', [$this, 'register_shortcodes']);
        
        // AJAX handlers
        add_action('wp_ajax_suno_generate_music', [$this, 'ajax_generate_music']);
        add_action('wp_ajax_nopriv_suno_generate_music', [$this, 'ajax_generate_music']);
        add_action('wp_ajax_suno_check_status', [$this, 'ajax_check_status']);
        add_action('wp_ajax_nopriv_suno_check_status', [$this, 'ajax_check_status']);
        add_action('wp_ajax_suno_get_history', [$this, 'ajax_get_history']);
        add_action('wp_ajax_nopriv_suno_get_history', [$this, 'ajax_get_history']);
        
        // Admin
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
        
        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    /**
     * Initialisation du plugin
     */
    public function init() {
        $this->api_key = get_option('suno_api_key', '');
        
        // Vérifier les mises à jour de la base de données
        $this->maybe_update_database();
    }
    
    /**
     * Activation du plugin
     */
    public function activate() {
        $this->create_database_tables();
        $this->create_default_options();
        
        // Créer les pages par défaut
        $this->create_default_pages();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Désactivation du plugin
     */
    public function deactivate() {
        // Nettoyer les transients
        $this->cleanup_transients();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Créer les tables de base de données
     */
    private function create_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'suno_generations';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT 0,
            task_id varchar(255) NOT NULL,
            prompt text NOT NULL,
            style varchar(100) DEFAULT '',
            title varchar(255) DEFAULT '',
            lyrics text DEFAULT '',
            tags varchar(500) DEFAULT '',
            instrumental tinyint(1) DEFAULT 0,
            status varchar(50) DEFAULT 'pending',
            progress int DEFAULT 0,
            audio_url varchar(500) DEFAULT '',
            video_url varchar(500) DEFAULT '',
            image_url varchar(500) DEFAULT '',
            model_name varchar(100) DEFAULT '',
            duration int DEFAULT 0,
            api_response longtext DEFAULT '',
            error_message text DEFAULT '',
            play_count int DEFAULT 0,
            like_count int DEFAULT 0,
            share_count int DEFAULT 0,
            is_public tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY is_public (is_public)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Table pour les likes
        $likes_table = $wpdb->prefix . 'suno_likes';
        $sql_likes = "CREATE TABLE $likes_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            generation_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_like (generation_id, user_id),
            KEY generation_id (generation_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($sql_likes);
        
        // Table pour les playlists
        $playlists_table = $wpdb->prefix . 'suno_playlists';
        $sql_playlists = "CREATE TABLE $playlists_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            description text DEFAULT '',
            is_public tinyint(1) DEFAULT 0,
            song_ids text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($sql_playlists);
        
        update_option('suno_db_version', '5.0.0');
    }
    
    /**
     * Créer les options par défaut
     */
    private function create_default_options() {
        add_option('suno_api_key', '');
        add_option('suno_default_style', 'auto');
        add_option('suno_enable_public_gallery', true);
        add_option('suno_enable_user_profiles', true);
        add_option('suno_max_generations_per_day', 10);
        add_option('suno_enable_cache', true);
        add_option('suno_notification_email', get_option('admin_email'));
        add_option('suno_terms_url', '');
        add_option('suno_privacy_url', '');
    }
    
    /**
     * Créer les pages par défaut
     */
    private function create_default_pages() {
        $pages = [
            'suno-generator' => [
                'title' => 'Générateur de Musique',
                'content' => '[suno_music_generator]'
            ],
            'suno-gallery' => [
                'title' => 'Galerie Musicale',
                'content' => '[suno_gallery]'
            ],
            'suno-my-music' => [
                'title' => 'Ma Musique',
                'content' => '[suno_my_music]'
            ]
        ];
        
        foreach ($pages as $slug => $page) {
            if (!get_page_by_path($slug)) {
                wp_insert_post([
                    'post_title' => $page['title'],
                    'post_content' => $page['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $slug
                ]);
            }
        }
    }
    
    /**
     * Charger les fichiers de traduction
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'suno-music-generator',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Enregistrer les shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('suno_music_generator', [$this, 'shortcode_generator']);
        add_shortcode('suno_gallery', [$this, 'shortcode_gallery']);
        add_shortcode('suno_my_music', [$this, 'shortcode_my_music']);
        add_shortcode('suno_player', [$this, 'shortcode_player']);
        add_shortcode('suno_test_api', [$this, 'shortcode_test_api']);
    }
    
    /**
     * Charger les scripts et styles frontend
     */
    public function enqueue_scripts() {
        // CSS
        wp_enqueue_style(
            'suno-music-generator',
            SUNO_PLUGIN_URL . 'assets/css/suno-music.css',
            [],
            SUNO_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'suno-music-generator',
            SUNO_PLUGIN_URL . 'assets/js/suno-music.js',
            ['jquery'],
            SUNO_VERSION,
            true
        );
        
        // Localisation
        wp_localize_script('suno-music-generator', 'suno_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('suno_music_nonce'),
            'api_url' => home_url('/wp-json/suno/v1/'),
            'strings' => [
                'generating' => __('Génération en cours...', 'suno-music-generator'),
                'error' => __('Une erreur est survenue', 'suno-music-generator'),
                'success' => __('Chanson générée avec succès!', 'suno-music-generator'),
                'confirm_delete' => __('Êtes-vous sûr de vouloir supprimer cette chanson?', 'suno-music-generator')
            ],
            'config' => [
                'polling_interval' => $this->config['polling_interval'],
                'max_polling_attempts' => $this->config['max_polling_attempts']
            ]
        ]);
    }
    
    /**
     * Charger les scripts admin
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'suno-music') === false) {
            return;
        }
        
        wp_enqueue_style(
            'suno-admin',
            SUNO_PLUGIN_URL . 'assets/css/suno-admin.css',
            [],
            SUNO_VERSION
        );
        
        wp_enqueue_script(
            'suno-admin',
            SUNO_PLUGIN_URL . 'assets/js/suno-admin.js',
            ['jquery', 'wp-color-picker'],
            SUNO_VERSION,
            true
        );
        
        wp_enqueue_style('wp-color-picker');
    }
    
    /**
     * Menu admin
     */
    public function admin_menu() {
        add_menu_page(
            __('Suno Music', 'suno-music-generator'),
            __('Suno Music', 'suno-music-generator'),
            'manage_options',
            'suno-music',
            [$this, 'admin_dashboard'],
            'dashicons-format-audio',
            30
        );
        
        add_submenu_page(
            'suno-music',
            __('Tableau de bord', 'suno-music-generator'),
            __('Tableau de bord', 'suno-music-generator'),
            'manage_options',
            'suno-music',
            [$this, 'admin_dashboard']
        );
        
        add_submenu_page(
            'suno-music',
            __('Générations', 'suno-music-generator'),
            __('Générations', 'suno-music-generator'),
            'manage_options',
            'suno-generations',
            [$this, 'admin_generations']
        );
        
        add_submenu_page(
            'suno-music',
            __('Paramètres', 'suno-music-generator'),
            __('Paramètres', 'suno-music-generator'),
            'manage_options',
            'suno-settings',
            [$this, 'admin_settings']
        );
        
        add_submenu_page(
            'suno-music',
            __('Outils', 'suno-music-generator'),
            __('Outils', 'suno-music-generator'),
            'manage_options',
            'suno-tools',
            [$this, 'admin_tools']
        );
    }
    
    /**
     * Admin init
     */
    public function admin_init() {
        register_setting('suno_settings', 'suno_api_key');
        register_setting('suno_settings', 'suno_default_style');
        register_setting('suno_settings', 'suno_enable_public_gallery');
        register_setting('suno_settings', 'suno_enable_user_profiles');
        register_setting('suno_settings', 'suno_max_generations_per_day');
    }
    
    /**
     * Page tableau de bord admin
     */
    public function admin_dashboard() {
        include SUNO_PLUGIN_DIR . 'includes/admin/dashboard.php';
    }
    
    /**
     * Page des générations admin
     */
    public function admin_generations() {
        include SUNO_PLUGIN_DIR . 'includes/admin/generations.php';
    }
    
    /**
     * Page des paramètres admin
     */
    public function admin_settings() {
        include SUNO_PLUGIN_DIR . 'includes/admin/settings.php';
    }
    
    /**
     * Page des outils admin
     */
    public function admin_tools() {
        include SUNO_PLUGIN_DIR . 'includes/admin/tools.php';
    }
    
    /**
     * Shortcode générateur principal
     */
    public function shortcode_generator($atts) {
        $atts = shortcode_atts([
            'styles' => 'all',
            'show_history' => 'true',
            'max_prompt' => $this->config['max_prompt_length'],
            'max_lyrics' => $this->config['max_lyrics_length']
        ], $atts);
        
        ob_start();
        include SUNO_PLUGIN_DIR . 'includes/shortcodes/generator.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode galerie
     */
    public function shortcode_gallery($atts) {
        $atts = shortcode_atts([
            'limit' => 12,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'style' => 'grid'
        ], $atts);
        
        ob_start();
        include SUNO_PLUGIN_DIR . 'includes/shortcodes/gallery.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode Ma Musique
     */
    public function shortcode_my_music($atts) {
        if (!is_user_logged_in()) {
            return '<div class="suno-notice">Vous devez être connecté pour voir vos créations.</div>';
        }
        
        $atts = shortcode_atts([
            'limit' => 20,
            'show_stats' => 'true'
        ], $atts);
        
        ob_start();
        include SUNO_PLUGIN_DIR . 'includes/shortcodes/my-music.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode player
     */
    public function shortcode_player($atts) {
        $atts = shortcode_atts([
            'id' => '',
            'autoplay' => 'false',
            'style' => 'default'
        ], $atts);
        
        if (empty($atts['id'])) {
            return '';
        }
        
        ob_start();
        include SUNO_PLUGIN_DIR . 'includes/shortcodes/player.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode test API
     */
    public function shortcode_test_api($atts) {
        if (!current_user_can('manage_options')) {
            return '<div class="suno-notice">Accès réservé aux administrateurs.</div>';
        }
        
        ob_start();
        include SUNO_PLUGIN_DIR . 'includes/shortcodes/test-api.php';
        return ob_get_clean();
    }
    
    /**
     * AJAX - Générer de la musique
     */
    public function ajax_generate_music() {
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        if (empty($this->api_key)) {
            wp_send_json_error(['message' => 'Clé API non configurée']);
        }
        
        // Vérifier les limites quotidiennes
        if (!$this->check_daily_limit()) {
            wp_send_json_error(['message' => 'Limite quotidienne atteinte']);
        }
        
        $prompt = sanitize_textarea_field($_POST['prompt'] ?? '');
        $style = sanitize_text_field($_POST['style'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $lyrics = sanitize_textarea_field($_POST['lyrics'] ?? '');
        $tags = sanitize_text_field($_POST['tags'] ?? '');
        $instrumental = isset($_POST['instrumental']) && $_POST['instrumental'] === 'true';
        
        if (empty($prompt)) {
            wp_send_json_error(['message' => 'Description requise']);
        }
        
        // Préparer les données pour l'API
        $api_data = [
            'prompt' => $prompt,
            'customMode' => !empty($style) || !empty($title) || !empty($lyrics),
            'instrumental' => $instrumental,
            'wait' => false
        ];
        
        if (!empty($style)) {
            $api_data['style'] = $style;
        }
        
        if (!empty($title)) {
            $api_data['title'] = $title;
        }
        
        if (!empty($lyrics)) {
            $api_data['lyric'] = $lyrics;
        }
        
        if (!empty($tags)) {
            $api_data['tags'] = $tags;
        }
        
        // Appel API
        $response = wp_remote_post(SUNO_API_BASE_URL . '/api/v1/generate', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($api_data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Erreur de connexion à l\'API']);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 401) {
            wp_send_json_error(['message' => 'Clé API invalide']);
        }
        
        if ($status_code === 402) {
            wp_send_json_error(['message' => 'Crédits insuffisants']);
        }
        
        if ($status_code !== 200 && $status_code !== 201) {
            wp_send_json_error(['message' => 'Erreur API: ' . $status_code]);
        }
        
        $data = json_decode($body, true);
        
        // Extraire le task_id
        $task_id = $data['data']['taskId'] ?? $data['data']['task_id'] ?? $data['taskId'] ?? $data['id'] ?? null;
        
        if (!$task_id) {
            wp_send_json_error(['message' => 'Aucun ID de tâche retourné']);
        }
        
        // Sauvegarder en base de données
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $wpdb->insert($table_name, [
            'user_id' => get_current_user_id(),
            'task_id' => $task_id,
            'prompt' => $prompt,
            'style' => $style,
            'title' => $title,
            'lyrics' => $lyrics,
            'tags' => $tags,
            'instrumental' => $instrumental ? 1 : 0,
            'status' => 'pending',
            'api_response' => $body,
            'created_at' => current_time('mysql')
        ]);
        
        $generation_id = $wpdb->insert_id;
        
        wp_send_json_success([
            'task_id' => $task_id,
            'generation_id' => $generation_id,
            'message' => 'Génération lancée avec succès'
        ]);
    }
    
    /**
     * AJAX - Vérifier le statut
     */
    public function ajax_check_status() {
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $task_id = sanitize_text_field($_POST['task_id'] ?? '');
        $generation_id = intval($_POST['generation_id'] ?? 0);
        
        if (empty($task_id)) {
            wp_send_json_error(['message' => 'ID de tâche requis']);
        }
        
        // Vérifier d'abord en base de données
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $generation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE task_id = %s OR id = %d",
            $task_id,
            $generation_id
        ));
        
        if ($generation && $generation->status === 'completed' && $generation->audio_url) {
            wp_send_json_success([
                'status' => 'completed',
                'audio_url' => $generation->audio_url,
                'video_url' => $generation->video_url,
                'image_url' => $generation->image_url,
                'title' => $generation->title,
                'duration' => $generation->duration
            ]);
            return;
        }
        
        // Vérifier via l'API
        $endpoints = [
            '/api/v1/music/' . $task_id,
            '/api/v1/get?ids=' . $task_id,
            '/api/v1/status/' . $task_id
        ];
        
        foreach ($endpoints as $endpoint) {
            $response = wp_remote_get(SUNO_API_BASE_URL . $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 15
            ]);
            
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                
                if ($status_code === 200 && !empty($body)) {
                    $data = json_decode($body, true);
                    
                    if ($data) {
                        $result = $this->parse_api_response($data);
                        
                        if ($result['audio_url']) {
                            // Mettre à jour en base de données
                            $wpdb->update($table_name, [
                                'status' => 'completed',
                                'audio_url' => $result['audio_url'],
                                'video_url' => $result['video_url'],
                                'image_url' => $result['image_url'],
                                'duration' => $result['duration'],
                                'model_name' => $result['model_name'],
                                'completed_at' => current_time('mysql')
                            ], ['task_id' => $task_id]);
                            
                            wp_send_json_success([
                                'status' => 'completed',
                                'audio_url' => $result['audio_url'],
                                'video_url' => $result['video_url'],
                                'image_url' => $result['image_url'],
                                'duration' => $result['duration']
                            ]);
                            return;
                        }
                    }
                }
            }
        }
        
        // Calculer le pourcentage de progression
        $progress = 50;
        if ($generation) {
            $time_elapsed = time() - strtotime($generation->created_at);
            $progress = min(90, ($time_elapsed / 60) * 30);
        }
        
        wp_send_json_success([
            'status' => 'processing',
            'progress' => $progress,
            'message' => 'Génération en cours...'
        ]);
    }
    
    /**
     * AJAX - Obtenir l'historique
     */
    public function ajax_get_history() {
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $limit = intval($_POST['limit'] ?? 10);
        $offset = intval($_POST['offset'] ?? 0);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        ));
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
            $user_id
        ));
        
        wp_send_json_success([
            'items' => $results,
            'total' => $total,
            'has_more' => ($offset + $limit) < $total
        ]);
    }
    
    /**
     * Enregistrer les routes REST API
     */
    public function register_rest_routes() {
        register_rest_route('suno/v1', '/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_generate_music'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
        
        register_rest_route('suno/v1', '/status/(?P<task_id>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_check_status'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('suno/v1', '/gallery', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_gallery'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * REST API - Générer de la musique
     */
    public function rest_generate_music($request) {
        // Implémentation similaire à ajax_generate_music
        return new WP_REST_Response(['message' => 'API endpoint'], 200);
    }
    
    /**
     * REST API - Vérifier le statut
     */
    public function rest_check_status($request) {
        // Implémentation similaire à ajax_check_status
        return new WP_REST_Response(['message' => 'API endpoint'], 200);
    }
    
    /**
     * REST API - Obtenir la galerie
     */
    public function rest_get_gallery($request) {
        // Implémentation pour récupérer la galerie publique
        return new WP_REST_Response(['message' => 'API endpoint'], 200);
    }
    
    /**
     * Parser la réponse de l'API
     */
    private function parse_api_response($data) {
        $result = [
            'audio_url' => '',
            'video_url' => '',
            'image_url' => '',
            'duration' => 0,
            'model_name' => ''
        ];
        
        // Gérer différents formats de réponse
        if (isset($data[0]) && is_array($data[0])) {
            $item = $data[0];
        } elseif (isset($data['data'][0])) {
            $item = $data['data'][0];
        } elseif (isset($data['data']) && !is_array($data['data'])) {
            $item = $data;
        } else {
            $item = $data;
        }
        
        $result['audio_url'] = $item['audio_url'] ?? $item['audioUrl'] ?? $item['url'] ?? '';
        $result['video_url'] = $item['video_url'] ?? $item['videoUrl'] ?? '';
        $result['image_url'] = $item['image_url'] ?? $item['imageUrl'] ?? $item['image'] ?? '';
        $result['duration'] = intval($item['duration'] ?? 0);
        $result['model_name'] = $item['model_name'] ?? $item['modelName'] ?? '';
        
        return $result;
    }
    
    /**
     * Vérifier la limite quotidienne
     */
    private function check_daily_limit() {
        $max_per_day = get_option('suno_max_generations_per_day', 10);
        
        if ($max_per_day == 0) {
            return true; // Pas de limite
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE user_id = %d 
             AND DATE(created_at) = CURDATE()",
            get_current_user_id()
        ));
        
        return $count < $max_per_day;
    }
    
    /**
     * Nettoyer les transients
     */
    private function cleanup_transients() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_suno_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_suno_%'");
    }
    
    /**
     * Vérifier et mettre à jour la base de données si nécessaire
     */
    private function maybe_update_database() {
        $current_db_version = get_option('suno_db_version', '1.0.0');
        
        if (version_compare($current_db_version, SUNO_VERSION, '<')) {
            $this->create_database_tables();
        }
    }
}

/**
 * Fonction principale pour initialiser le plugin
 */
function suno_music_generator() {
    return SunoMusicGeneratorV5::get_instance();
}

// Lancer le plugin
suno_music_generator();

// Hooks pour la compatibilité avec d'autres plugins
do_action('suno_music_generator_loaded');
