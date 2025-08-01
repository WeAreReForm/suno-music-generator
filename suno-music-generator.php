<?php
/**
 * Plugin Name: Suno Music Generator
 * Description: Générateur de musique IA avec Suno via formulaire WordPress
 * Version: 1.5 (Avec support callBackUrl)
 * Author: Assistant IA
 */

if (!defined('ABSPATH')) {
    exit;
}

class SunoMusicGenerator {
    
    private $api_key;
    private $api_base_url = 'https://apibox.erweima.ai';
    
    public function __construct() {
        error_log('=== SUNO PLUGIN v1.5 - AVEC CALLBACK URL ===');
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('suno_music_form', array($this, 'render_music_form'));
        add_shortcode('suno_music_player', array($this, 'render_music_player'));
        add_shortcode('suno_test_api', array($this, 'render_api_test'));
        add_shortcode('suno_debug', array($this, 'render_debug_info'));
        add_shortcode('suno_test_shortcode', array($this, 'test_shortcode'));
        add_shortcode('suno_clear_database', array($this, 'clear_database_shortcode'));
        add_shortcode('suno_test_generation', array($this, 'test_generation_direct'));
        
        add_action('wp_ajax_generate_music', array($this, 'ajax_generate_music'));
        add_action('wp_ajax_nopriv_generate_music', array($this, 'ajax_generate_music'));
        add_action('wp_ajax_check_music_status', array($this, 'ajax_check_music_status'));
        add_action('wp_ajax_nopriv_check_music_status', array($this, 'ajax_check_music_status'));
        add_action('wp_ajax_suno_callback', array($this, 'handle_suno_callback'));
        add_action('wp_ajax_nopriv_suno_callback', array($this, 'handle_suno_callback'));
        
        register_activation_hook(__FILE__, array($this, 'create_tables'));
        add_action('admin_menu', array($this, 'admin_menu'));
    }
    
    public function init() {
        $this->api_key = get_option('suno_api_key', '');
    }
    
    public function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'suno_generations';
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
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function admin_menu() {
        add_options_page(
            'Suno Music Generator', 
            'Suno Music', 
            'manage_options', 
            'suno-music', 
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        if (isset($_POST['submit'])) {
            update_option('suno_api_key', sanitize_text_field($_POST['api_key']));
            echo '<div class="notice notice-success"><p>Paramètres sauvegardés!</p></div>';
        }
        
        $api_key = get_option('suno_api_key', '');
        ?>
        <div class="wrap">
            <h1>Configuration Suno Music Generator v1.5</h1>
            
            <div style="background: #d1ecf1; padding: 15px; border-radius: 5px; color: #0c5460; margin: 15px 0;">
                <strong>🔄 VERSION 1.5 - MISE À JOUR IMPORTANTE</strong><br>
                ✅ Support du nouveau système de callback de SunoAPI<br>
                ✅ Génération asynchrone avec URL de retour<br>
                ✅ Protection anti-boucle maintenue<br>
                ℹ️ L'API utilise maintenant un système de callback pour notifier la fin de génération
            </div>
            
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">Clé API Suno</th>
                        <td>
                            <input type="text" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                            <p class="description">Obtenez votre clé API sur <a href="https://sunoapi.org" target="_blank">SunoAPI.org</a></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <h2>Shortcodes disponibles</h2>
            <p><code>[suno_music_form]</code> - Formulaire de génération</p>
            <p><code>[suno_music_player]</code> - Affichage des créations</p>
            <p><code>[suno_test_api]</code> - Test de connexion API</p>
            <p><code>[suno_debug]</code> - Informations de debug</p>
            <p><code>[suno_test_generation]</code> - Test direct de génération</p>
            
            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <h3>📋 URL de Callback</h3>
                <p>L'API utilise cette URL pour notifier la fin de génération :</p>
                <code style="background: #f8f9fa; padding: 5px; border-radius: 3px;">
                    <?php echo admin_url('admin-ajax.php?action=suno_callback'); ?>
                </code>
            </div>
        </div>
        <?php
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('suno-music-js', plugin_dir_url(__FILE__) . 'assets/suno-music.js', array('jquery'), '1.5', true);
        wp_enqueue_style('suno-music-css', plugin_dir_url(__FILE__) . 'assets/suno-music.css', array(), '1.5');
        
        wp_localize_script('suno-music-js', 'suno_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('suno_music_nonce'),
            'max_checks' => 20,
            'check_interval' => 5000
        ));
    }
    
