<?php
/**
 * Plugin Name: Suno Music Generator
 * Description: Générateur de musique IA avec Suno via formulaire WordPress
 * Version: 1.7.0 (Timeout étendu + Debug amélioré)
 * Author: Assistant IA
 */

if (!defined('ABSPATH')) {
    exit;
}

class SunoMusicGenerator {
    
    private $api_key;
    private $api_base_url = 'https://apibox.erweima.ai';
    private $max_status_checks = 60; // Augmenté de 20 à 60 (5 minutes)
    
    public function __construct() {
        error_log('=== SUNO PLUGIN v1.7.0 - TIMEOUT ÉTENDU ===');
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('suno_music_form', array($this, 'render_music_form'));
        add_shortcode('suno_music_player', array($this, 'render_music_player'));
        add_shortcode('suno_test_api', array($this, 'render_api_test'));
        add_shortcode('suno_debug', array($this, 'render_debug_info'));
        add_shortcode('suno_test_shortcode', array($this, 'test_shortcode'));
        add_shortcode('suno_clear_database', array($this, 'clear_database_shortcode'));
        add_shortcode('suno_force_check', array($this, 'force_check_status'));
        add_shortcode('suno_check_task', array($this, 'check_specific_task'));
        add_shortcode('suno_test_response', array($this, 'test_api_response'));
        
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
            api_response text DEFAULT '',
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
            <h1>Configuration Suno Music Generator v1.7.0</h1>
            
            <div style="background: #d4edda; padding: 15px; border-radius: 5px; color: #155724; margin: 15px 0;">
                <strong>🎯 VERSION 1.7.0 - TIMEOUT ÉTENDU</strong><br>
                ✅ Limite augmentée à 60 vérifications (5 minutes)<br>
                ✅ Sauvegarde des réponses API pour debug<br>
                ✅ Nouveau shortcode [suno_test_response] pour tester<br>
                ✅ Meilleur suivi des erreurs
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
            <ul>
                <li><code>[suno_music_form]</code> - Formulaire de génération</li>
                <li><code>[suno_music_player]</code> - Affichage des créations</li>
                <li><code>[suno_test_api]</code> - Test de connexion API</li>
                <li><code>[suno_debug]</code> - Informations de debug</li>
                <li><code>[suno_force_check]</code> - Forcer la vérification des générations</li>
                <li><code>[suno_check_task task_id="XXX"]</code> - Vérifier un task_id spécifique</li>
                <li><code>[suno_test_response]</code> - Tester la réponse API</li>
                <li><code>[suno_clear_database]</code> - Vider la base de données</li>
            </ul>
        </div>
        <?php
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('suno-music-js', plugin_dir_url(__FILE__) . 'assets/suno-music.js', array('jquery'), '1.7.0', true);
        wp_enqueue_style('suno-music-css', plugin_dir_url(__FILE__) . 'assets/suno-music.css', array(), '1.7.0');
        
        wp_localize_script('suno-music-js', 'suno_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('suno_music_nonce'),
            'max_checks' => $this->max_status_checks,
            'check_interval' => 5000
        ));
    }
    
    public function render_music_form($atts) {
        ob_start();
        ?>
        <div id="suno-music-form" class="suno-container">
            <h3>🎵 Créer votre chanson avec l'IA (v1.7.0)</h3>
            
            <div style="background: #d4edda; padding: 10px; border-radius: 5px; margin: 15px 0; color: #155724;">
                <strong>✅ Timeout étendu :</strong> Jusqu'à 5 minutes de génération supportées
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
                        Vérifications : <span id="check-count">0</span>/<?php echo $this->max_status_checks; ?>
                        <br>
                        <small>La génération peut prendre jusqu'à 5 minutes</small>
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
    
    public function ajax_generate_music() {
        error_log('=== GENERATE MUSIC v1.7.0 ===');
        
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
        
        // Données API
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
        
        error_log('API Request Data: ' . json_encode($api_data));
        
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
        error_log('API Response Body: ' . $body);
        
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
        
        // Extraire le task_id
        $task_id = null;
        
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
            $task_id = $generation_id;
            error_log('No task_id from API, using generation_id: ' . $task_id);
        } else {
            error_log('Task ID from API: ' . $task_id);
        }
        
        // Sauvegarder en base de données avec la réponse complète
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
            'api_response' => $body, // Sauvegarder la réponse complète
            'created_at' => current_time('mysql')
        ));
        
        if ($insert_result === false) {
            wp_send_json_error('Erreur de sauvegarde: ' . $wpdb->last_error);
            return;
        }
        
        wp_send_json_success(array(
            'task_id' => $task_id,
            'message' => 'Génération démarrée ! Patience, cela peut prendre jusqu\'à 5 minutes...',
            'callback_url' => $callback_url,
            'max_checks' => $this->max_status_checks
        ));
    }
    
    // Fonction améliorée d'extraction des URLs
    private function extract_urls_from_response($data) {
        $urls = array(
            'audio_url' => '',
            'video_url' => '',
            'image_url' => ''
        );
        
        error_log('=== EXTRACTING URLs v1.7.0 ===');
        error_log('Full response structure: ' . json_encode($data));
        
        // Fonction récursive pour chercher les URLs dans toute la structure
        $find_urls = function($obj) use (&$find_urls, &$urls) {
            if (is_array($obj)) {
                foreach ($obj as $key => $value) {
                    // Chercher les clés audio
                    if (in_array($key, ['audio_url', 'audioUrl', 'audio', 'url', 'music_url', 
                                       'musicUrl', 'mp3_url', 'mp3Url', 'song_url', 'songUrl', 
                                       'track_url', 'trackUrl', 'file_url', 'fileUrl', 
                                       'download_url', 'downloadUrl']) && 
                        is_string($value) && !empty($value) && empty($urls['audio_url'])) {
                        $urls['audio_url'] = $value;
                        error_log('Found audio URL: ' . $value);
                    }
                    
                    // Chercher les clés vidéo
                    if (in_array($key, ['video_url', 'videoUrl', 'video', 'mp4_url', 'mp4Url']) && 
                        is_string($value) && !empty($value) && empty($urls['video_url'])) {
                        $urls['video_url'] = $value;
                    }
                    
                    // Chercher les clés image
                    if (in_array($key, ['image_url', 'imageUrl', 'image', 'cover_url', 
                                       'coverUrl', 'cover', 'thumbnail', 'thumbnailUrl']) && 
                        is_string($value) && !empty($value) && empty($urls['image_url'])) {
                        $urls['image_url'] = $value;
                    }
                    
                    // Récursion
                    if (is_array($value)) {
                        $find_urls($value);
                    }
                }
            }
        };
        
        $find_urls($data);
        
        error_log('Extracted URLs: ' . json_encode($urls));
        return $urls;
    }
    
    // Vérification du statut améliorée
    public function ajax_check_music_status() {
        error_log('=== CHECK MUSIC STATUS v1.7.0 ===');
        
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $task_id = sanitize_text_field($_POST['task_id']);
        $check_count = intval($_POST['check_count'] ?? 0);
        
        if (empty($task_id)) {
            wp_send_json_error('Task ID requis');
        }
        
        error_log('Checking task: ' . $task_id . ' (attempt ' . $check_count . '/' . $this->max_status_checks . ')');
        
        // Protection anti-boucle avec limite augmentée
        if ($check_count >= $this->max_status_checks) {
            error_log('Maximum checks reached for task: ' . $task_id);
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'suno_generations';
            $wpdb->update($table_name, 
                array('status' => 'timeout', 'completed_at' => current_time('mysql')), 
                array('task_id' => $task_id)
            );
            
            wp_send_json_success(array(
                'status' => 'timeout',
                'message' => 'La génération prend plus de temps que prévu. Utilisez [suno_check_task task_id="' . $task_id . '"] pour vérifier manuellement.',
                'check_count' => $check_count
            ));
            return;
        }
        
        // Vérifier d'abord en base de données
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
        
        // Vérification via l'API
        $endpoints = array(
            '/api/v1/music/' . $task_id,
            '/api/v1/status/' . $task_id,
            '/api/v1/task/' . $task_id,
            '/api/v1/get?ids=' . $task_id,
            '/api/v1/query?ids=' . $task_id
        );
        
        foreach ($endpoints as $endpoint) {
            $api_url = $this->api_base_url . $endpoint;
            error_log('Trying endpoint: ' . $api_url);
            
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
                        error_log('API Response: ' . json_encode($data));
                        
                        // Sauvegarder la réponse pour debug
                        $wpdb->update($table_name, 
                            array('api_response' => $body), 
                            array('task_id' => $task_id)
                        );
                        
                        // Extraire les URLs
                        $urls = $this->extract_urls_from_response($data);
                        
                        if (!empty($urls['audio_url'])) {
                            // Mise à jour en base de données
                            $update_data = array(
                                'status' => 'completed',
                                'completed_at' => current_time('mysql'),
                                'audio_url' => $urls['audio_url'],
                                'video_url' => $urls['video_url'],
                                'image_url' => $urls['image_url']
                            );
                            
                            $wpdb->update($table_name, $update_data, array('task_id' => $task_id));
                            
                            wp_send_json_success(array(
                                'status' => 'completed',
                                'audio_url' => $urls['audio_url'],
                                'video_url' => $urls['video_url'],
                                'image_url' => $urls['image_url'],
                                'check_count' => $check_count + 1
                            ));
                            return;
                        }
                    }
                }
            }
        }
        
        // Toujours en cours
        wp_send_json_success(array(
            'status' => 'processing',
            'message' => 'Génération en cours... (tentative ' . ($check_count + 1) . '/' . $this->max_status_checks . ')',
            'check_count' => $check_count + 1,
            'max_checks' => $this->max_status_checks
        ));
    }
    
    // Nouveau shortcode pour tester la réponse API
    public function test_api_response($atts) {
        if (!current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24;">❌ Accès refusé</div>';
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $latest = $wpdb->get_row("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 1");
        
        if (!$latest || empty($latest->api_response)) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px;">
                ❌ Aucune réponse API sauvegardée. Générez d\'abord une chanson.
            </div>';
        }
        
        ob_start();
        ?>
        <div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3>🔍 Dernière réponse API (v1.7.0)</h3>
            
            <div style="background: #fff; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <h4>Génération : <?php echo esc_html($latest->title ?: $latest->prompt); ?></h4>
                <p><strong>Task ID :</strong> <?php echo esc_html($latest->task_id); ?></p>
                <p><strong>Statut :</strong> <?php echo esc_html($latest->status); ?></p>
                <p><strong>Date :</strong> <?php echo esc_html($latest->created_at); ?></p>
            </div>
            
            <details open>
                <summary style="cursor: pointer; padding: 10px; background: #e9ecef; border-radius: 5px;">
                    📋 Réponse API complète
                </summary>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; margin-top: 10px; overflow: auto; font-size: 12px;">
<?php echo esc_html(json_encode(json_decode($latest->api_response), JSON_PRETTY_PRINT)); ?>
                </pre>
            </details>
            
            <?php
            $data = json_decode($latest->api_response, true);
            if ($data) {
                $urls = $this->extract_urls_from_response($data);
                
                if (!empty($urls['audio_url']) || !empty($urls['video_url']) || !empty($urls['image_url'])) {
                    echo '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; color: #155724;">';
                    echo '<strong>✅ URLs trouvées dans la réponse :</strong><br>';
                    if ($urls['audio_url']) echo 'Audio : <code>' . esc_html($urls['audio_url']) . '</code><br>';
                    if ($urls['video_url']) echo 'Vidéo : <code>' . esc_html($urls['video_url']) . '</code><br>';
                    if ($urls['image_url']) echo 'Image : <code>' . esc_html($urls['image_url']) . '</code><br>';
                    echo '</div>';
                } else {
                    echo '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0; color: #721c24;">';
                    echo '<strong>❌ Aucune URL trouvée dans cette réponse</strong><br>';
                    echo 'L\'API n\'a pas encore retourné les fichiers générés.';
                    echo '</div>';
                }
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // Autres méthodes inchangées...
    public function check_specific_task($atts) {
        if (!current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24;">❌ Accès refusé</div>';
        }
        
        $atts = shortcode_atts(array(
            'task_id' => ''
        ), $atts);
        
        if (empty($atts['task_id'])) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px;">
                ❌ Utilisez : [suno_check_task task_id="VOTRE_TASK_ID"]
            </div>';
        }
        
        $task_id = $atts['task_id'];
        
        ob_start();
        ?>
        <div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3>🔍 Vérification du Task ID : <?php echo esc_html($task_id); ?></h3>
            
            <?php
            // Essayer tous les endpoints possibles
            $endpoints = array(
                '/api/v1/music/' . $task_id => 'Music endpoint',
                '/api/v1/status/' . $task_id => 'Status endpoint',
                '/api/v1/task/' . $task_id => 'Task endpoint',
                '/api/v1/get?ids=' . $task_id => 'Get endpoint',
                '/api/v1/query?ids=' . $task_id => 'Query endpoint'
            );
            
            $found = false;
            
            foreach ($endpoints as $endpoint => $name) {
                $api_url = $this->api_base_url . $endpoint;
                
                echo '<div style="background: #fff; padding: 15px; border-radius: 5px; margin: 15px 0;">';
                echo '<h4>Test : ' . esc_html($name) . '</h4>';
                echo '<p><code>' . esc_html($api_url) . '</code></p>';
                
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
                    
                    echo '<p><strong>Code HTTP :</strong> ' . $status_code . '</p>';
                    
                    if ($status_code === 200 && $body) {
                        $data = json_decode($body, true);
                        
                        if ($data) {
                            echo '<details>';
                            echo '<summary style="cursor: pointer;">Voir la réponse complète</summary>';
                            echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; font-size: 11px;">';
                            echo esc_html(json_encode($data, JSON_PRETTY_PRINT));
                            echo '</pre>';
                            echo '</details>';
                            
                            // Extraire les URLs
                            $urls = $this->extract_urls_from_response($data);
                            
                            if (!empty($urls['audio_url'])) {
                                $found = true;
                                echo '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; color: #155724;">';
                                echo '<strong>✅ URLs trouvées !</strong><br>';
                                echo 'Audio : <a href="' . esc_url($urls['audio_url']) . '" target="_blank">Écouter</a><br>';
                                if ($urls['video_url']) {
                                    echo 'Vidéo : <a href="' . esc_url($urls['video_url']) . '" target="_blank">Voir</a><br>';
                                }
                                if ($urls['image_url']) {
                                    echo 'Image : <a href="' . esc_url($urls['image_url']) . '" target="_blank">Voir</a><br>';
                                }
                                echo '</div>';
                                
                                // Mettre à jour la base de données
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'suno_generations';
                                
                                $wpdb->update($table_name, array(
                                    'status' => 'completed',
                                    'completed_at' => current_time('mysql'),
                                    'audio_url' => $urls['audio_url'],
                                    'video_url' => $urls['video_url'],
                                    'image_url' => $urls['image_url'],
                                    'api_response' => $body
                                ), array('task_id' => $task_id));
                                
                                echo '<p style="color: green;">✅ Base de données mise à jour !</p>';
                            }
                        }
                    }
                } else {
                    echo '<p style="color: red;">Erreur : ' . $response->get_error_message() . '</p>';
                }
                
                echo '</div>';
            }
            
            if (!$found) {
                echo '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24; margin: 15px 0;">';
                echo '<strong>❌ Aucune URL audio trouvée</strong><br>';
                echo 'Le task_id existe peut-être mais les fichiers ne sont pas encore prêts.';
                echo '</div>';
            }
            ?>
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
                <small>v1.7.0 - Timeout étendu à 5 minutes</small>
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
                        <br><small>Task ID: <?php echo esc_html(substr($track->task_id, 0, 20)); ?>...</small>
                    </div>
                <?php elseif ($track->status === 'timeout'): ?>
                    <div style="background: #fff3cd; padding: 10px; border-radius: 5px; font-size: 14px; color: #856404;">
                        ⏱️ La génération a pris trop de temps. 
                        <br>Utilisez <code>[suno_check_task task_id="<?php echo esc_attr($track->task_id); ?>"]</code> pour vérifier manuellement.
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 5px;">
                <p style="margin: 0; font-size: 14px;">
                    💡 <strong>Astuce v1.7.0 :</strong> Si une chanson reste "En cours", utilisez 
                    <code>[suno_force_check]</code> ou <code>[suno_check_task task_id="XXX"]</code> pour forcer la vérification.
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function force_check_status($atts) {
        if (!current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24;">❌ Accès refusé</div>';
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $pending = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'pending' OR status = 'timeout'");
        
        if (empty($pending)) {
            return '<div style="background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;">
                ✅ Aucune génération en attente.
            </div>';
        }
        
        $output = '<div style="background: #f9f9f9; padding: 20px; border-radius: 5px;">';
        $output .= '<h3>🔄 Vérification forcée des générations (v1.7.0)</h3>';
        
        foreach ($pending as $generation) {
            $output .= '<div style="background: #fff; padding: 15px; border-radius: 5px; margin: 15px 0;">';
            $output .= '<h4>' . esc_html($generation->title ?: $generation->prompt) . '</h4>';
            $output .= '<p><small>Task ID: ' . esc_html($generation->task_id) . '</small></p>';
            $output .= '<p><small>Statut actuel: ' . esc_html($generation->status) . '</small></p>';
            
            // Essayer plusieurs endpoints
            $endpoints = array(
                '/api/v1/music/' . $generation->task_id,
                '/api/v1/status/' . $generation->task_id,
                '/api/v1/get?ids=' . $generation->task_id,
                '/api/v1/query?ids=' . $generation->task_id
            );
            
            $found = false;
            
            foreach ($endpoints as $endpoint) {
                if ($found) break;
                
                $api_url = $this->api_base_url . $endpoint;
                
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
                    
                    if ($data) {
                        // Sauvegarder la réponse
                        $wpdb->update($table_name, 
                            array('api_response' => $body), 
                            array('id' => $generation->id)
                        );
                        
                        $urls = $this->extract_urls_from_response($data);
                        
                        if (!empty($urls['audio_url'])) {
                            $found = true;
                            
                            $update_data = array(
                                'status' => 'completed',
                                'completed_at' => current_time('mysql'),
                                'audio_url' => $urls['audio_url'],
                                'video_url' => $urls['video_url'],
                                'image_url' => $urls['image_url']
                            );
                            
                            $wpdb->update($table_name, $update_data, array('id' => $generation->id));
                            
                            $output .= '<p style="color: green;">✅ Mis à jour ! Audio trouvé.</p>';
                            $output .= '<audio controls style="width: 100%; margin-top: 10px;">';
                            $output .= '<source src="' . esc_url($urls['audio_url']) . '" type="audio/mpeg">';
                            $output .= '</audio>';
                        }
                    }
                }
            }
            
            if (!$found) {
                $output .= '<p style="color: orange;">⏳ Pas encore prêt ou introuvable...</p>';
            }
            
            $output .= '</div>';
        }
        
        $output .= '<p><a href="' . get_permalink() . '" style="background: #007cba; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block; margin-top: 10px;">🔄 Rafraîchir</a></p>';
        $output .= '</div>';
        
        return $output;
    }
    
    // Autres méthodes du plugin...
    public function test_shortcode($atts) {
        return '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; color: #155724;">
            <strong>✅ SUCCÈS !</strong> Plugin v1.7.0 (Timeout étendu) fonctionnel<br>
            <strong>Heure :</strong> ' . current_time('d/m/Y H:i:s') . '<br>
            <strong>Améliorations :</strong> ✅ Jusqu\'à 5 minutes de génération supportées
        </div>';
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
            <h3>🔍 Debug v1.7.0 - Timeout étendu</h3>
            
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
                <strong>🎯 Version 1.7.0 :</strong><br>
                ✅ Limite de vérifications : <?php echo $this->max_status_checks; ?> (5 minutes)<br>
                ✅ Sauvegarde des réponses API pour debug<br>
                ✅ Fonction d'extraction d'URLs améliorée<br>
                ✅ Support de plus d'endpoints API
            </div>
            
            <?php if (!empty($recent)): ?>
            <h4>📋 Dernières générations :</h4>
            <div style="background: #fff; padding: 10px; border-radius: 5px; font-size: 12px; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 8px; text-align: left; border-bottom: 2px solid #dee2e6;">Titre/Prompt</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 2px solid #dee2e6;">Statut</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 2px solid #dee2e6;">Audio</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 2px solid #dee2e6;">Date</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 2px solid #dee2e6;">API</th>
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
                                <?php echo $item->audio_url ? '✅' : '❌'; ?>
                            </td>
                            <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">
                                <?php echo date('d/m H:i', strtotime($item->created_at)); ?>
                            </td>
                            <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">
                                <?php echo !empty($item->api_response) ? '📋' : '-'; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <p style="margin-top: 10px;">
                <a href="?page=<?php echo $_GET['page'] ?? ''; ?>&amp;shortcode=suno_test_response" 
                   style="background: #007cba; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none;">
                   📋 Voir la dernière réponse API
                </a>
            </p>
            <?php endif; ?>
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
            <h3>🛡️ Test API - v1.7.0</h3>
            
            <?php if (isset($test_result['success']) && $test_result['success']): ?>
                <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724;">
                    <strong>✅ API accessible !</strong><br>
                    La version 1.7.0 est prête avec timeout étendu à 5 minutes.
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
    
    // Gestionnaire de callback
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
            
            // Sauvegarder la réponse du callback
            $wpdb->update($table_name, 
                array('api_response' => json_encode($data)), 
                array('task_id' => $generation_id)
            );
            
            $urls = $this->extract_urls_from_response($data);
            
            if (!empty($urls['audio_url'])) {
                $update_data = array(
                    'status' => 'completed',
                    'completed_at' => current_time('mysql'),
                    'audio_url' => $urls['audio_url'],
                    'video_url' => $urls['video_url'],
                    'image_url' => $urls['image_url']
                );
                
                $wpdb->update($table_name, $update_data, array('task_id' => $generation_id));
            }
        }
        
        wp_send_json_success(array('message' => 'Callback received'));
    }
}

new SunoMusicGenerator();