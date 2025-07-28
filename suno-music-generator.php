<?php
/**
 * Plugin Name: Suno Music Generator
 * Description: Générateur de musique IA avec Suno via formulaire WordPress
 * Version: 1.1 (Corrigé pour SunoAPI.org)
 * Author: Assistant IA
 */

if (!defined('ABSPATH')) {
    exit;
}

class SunoMusicGenerator {
    
    private $api_key;
    private $possible_endpoints = array(
        'https://apibox.erweima.ai/api/v1/generate',
        'https://api.sunoapi.org/api/v1/generate',
        'https://sunoapi.org/api/v1/generate'
    );
    
    private $status_endpoints = array(
        'https://apibox.erweima.ai/api/v1/music/',
        'https://api.sunoapi.org/api/v1/music/',
        'https://api.sunoapi.org/api/get?ids='
    );
    
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
        wp_enqueue_script('suno-music-js', plugin_dir_url(__FILE__) . 'assets/suno-music.js', array('jquery'), '1.1', true);
        wp_enqueue_style('suno-music-css', plugin_dir_url(__FILE__) . 'assets/suno-music.css', array(), '1.1');
        
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
        
        $where_clause = $atts['user_id'] ? "WHERE user_id = " . intval($atts['user_id']) : "";
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name $where_clause AND status = 'completed' ORDER BY created_at DESC LIMIT %d",
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
        error_log('=== SUNO AJAX GENERATE MUSIC (CORRECTED v1.1) ===');
        
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
        error_log('API Key configured: YES (length: ' . strlen($this->api_key) . ')');
        
        // Format de données pour SunoAPI.org
        $api_data = array(
            'customMode' => true,
            'input' => array(
                'gpt_description_prompt' => $prompt,
                'make_instrumental' => $instrumental
            )
        );
        
        // Ajouter les paramètres optionnels
        if (!empty($style)) {
            $api_data['input']['style'] = $style;
        }
        
        if (!empty($title)) {
            $api_data['input']['title'] = $title;
        }
        
        if (!empty($lyrics)) {
            $api_data['input']['lyric'] = $lyrics;
        }
        
        error_log('API Data: ' . json_encode($api_data));
        
        // Essayer différents endpoints
        foreach ($this->possible_endpoints as $api_url) {
            error_log('Trying endpoint: ' . $api_url);
            
            $response = wp_remote_post($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($api_data),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                error_log('Endpoint ' . $api_url . ' failed: ' . $response->get_error_message());
                continue;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            error_log('Endpoint ' . $api_url . ' - Status: ' . $status_code);
            error_log('Response: ' . $body);
            
            if ($status_code === 200) {
                $data = json_decode($body, true);
                
                // Vérifier différents formats de réponse
                $task_id = null;
                if (isset($data['task_id'])) {
                    $task_id = $data['task_id'];
                } elseif (isset($data['id'])) {
                    $task_id = $data['id'];
                } elseif (isset($data['data']['task_id'])) {
                    $task_id = $data['data']['task_id'];
                } elseif (isset($data['data']['id'])) {
                    $task_id = $data['data']['id'];
                } elseif (isset($data['code']) && $data['code'] === 200 && isset($data['data'])) {
                    $task_id = $data['data'];
                }
                
                if ($task_id) {
                    error_log('SUCCESS! Task ID: ' . $task_id . ' with endpoint: ' . $api_url);
                    
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
                    }
                    
                    wp_send_json_success(array(
                        'task_id' => $task_id,
                        'message' => 'Génération démarrée avec succès !',
                        'endpoint_used' => $api_url
                    ));
                    return;
                } else {
                    error_log('No task_id found in response: ' . json_encode($data));
                }
            } elseif ($status_code === 401) {
                wp_send_json_error('Clé API invalide. Vérifiez votre clé dans Réglages > Suno Music.');
                return;
            } elseif ($status_code === 429) {
                wp_send_json_error('Limite de crédits atteinte. Ajoutez des crédits sur sunoapi.org.');
                return;
            }
        }
        