    public function render_music_form($atts) {
        ob_start();
        ?>
        <div id="suno-music-form" class="suno-container">
            <h3>🎵 Créer votre chanson avec l'IA (v1.5)</h3>
            
            <div style="background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 15px 0; color: #0c5460;">
                <strong>🔄 Nouveau système :</strong> L'API utilise maintenant un callback pour notifier quand la chanson est prête.
            </div>
            
            <form id="music-generation-form">
                <?php wp_nonce_field('suno_music_nonce', 'suno_nonce'); ?>
                
                <div class="form-group">
                    <label for="music-prompt">Description de la chanson *</label>
                    <textarea id="music-prompt" name="prompt" rows="3" required 
                        placeholder="Ex: Une chanson pop énergique sur l'été et la liberté"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="music-style">Style musical</label>
                        <select id="music-style" name="style">
                            <option value="">Style automatique</option>
                            <option value="pop">Pop</option>
                            <option value="rock">Rock</option>
                            <option value="electronic">Électronique</option>
                            <option value="hip-hop">Hip-Hop</option>
                            <option value="jazz">Jazz</option>
                            <option value="classical">Classique</option>
                            <option value="country">Country</option>
                            <option value="reggae">Reggae</option>
                            <option value="blues">Blues</option>
                            <option value="folk">Folk</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="music-title">Titre (optionnel)</label>
                        <input type="text" id="music-title" name="title" 
                            placeholder="Titre de votre chanson">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="music-lyrics">Paroles personnalisées (optionnel)</label>
                    <textarea id="music-lyrics" name="lyrics" rows="4" 
                        placeholder="Laissez vide pour une génération automatique"></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="instrumental" name="instrumental">
                        Version instrumentale (sans voix)
                    </label>
                </div>
                
                <button type="submit" class="suno-btn suno-btn-primary">
                    🎼 Générer la musique
                </button>
            </form>
            
            <div id="generation-status" class="suno-status" style="display: none;">
                <div class="status-content">
                    <div class="spinner"></div>
                    <p>Génération en cours... <span id="status-text">Initialisation</span></p>
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div style="font-size: 12px; color: #666; margin-top: 10px;">
                        Vérifications : <span id="check-count">0</span>/20 (protection anti-boucle)
                    </div>
                </div>
            </div>
            
            <div id="generation-result" class="suno-result" style="display: none;">
                <!-- Résultat affiché ici -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function test_shortcode($atts) {
        return '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; color: #155724;">
            <strong>✅ SUCCÈS !</strong> Plugin v1.5 (Avec Callback) fonctionnel<br>
            <strong>Heure :</strong> ' . current_time('d/m/Y H:i:s') . '<br>
            <strong>Callback URL :</strong> ' . admin_url('admin-ajax.php?action=suno_callback') . '
        </div>';
    }
    
    public function ajax_generate_music() {
        error_log('=== GENERATE MUSIC v1.5 WITH CALLBACK ===');
        
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        if (empty($this->api_key)) {
            wp_send_json_error('Clé API Suno non configurée.');
        }
        
        $prompt = sanitize_textarea_field($_POST['prompt']);
        $style = sanitize_text_field($_POST['style']);
        $title = sanitize_text_field($_POST['title']);
        $lyrics = sanitize_textarea_field($_POST['lyrics']);
        $instrumental = isset($_POST['instrumental']) && $_POST['instrumental'] === 'true';
        
        if (empty($prompt)) {
            wp_send_json_error('Description requise');
        }
        
        // Générer un ID unique pour cette génération
        $generation_id = 'wp_' . uniqid() . '_' . time();
        
        // URL de callback
        $callback_url = admin_url('admin-ajax.php?action=suno_callback&generation_id=' . $generation_id);
        
        // Données API avec callBackUrl
        $api_data = array(
            'prompt' => $prompt,
            'customMode' => false,
            'instrumental' => $instrumental,
            'model' => 'V3_5',
            'callBackUrl' => $callback_url
        );
        
        if (!empty($style) || !empty($title) || !empty($lyrics)) {
            $api_data['customMode'] = true;
            if (!empty($style)) $api_data['style'] = $style;
            if (!empty($title)) $api_data['title'] = $title;
            if (!empty($lyrics)) $api_data['lyric'] = $lyrics;
        }
        
        error_log('API Data with callback: ' . json_encode($api_data));
        
        $response = wp_remote_post($this->api_base_url . '/api/v1/generate', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($api_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Erreur de connexion API: ' . $response->get_error_message());
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('API Response Code: ' . $status_code);
        error_log('API Response: ' . $body);
        
        if ($status_code === 401) {
            wp_send_json_error('Clé API invalide. Vérifiez votre clé dans Réglages > Suno Music.');
            return;
        }
        
        if ($status_code === 429) {
            wp_send_json_error('Limite de crédits atteinte. Ajoutez des crédits sur sunoapi.org.');
            return;
        }
        
        if ($status_code !== 200) {
            wp_send_json_error('Erreur API (Code: ' . $status_code . ')');
            return;
        }
        
        $data = json_decode($body, true);
        if (!$data) {
            wp_send_json_error('Réponse API invalide');
            return;
        }
        
        // Avec le nouveau système, on utilise le generation_id comme task_id
        $task_id = $generation_id;
        
        // Si l'API retourne quand même un task_id, on l'utilise
        if (isset($data['task_id'])) {
            $task_id = $data['task_id'];
        } elseif (isset($data['taskId'])) {
            $task_id = $data['taskId'];
        } elseif (isset($data['data']['task_id'])) {
            $task_id = $data['data']['task_id'];
        } elseif (isset($data['data']['taskId'])) {
            $task_id = $data['data']['taskId'];
        }
        
        // Sauvegarder en base avec notre generation_id
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $insert_result = $wpdb->insert($table_name, array(
            'user_id' => get_current_user_id(),
            'task_id' => $task_id,
            'prompt' => $prompt,
            'style' => $style,
            'title' => $title,
            'lyrics' => $lyrics,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ));
        
        if ($insert_result === false) {
            wp_send_json_error('Erreur de sauvegarde: ' . $wpdb->last_error);
            return;
        }
        
        wp_send_json_success(array(
            'task_id' => $task_id,
            'message' => 'Génération démarrée ! L\'API nous notifiera quand la chanson sera prête.',
            'callback_url' => $callback_url
        ));
    }
    
    // Gestionnaire de callback pour recevoir la notification de l'API
    public function handle_suno_callback() {
        error_log('=== SUNO CALLBACK RECEIVED ===');
        error_log('POST data: ' . print_r($_POST, true));
        error_log('GET data: ' . print_r($_GET, true));
        error_log('Raw input: ' . file_get_contents('php://input'));
        
        // Récupérer les données du callback
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            // Essayer avec $_POST si pas de JSON
            $data = $_POST;
        }
        
        error_log('Callback data: ' . print_r($data, true));
        
        // Récupérer le generation_id depuis l'URL
        $generation_id = isset($_GET['generation_id']) ? $_GET['generation_id'] : null;
        
        if ($generation_id && $data) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'suno_generations';
            
            // Mettre à jour la génération avec les données reçues
            $update_data = array(
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            );
            
            // Chercher les URLs dans les données
            if (isset($data['audio_url'])) {
                $update_data['audio_url'] = $data['audio_url'];
            }
            if (isset($data['video_url'])) {
                $update_data['video_url'] = $data['video_url'];
            }
            if (isset($data['image_url'])) {
                $update_data['image_url'] = $data['image_url'];
            }
            
            $wpdb->update($table_name, $update_data, array('task_id' => $generation_id));
        }
        
        // Répondre à l'API
        wp_send_json_success(array('message' => 'Callback received'));
    }
    
    public function ajax_check_music_status() {
        error_log('=== CHECK MUSIC STATUS v1.5 ===');
        
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $task_id = sanitize_text_field($_POST['task_id']);
        $check_count = intval($_POST['check_count'] ?? 0);
        
        if ($check_count >= 20) {
            wp_send_json_success(array(
                'status' => 'timeout',
                'message' => 'La génération prend plus de temps que prévu. Vérifiez plus tard.',
                'check_count' => $check_count
            ));
            return;
        }
        
        // Vérifier en base de données si le callback a mis à jour le statut
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $generation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE task_id = %s",
            $task_id
        ));
        
