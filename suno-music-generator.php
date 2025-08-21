<?php
/**
 * Plugin Name: Suno Music Generator
 * Plugin URI: https://github.com/WeAreReForm/suno-music-generator
 * Description: Générateur de musique IA professionnel avec Suno API
 * Version: 5.0.1
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
define('SUNO_VERSION', '5.0.1');
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
        
        // NE PAS créer de pages automatiquement
        // L'utilisateur créera ses propres pages avec les shortcodes
        
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
        
        update_option('suno_db_version', SUNO_VERSION);
    }
    
    /**
     * Créer les options par défaut
     */
    private function create_default_options() {
        add_option('suno_api_key', '');
        add_option('suno_default_style', 'auto');
        add_option('suno_enable_public_gallery', false);
        add_option('suno_enable_user_profiles', true);
        add_option('suno_max_generations_per_day', 10);
        add_option('suno_enable_cache', true);
        add_option('suno_notification_email', get_option('admin_email'));
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
        add_shortcode('suno_music_form', [$this, 'shortcode_generator']); // Alias pour compatibilité
        add_shortcode('suno_gallery', [$this, 'shortcode_gallery']);
        add_shortcode('suno_my_music', [$this, 'shortcode_my_music']);
        add_shortcode('suno_player', [$this, 'shortcode_player']);
        add_shortcode('suno_music_player', [$this, 'shortcode_player']); // Alias pour compatibilité
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
        if (strpos($hook, 'suno') === false) {
            return;
        }
        
        wp_enqueue_style(
            'suno-admin',
            SUNO_PLUGIN_URL . 'assets/css/suno-admin.css',
            [],
            SUNO_VERSION
        );
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
            __('Paramètres', 'suno-music-generator'),
            __('Paramètres', 'suno-music-generator'),
            'manage_options',
            'suno-settings',
            [$this, 'admin_settings']
        );
    }
    
    /**
     * Admin init
     */
    public function admin_init() {
        register_setting('suno_settings', 'suno_api_key');
        register_setting('suno_settings', 'suno_default_style');
        register_setting('suno_settings', 'suno_enable_public_gallery');
        register_setting('suno_settings', 'suno_max_generations_per_day');
    }
    
    /**
     * Page tableau de bord admin
     */
    public function admin_dashboard() {
        // Dashboard simple avec statistiques
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
        $completed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
        
        ?>
        <div class="wrap">
            <h1>🎵 Suno Music Generator - Tableau de bord</h1>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">📊 Total générations</h2>
                    <p style="font-size: 2em; margin: 0;"><?php echo intval($total); ?></p>
                </div>
                
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">⏳ En cours</h2>
                    <p style="font-size: 2em; margin: 0;"><?php echo intval($pending); ?></p>
                </div>
                
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">✅ Terminées</h2>
                    <p style="font-size: 2em; margin: 0;"><?php echo intval($completed); ?></p>
                </div>
            </div>
            
            <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h2>🚀 Démarrage rapide</h2>
                <ol>
                    <li>Configurez votre clé API dans <a href="<?php echo admin_url('admin.php?page=suno-settings'); ?>">les paramètres</a></li>
                    <li>Créez une page et ajoutez le shortcode <code>[suno_music_generator]</code></li>
                    <li>Testez la génération avec une description simple</li>
                </ol>
            </div>
            
            <p>
                <a href="<?php echo admin_url('admin.php?page=suno-settings'); ?>" class="button button-primary">
                    ⚙️ Configurer le plugin
                </a>
                <a href="https://sunoapi.org/fr/logs" target="_blank" class="button button-secondary">
                    📊 Voir les logs SunoAPI
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Page des paramètres admin
     */
    public function admin_settings() {
        include SUNO_PLUGIN_DIR . 'includes/admin/settings.php';
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
        
        if (file_exists(SUNO_PLUGIN_DIR . 'includes/shortcodes/generator.php')) {
            include SUNO_PLUGIN_DIR . 'includes/shortcodes/generator.php';
        } else {
            // Template de secours si le fichier n'existe pas
            ?>
            <div id="suno-music-generator" class="suno-container">
                <div class="suno-header">
                    <h2>🎵 Créez votre musique avec l'IA</h2>
                </div>
                
                <?php if (empty($this->api_key) && current_user_can('manage_options')) : ?>
                    <div class="suno-notice suno-notice-warning">
                        Veuillez configurer votre clé API dans 
                        <a href="<?php echo admin_url('admin.php?page=suno-settings'); ?>">les paramètres</a>.
                    </div>
                <?php endif; ?>
                
                <form id="suno-music-form" class="suno-form">
                    <?php wp_nonce_field('suno_music_nonce', 'suno_nonce'); ?>
                    
                    <div class="suno-form-group">
                        <label for="suno-prompt">Description de votre chanson *</label>
                        <textarea id="suno-prompt" name="prompt" rows="4" required 
                                  placeholder="Ex: Une ballade pop mélancolique sur l'amour perdu"></textarea>
                    </div>
                    
                    <div class="suno-form-row">
                        <div class="suno-form-group">
                            <label for="suno-style">Style musical</label>
                            <select id="suno-style" name="style">
                                <option value="">Automatique</option>
                                <option value="pop">Pop</option>
                                <option value="rock">Rock</option>
                                <option value="electronic">Électronique</option>
                                <option value="hip-hop">Hip-Hop</option>
                                <option value="jazz">Jazz</option>
                            </select>
                        </div>
                        
                        <div class="suno-form-group">
                            <label for="suno-title">Titre (optionnel)</label>
                            <input type="text" id="suno-title" name="title" placeholder="Titre de votre chanson">
                        </div>
                    </div>
                    
                    <div class="suno-form-group">
                        <label for="suno-lyrics">Paroles (optionnel)</label>
                        <textarea id="suno-lyrics" name="lyrics" rows="6" 
                                  placeholder="Laissez vide pour une génération automatique"></textarea>
                    </div>
                    
                    <div class="suno-form-group">
                        <label>
                            <input type="checkbox" id="suno-instrumental" name="instrumental">
                            Version instrumentale (sans voix)
                        </label>
                    </div>
                    
                    <button type="submit" class="suno-btn suno-btn-primary">
                        🎼 Générer ma musique
                    </button>
                </form>
                
                <div id="suno-generation-status" style="display: none;">
                    <div class="suno-status-content">
                        <div class="suno-spinner"></div>
                        <p><span id="suno-status-text">Initialisation...</span></p>
                        <div class="suno-progress-bar">
                            <div class="suno-progress-fill"></div>
                        </div>
                    </div>
                </div>
                
                <div id="suno-generation-result" style="display: none;"></div>
            </div>
            <?php
        }
        
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
        
        if (!get_option('suno_enable_public_gallery', false)) {
            return '<div class="suno-notice">La galerie publique est désactivée.</div>';
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE status = 'completed' AND is_public = 1 
             ORDER BY %s %s 
             LIMIT %d",
            $atts['orderby'],
            $atts['order'],
            intval($atts['limit'])
        ));
        
        ob_start();
        ?>
        <div class="suno-gallery">
            <?php if (empty($results)) : ?>
                <p>Aucune création publique pour le moment.</p>
            <?php else : ?>
                <?php foreach ($results as $item) : ?>
                    <div class="suno-gallery-item">
                        <?php if ($item->image_url) : ?>
                            <img src="<?php echo esc_url($item->image_url); ?>" alt="" class="suno-gallery-image">
                        <?php else : ?>
                            <div class="suno-gallery-image" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                                <span style="font-size: 3em;">🎵</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="suno-gallery-content">
                            <h4 class="suno-gallery-title"><?php echo esc_html($item->title ?: 'Sans titre'); ?></h4>
                            <div class="suno-gallery-meta">
                                <span><?php echo esc_html($item->style ?: 'Auto'); ?></span>
                                <span><?php echo date('d/m/Y', strtotime($item->created_at)); ?></span>
                            </div>
                            
                            <?php if ($item->audio_url) : ?>
                                <audio controls preload="none" style="width: 100%; margin-top: 10px;">
                                    <source src="<?php echo esc_url($item->audio_url); ?>" type="audio/mpeg">
                                </audio>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
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
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            get_current_user_id(),
            intval($atts['limit'])
        ));
        
        ob_start();
        ?>
        <div class="suno-my-music">
            <h3>🎵 Mes créations musicales</h3>
            
            <?php if (empty($results)) : ?>
                <p>Vous n'avez pas encore créé de musique.</p>
                <a href="#" class="suno-btn suno-btn-primary">Créer ma première chanson</a>
            <?php else : ?>
                <div class="suno-playlist">
                    <?php foreach ($results as $track) : ?>
                        <div class="suno-track">
                            <div class="suno-track-info">
                                <div class="suno-track-title"><?php echo esc_html($track->title ?: 'Sans titre'); ?></div>
                                <div class="suno-track-meta">
                                    <?php echo esc_html($track->style ?: 'Auto'); ?> • 
                                    <?php echo date('d/m/Y', strtotime($track->created_at)); ?>
                                    <?php if ($track->status === 'pending') : ?>
                                        <span style="color: orange;"> • En cours...</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($track->audio_url) : ?>
                                <audio controls preload="none">
                                    <source src="<?php echo esc_url($track->audio_url); ?>" type="audio/mpeg">
                                </audio>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode player
     */
    public function shortcode_player($atts) {
        $atts = shortcode_atts([
            'id' => '',
            'user_id' => get_current_user_id(),
            'limit' => 10,
            'autoplay' => 'false',
            'style' => 'default'
        ], $atts);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        // Si un ID spécifique est fourni
        if (!empty($atts['id'])) {
            $track = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                intval($atts['id'])
            ));
            
            if (!$track || !$track->audio_url) {
                return '<p>Chanson non trouvée.</p>';
            }
            
            ob_start();
            ?>
            <div class="suno-player">
                <h4><?php echo esc_html($track->title ?: 'Sans titre'); ?></h4>
                <audio controls <?php echo $atts['autoplay'] === 'true' ? 'autoplay' : ''; ?>>
                    <source src="<?php echo esc_url($track->audio_url); ?>" type="audio/mpeg">
                </audio>
            </div>
            <?php
            return ob_get_clean();
        }
        
        // Sinon, afficher les dernières créations
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE user_id = %d AND status = 'completed' 
             ORDER BY created_at DESC 
             LIMIT %d",
            intval($atts['user_id']),
            intval($atts['limit'])
        ));
        
        if (empty($results)) {
            return '<p>Aucune chanson disponible.</p>';
        }
        
        ob_start();
        ?>
        <div class="suno-playlist">
            <?php foreach ($results as $track) : ?>
                <div class="suno-track">
                    <h4><?php echo esc_html($track->title ?: 'Sans titre'); ?></h4>
                    <p><?php echo esc_html(wp_trim_words($track->prompt, 15)); ?></p>
                    <?php if ($track->audio_url) : ?>
                        <audio controls preload="none">
                            <source src="<?php echo esc_url($track->audio_url); ?>" type="audio/mpeg">
                        </audio>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
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
        ?>
        <div class="suno-api-test">
            <h4>🔧 Test de connexion API</h4>
            
            <?php if (empty($this->api_key)): ?>
                <div class="suno-notice suno-notice-error">
                    ❌ Clé API non configurée. 
                    <a href="<?php echo admin_url('admin.php?page=suno-settings'); ?>">Configurer</a>
                </div>
            <?php else: ?>
                <div class="suno-notice suno-notice-info">
                    ✅ Clé API configurée (<?php echo strlen($this->api_key); ?> caractères)
                </div>
                
                <?php
                $response = wp_remote_get(SUNO_API_BASE_URL . '/api/v1/get_limit', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->api_key,
                        'Content-Type' => 'application/json'
                    ],
                    'timeout' => 10
                ]);
                
                if (!is_wp_error($response)) {
                    $status = wp_remote_retrieve_response_code($response);
                    $body = wp_remote_retrieve_body($response);
                    
                    if ($status === 200) {
                        echo '<div class="suno-notice suno-notice-success">✅ Connexion API réussie !</div>';
                        $data = json_decode($body, true);
                        if (isset($data['credits'])) {
                            echo '<p>Crédits disponibles : ' . $data['credits'] . '</p>';
                        }
                    } else {
                        echo '<div class="suno-notice suno-notice-error">❌ Erreur API : Code ' . $status . '</div>';
                    }
                } else {
                    echo '<div class="suno-notice suno-notice-error">❌ Erreur de connexion : ' . $response->get_error_message() . '</div>';
                }
                ?>
            <?php endif; ?>
            
            <p>
                <a href="https://sunoapi.org/fr/logs" target="_blank" class="suno-btn suno-btn-secondary">
                    📊 Voir les logs SunoAPI
                </a>
            </p>
        </div>
        <?php
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
        
        error_log('Suno API Response: ' . $status_code . ' - ' . $body);
        
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
        
        wp_send_json_success([
            'status' => 'processing',
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
