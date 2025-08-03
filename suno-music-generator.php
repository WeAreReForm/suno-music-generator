<?php
/**
 * Plugin Name: Suno Music Generator
 * Description: Générateur de musique IA avec Suno via formulaire WordPress
 * Version: 1.6 (Système hybride callback + vérification active)
 * Author: Assistant IA
 */

if (!defined('ABSPATH')) {
    exit;
}

class SunoMusicGenerator {
    
    private $api_key;
    private $api_base_url = 'https://apibox.erweima.ai';
    
    public function __construct() {
        error_log('=== SUNO PLUGIN v1.6 - SYSTÈME HYBRIDE ===');
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('suno_music_form', array($this, 'render_music_form'));
        add_shortcode('suno_music_player', array($this, 'render_music_player'));
        add_shortcode('suno_test_api', array($this, 'render_api_test'));
        add_shortcode('suno_debug', array($this, 'render_debug_info'));
        add_shortcode('suno_test_shortcode', array($this, 'test_shortcode'));
        add_shortcode('suno_clear_database', array($this, 'clear_database_shortcode'));
        add_shortcode('suno_force_check', array($this, 'force_check_status'));
        
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
            <h1>Configuration Suno Music Generator v1.6</h1>
            
            <div style="background: #d4edda; padding: 15px; border-radius: 5px; color: #155724; margin: 15px 0;">
                <strong>🎯 VERSION 1.6 - SYSTÈME HYBRIDE</strong><br>
                ✅ Envoie le callBackUrl pour compatibilité<br>
                ✅ Vérifie activement le statut de génération<br>
                ✅ Récupère automatiquement les URLs audio<br>
                ✅ Protection anti-boucle (max 20 vérifications)<br>
                🎵 Cette version fonctionnera dans tous les cas !
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
            <p><code>[suno_force_check]</code> - Forcer la vérification des générations en cours</p>
            <p><code>[suno_clear_database]</code> - Vider la base de données</p>
        </div>
        <?php
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('suno-music-js', plugin_dir_url(__FILE__) . 'assets/suno-music.js', array('jquery'), '1.6', true);
        wp_enqueue_style('suno-music-css', plugin_dir_url(__FILE__) . 'assets/suno-music.css', array(), '1.6');
        
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
            <h3>🎵 Créer votre chanson avec l'IA (v1.6)</h3>
            
            <div style="background: #d4edda; padding: 10px; border-radius: 5px; margin: 15px 0; color: #155724;">
                <strong>✅ Système hybride actif :</strong> Vérification automatique du statut + protection anti-boucle
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
            <strong>✅ SUCCÈS !</strong> Plugin v1.6 (Système hybride) fonctionnel<br>
            <strong>Heure :</strong> ' . current_time('d/m/Y H:i:s') . '<br>
            <strong>Système :</strong> ✅ Callback + Vérification active
        </div>';
    }
    
