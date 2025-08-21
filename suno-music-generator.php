<?php
/**
 * Plugin Name: Suno Music Generator
 * Plugin URI: https://github.com/WeAreReForm/suno-music-generator
 * Description: Générateur de musique IA avec Suno via SunoAPI.org
 * Version: 5.0.0
 * Author: WeAreReForm
 * Author URI: https://wearereform.fr
 * License: GPL v2 or later
 * Text Domain: suno-music
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Constantes du plugin
define('SUNO_VERSION', '5.0.0');
define('SUNO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SUNO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SUNO_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principale du plugin Suno Music Generator v5
 */
class SunoMusicGenerator {
    
    private static $instance = null;
    private $api_key;
    private $api_base_url = 'https://api.sunoapi.org';
    private $db_version = '5.0';
    
    /**
     * Singleton
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialisation des hooks WordPress
     */
    private function init_hooks() {
        // Actions d'initialisation
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Shortcodes
        add_shortcode('suno_form', array($this, 'render_form'));
        add_shortcode('suno_player', array($this, 'render_player'));
        add_shortcode('suno_test', array($this, 'render_test'));
        
        // AJAX
        add_action('wp_ajax_suno_generate', array($this, 'ajax_generate'));
        add_action('wp_ajax_nopriv_suno_generate', array($this, 'ajax_generate'));
        add_action('wp_ajax_suno_check_status', array($this, 'ajax_check_status'));
        add_action('wp_ajax_nopriv_suno_check_status', array($this, 'ajax_check_status'));
        
        // Admin
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // Activation/Désactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialisation
     */
    public function init() {
        $this->api_key = get_option('suno_api_key_v5', '');
        
        // Charger les traductions
        load_plugin_textdomain('suno-music', false, dirname(SUNO_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Activation du plugin
     */
    public function activate() {
        $this->create_table();
        $this->migrate_old_data();
        update_option('suno_plugin_version', SUNO_VERSION);
        flush_rewrite_rules();
    }
    
    /**
     * Désactivation du plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Création de la table
     */
    private function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'suno_music';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT 0,
            task_id varchar(255) NOT NULL,
            prompt text NOT NULL,
            style varchar(100) DEFAULT '',
            title varchar(255) DEFAULT '',
            status varchar(50) DEFAULT 'pending',
            audio_url varchar(500) DEFAULT '',
            image_url varchar(500) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Migration des anciennes données
     */
    private function migrate_old_data() {
        global $wpdb;
        
        // Récupérer l'ancienne clé API si elle existe
        $old_key = get_option('suno_api_key');
        if ($old_key && !get_option('suno_api_key_v5')) {
            update_option('suno_api_key_v5', $old_key);
        }
        
        // Nettoyer les anciennes options
        $old_options = array(
            'suno_api_key',
            'suno_music_generator_version',
            'suno_music_settings',
            'suno_music_cache'
        );
        
        foreach ($old_options as $option) {
            delete_option($option);
        }
        
        // Supprimer les anciennes tables si elles existent
        $old_table = $wpdb->prefix . 'suno_generations';
        if ($wpdb->get_var("SHOW TABLES LIKE '$old_table'") == $old_table) {
            // Migrer les données si nécessaire
            $new_table = $wpdb->prefix . 'suno_music';
            $wpdb->query("INSERT IGNORE INTO $new_table (user_id, task_id, prompt, style, title, status, audio_url, created_at) 
                         SELECT user_id, task_id, prompt, style, title, status, audio_url, created_at 
                         FROM $old_table WHERE status = 'completed'");
            
            // Supprimer l'ancienne table
            $wpdb->query("DROP TABLE IF EXISTS $old_table");
        }
    }
    
    /**
     * Enqueue des scripts et styles
     */
    public function enqueue_scripts() {
        // CSS
        wp_enqueue_style(
            'suno-music-style',
            SUNO_PLUGIN_URL . 'assets/style.css',
            array(),
            SUNO_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'suno-music-script',
            SUNO_PLUGIN_URL . 'assets/script.js',
            array('jquery'),
            SUNO_VERSION,
            true
        );
        
        // Localisation pour AJAX
        wp_localize_script('suno-music-script', 'suno_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('suno_nonce_v5')
        ));
    }
    
    /**
     * Menu admin
     */
    public function admin_menu() {
        add_options_page(
            'Suno Music Generator',
            'Suno Music',
            'manage_options',
            'suno-music-v5',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Page d'administration
     */
    public function admin_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer('suno_admin_v5');
            update_option('suno_api_key_v5', sanitize_text_field($_POST['api_key']));
            echo '<div class="notice notice-success"><p>✅ Paramètres sauvegardés !</p></div>';
        }
        
        $api_key = get_option('suno_api_key_v5', '');
        ?>
        <div class="wrap">
            <h1>🎵 Suno Music Generator v5.0</h1>
            
            <div style="background: #f0f9ff; padding: 20px; border-radius: 10px; margin: 20px 0;">
                <h2>✨ Version 5.0 - Nouveau départ</h2>
                <ul>
                    <li>✅ Code complètement réécrit</li>
                    <li>✅ Plus de conflits de versions</li>
                    <li>✅ Performance optimisée</li>
                    <li>✅ Interface simplifiée</li>
                </ul>
            </div>
            
            <form method="post">
                <?php wp_nonce_field('suno_admin_v5'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Clé API SunoAPI.org</th>
                        <td>
                            <input type="text" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                            <p class="description">
                                Obtenez votre clé sur <a href="https://sunoapi.org" target="_blank">SunoAPI.org</a>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2>📖 Shortcodes disponibles</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Shortcode</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[suno_form]</code></td>
                        <td>Formulaire de génération de musique</td>
                    </tr>
                    <tr>
                        <td><code>[suno_player]</code></td>
                        <td>Liste des musiques générées</td>
                    </tr>
                    <tr>
                        <td><code>[suno_test]</code></td>
                        <td>Test de connexion API (admin uniquement)</td>
                    </tr>
                </tbody>
            </table>
            
            <?php if (!empty($api_key)): ?>
            <h2>🔧 Test de connexion</h2>
            <button id="test-api" class="button button-primary">Tester l'API</button>
            <div id="test-result" style="margin-top: 20px;"></div>
            
            <script>
            jQuery('#test-api').on('click', function() {
                jQuery('#test-result').html('<p>Test en cours...</p>');
                jQuery.post(ajaxurl, {
                    action: 'suno_test_api',
                    nonce: '<?php echo wp_create_nonce('suno_test_api'); ?>'
                }, function(response) {
                    if (response.success) {
                        jQuery('#test-result').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    } else {
                        jQuery('#test-result').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                });
            });
            </script>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Shortcode : Formulaire
     */
    public function render_form($atts) {
        if (empty($this->api_key)) {
            return '<div class="suno-error">⚠️ Plugin non configuré. Ajoutez votre clé API dans les réglages.</div>';
        }
        
        ob_start();
        ?>
        <div class="suno-form-container">
            <h3>🎵 Créer votre musique</h3>
            
            <form id="suno-generate-form">
                <div class="suno-field">
                    <label for="suno-prompt">Description de la musique *</label>
                    <textarea id="suno-prompt" required placeholder="Ex: Une chanson pop joyeuse sur l'été"></textarea>
                </div>
                
                <div class="suno-field">
                    <label for="suno-style">Style (optionnel)</label>
                    <input type="text" id="suno-style" placeholder="Ex: pop, rock, jazz...">
                </div>
                
                <button type="submit" class="suno-button">🎼 Générer la musique</button>
            </form>
            
            <div id="suno-status" style="display:none;"></div>
            <div id="suno-result" style="display:none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode : Player
     */
    public function render_player($atts) {
        global $wpdb;
        
        $atts = shortcode_atts(array(
            'limit' => 10,
            'user' => 'all'
        ), $atts);
        
        $table_name = $wpdb->prefix . 'suno_music';
        
        $where = "WHERE status = 'completed'";
        if ($atts['user'] === 'current') {
            $where .= " AND user_id = " . get_current_user_id();
        }
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT %d",
                intval($atts['limit'])
            )
        );
        
        if (empty($results)) {
            return '<div class="suno-info">Aucune musique générée pour le moment.</div>';
        }
        
        ob_start();
        ?>
        <div class="suno-player-container">
            <h3>🎵 Musiques générées</h3>
            
            <div class="suno-tracks">
                <?php foreach ($results as $track): ?>
                <div class="suno-track">
                    <h4><?php echo esc_html($track->title ?: 'Sans titre'); ?></h4>
                    <p><?php echo esc_html($track->prompt); ?></p>
                    
                    <?php if ($track->audio_url): ?>
                    <audio controls>
                        <source src="<?php echo esc_url($track->audio_url); ?>" type="audio/mpeg">
                    </audio>
                    <?php endif; ?>
                    
                    <small>Créé le <?php echo date_i18n('d/m/Y à H:i', strtotime($track->created_at)); ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode : Test
     */
    public function render_test($atts) {
        if (!current_user_can('manage_options')) {
            return '<div class="suno-error">Accès réservé aux administrateurs.</div>';
        }
        
        ob_start();
        ?>
        <div class="suno-test-container">
            <h3>🔧 Test de connexion API</h3>
            <button id="suno-test-btn" class="suno-button">Tester la connexion</button>
            <div id="suno-test-result"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX : Générer la musique
     */
    public function ajax_generate() {
        check_ajax_referer('suno_nonce_v5', 'nonce');
        
        if (empty($this->api_key)) {
            wp_send_json_error('Clé API non configurée');
        }
        
        $prompt = sanitize_text_field($_POST['prompt'] ?? '');
        $style = sanitize_text_field($_POST['style'] ?? '');
        
        if (empty($prompt)) {
            wp_send_json_error('Description requise');
        }
        
        // Appel API
        $response = wp_remote_post($this->api_base_url . '/api/generate', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'prompt' => $prompt,
                'tags' => $style,
                'wait_audio' => false
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Erreur de connexion');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['id'])) {
            // Sauvegarder en base
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'suno_music',
                array(
                    'user_id' => get_current_user_id(),
                    'task_id' => $body['id'],
                    'prompt' => $prompt,
                    'style' => $style,
                    'status' => 'pending'
                )
            );
            
            wp_send_json_success(array('task_id' => $body['id']));
        } else {
            wp_send_json_error($body['error'] ?? 'Erreur inconnue');
        }
    }
    
    /**
     * AJAX : Vérifier le statut
     */
    public function ajax_check_status() {
        check_ajax_referer('suno_nonce_v5', 'nonce');
        
        $task_id = sanitize_text_field($_POST['task_id'] ?? '');
        
        if (empty($task_id)) {
            wp_send_json_error('Task ID requis');
        }
        
        // Vérifier le statut
        $response = wp_remote_get($this->api_base_url . '/api/get?ids=' . $task_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Erreur de vérification');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body[0])) {
            $track = $body[0];
            
            if (isset($track['audio_url']) && !empty($track['audio_url'])) {
                // Mettre à jour en base
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'suno_music',
                    array(
                        'status' => 'completed',
                        'audio_url' => $track['audio_url'],
                        'title' => $track['title'] ?? '',
                        'image_url' => $track['image_url'] ?? ''
                    ),
                    array('task_id' => $task_id)
                );
                
                wp_send_json_success(array(
                    'status' => 'completed',
                    'audio_url' => $track['audio_url'],
                    'title' => $track['title'] ?? ''
                ));
            } else {
                wp_send_json_success(array('status' => 'processing'));
            }
        } else {
            wp_send_json_success(array('status' => 'processing'));
        }
    }
}

// Initialisation du plugin
add_action('plugins_loaded', function() {
    SunoMusicGenerator::getInstance();
});

// Fonction AJAX pour le test API (admin)
add_action('wp_ajax_suno_test_api', function() {
    check_ajax_referer('suno_test_api', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission refusée');
    }
    
    $api_key = get_option('suno_api_key_v5', '');
    
    if (empty($api_key)) {
        wp_send_json_error('Clé API non configurée');
    }
    
    $response = wp_remote_get('https://api.sunoapi.org/api/get_limit', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key
        ),
        'timeout' => 10
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error('Erreur de connexion');
    }
    
    $code = wp_remote_retrieve_response_code($response);
    
    if ($code === 200) {
        wp_send_json_success('✅ Connexion réussie !');
    } else {
        wp_send_json_error('Erreur ' . $code);
    }
});