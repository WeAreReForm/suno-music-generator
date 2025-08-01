<?php
/**
 * Plugin Name: Suno Music Generator
 * Description: Générateur de musique IA avec Suno via formulaire WordPress
 * Version: 1.3 (Retour version stable)
 * Author: Assistant IA
 */

if (!defined('ABSPATH')) {
    exit;
}

class SunoMusicGenerator {
    
    private $api_key;
    private $api_base_url = 'https://apibox.erweima.ai';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('suno_music_form', array($this, 'render_music_form'));
        add_shortcode('suno_music_player', array($this, 'render_music_player'));
        add_shortcode('suno_test_api', array($this, 'render_api_test'));
        add_action('wp_ajax_generate_music', array($this, 'ajax_generate_music'));
        add_action('wp_ajax_nopriv_generate_music', array($this, 'ajax_generate_music'));
        add_action('wp_ajax_check_music_status', array($this, 'ajax_check_music_status'));
        add_action('wp_ajax_nopriv_check_music_status', array($this, 'ajax_check_music_status'));
        
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
            <h1>Configuration Suno Music Generator</h1>
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
            
            <h2>Test de connectivité</h2>
            <p>Utilisez le shortcode <code>[suno_test_api]</code> pour tester votre connexion API.</p>
            
            <h2>Shortcodes disponibles</h2>
            <p><code>[suno_music_form]</code> - Affiche le formulaire de génération</p>
            <p><code>[suno_music_player user_id="X"]</code> - Affiche les créations d'un utilisateur</p>
            <p><code>[suno_test_api]</code> - Test de connectivité API (admin uniquement)</p>
        </div>
        <?php
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('suno-music-js', plugin_dir_url(__FILE__) . 'assets/suno-music.js', array('jquery'), '1.3', true);
        wp_enqueue_style('suno-music-css', plugin_dir_url(__FILE__) . 'assets/suno-music.css', array(), '1.3');
        