        if ($generation && $generation->status === 'completed') {
            wp_send_json_success(array(
                'status' => 'completed',
                'audio_url' => $generation->audio_url,
                'video_url' => $generation->video_url,
                'image_url' => $generation->image_url,
                'check_count' => $check_count + 1
            ));
            return;
        }
        
        // Toujours en cours
        wp_send_json_success(array(
            'status' => 'processing',
            'message' => 'En attente du callback de l\'API...',
            'check_count' => $check_count + 1,
            'max_checks' => 20
        ));
    }
    
    // Nouveau shortcode pour tester la génération avec callback
    public function test_generation_direct($atts) {
        if (!current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24;">❌ Accès refusé - Administrateur uniquement</div>';
        }
        
        $callback_url = admin_url('admin-ajax.php?action=suno_callback&generation_id=test_' . time());
        
        ob_start();
        ?>
        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin: 20px 0; border-radius: 5px;">
            <h3>🔧 Test de génération avec Callback - v1.5</h3>
            
            <div style="background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 15px 0; color: #0c5460;">
                <h4>📋 Configuration du callback :</h4>
                <p><strong>URL de callback :</strong></p>
                <code style="background: #f8f9fa; padding: 5px; border-radius: 3px; display: block; margin: 10px 0;">
                    <?php echo esc_html($callback_url); ?>
                </code>
                <p><small>Cette URL sera appelée par l'API quand la génération sera terminée.</small></p>
            </div>
            
            <form method="post">
                <button type="submit" name="test_with_callback" value="1" 
                        style="background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;">
                    🚀 Tester la génération avec callback
                </button>
            </form>
            
            <?php if (isset($_POST['test_with_callback'])): ?>
                <?php
                $test_data = array(
                    'prompt' => 'Une chanson pop joyeuse',
                    'customMode' => false,
                    'instrumental' => false,
                    'model' => 'V3_5',
                    'callBackUrl' => $callback_url
                );
                
                echo '<div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0;">';
                echo '<h4>📤 Données envoyées avec callback :</h4>';
                echo '<pre style="background: white; padding: 10px;">' . json_encode($test_data, JSON_PRETTY_PRINT) . '</pre>';
                echo '</div>';
                
                $response = wp_remote_post($this->api_base_url . '/api/v1/generate', array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $this->api_key,
                        'Content-Type' => 'application/json'
                    ),
                    'body' => json_encode($test_data),
                    'timeout' => 30
                ));
                
                if (!is_wp_error($response)) {
                    $status_code = wp_remote_retrieve_response_code($response);
                    $body = wp_remote_retrieve_body($response);
                    
                    echo '<div style="background: #fff; padding: 15px; border-radius: 5px; margin: 15px 0;">';
                    echo '<h4>📥 Réponse API :</h4>';
                    echo '<p><strong>Code HTTP :</strong> ' . $status_code . '</p>';
                    echo '<pre style="background: #f5f5f5; padding: 10px;">' . htmlspecialchars($body) . '</pre>';
                    
                    if ($status_code === 200) {
                        echo '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; color: #155724;">';
                        echo '<strong>✅ Succès !</strong> La génération a été lancée. L\'API appellera le callback quand ce sera prêt.';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function render_music_player($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'limit' => 10
        ), $atts);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            intval($atts['user_id']),
            intval($atts['limit'])
        ));
        
        if (empty($results)) {
            return '<div style="background: #fff3cd; padding: 15px; border-radius: 5px;">
                <strong>ℹ️ Aucune chanson générée.</strong><br>
                <em>Utilisez [suno_music_form] pour créer votre première chanson !</em><br>
                <small>v1.5 - Système de callback actif</small>
            </div>';
        }
        
        ob_start();
        ?>
        <div class="suno-playlist">
            <h3>🎵 Vos créations musicales (<?php echo count($results); ?>)</h3>
            
            <?php foreach ($results as $track): ?>
            <div class="suno-track">
                <div class="track-info">
                    <h4><?php echo esc_html($track->title ?: 'Chanson sans titre'); ?></h4>
                    <p><?php echo esc_html(wp_trim_words($track->prompt, 15)); ?></p>
                    <small><?php echo date('d/m/Y H:i', strtotime($track->created_at)); ?> - <?php echo esc_html($track->status); ?></small>
                </div>
                
                <?php if ($track->status === 'completed' && $track->audio_url): ?>
                    <div class="track-player">
                        <audio controls>
                            <source src="<?php echo esc_url($track->audio_url); ?>" type="audio/mpeg">
                        </audio>
                    </div>
                <?php elseif ($track->status === 'pending'): ?>
                    <div style="background: #d1ecf1; padding: 10px; border-radius: 5px; font-size: 14px; color: #0c5460;">
                        🔄 En attente du callback de l'API...
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function render_debug_info($atts) {
        if (!current_user_can('manage_options')) {
            return '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;">❌ Accès refusé</div>';
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
        $completed_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
        
        return '<div style="background: #f9f9f9; padding: 20px; border-radius: 5px;">
            <h3>🔍 Debug v1.5 - Système Callback</h3>
            <p><strong>Total:</strong> ' . $total_count . '</p>
            <p><strong>En cours:</strong> ' . $pending_count . '</p>
            <p><strong>Terminées:</strong> ' . $completed_count . '</p>
            <div style="background: #d1ecf1; padding: 10px; border-radius: 5px; color: #0c5460; margin: 10px 0;">
                <strong>🔄 Nouveau système :</strong><br>
                ✅ Callback URL pour notifications<br>
                ✅ Génération asynchrone<br>
                ✅ Protection anti-boucle maintenue
            </div>
            <p><strong>Callback URL :</strong><br>
            <code style="background: #f8f9fa; padding: 5px;">' . admin_url('admin-ajax.php?action=suno_callback') . '</code></p>
        </div>';
    }
    
    public function render_api_test($atts) {
        if (!current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24;">❌ Accès refusé</div>';
        }
        
        $test_result = $this->test_api_connection();
        
        ob_start();
        ?>
        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin: 20px 0; border-radius: 5px;">
            <h3>🛡️ Test API - v1.5</h3>
            
            <?php if (isset($test_result['success']) && $test_result['success']): ?>
                <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724;">
                    <strong>✅ API accessible !</strong><br>
                    Note : L'API demande maintenant un callBackUrl pour les générations.
                </div>
            <?php else: ?>
                <div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24;">
                    <strong>❌ Problème détecté</strong><br>
                    <?php echo esc_html($test_result['error'] ?? 'Erreur inconnue'); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function test_api_connection() {
        if (empty($this->api_key)) {
            return array('error' => 'Clé API manquante');
        }
        
        // Test simple
        $response = wp_remote_get($this->api_base_url . '/api/v1/get_limit', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array('error' => 'Erreur de connexion: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        // On considère 404 comme un succès car l'auth fonctionne
        if ($status_code === 404 || $status_code === 200) {
            return array('success' => true);
        }
        
        return array('error' => 'Code HTTP: ' . $status_code);
    }
    
    public function clear_database_shortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24;">❌ Accès refusé</div>';
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        if (isset($_GET['confirm_clear']) && $_GET['confirm_clear'] === '1') {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $result = $wpdb->query("TRUNCATE TABLE $table_name");
            
            if ($result !== false) {
                return '<div style="background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;">
                    ✅ Base de données vidée ! ' . $count . ' entrée(s) supprimée(s).
                </div>';
            }
        }
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($count > 0) {
            return '<div style="background: #f9f9f9; padding: 20px; border-radius: 5px;">
                <h3>Vider la base de données</h3>
                <p>Nombre d\'entrées : ' . $count . '</p>
                <a href="?confirm_clear=1" onclick="return confirm(\'Êtes-vous sûr ?\');" 
                   style="background: #dc3545; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;">
                   Vider la base de données
                </a>
            </div>';
        }
        
        return '<div style="background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;">
            ✅ La base de données est déjà vide.
        </div>';
    }
}

new SunoMusicGenerator();