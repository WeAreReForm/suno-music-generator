<?php
/**
 * Plugin Name: Suno Music Generator
 * Description: Générateur de musique IA avec l'API officielle Suno - Version simplifiée
 * Version: 4.0.0
 * Author: WeAreReForm
 * License: GPL-2.0+
 * Text Domain: suno-music
 */

// Sécurité WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Constantes du plugin
define('SUNO_VERSION', '4.0.0');
define('SUNO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SUNO_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Classe principale du plugin
 */
class SunoMusicGenerator {
    
    private static $instance = null;
    private $token = '';
    
    /**
     * Singleton
     */
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new SunoMusicGenerator();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur
     */
    private function __construct() {
        // Récupérer le token depuis les options
        $this->token = get_option('suno_token', '');
        
        // Hooks d'initialisation
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        
        // Shortcodes
        add_shortcode('suno_form', array($this, 'render_form'));
        add_shortcode('suno_test', array($this, 'render_test'));
        
        // AJAX
        add_action('wp_ajax_suno_generate', array($this, 'ajax_generate'));
        add_action('wp_ajax_nopriv_suno_generate', array($this, 'ajax_generate'));
        
        // Scripts et styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Initialisation
     */
    public function init() {
        // Créer la table si nécessaire
        $this->maybe_create_table();
    }
    
    /**
     * Créer la table en base de données
     */
    private function maybe_create_table() {
        $installed_version = get_option('suno_db_version');
        
        if ($installed_version != SUNO_VERSION) {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'suno_songs';
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) DEFAULT 0,
                clip_ids text NOT NULL,
                prompt text NOT NULL,
                style varchar(50) DEFAULT '',
                status varchar(20) DEFAULT 'pending',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            update_option('suno_db_version', SUNO_VERSION);
        }
    }
    
    /**
     * Menu admin
     */
    public function admin_menu() {
        add_options_page(
            'Suno Music Generator',
            'Suno Music',
            'manage_options',
            'suno-music',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Initialisation admin
     */
    public function admin_init() {
        register_setting('suno_settings', 'suno_token');
    }
    
    /**
     * Page d'administration
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Suno Music Generator v<?php echo SUNO_VERSION; ?></h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Configuration sauvegardée !</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('suno_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Token Suno</th>
                        <td>
                            <textarea name="suno_token" rows="5" cols="100" class="large-text"><?php echo esc_attr($this->token); ?></textarea>
                            <p class="description">
                                Collez votre token complet avec "Bearer " au début.<br>
                                Pour récupérer votre token : Suno.com → F12 → Application → Cookies → __session
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2>Test de connexion</h2>
            <?php echo do_shortcode('[suno_test]'); ?>
            
            <h2>Utilisation</h2>
            <p>Ajoutez ces shortcodes dans vos pages :</p>
            <ul>
                <li><code>[suno_form]</code> - Formulaire de génération</li>
                <li><code>[suno_test]</code> - Test de connexion</li>
            </ul>
            
            <h2>Historique des générations</h2>
            <?php $this->display_history(); ?>
        </div>
        <?php
    }
    
    /**
     * Afficher l'historique
     */
    private function display_history() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_songs';
        
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 20");
        
        if (empty($results)) {
            echo '<p>Aucune génération pour le moment.</p>';
            return;
        }
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Prompt</th>
                    <th>Style</th>
                    <th>IDs</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
                    <td><?php echo esc_html(substr($row->prompt, 0, 50)); ?>...</td>
                    <td><?php echo esc_html($row->style); ?></td>
                    <td><small><?php echo esc_html($row->clip_ids); ?></small></td>
                    <td><?php echo esc_html($row->status); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Enqueue scripts et styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style('suno-style', SUNO_PLUGIN_URL . 'assets/style.css', array(), SUNO_VERSION);
        wp_enqueue_script('suno-script', SUNO_PLUGIN_URL . 'assets/script.js', array('jquery'), SUNO_VERSION, true);
        
        wp_localize_script('suno-script', 'suno_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('suno_nonce')
        ));
    }
    
    /**
     * Shortcode formulaire
     */
    public function render_form() {
        if (empty($this->token)) {
            return '<div class="suno-error">⚠️ Plugin non configuré. Allez dans Réglages → Suno Music.</div>';
        }
        
        ob_start();
        ?>
        <div class="suno-generator">
            <h2>🎵 Générateur de Musique IA</h2>
            
            <form id="suno-form" class="suno-form">
                <div class="suno-field">
                    <label for="suno-prompt">Description de votre chanson :</label>
                    <textarea id="suno-prompt" name="prompt" rows="3" required 
                        placeholder="Ex: Une chanson joyeuse sur le soleil et l'été"></textarea>
                </div>
                
                <div class="suno-field">
                    <label for="suno-style">Style musical (optionnel) :</label>
                    <select id="suno-style" name="style">
                        <option value="">Automatique</option>
                        <option value="pop">Pop</option>
                        <option value="rock">Rock</option>
                        <option value="electronic">Électronique</option>
                        <option value="jazz">Jazz</option>
                        <option value="classical">Classique</option>
                        <option value="hip-hop">Hip-Hop</option>
                        <option value="country">Country</option>
                        <option value="folk">Folk</option>
                        <option value="r&b">R&B</option>
                        <option value="metal">Metal</option>
                    </select>
                </div>
                
                <button type="submit" class="suno-button">
                    🎼 Générer la musique
                </button>
            </form>
            
            <div id="suno-status" class="suno-status" style="display: none;">
                <div class="suno-message"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode test
     */
    public function render_test() {
        if (empty($this->token)) {
            return '<div class="suno-error">⚠️ Token non configuré</div>';
        }
        
        $response = wp_remote_get('https://studio-api.suno.ai/api/billing/info/', array(
            'headers' => array(
                'Authorization' => $this->token
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return '<div class="suno-error">❌ Erreur : ' . $response->get_error_message() . '</div>';
        }
        
        $status = wp_remote_retrieve_response_code($response);
        
        if ($status === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $credits = isset($body['total_credits_left']) ? $body['total_credits_left'] : 'N/A';
            return '<div class="suno-success">✅ Connexion OK - Crédits : ' . $credits . '</div>';
        } elseif ($status === 401) {
            return '<div class="suno-error">❌ Token invalide ou expiré</div>';
        } else {
            return '<div class="suno-error">❌ Erreur API (Code ' . $status . ')</div>';
        }
    }
    
    /**
     * Handler AJAX
     */
    public function ajax_generate() {
        // Vérifier le nonce
        if (!wp_verify_nonce($_POST['nonce'], 'suno_nonce')) {
            wp_send_json_error('Erreur de sécurité');
        }
        
        // Vérifier le token
        if (empty($this->token)) {
            wp_send_json_error('Plugin non configuré');
        }
        
        // Récupérer les données
        $prompt = sanitize_text_field($_POST['prompt']);
        $style = sanitize_text_field($_POST['style']);
        
        if (empty($prompt)) {
            wp_send_json_error('Description requise');
        }
        
        // Préparer la requête
        $body = array(
            'prompt' => $prompt,
            'mv' => 'chirp-v3-5'
        );
        
        if (!empty($style)) {
            $body['tags'] = $style;
        }
        
        // Appel API
        $response = wp_remote_post('https://studio-api.suno.ai/api/generate/v2/', array(
            'headers' => array(
                'Authorization' => $this->token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Erreur API : ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($status_code === 401) {
            wp_send_json_error('Token expiré - Mettez à jour dans les réglages');
        }
        
        if ($status_code !== 200 && $status_code !== 201) {
            wp_send_json_error('Erreur Suno (Code ' . $status_code . ')');
        }
        
        $data = json_decode($response_body, true);
        
        if (isset($data['clips'])) {
            // Sauvegarder en base
            global $wpdb;
            $table_name = $wpdb->prefix . 'suno_songs';
            
            $clip_ids = array_column($data['clips'], 'id');
            
            $wpdb->insert($table_name, array(
                'user_id' => get_current_user_id(),
                'clip_ids' => implode(',', $clip_ids),
                'prompt' => $prompt,
                'style' => $style,
                'status' => 'completed'
            ));
            
            wp_send_json_success(array(
                'message' => 'Génération réussie !',
                'clips' => $clip_ids
            ));
        } else {
            wp_send_json_error('Réponse inattendue de Suno');
        }
    }
}

// Initialiser le plugin
SunoMusicGenerator::getInstance();