        wp_localize_script('suno-music-js', 'suno_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('suno_music_nonce')
        ));
    }
    
    public function render_music_form($atts) {
        ob_start();
        ?>
        <div id="suno-music-form" class="suno-container">
            <h3>🎵 Créer votre chanson avec l'IA</h3>
            
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
                </div>
            </div>
            
            <div id="generation-result" class="suno-result" style="display: none;">
                <!-- Résultat affiché ici -->
            </div>
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
                    <span class="track-style"><?php echo esc_html($track->style); ?></span>
                    <span class="track-date"><?php echo date('d/m/Y', strtotime($track->created_at)); ?></span>
                </div>
                
                <?php if ($track->audio_url): ?>
                <div class="track-player">
                    <audio controls preload="none">
                        <source src="<?php echo esc_url($track->audio_url); ?>" type="audio/mpeg">
                        Votre navigateur ne supporte pas l'élément audio.
                    </audio>
                </div>
                <?php endif; ?>
                
                <?php if ($track->image_url): ?>
                <div class="track-image">
                    <img src="<?php echo esc_url($track->image_url); ?>" alt="Visuel de la chanson" />
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function ajax_generate_music() {
        error_log('=== SUNO AJAX GENERATE MUSIC v1.3 ===');
        
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        if (empty($this->api_key)) {
            wp_send_json_error('Clé API Suno non configurée. Allez dans Réglages > Suno Music.');
        }
        
        $prompt = sanitize_textarea_field($_POST['prompt']);
        $style = sanitize_text_field($_POST['style']);
        $title = sanitize_text_field($_POST['title']);
        $lyrics = sanitize_textarea_field($_POST['lyrics']);
        $instrumental = isset($_POST['instrumental']) && $_POST['instrumental'] === 'true';
        
        if (empty($prompt)) {
            wp_send_json_error('Description requise');
        }
        
        error_log('Prompt: ' . $prompt);
        
        // Construction des données pour l'API
        $api_data = array(
            'prompt' => $prompt,
            'customMode' => false,
            'instrumental' => $instrumental,
            'model' => 'V3_5'
        );
        
        // Si on a des données custom
        if (!empty($style) || !empty($title) || !empty($lyrics)) {
            $api_data['customMode'] = true;
            if (!empty($style)) $api_data['style'] = $style;
            if (!empty($title)) $api_data['title'] = $title;
            if (!empty($lyrics)) $api_data['lyric'] = $lyrics;
        }
        
        error_log('API Data: ' . json_encode($api_data));
        
        $api_url = $this->api_base_url . '/api/v1/generate';
        
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($api_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('API Error: ' . $response->get_error_message());
            wp_send_json_error('Erreur de connexion: ' . $response->get_error_message());
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
        
        // Extraire le task_id selon le format de réponse
        $task_id = null;
        
        if (isset($data['code']) && $data['code'] === 200) {
            if (isset($data['data']['taskId'])) {
                $task_id = $data['data']['taskId'];
            } elseif (isset($data['data']['task_id'])) {
                $task_id = $data['data']['task_id'];
            } elseif (isset($data['data']) && is_string($data['data'])) {
                $task_id = $data['data'];
            }
        } elseif (isset($data['task_id'])) {
            $task_id = $data['task_id'];
        } elseif (isset($data['taskId'])) {
            $task_id = $data['taskId'];
        }
        
        if (!$task_id) {
            error_log('No task_id found in response: ' . json_encode($data));
            wp_send_json_error('Pas de task_id dans la réponse API');
            return;
        }
        
        error_log('Task ID received: ' . $task_id);
        
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
            error_log('Database insert failed: ' . $wpdb->last_error);
            wp_send_json_error('Erreur de sauvegarde en base de données');
            return;
        }
        
        wp_send_json_success(array(
            'task_id' => $task_id,
            'message' => 'Génération démarrée avec succès !'
        ));
    }
    
    public function ajax_check_music_status() {
        error_log('=== CHECK MUSIC STATUS v1.3 ===');
        
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $task_id = sanitize_text_field($_POST['task_id']);
        
        if (empty($task_id)) {
            wp_send_json_error('Task ID requis');
        }
        
        if (empty($this->api_key)) {
            wp_send_json_error('Clé API non configurée');
        }
        
        error_log('Checking status for task: ' . $task_id);
        
        // Appel à l'API pour vérifier le statut
        $api_url = $this->api_base_url . '/api/v1/music/' . $task_id;
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            error_log('Status check error: ' . $response->get_error_message());
            wp_send_json_success(array(
                'status' => 'processing',
                'message' => 'Vérification en cours...'
            ));
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('Status check response code: ' . $status_code);
        
        if ($status_code === 200) {
            $data = json_decode($body, true);
            
            if ($data && isset($data['code']) && $data['code'] === 200) {
                // Chercher les données de la musique
                $music_data = null;
                
                if (isset($data['data']) && is_array($data['data']) && !empty($data['data'])) {
                    $music_data = $data['data'][0];
                } elseif (isset($data['data'])) {
                    $music_data = $data['data'];
                }
                
                if ($music_data) {
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'suno_generations';
                    
                    $update_data = array(
                        'status' => 'completed',
                        'completed_at' => current_time('mysql')
                    );
                    
                    // Récupérer les URLs
                    if (isset($music_data['audio_url'])) {
                        $update_data['audio_url'] = $music_data['audio_url'];
                    }
                    if (isset($music_data['video_url'])) {
                        $update_data['video_url'] = $music_data['video_url'];
                    }
                    if (isset($music_data['image_url'])) {
                        $update_data['image_url'] = $music_data['image_url'];
                    }
                    if (isset($music_data['duration'])) {
                        $update_data['duration'] = intval($music_data['duration']);
                    }
                    
                    $wpdb->update($table_name, $update_data, array('task_id' => $task_id));
                    
                    wp_send_json_success(array(
                        'status' => 'completed',
                        'audio_url' => $update_data['audio_url'] ?? '',
                        'video_url' => $update_data['video_url'] ?? '',
                        'image_url' => $update_data['image_url'] ?? ''
                    ));
                    return;
                }
            }
        }
        
        // Par défaut, on continue le processing
        wp_send_json_success(array(
            'status' => 'processing',
            'message' => 'Génération en cours...'
        ));
    }
    
    public function test_api_connection() {
        if (empty($this->api_key)) {
            return array('error' => 'Clé API manquante');
        }
        
        // Test simple avec l'endpoint get_limit
        $api_url = $this->api_base_url . '/api/v1/get_limit';
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array(
                'error' => 'Erreur de connexion: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        return array(
            'status_code' => $status_code,
            'response' => $body,
            'success' => $status_code === 200
        );
    }
    
    public function render_api_test($atts) {
        if (!current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24;">❌ Accès refusé - Administrateur uniquement</div>';
        }
        
        $test_result = $this->test_api_connection();
        
        ob_start();
        ?>
        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin: 20px 0; border-radius: 5px; font-family: Arial;">
            <h3>🔧 Test SunoAPI.org - Version 1.3</h3>
            
            <div style="background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 10px 0;">
                <strong>Configuration :</strong><br>
                ✅ Clé API : <?php echo !empty($this->api_key) ? 'Configurée (' . strlen($this->api_key) . ' caractères)' : '❌ NON CONFIGURÉE'; ?><br>
                ✅ Plugin : Actif (Version 1.3)
            </div>
            
            <?php if (isset($test_result['success']) && $test_result['success']): ?>
                <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724;">
                    <strong>✅ SUCCÈS !</strong><br>
                    L'API fonctionne correctement !<br>
                    <em>Réponse : <?php echo esc_html($test_result['response']); ?></em>
                </div>
            <?php else: ?>
                <div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24;">
                    <strong>❌ ÉCHEC</strong><br>
                    <?php if (isset($test_result['error'])): ?>
                        <?php echo esc_html($test_result['error']); ?>
                    <?php else: ?>
                        Code HTTP : <?php echo esc_html($test_result['status_code']); ?><br>
                        Réponse : <?php echo esc_html($test_result['response']); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;">
                <strong>📋 Prochaines étapes :</strong><br>
                <?php if (isset($test_result['success']) && $test_result['success']): ?>
                    1. ✅ L'API fonctionne !<br>
                    2. 🎵 Testez le formulaire avec [suno_music_form]<br>
                    3. 🔍 Si problème, vérifiez les logs WordPress
                <?php else: ?>
                    1. 🔑 Vérifiez votre clé API sur sunoapi.org<br>
                    2. 💰 Vérifiez vos crédits disponibles<br>
                    3. 🔄 Essayez de régénérer une nouvelle clé API
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new SunoMusicGenerator();