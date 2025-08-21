<?php
/**
 * Plugin Name: Suno Music Generator
 * Description: Générateur de musique IA avec l'API officielle Suno
 * Version: 3.1.0
 * Author: WeAreReForm
 * License: GPL-2.0
 * Text Domain: suno-music-generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class SunoMusicGenerator {
    
    private $api_key;
    private $version = '3.1.0';
    private $api_base_url = 'https://studio-api.suno.ai';
    private $api_mode = 'official'; // 'official' ou 'sunoapi'
    private $auth_method = 'token'; // 'token' ou 'password'
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Shortcodes
        add_shortcode('suno_music_form', array($this, 'render_music_form'));
        add_shortcode('suno_music_player', array($this, 'render_music_player'));
        add_shortcode('suno_test_api', array($this, 'render_api_test'));
        add_shortcode('suno_debug', array($this, 'render_debug_info'));
        add_shortcode('suno_get_token', array($this, 'render_token_helper'));
        
        // Actions AJAX
        add_action('wp_ajax_generate_music', array($this, 'ajax_generate_music'));
        add_action('wp_ajax_nopriv_generate_music', array($this, 'ajax_generate_music'));
        add_action('wp_ajax_check_music_status', array($this, 'ajax_check_music_status'));
        add_action('wp_ajax_nopriv_check_music_status', array($this, 'ajax_check_music_status'));
        
        // Admin
        register_activation_hook(__FILE__, array($this, 'create_tables'));
        add_action('admin_menu', array($this, 'admin_menu'));
    }
    
    public function init() {
        $this->api_key = get_option('suno_api_key', '');
        $this->api_mode = get_option('suno_api_mode', 'official');
        $this->auth_method = get_option('suno_auth_method', 'token');
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
            update_option('suno_auth_method', sanitize_text_field($_POST['auth_method']));
            update_option('suno_token', sanitize_text_field($_POST['suno_token']));
            update_option('suno_cookie', sanitize_text_field($_POST['suno_cookie']));
            update_option('suno_email', sanitize_email($_POST['suno_email']));
            update_option('suno_password', sanitize_text_field($_POST['suno_password']));
            echo '<div class="notice notice-success"><p>Paramètres sauvegardés!</p></div>';
        }
        
        $api_key = get_option('suno_api_key', '');
        $api_mode = get_option('suno_api_mode', 'official');
        $auth_method = get_option('suno_auth_method', 'token');
        $suno_token = get_option('suno_token', '');
        $suno_cookie = get_option('suno_cookie', '');
        $suno_email = get_option('suno_email', '');
        $suno_password = get_option('suno_password', '');
        ?>
        <div class="wrap">
            <h1>Suno Music Generator - Version <?php echo $this->version; ?></h1>
            
            <div style="background: #e8f5e9; padding: 20px; border-left: 4px solid #4caf50; margin: 20px 0;">
                <h2 style="margin-top: 0;">🎉 Version 3.1 - Support OAuth/Token!</h2>
                <p>Support complet pour les utilisateurs Google, Discord, Apple avec authentification par token/cookie</p>
            </div>
            
            <?php if ($api_mode === 'official' && $auth_method === 'token' && empty($suno_token) && empty($suno_cookie)): ?>
            <div style="background: #fff3cd; padding: 20px; border-left: 4px solid #ffc107; margin: 20px 0;">
                <h3>🔑 Besoin d'aide pour récupérer votre token ?</h3>
                <p>Si vous utilisez Google, Discord ou Apple pour vous connecter à Suno :</p>
                <a href="<?php echo plugin_dir_url(__FILE__); ?>get-suno-token.php" target="_blank" class="button button-primary">
                    📋 Guide pour récupérer votre token
                </a>
                <p style="margin-top: 10px;">Ou utilisez le shortcode <code>[suno_get_token]</code> sur une page</p>
            </div>
            <?php endif; ?>
            
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
                            <th scope="row">Méthode d'authentification</th>
                            <td>
                                <select name="auth_method" id="auth_method" onchange="toggleAuthFields()">
                                    <option value="token" <?php selected($auth_method, 'token'); ?>>
                                        Token/Cookie (Google, Discord, Apple)
                                    </option>
                                    <option value="password" <?php selected($auth_method, 'password'); ?>>
                                        Email + Mot de passe
                                    </option>
                                </select>
                                <p class="description">Choisissez selon votre méthode de connexion à Suno</p>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Auth par Token -->
                    <div id="token-auth" style="<?php echo $auth_method === 'token' ? '' : 'display:none;'; ?>">
                        <h4>🎫 Authentification par Token/Cookie</h4>
                        <p style="background: #f0f9ff; padding: 15px; border-radius: 5px;">
                            <strong>Pour les utilisateurs Google, Discord, Apple :</strong><br>
                            Récupérez votre token depuis Suno.com en suivant 
                            <a href="<?php echo plugin_dir_url(__FILE__); ?>get-suno-token.php" target="_blank">ce guide</a>
                        </p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Token Bearer (optionnel)</th>
                                <td>
                                    <input type="text" name="suno_token" value="<?php echo esc_attr(substr($suno_token, 0, 20)); ?><?php echo strlen($suno_token) > 20 ? '...' : ''; ?>" 
                                           class="regular-text" placeholder="Bearer xxxxx..." />
                                    <p class="description">Token d'autorisation (commence par "Bearer ")</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Cookie de session (optionnel)</th>
                                <td>
                                    <textarea name="suno_cookie" rows="3" class="regular-text" style="width: 100%;" 
                                              placeholder="__session=xxxxx ou __client=xxxxx"><?php echo esc_attr(substr($suno_cookie, 0, 50)); ?><?php echo strlen($suno_cookie) > 50 ? '...' : ''; ?></textarea>
                                    <p class="description">Cookie __session ou __client depuis Suno.com</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Auth par Email/Password -->
                    <div id="password-auth" style="<?php echo $auth_method === 'password' ? '' : 'display:none;'; ?>">
                        <h4>📧 Authentification Email/Mot de passe</h4>
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
                                    <p class="description">Mot de passe de votre compte Suno.com</p>
                                </td>
                            </tr>
                        </table>
                    </div>
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
            
            function toggleAuthFields() {
                const method = document.getElementById('auth_method').value;
                document.getElementById('token-auth').style.display = 
                    method === 'token' ? 'block' : 'none';
                document.getElementById('password-auth').style.display = 
                    method === 'password' ? 'block' : 'none';
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
                        <code style="background: #fff; padding: 5px 10px; border-radius: 4px;">[suno_get_token]</code> 
                        - Guide pour récupérer le token
                    </li>
                </ul>
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
        // Vérifier d'abord le cache
        $cached_token = get_transient('suno_auth_token');
        if ($cached_token) {
            return $cached_token;
        }
        
        // Si méthode token direct
        if ($this->auth_method === 'token') {
            $token = get_option('suno_token', '');
            $cookie = get_option('suno_cookie', '');
            
            // Si on a un token Bearer
            if (!empty($token)) {
                // Nettoyer le token
                $token = str_replace('Bearer ', '', trim($token));
                set_transient('suno_auth_token', $token, HOUR_IN_SECONDS);
                return $token;
            }
            
            // Si on a un cookie de session
            if (!empty($cookie)) {
                // Le cookie peut être utilisé directement dans certains cas
                // ou converti en token via un appel API
                return $this->convert_cookie_to_token($cookie);
            }
            
            return false;
        }
        
        // Méthode email/password classique
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
            set_transient('suno_auth_token', $data['token'], HOUR_IN_SECONDS);
            return $data['token'];
        }
        
        return false;
    }
    
    /**
     * Convertir un cookie en token (si nécessaire)
     */
    private function convert_cookie_to_token($cookie) {
        // Certaines APIs acceptent directement le cookie
        // D'autres nécessitent une conversion
        
        // Pour l'instant, on utilise le cookie tel quel
        // et on laisse l'API décider
        return $cookie;
    }
    
    /**
     * Obtenir les headers d'authentification appropriés
     */
    private function get_auth_headers() {
        $token = $this->get_suno_token();
        $cookie = get_option('suno_cookie', '');
        
        $headers = array(
            'Content-Type' => 'application/json'
        );
        
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        
        if ($this->auth_method === 'token' && !empty($cookie)) {
            // Ajouter le cookie si disponible
            $headers['Cookie'] = $cookie;
        }
        
        return $headers;
    }
    
    public function ajax_generate_music() {
        error_log('=== SUNO GENERATE v' . $this->version . ' (Mode: ' . $this->api_mode . ', Auth: ' . $this->auth_method . ') ===');
        
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
            $this->generate_with_official_api($prompt, $style, $title, $lyrics, $instrumental);
        } else {
            $this->generate_with_sunoapi($prompt, $style, $title, $lyrics, $instrumental);
        }
    }
    
    /**
     * Génération avec l'API officielle Suno
     */
    private function generate_with_official_api($prompt, $style, $title, $lyrics, $instrumental) {
        $headers = $this->get_auth_headers();
        
        if (!isset($headers['Authorization']) && !isset($headers['Cookie'])) {
            wp_send_json_error('Impossible de se connecter à Suno. Vérifiez votre configuration.');
            return;
        }
        
        // Préparer les données pour l'API officielle
        $api_data = array(
            'prompt' => $prompt,
            'mv' => 'chirp-v3-5',
            'instrumental' => $instrumental
        );
        
        if (!empty($lyrics)) {
            $api_data['lyrics'] = $lyrics;
            $api_data['title'] = $title ?: 'Untitled';
            $api_data['tags'] = $style ?: 'pop';
        } else {
            $full_prompt = $prompt;
            if (!empty($style)) {
                $full_prompt = "[$style] " . $full_prompt;
            }
            $api_data['gpt_description_prompt'] = $full_prompt;
        }
        
        error_log('Sending to Official API: ' . json_encode($api_data));
        
        // Appel à l'API officielle
        $response = wp_remote_post($this->api_base_url . '/api/generate/v2', array(
            'headers' => $headers,
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
        error_log('Official API Response: ' . substr($body, 0, 500));
        
        if ($status_code === 401) {
            delete_transient('suno_auth_token');
            wp_send_json_error('Token expiré ou invalide. Veuillez mettre à jour votre token.');
            return;
        }
        
        if ($status_code !== 200 && $status_code !== 201) {
            wp_send_json_error('Erreur API: Code ' . $status_code . ' - ' . substr($body, 0, 200));
            return;
        }
        
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['clips'])) {
            wp_send_json_error('Réponse API invalide');
            return;
        }
        
        $clips = $data['clips'];
        $clip_ids = array_column($clips, 'id');
        $task_id = implode(',', $clip_ids);
        
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
    
    // ... reste du code similaire mais avec support du token/cookie ...
    
    public function render_token_helper() {
        ob_start();
        ?>
        <div style="max-width: 800px; margin: 20px auto; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h2>🔑 Comment récupérer votre Token Suno</h2>
            
            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <strong>⚠️ Important :</strong> Si vous utilisez Google, Discord ou Apple pour vous connecter à Suno, 
                vous devez récupérer votre token d'authentification.
            </div>
            
            <h3>📋 Méthode 1 : Via les Cookies</h3>
            <ol>
                <li>Connectez-vous à <a href="https://suno.com" target="_blank">Suno.com</a></li>
                <li>Ouvrez les DevTools (F12)</li>
                <li>Allez dans Application → Cookies → https://suno.com</li>
                <li>Trouvez <code>__session</code> ou <code>__client</code></li>
                <li>Copiez la valeur complète</li>
            </ol>
            
            <h3>📋 Méthode 2 : Via le Network</h3>
            <ol>
                <li>Dans les DevTools, allez dans Network</li>
                <li>Générez une chanson sur Suno</li>
                <li>Cherchez une requête vers <code>api/generate</code></li>
                <li>Dans Headers, trouvez <code>Authorization: Bearer xxxxx</code></li>
                <li>Copiez le token (après "Bearer ")</li>
            </ol>
            
            <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <strong>✅ Une fois que vous avez votre token :</strong><br>
                Collez-le dans les réglages du plugin WordPress → Réglages → Suno Music
            </div>
            
            <p>
                <a href="<?php echo plugin_dir_url(__FILE__); ?>get-suno-token.php" target="_blank" class="button button-primary">
                    📖 Guide détaillé avec captures d'écran
                </a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // ... reste des méthodes inchangées ...
}

// Initialiser le plugin
new SunoMusicGenerator();