        // Aucun endpoint n'a fonctionné
        error_log('All endpoints failed');
        wp_send_json_error('Impossible de se connecter à l\'API Suno. Vérifiez votre clé API et vos crédits.');
    }
    
    public function ajax_check_music_status() {
        error_log('=== CHECK MUSIC STATUS (CORRECTED v1.1) ===');
        
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $task_id = sanitize_text_field($_POST['task_id']);
        
        if (empty($task_id)) {
            wp_send_json_error('Task ID requis');
        }
        
        if (empty($this->api_key)) {
            wp_send_json_error('Clé API non configurée');
        }
        
        error_log('Checking status for task: ' . $task_id);
        
        // Essayer différents endpoints pour récupérer le statut
        foreach ($this->status_endpoints as $base_url) {
            $api_url = $base_url . $task_id;
            error_log('Trying status endpoint: ' . $api_url);
            
            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 15
            ));
            
            if (is_wp_error($response)) {
                error_log('Status endpoint failed: ' . $response->get_error_message());
                continue;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            error_log('Status endpoint ' . $api_url . ' - Code: ' . $status_code);
            
            if ($status_code === 200) {
                $data = json_decode($body, true);
                
                if ($data) {
                    // Adapter selon le format de réponse
                    $track_data = $data;
                    
                    // Si c'est un tableau, prendre le premier élément
                    if (is_array($data) && isset($data[0])) {
                        $track_data = $data[0];
                    }
                    
                    // Si data est dans un wrapper
                    if (isset($track_data['data'])) {
                        if (is_array($track_data['data'])) {
                            $track_data = $track_data['data'][0] ?? $track_data['data'];
                        } else {
                            $track_data = $track_data['data'];
                        }
                    }
                    
                    error_log('Track data received: ' . json_encode($track_data));
                    
                    // Mettre à jour la base de données
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'suno_generations';
                    
                    $update_data = array();
                    
                    // Status mapping - essayer différents champs
                    $status_fields = ['status', 'state', 'progress'];
                    foreach ($status_fields as $field) {
                        if (isset($track_data[$field])) {
                            $update_data['status'] = $track_data[$field];
                            break;
                        }
                    }
                    
                    // URL audio - essayer différents champs
                    $audio_fields = ['audio_url', 'audioUrl', 'url', 'file_url', 'audio'];
                    foreach ($audio_fields as $field) {
                        if (isset($track_data[$field]) && !empty($track_data[$field])) {
                            $update_data['audio_url'] = $track_data[$field];
                            $update_data['status'] = 'completed';
                            $update_data['completed_at'] = current_time('mysql');
                            break;
                        }
                    }
                    
                    // URL vidéo
                    $video_fields = ['video_url', 'videoUrl', 'video'];
                    foreach ($video_fields as $field) {
                        if (isset($track_data[$field]) && !empty($track_data[$field])) {
                            $update_data['video_url'] = $track_data[$field];
                            break;
                        }
                    }
                    
                    // URL image
                    $image_fields = ['image_url', 'imageUrl', 'image', 'cover_url', 'artwork'];
                    foreach ($image_fields as $field) {
                        if (isset($track_data[$field]) && !empty($track_data[$field])) {
                            $update_data['image_url'] = $track_data[$field];
                            break;
                        }
                    }
                    
                    // Durée
                    if (isset($track_data['duration'])) {
                        $update_data['duration'] = intval($track_data['duration']);
                    }
                    
                    if (!empty($update_data)) {
                        $wpdb->update($table_name, $update_data, array('task_id' => $task_id));
                        error_log('Database updated successfully with: ' . json_encode($update_data));
                    }
                    
                    wp_send_json_success($update_data);
                    return;
                }
            }
        }
        
        // Statut par défaut si aucun endpoint ne répond
        wp_send_json_success(array(
            'status' => 'processing',
            'message' => 'Génération en cours...'
        ));
    }
    
    public function test_api_connection() {
        if (empty($this->api_key)) {
            return array('error' => 'Clé API manquante');
        }
        
        // Test avec différents endpoints
        $test_endpoints = array(
            'https://apibox.erweima.ai/api/v1/get_limit',
            'https://api.sunoapi.org/api/v1/get_limit',
            'https://api.sunoapi.org/get_limit'
        );
        
        $results = array();
        
        foreach ($test_endpoints as $url) {
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 15
            ));
            
            if (is_wp_error($response)) {
                $results[$url] = 'ERROR: ' . $response->get_error_message();
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                
                $results[$url] = array(
                    'status_code' => $status_code,
                    'body' => $status_code === 200 ? 'SUCCESS' : 'FAILED: ' . substr($body, 0, 100)
                );
                
                if ($status_code === 200) {
                    $results['WORKING_ENDPOINT'] = $url;
                }
            }
        }
        
        return $results;
    }
    
    public function render_api_test($atts) {
        if (!current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24;">❌ Accès refusé - Administrateur uniquement</div>';
        }
        
        $test_result = $this->test_api_connection();
        
        ob_start();
        ?>
        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin: 20px 0; border-radius: 5px; font-family: Arial;">
            <h3>🔧 Test SunoAPI.org - Version 1.1</h3>
            
            <div style="background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 10px 0;">
                <strong>Configuration :</strong><br>
                ✅ Clé API : <?php echo !empty($this->api_key) ? 'Configurée (' . strlen($this->api_key) . ' caractères)' : '❌ NON CONFIGURÉE'; ?><br>
                ✅ Plugin : Actif (Version 1.1 corrigée)
            </div>
            
            <?php if (isset($test_result['WORKING_ENDPOINT'])): ?>
                <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724;">
                    <strong>✅ SUCCÈS !</strong><br>
                    <strong>Endpoint fonctionnel :</strong> <?php echo esc_html($test_result['WORKING_ENDPOINT']); ?><br>
                    <em>Votre API fonctionne ! Testez maintenant le formulaire de génération.</em>
                </div>
            <?php elseif (isset($test_result['error'])): ?>
                <div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24;">
                    <strong>❌ ERREUR</strong><br>
                    <?php echo esc_html($test_result['error']); ?><br>
                    Allez dans <strong>Réglages > Suno Music</strong> pour configurer votre clé API.
                </div>
            <?php else: ?>
                <div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24;">
                    <strong>❌ ÉCHEC</strong><br>
                    Aucun endpoint ne répond correctement.<br>
                    <strong>Vérifiez :</strong>
                    <ul>
                        <li>Votre clé API sur sunoapi.org</li>
                        <li>Que vous avez des crédits disponibles</li>
                        <li>Que la clé n'est pas expirée</li>
                    </ul>
                </div>
            <?php endif; ?>
            
            <details style="margin: 15px 0;">
                <summary style="cursor: pointer; padding: 10px; background: #e9ecef; border-radius: 5px;">
                    🔍 Détails techniques (cliquez pour voir)
                </summary>
                <pre style="background: #fff; padding: 10px; border: 1px solid #ccc; margin-top: 10px; overflow: auto; font-size: 11px;"><?php echo esc_html(print_r($test_result, true)); ?></pre>
            </details>
            
            <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;">
                <strong>📋 Prochaines étapes :</strong><br>
                <?php if (isset($test_result['WORKING_ENDPOINT'])): ?>
                    1. ✅ L'API fonctionne !<br>
                    2. 🎵 Testez le formulaire avec [suno_music_form]<br>
                    3. 🔍 Si problème, vérifiez les logs WordPress
                <?php else: ?>
                    1. 🔑 Allez dans Réglages > Suno Music<br>
                    2. 🔑 Configurez votre clé API de sunoapi.org<br>
                    3. 💰 Vérifiez vos crédits sur votre dashboard
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new SunoMusicGenerator();