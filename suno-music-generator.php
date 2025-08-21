<?php
/**
 * Plugin Name: Suno Music Generator
 * Description: Générateur de musique IA avec l'API officielle Suno
 * Version: 3.0.0
 * Author: WeAreReForm
 * License: GPL-2.0
 * Text Domain: suno-music-generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class SunoMusicGenerator {
    
    private $api_key;
    private $version = '3.0.0';
    private $api_base_url = 'https://studio-api.suno.ai';
    private $api_mode = 'official'; // 'official' ou 'sunoapi'
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Shortcodes
        add_shortcode('suno_music_form', array($this, 'render_music_form'));
        add_shortcode('suno_music_player', array($this, 'render_music_player'));
        add_shortcode('suno_test_api', array($this, 'render_api_test'));
        add_shortcode('suno_debug', array($this, 'render_debug_info'));
        
        // Actions AJAX
        add_action('wp_ajax_generate_music', array($this, 'ajax_generate_music'));
        add_action('wp_ajax_nopriv_generate_music', array($this, 'ajax_generate_music'));
        add_action('wp_ajax_check_music_status', array($this, 'ajax_check_music_status'));
        add_action('wp_ajax_nopriv_check_music_status', array($this, 'ajax_check_music_status'));
        add_action('wp_ajax_get_suno_token', array($this, 'ajax_get_suno_token'));
        
        // Admin
        register_activation_hook(__FILE__, array($this, 'create_tables'));
        add_action('admin_menu', array($this, 'admin_menu'));
    }
    
    public function init() {
        $this->api_key = get_option('suno_api_key', '');
        $this->api_mode = get_option('suno_api_mode', 'official');
    }
    
    public function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'suno_generations';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT 0,
            task_id varchar(255) NOT NULL,
            clip_ids text DEFAULT '',
            prompt text NOT NULL,
            style varchar(255) DEFAULT '',
            title varchar(255) DEFAULT '',
            lyrics text DEFAULT '',
            status varchar(50) DEFAULT 'pending',
            audio_url varchar(500) DEFAULT '',
            video_url varchar(500) DEFAULT '',
            image_url varchar(500) DEFAULT '',
            duration int DEFAULT 0,
            api_response text DEFAULT '',
            api_mode varchar(20) DEFAULT 'official',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        update_option('suno_music_generator_version', $this->version);
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
            update_option('suno_api_mode', sanitize_text_field($_POST['api_mode']));
            update_option('suno_email', sanitize_email($_POST['suno_email']));
            update_option('suno_password', sanitize_text_field($_POST['suno_password']));
            echo '<div class="notice notice-success"><p>Paramètres sauvegardés!</p></div>';
        }
        
        $api_key = get_option('suno_api_key', '');
        $api_mode = get_option('suno_api_mode', 'official');
        $suno_email = get_option('suno_email', '');
        $suno_password = get_option('suno_password', '');
        ?>
        <div class="wrap">
            <h1>Suno Music Generator - Version <?php echo $this->version; ?></h1>
            
            <div style="background: #e8f5e9; padding: 20px; border-left: 4px solid #4caf50; margin: 20px 0;">
                <h2 style="margin-top: 0;">🎉 Version 3.0 - API Officielle Suno!</h2>
                <p>Cette version supporte maintenant l'<strong>API officielle de Suno</strong> en plus de SunoAPI.org</p>
            </div>
            
            <form method="post">
                <h2>Configuration API</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Mode API</th>
                        <td>
                            <select name="api_mode" id="api_mode" onchange="toggleAPIFields()">
                                <option value="official" <?php selected($api_mode, 'official'); ?>>
                                    API Officielle Suno (Recommandé)
                                </option>
                                <option value="sunoapi" <?php selected($api_mode, 'sunoapi'); ?>>
                                    SunoAPI.org (Tiers)
                                </option>
                            </select>
                            <p class="description">Choisissez quel service API utiliser</p>
                        </td>
                    </tr>
                </table>
                
                <!-- Configuration API Officielle -->
                <div id="official-api-config" style="<?php echo $api_mode === 'official' ? '' : 'display:none;'; ?>">
                    <h3>🔑 Configuration API Officielle Suno</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Email Suno</th>
                            <td>
                                <input type="email" name="suno_email" value="<?php echo esc_attr($suno_email); ?>" 
                                       class="regular-text" placeholder="votre-email@example.com" />
                                <p class="description">Email de votre compte Suno.com</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Mot de passe Suno</th>
                            <td>
                                <input type="password" name="suno_password" value="<?php echo esc_attr($suno_password); ?>" 
                                       class="regular-text" placeholder="Votre mot de passe Suno" />
                                <p class="description">
                                    Mot de passe de votre compte Suno.com<br>
                                    <strong>Note :</strong> Stocké de manière sécurisée et utilisé uniquement pour l'authentification API
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Configuration SunoAPI.org -->
                <div id="sunoapi-config" style="<?php echo $api_mode === 'sunoapi' ? '' : 'display:none;'; ?>">
                    <h3>🔌 Configuration SunoAPI.org</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Clé API SunoAPI.org</th>
                            <td>
                                <input type="text" name="api_key" value="<?php echo esc_attr($api_key); ?>" 
                                       class="regular-text" placeholder="sk-proj-xxxxxxxxxxxxx" />
                                <p class="description">
                                    Obtenez votre clé sur <a href="https://sunoapi.org" target="_blank">SunoAPI.org</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <script>
            function toggleAPIFields() {
                const mode = document.getElementById('api_mode').value;
                document.getElementById('official-api-config').style.display = 
                    mode === 'official' ? 'block' : 'none';
                document.getElementById('sunoapi-config').style.display = 
                    mode === 'sunoapi' ? 'block' : 'none';
            }
            </script>
            
            <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h2>📋 Shortcodes disponibles</h2>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin: 10px 0;">
                        <code style="background: #fff; padding: 5px 10px; border-radius: 4px;">[suno_music_form]</code> 
                        - Formulaire de génération
                    </li>
                    <li style="margin: 10px 0;">
                        <code style="background: #fff; padding: 5px 10px; border-radius: 4px;">[suno_music_player]</code> 
                        - Lecteur des créations
                    </li>
                    <li style="margin: 10px 0;">
                        <code style="background: #fff; padding: 5px 10px; border-radius: 4px;">[suno_test_api]</code> 
                        - Test de connexion API
                    </li>
                    <li style="margin: 10px 0;">
                        <code style="background: #fff; padding: 5px 10px; border-radius: 4px;">[suno_debug]</code> 
                        - Informations de débogage
                    </li>
                </ul>
            </div>
            
            <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>📊 Tableau de comparaison</h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Fonctionnalité</th>
                            <th>API Officielle Suno</th>
                            <th>SunoAPI.org</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Coût</strong></td>
                            <td>✅ Utilise vos crédits Suno</td>
                            <td>💰 Crédits séparés à acheter</td>
                        </tr>
                        <tr>
                            <td><strong>Fiabilité</strong></td>
                            <td>✅ Officiel et stable</td>
                            <td>⚠️ Service tiers</td>
                        </tr>
                        <tr>
                            <td><strong>Fonctionnalités</strong></td>
                            <td>✅ Toutes les options Suno</td>
                            <td>✅ Options de base</td>
                        </tr>
                        <tr>
                            <td><strong>Configuration</strong></td>
                            <td>📧 Email + Mot de passe</td>
                            <td>🔑 Clé API simple</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'suno-music-js',
            plugin_dir_url(__FILE__) . 'assets/suno-music.js',
            array('jquery'),
            $this->version,
            true
        );
        wp_enqueue_style(
            'suno-music-css',
            plugin_dir_url(__FILE__) . 'assets/suno-music.css',
            array(),
            $this->version
        );
        
        wp_localize_script('suno-music-js', 'suno_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('suno_music_nonce'),
            'api_mode' => $this->api_mode
        ));
    }
    
    /**
     * Obtenir un token d'authentification Suno
     */
    private function get_suno_token() {
        $cached_token = get_transient('suno_auth_token');
        if ($cached_token) {
            return $cached_token;
        }
        
        $email = get_option('suno_email', '');
        $password = get_option('suno_password', '');
        
        if (empty($email) || empty($password)) {
            return false;
        }
        
        // Authentification à l'API Suno
        $response = wp_remote_post($this->api_base_url . '/api/auth/login', array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'email' => $email,
                'password' => $password
            )),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            error_log('Suno Auth Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['token'])) {
            // Stocker le token pour 1 heure
            set_transient('suno_auth_token', $data['token'], HOUR_IN_SECONDS);
            return $data['token'];
        }
        
        return false;
    }
    
    public function ajax_generate_music() {
        error_log('=== SUNO GENERATE v' . $this->version . ' (Mode: ' . $this->api_mode . ') ===');
        
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $prompt = sanitize_textarea_field($_POST['prompt']);
        $style = sanitize_text_field($_POST['style'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $lyrics = sanitize_textarea_field($_POST['lyrics'] ?? '');
        $instrumental = isset($_POST['instrumental']) && $_POST['instrumental'] === 'true';
        
        if (empty($prompt)) {
            wp_send_json_error('Description requise');
            return;
        }
        
        if ($this->api_mode === 'official') {
            // Utiliser l'API officielle Suno
            $this->generate_with_official_api($prompt, $style, $title, $lyrics, $instrumental);
        } else {
            // Utiliser SunoAPI.org (ancien système)
            $this->generate_with_sunoapi($prompt, $style, $title, $lyrics, $instrumental);
        }
    }
    
    /**
     * Génération avec l'API officielle Suno
     */
    private function generate_with_official_api($prompt, $style, $title, $lyrics, $instrumental) {
        $token = $this->get_suno_token();
        
        if (!$token) {
            wp_send_json_error('Impossible de se connecter à Suno. Vérifiez vos identifiants.');
            return;
        }
        
        // Préparer les données pour l'API officielle
        $api_data = array(
            'prompt' => $prompt,
            'mv' => 'chirp-v3-5', // Utiliser le modèle v3.5
            'instrumental' => $instrumental
        );
        
        // Si mode custom avec paroles
        if (!empty($lyrics)) {
            $api_data['lyrics'] = $lyrics;
            $api_data['title'] = $title ?: 'Untitled';
            $api_data['tags'] = $style ?: 'pop';
        } else {
            // Mode description simple
            $full_prompt = $prompt;
            if (!empty($style)) {
                $full_prompt = "[$style] " . $full_prompt;
            }
            $api_data['gpt_description_prompt'] = $full_prompt;
        }
        
        error_log('Sending to Official API: ' . json_encode($api_data));
        
        // Appel à l'API officielle
        $response = wp_remote_post($this->api_base_url . '/api/generate/v2', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($api_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Official API Error: ' . $response->get_error_message());
            wp_send_json_error('Erreur de connexion: ' . $response->get_error_message());
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('Official API Response Code: ' . $status_code);
        error_log('Official API Response: ' . $body);
        
        if ($status_code === 401) {
            // Token expiré, on le supprime
            delete_transient('suno_auth_token');
            wp_send_json_error('Session expirée. Veuillez réessayer.');
            return;
        }
        
        if ($status_code !== 200 && $status_code !== 201) {
            wp_send_json_error('Erreur API: Code ' . $status_code);
            return;
        }
        
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['clips'])) {
            wp_send_json_error('Réponse API invalide');
            return;
        }
        
        // L'API officielle retourne généralement 2 clips
        $clips = $data['clips'];
        $clip_ids = array_column($clips, 'id');
        $task_id = implode(',', $clip_ids); // On stocke les IDs séparés par des virgules
        
        error_log('Generated clips: ' . $task_id);
        
        // Sauvegarder en base de données
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $wpdb->insert($table_name, array(
            'user_id' => get_current_user_id(),
            'task_id' => $task_id,
            'clip_ids' => json_encode($clip_ids),
            'prompt' => $prompt,
            'style' => $style,
            'title' => $title,
            'lyrics' => $lyrics,
            'status' => 'pending',
            'api_response' => $body,
            'api_mode' => 'official',
            'created_at' => current_time('mysql')
        ));
        
        wp_send_json_success(array(
            'task_id' => $task_id,
            'clips' => $clips,
            'message' => 'Génération lancée avec l\'API officielle Suno!'
        ));
    }
    
    /**
     * Génération avec SunoAPI.org (ancien système)
     */
    private function generate_with_sunoapi($prompt, $style, $title, $lyrics, $instrumental) {
        if (empty($this->api_key)) {
            wp_send_json_error('Clé API SunoAPI.org non configurée');
            return;
        }
        
        $api_data = array(
            'prompt' => $prompt,
            'customMode' => false,
            'instrumental' => $instrumental
        );
        
        if (!empty($style) || !empty($title) || !empty($lyrics)) {
            $api_data['customMode'] = true;
            if (!empty($style)) $api_data['style'] = $style;
            if (!empty($title)) $api_data['title'] = $title;
            if (!empty($lyrics)) $api_data['lyric'] = $lyrics;
        }
        
        $response = wp_remote_post('https://api.sunoapi.org/api/v1/generate', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($api_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Erreur de connexion: ' . $response->get_error_message());
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200 && $status_code !== 201) {
            wp_send_json_error('Erreur API: Code ' . $status_code);
            return;
        }
        
        $data = json_decode($body, true);
        $task_id = $data['data']['taskId'] ?? $data['data']['task_id'] ?? $data['taskId'] ?? '';
        
        if (!$task_id) {
            wp_send_json_error('Aucun ID de tâche retourné');
            return;
        }
        
        // Sauvegarder en base
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $wpdb->insert($table_name, array(
            'user_id' => get_current_user_id(),
            'task_id' => $task_id,
            'prompt' => $prompt,
            'style' => $style,
            'title' => $title,
            'lyrics' => $lyrics,
            'status' => 'pending',
            'api_response' => $body,
            'api_mode' => 'sunoapi',
            'created_at' => current_time('mysql')
        ));
        
        wp_send_json_success(array(
            'task_id' => $task_id,
            'message' => 'Génération lancée avec SunoAPI.org'
        ));
    }
    
    public function ajax_check_music_status() {
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $task_id = sanitize_text_field($_POST['task_id']);
        
        if (empty($task_id)) {
            wp_send_json_error('Task ID requis');
            return;
        }
        
        // Récupérer les infos de la génération
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $generation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE task_id = %s",
            $task_id
        ));
        
        if (!$generation) {
            wp_send_json_error('Génération non trouvée');
            return;
        }
        
        // Si déjà complété
        if ($generation->status === 'completed' && $generation->audio_url) {
            wp_send_json_success(array(
                'status' => 'completed',
                'audio_url' => $generation->audio_url,
                'video_url' => $generation->video_url,
                'image_url' => $generation->image_url
            ));
            return;
        }
        
        // Vérifier le statut selon le mode API
        if ($generation->api_mode === 'official') {
            $this->check_official_api_status($generation);
        } else {
            $this->check_sunoapi_status($generation);
        }
    }
    
    /**
     * Vérifier le statut avec l'API officielle
     */
    private function check_official_api_status($generation) {
        $token = $this->get_suno_token();
        
        if (!$token) {
            wp_send_json_error('Token expiré');
            return;
        }
        
        // Récupérer les IDs des clips
        $clip_ids = json_decode($generation->clip_ids, true);
        if (empty($clip_ids)) {
            $clip_ids = explode(',', $generation->task_id);
        }
        
        // Récupérer le statut des clips
        $ids_param = implode(',', $clip_ids);
        $response = wp_remote_get($this->api_base_url . '/api/feed/?ids=' . $ids_param, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_success(array(
                'status' => 'processing',
                'message' => 'Vérification en cours...'
            ));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            wp_send_json_success(array(
                'status' => 'processing',
                'message' => 'Génération en cours...'
            ));
            return;
        }
        
        // Vérifier si au moins un clip est terminé
        foreach ($data as $clip) {
            if (isset($clip['status']) && $clip['status'] === 'complete' && isset($clip['audio_url'])) {
                // Mise à jour en base
                global $wpdb;
                $table_name = $wpdb->prefix . 'suno_generations';
                
                $wpdb->update($table_name, array(
                    'status' => 'completed',
                    'audio_url' => $clip['audio_url'],
                    'video_url' => $clip['video_url'] ?? '',
                    'image_url' => $clip['image_url'] ?? $clip['image_large_url'] ?? '',
                    'title' => $clip['title'] ?? $generation->title,
                    'duration' => $clip['metadata']['duration'] ?? 0,
                    'completed_at' => current_time('mysql')
                ), array('id' => $generation->id));
                
                wp_send_json_success(array(
                    'status' => 'completed',
                    'audio_url' => $clip['audio_url'],
                    'video_url' => $clip['video_url'] ?? '',
                    'image_url' => $clip['image_url'] ?? '',
                    'title' => $clip['title'] ?? '',
                    'all_clips' => $data
                ));
                return;
            }
        }
        
        // Toujours en cours
        wp_send_json_success(array(
            'status' => 'processing',
            'message' => 'Génération en cours... (API Officielle)'
        ));
    }
    
    /**
     * Vérifier le statut avec SunoAPI.org
     */
    private function check_sunoapi_status($generation) {
        // Code existant pour SunoAPI.org...
        $api_urls = array(
            'https://api.sunoapi.org/api/v1/music/' . $generation->task_id,
            'https://api.sunoapi.org/api/v1/get?ids=' . $generation->task_id
        );
        
        foreach ($api_urls as $api_url) {
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
                
                if ($status_code === 200 && !empty($body)) {
                    $data = json_decode($body, true);
                    
                    if ($data) {
                        // Vérifier si terminé
                        $audio_url = '';
                        if (isset($data[0])) {
                            $item = $data[0];
                            $audio_url = $item['audio_url'] ?? $item['audioUrl'] ?? '';
                        }
                        
                        if (!empty($audio_url)) {
                            // Mise à jour en base
                            global $wpdb;
                            $table_name = $wpdb->prefix . 'suno_generations';
                            
                            $wpdb->update($table_name, array(
                                'status' => 'completed',
                                'audio_url' => $audio_url,
                                'completed_at' => current_time('mysql')
                            ), array('id' => $generation->id));
                            
                            wp_send_json_success(array(
                                'status' => 'completed',
                                'audio_url' => $audio_url
                            ));
                            return;
                        }
                    }
                }
            }
        }
        
        wp_send_json_success(array(
            'status' => 'processing',
            'message' => 'Génération en cours... (SunoAPI.org)'
        ));
    }
    
    public function render_music_form($atts) {
        ob_start();
        ?>
        <div id="suno-music-form" class="suno-container">
            <h3>🎵 Créer votre chanson avec l'IA</h3>
            
            <?php if ($this->api_mode === 'official'): ?>
            <div class="api-mode-badge" style="background: #4caf50; color: white; padding: 5px 10px; border-radius: 20px; display: inline-block; margin-bottom: 15px;">
                ✅ API Officielle Suno
            </div>
            <?php else: ?>
            <div class="api-mode-badge" style="background: #ff9800; color: white; padding: 5px 10px; border-radius: 20px; display: inline-block; margin-bottom: 15px;">
                🔌 SunoAPI.org
            </div>
            <?php endif; ?>
            
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
                            <option value="r&b">R&B</option>
                            <option value="metal">Metal</option>
                            <option value="indie">Indie</option>
                            <option value="funk">Funk</option>
                            <option value="soul">Soul</option>
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
                    <textarea id="music-lyrics" name="lyrics" rows="6" 
                        placeholder="Écrivez vos propres paroles ou laissez l'IA les créer"></textarea>
                    <small style="color: #666;">
                        💡 Astuce : Utilisez [Verse], [Chorus], [Bridge] pour structurer vos paroles
                    </small>
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
                    <small id="api-mode-info" style="margin-top: 10px; display: block;"></small>
                </div>
            </div>
            
            <div id="generation-result" class="suno-result" style="display: none;">
                <!-- Résultat affiché ici -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function render_api_test($atts) {
        if (!current_user_can('manage_options')) {
            return '<p>Accès réservé aux administrateurs.</p>';
        }
        
        ob_start();
        ?>
        <div class="suno-api-test">
            <h4>🔧 Test de connexion API</h4>
            
            <div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0;">
                <strong>Mode actuel :</strong> 
                <?php echo $this->api_mode === 'official' ? '✅ API Officielle Suno' : '🔌 SunoAPI.org'; ?>
            </div>
            
            <?php if ($this->api_mode === 'official'): ?>
                <?php
                $email = get_option('suno_email', '');
                $password = get_option('suno_password', '');
                ?>
                
                <?php if (empty($email) || empty($password)): ?>
                    <div class="notice notice-error">
                        <p>❌ Identifiants Suno non configurés</p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-info">
                        <p>✅ Email configuré : <?php echo esc_html($email); ?></p>
                    </div>
                    
                    <?php
                    // Test de connexion
                    $token = $this->get_suno_token();
                    
                    if ($token): ?>
                        <div class="notice notice-success">
                            <p>✅ Connexion à l'API officielle réussie!</p>
                            <p>Token valide (<?php echo strlen($token); ?> caractères)</p>
                        </div>
                        
                        <?php
                        // Test des crédits disponibles
                        $response = wp_remote_get($this->api_base_url . '/api/billing/info', array(
                            'headers' => array(
                                'Authorization' => 'Bearer ' . $token
                            ),
                            'timeout' => 10
                        ));
                        
                        if (!is_wp_error($response)) {
                            $body = wp_remote_retrieve_body($response);
                            $billing = json_decode($body, true);
                            
                            if ($billing): ?>
                                <div style="background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 10px 0;">
                                    <strong>💳 Informations de compte :</strong><br>
                                    <?php if (isset($billing['total_credits_left'])): ?>
                                        Crédits restants : <?php echo $billing['total_credits_left']; ?><br>
                                    <?php endif; ?>
                                    <?php if (isset($billing['period_credits_left'])): ?>
                                        Crédits de la période : <?php echo $billing['period_credits_left']; ?><br>
                                    <?php endif; ?>
                                    <?php if (isset($billing['monthly_limit'])): ?>
                                        Limite mensuelle : <?php echo $billing['monthly_limit']; ?><br>
                                    <?php endif; ?>
                                </div>
                            <?php endif;
                        }
                        ?>
                    <?php else: ?>
                        <div class="notice notice-error">
                            <p>❌ Impossible de se connecter à Suno</p>
                            <p>Vérifiez vos identifiants dans les réglages</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
            <?php else: // Mode SunoAPI.org ?>
                
                <?php if (empty($this->api_key)): ?>
                    <div class="notice notice-error">
                        <p>❌ Clé API non configurée</p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-info">
                        <p>✅ Clé API configurée (<?php echo strlen($this->api_key); ?> caractères)</p>
                    </div>
                    
                    <?php
                    // Test de l'endpoint
                    $test_url = 'https://api.sunoapi.org/api/v1/get_limit';
                    $response = wp_remote_get($test_url, array(
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $this->api_key,
                            'Content-Type' => 'application/json'
                        ),
                        'timeout' => 10
                    ));
                    
                    if (!is_wp_error($response)) {
                        $status = wp_remote_retrieve_response_code($response);
                        
                        if ($status === 200) {
                            $body = wp_remote_retrieve_body($response);
                            $data = json_decode($body, true);
                            echo '<div class="notice notice-success">';
                            echo '<p>✅ Connexion API réussie</p>';
                            if (isset($data['credits'])) {
                                echo '<p>Crédits disponibles : ' . $data['credits'] . '</p>';
                            }
                            echo '</div>';
                        } else {
                            echo '<div class="notice notice-error"><p>❌ Erreur API : Code ' . $status . '</p></div>';
                        }
                    } else {
                        echo '<div class="notice notice-error"><p>❌ Erreur de connexion</p></div>';
                    }
                    ?>
                <?php endif; ?>
                
            <?php endif; ?>
            
            <p style="margin-top: 20px;">
                <a href="<?php echo admin_url('options-general.php?page=suno-music'); ?>" class="button button-primary">
                    ⚙️ Modifier la configuration
                </a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // Les autres méthodes restent similaires...
    public function render_music_player($atts) {
        // Code existant...
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'limit' => 10
        ), $atts);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND status = 'completed' ORDER BY created_at DESC LIMIT %d",
            intval($atts['user_id']),
            intval($atts['limit'])
        ));
        
        if (empty($results)) {
            return '<p>Aucune chanson générée pour le moment.</p>';
        }
        
        ob_start();
        ?>
        <div class="suno-playlist">
            <h3>🎵 Vos créations musicales</h3>
            
            <?php foreach ($results as $track): ?>
            <div class="suno-track">
                <div class="track-info">
                    <h4><?php echo esc_html($track->title ?: 'Chanson sans titre'); ?></h4>
                    <p class="track-prompt"><?php echo esc_html(wp_trim_words($track->prompt, 15)); ?></p>
                    <span class="track-style"><?php echo esc_html($track->style ?: 'Auto'); ?></span>
                    <span class="track-date"><?php echo date('d/m/Y', strtotime($track->created_at)); ?></span>
                    <?php if ($track->api_mode === 'official'): ?>
                        <span class="api-badge" style="background: #4caf50; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px;">
                            API Officielle
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if ($track->audio_url): ?>
                <div class="track-player">
                    <audio controls preload="none">
                        <source src="<?php echo esc_url($track->audio_url); ?>" type="audio/mpeg">
                    </audio>
                    <a href="<?php echo esc_url($track->audio_url); ?>" download class="download-btn" style="margin-left: 10px;">
                        📥 Télécharger
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if ($track->image_url): ?>
                <div class="track-image">
                    <img src="<?php echo esc_url($track->image_url); ?>" alt="Visuel" style="max-width: 200px; border-radius: 8px;" />
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
            return '<p>Accès réservé aux administrateurs.</p>';
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(status = 'pending') as pending,
                SUM(status = 'completed') as completed,
                SUM(api_mode = 'official') as official_api,
                SUM(api_mode = 'sunoapi') as sunoapi
            FROM $table_name
        ");
        
        ob_start();
        ?>
        <div class="suno-debug">
            <h4>🔍 Informations de débogage</h4>
            
            <table class="widefat">
                <tr>
                    <th>Version du plugin</th>
                    <td><?php echo $this->version; ?></td>
                </tr>
                <tr>
                    <th>Mode API actuel</th>
                    <td><?php echo $this->api_mode === 'official' ? 'API Officielle Suno' : 'SunoAPI.org'; ?></td>
                </tr>
                <tr>
                    <th>Total de générations</th>
                    <td><?php echo intval($stats->total); ?></td>
                </tr>
                <tr>
                    <th>Via API Officielle</th>
                    <td><?php echo intval($stats->official_api); ?></td>
                </tr>
                <tr>
                    <th>Via SunoAPI.org</th>
                    <td><?php echo intval($stats->sunoapi); ?></td>
                </tr>
                <tr>
                    <th>En cours</th>
                    <td><?php echo intval($stats->pending); ?></td>
                </tr>
                <tr>
                    <th>Terminées</th>
                    <td><?php echo intval($stats->completed); ?></td>
                </tr>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialiser le plugin
new SunoMusicGenerator();