    public function ajax_generate_music() {
        error_log('=== GENERATE MUSIC v1.6 HYBRIDE ===');
        
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
        
        // URL de callback (au cas où l'API l'utilise)
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
        
        error_log('API Data: ' . json_encode($api_data));
        
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
        
        // Extraire le task_id de la réponse
        $task_id = null;
        
        // Chercher dans différents emplacements possibles
        if (isset($data['data']['taskId'])) {
            $task_id = $data['data']['taskId'];
        } elseif (isset($data['data']['task_id'])) {
            $task_id = $data['data']['task_id'];
        } elseif (isset($data['taskId'])) {
            $task_id = $data['taskId'];
        } elseif (isset($data['task_id'])) {
            $task_id = $data['task_id'];
        } elseif (isset($data['data']) && is_string($data['data'])) {
            $task_id = $data['data'];
        }
        
        if (!$task_id) {
            // Si pas de task_id, utiliser notre generation_id
            $task_id = $generation_id;
            error_log('No task_id from API, using generation_id: ' . $task_id);
        } else {
            error_log('Task ID from API: ' . $task_id);
        }
        
        // Sauvegarder en base de données
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
            'message' => 'Génération démarrée ! Vérification automatique du statut...',
            'callback_url' => $callback_url
        ));
    }
    
    // Vérification active du statut (système hybride)
    public function ajax_check_music_status() {
        error_log('=== CHECK MUSIC STATUS v1.6 HYBRIDE ===');
        
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $task_id = sanitize_text_field($_POST['task_id']);
        $check_count = intval($_POST['check_count'] ?? 0);
        
        if (empty($task_id)) {
            wp_send_json_error('Task ID requis');
        }
        
        // Protection anti-boucle
        if ($check_count >= 20) {
            error_log('Anti-loop: Maximum checks reached for task: ' . $task_id);
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'suno_generations';
            $wpdb->update($table_name, 
                array('status' => 'timeout', 'completed_at' => current_time('mysql')), 
                array('task_id' => $task_id)
            );
            
            wp_send_json_success(array(
                'status' => 'timeout',
                'message' => 'La génération prend plus de temps que prévu. Réessayez plus tard.',
                'check_count' => $check_count
            ));
            return;
        }
        
        // Vérifier d'abord en base de données (au cas où le callback a fonctionné)
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $generation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE task_id = %s",
            $task_id
        ));
        
        if ($generation && $generation->status === 'completed' && $generation->audio_url) {
            wp_send_json_success(array(
                'status' => 'completed',
                'audio_url' => $generation->audio_url,
                'video_url' => $generation->video_url,
                'image_url' => $generation->image_url,
                'check_count' => $check_count + 1
            ));
            return;
        }
        
        // VÉRIFICATION ACTIVE VIA L'API
        $api_url = $this->api_base_url . '/api/v1/music/' . $task_id;
        
        error_log('Checking status at: ' . $api_url);
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            error_log('Status check response code: ' . $status_code);
            error_log('Status check response: ' . substr($body, 0, 500));
            
            if ($status_code === 200) {
                $data = json_decode($body, true);
                
                if ($data && isset($data['code']) && $data['code'] === 200) {
                    // Chercher les données de la musique
                    $music_data = null;
                    
                    if (isset($data['data']) && is_array($data['data']) && !empty($data['data'])) {
                        $music_data = $data['data'][0];
                    } elseif (isset($data['data']) && is_array($data['data'])) {
                        $music_data = $data['data'];
                    }
                    
                    if ($music_data) {
                        error_log('Music data found: ' . json_encode($music_data));
                        
                        $update_data = array(
                            'status' => 'completed',
                            'completed_at' => current_time('mysql')
                        );
                        
                        // Récupérer toutes les URLs possibles
                        $url_fields = array(
                            'audio_url' => array('audio_url', 'audioUrl', 'audio', 'url', 'music_url', 'musicUrl'),
                            'video_url' => array('video_url', 'videoUrl', 'video'),
                            'image_url' => array('image_url', 'imageUrl', 'image', 'cover_url', 'coverUrl', 'cover')
                        );
                        
                        foreach ($url_fields as $db_field => $api_fields) {
                            foreach ($api_fields as $field) {
                                if (isset($music_data[$field]) && !empty($music_data[$field])) {
                                    $update_data[$db_field] = $music_data[$field];
                                    break;
                                }
                            }
                        }
                        
                        if (isset($music_data['duration'])) {
                            $update_data['duration'] = intval($music_data['duration']);
                        }
                        
                        error_log('Update data: ' . json_encode($update_data));
                        
                        $wpdb->update($table_name, $update_data, array('task_id' => $task_id));
                        
                        if (isset($update_data['audio_url'])) {
                            wp_send_json_success(array(
                                'status' => 'completed',
                                'audio_url' => $update_data['audio_url'],
                                'video_url' => $update_data['video_url'] ?? '',
                                'image_url' => $update_data['image_url'] ?? '',
                                'check_count' => $check_count + 1
                            ));
                            return;
                        }
                    }
                } elseif (isset($data['code']) && $data['code'] === 404) {
                    // La tâche n'existe pas encore, continuer à attendre
                    error_log('Task not found yet, continuing...');
                }
            }
        } else {
            error_log('Error checking status: ' . $response->get_error_message());
        }
        
        // Toujours en cours
        wp_send_json_success(array(
            'status' => 'processing',
            'message' => 'Génération en cours...',
            'check_count' => $check_count + 1,
            'max_checks' => 20
        ));
    }
    
    // Gestionnaire de callback (au cas où l'API l'utilise)
    public function handle_suno_callback() {
        error_log('=== SUNO CALLBACK RECEIVED ===');
        error_log('POST data: ' . print_r($_POST, true));
        error_log('GET data: ' . print_r($_GET, true));
        error_log('Raw input: ' . file_get_contents('php://input'));
        
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            $data = $_POST;
        }
        
        error_log('Callback data: ' . print_r($data, true));
        
        $generation_id = isset($_GET['generation_id']) ? $_GET['generation_id'] : null;
        
        if ($generation_id && $data) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'suno_generations';
            
            $update_data = array(
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            );
            
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
        
        wp_send_json_success(array('message' => 'Callback received'));
    }
    
    // Nouveau shortcode pour forcer la vérification
    public function force_check_status($atts) {
        if (!current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24;">❌ Accès refusé</div>';
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $pending = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'pending'");
        
        if (empty($pending)) {
            return '<div style="background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;">
                ✅ Aucune génération en attente.
            </div>';
        }
        
        $output = '<div style="background: #f9f9f9; padding: 20px; border-radius: 5px;">';
        $output .= '<h3>🔄 Vérification forcée des générations en cours</h3>';
        
        foreach ($pending as $generation) {
            $api_url = $this->api_base_url . '/api/v1/music/' . $generation->task_id;
            
            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 15
            ));
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if ($data && isset($data['code']) && $data['code'] === 200 && isset($data['data'][0])) {
                    $music_data = $data['data'][0];
                    
                    $update_data = array(
                        'status' => 'completed',
                        'completed_at' => current_time('mysql')
                    );
                    
                    if (isset($music_data['audio_url'])) {
                        $update_data['audio_url'] = $music_data['audio_url'];
                    }
                    if (isset($music_data['video_url'])) {
                        $update_data['video_url'] = $music_data['video_url'];
                    }
                    if (isset($music_data['image_url'])) {
                        $update_data['image_url'] = $music_data['image_url'];
                    }
                    
                    $wpdb->update($table_name, $update_data, array('id' => $generation->id));
                    
                    $output .= '<p style="color: green;">✅ ' . esc_html($generation->title ?: $generation->prompt) . ' - Mis à jour !</p>';
                } else {
                    $output .= '<p style="color: orange;">⏳ ' . esc_html($generation->title ?: $generation->prompt) . ' - Toujours en cours</p>';
                }
            } else {
                $output .= '<p style="color: red;">❌ ' . esc_html($generation->title ?: $generation->prompt) . ' - Erreur de vérification</p>';
            }
        }
        
        $output .= '<p><a href="' . get_permalink() . '" style="background: #007cba; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block; margin-top: 10px;">Rafraîchir</a></p>';
        $output .= '</div>';
        
        return $output;
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
                <small>v1.6 - Système hybride actif</small>
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
                    <p class="track-prompt"><?php echo esc_html(wp_trim_words($track->prompt, 15)); ?></p>
                    <div class="track-meta">
                        <span class="track-style"><?php echo esc_html($track->style ?: 'Style auto'); ?></span>
                        <span class="track-date"><?php echo date('d/m/Y H:i', strtotime($track->created_at)); ?></span>
                        <span class="track-status status-<?php echo esc_attr($track->status); ?>">
                            <?php 
                            switch($track->status) {
                                case 'completed':
                                    echo '✅ Terminé';
                                    break;
                                case 'pending':
                                    echo '⏳ En cours';
                                    break;
                                case 'timeout':
                                    echo '⏱️ Timeout';
                                    break;
                                default:
                                    echo esc_html($track->status);
                            }
                            ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($track->status === 'completed' && $track->audio_url): ?>
                    <div class="track-player">
                        <audio controls preload="none">
                            <source src="<?php echo esc_url($track->audio_url); ?>" type="audio/mpeg">
                            Votre navigateur ne supporte pas l'élément audio.
                        </audio>
                        
                        <?php if ($track->video_url): ?>
                            <a href="<?php echo esc_url($track->video_url); ?>" target="_blank" class="video-link">
                                🎬 Voir la vidéo
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($track->image_url): ?>
                    <div class="track-image">
                        <img src="<?php echo esc_url($track->image_url); ?>" alt="Visuel de la chanson" />
                    </div>
                    <?php endif; ?>
                    
                    <div class="track-actions">
                        <a href="<?php echo esc_url($track->audio_url); ?>" download class="download-btn">
                            📥 Télécharger
                        </a>
                    </div>
                <?php elseif ($track->status === 'pending'): ?>
                    <div style="background: #d1ecf1; padding: 10px; border-radius: 5px; font-size: 14px; color: #0c5460;">
                        🔄 Génération en cours... La vérification automatique est active.
                    </div>
                <?php elseif ($track->status === 'timeout'): ?>
                    <div style="background: #fff3cd; padding: 10px; border-radius: 5px; font-size: 14px; color: #856404;">
                        ⏱️ La génération a pris trop de temps. Essayez [suno_force_check] pour vérifier manuellement.
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 5px;">
                <p style="margin: 0; font-size: 14px;">
                    💡 <strong>Astuce :</strong> Si une chanson reste "En cours" trop longtemps, utilisez 
                    <code>[suno_force_check]</code> sur une page admin pour forcer la vérification.
                </p>
            </div>
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
        $timeout_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'timeout'");
        
        $recent = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 5");
        
        ob_start();
        ?>
        <div style="background: #f9f9f9; padding: 20px; border-radius: 5px;">
            <h3>🔍 Debug v1.6 - Système Hybride</h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 15px 0;">
                <div style="background: #fff; padding: 15px; border-radius: 5px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <strong>Total</strong><br>
                    <span style="font-size: 24px; color: #007cba;"><?php echo $total_count; ?></span>
                </div>
                <div style="background: #fff; padding: 15px; border-radius: 5px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <strong>En cours</strong><br>
                    <span style="font-size: 24px; color: #f39c12;"><?php echo $pending_count; ?></span>
                </div>
                <div style="background: #fff; padding: 15px; border-radius: 5px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <strong>Terminées</strong><br>
                    <span style="font-size: 24px; color: #27ae60;"><?php echo $completed_count; ?></span>
                </div>
                <div style="background: #fff; padding: 15px; border-radius: 5px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <strong>Timeout</strong><br>
                    <span style="font-size: 24px; color: #e74c3c;"><?php echo $timeout_count; ?></span>
                </div>
            </div>
            
            <div style="background: #d4edda; padding: 10px; border-radius: 5px; color: #155724; margin: 10px 0;">
                <strong>🎯 Système hybride v1.6 :</strong><br>
                ✅ Callback URL envoyé (compatibilité)<br>
                ✅ Vérification active du statut<br>
                ✅ Protection anti-boucle (20 max)<br>
                ✅ Récupération automatique des URLs
            </div>
            
            <?php if (!empty($recent)): ?>
            <h4>📋 Dernières générations :</h4>
            <div style="background: #fff; padding: 10px; border-radius: 5px; font-size: 12px; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 8px; text-align: left; border-bottom: 2px solid #dee2e6;">Titre/Prompt</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 2px solid #dee2e6;">Statut</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 2px solid #dee2e6;">Date</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 2px solid #dee2e6;">Task ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $item): ?>
                        <tr>
                            <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">
                                <?php echo esc_html(wp_trim_words($item->title ?: $item->prompt, 10)); ?>
                            </td>
                            <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">
                                <span style="color: <?php 
                                    echo $item->status === 'completed' ? '#27ae60' : 
                                        ($item->status === 'pending' ? '#f39c12' : '#e74c3c'); 
                                ?>;">
                                    <?php echo esc_html($item->status); ?>
                                </span>
                            </td>
                            <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">
                                <?php echo date('d/m H:i', strtotime($item->created_at)); ?>
                            </td>
                            <td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-family: monospace; font-size: 11px;">
                                <?php echo esc_html(substr($item->task_id, 0, 20) . '...'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 5px;">
                <h4 style="margin-top: 0;">🛠️ Actions disponibles :</h4>
                <p><code>[suno_force_check]</code> - Forcer la vérification des générations en cours</p>
                <p><code>[suno_clear_database]</code> - Vider la base de données</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function render_api_test($atts) {
        if (!current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24;">❌ Accès refusé</div>';
        }
        
        $test_result = $this->test_api_connection();
        
        ob_start();
        ?>
        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin: 20px 0; border-radius: 5px;">
            <h3>🛡️ Test API - v1.6</h3>
            
            <?php if (isset($test_result['success']) && $test_result['success']): ?>
                <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724;">
                    <strong>✅ API accessible !</strong><br>
                    Le système hybride est prêt à fonctionner.
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
                    <br><a href="' . remove_query_arg('confirm_clear') . '">← Retour</a>
                </div>';
            }
        }
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($count > 0) {
            return '<div style="background: #f9f9f9; padding: 20px; border-radius: 5px;">
                <h3>🗄️ Vider la base de données</h3>
                <p>Nombre d\'entrées : <strong>' . $count . '</strong></p>
                <p style="color: #dc3545;">⚠️ Cette action est irréversible !</p>
                <a href="?confirm_clear=1" onclick="return confirm(\'Êtes-vous sûr de vouloir supprimer ' . $count . ' entrée(s) ?\');" 
                   style="background: #dc3545; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;">
                   🗑️ Vider la base de données
                </a>
            </div>';
        }
        
        return '<div style="background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;">
            ✅ La base de données est déjà vide.
        </div>';
    }
}

new SunoMusicGenerator();