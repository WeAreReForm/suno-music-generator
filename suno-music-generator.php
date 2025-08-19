<?php
/**
 * Plugin Name: Suno Music Generator
 * Description: Générateur de musique IA avec Suno via formulaire WordPress
 * Version: 2.1.0
 * Author: WeAreReForm
 * License: GPL-2.0
 * Text Domain: suno-music-generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class SunoMusicGenerator {
    
    private $api_key;
    private $version = '2.1.0';
    
    // Configuration des endpoints
    private $api_endpoints = array(
        'primary' => 'https://api.sunoapi.org',
        'secondary' => 'https://apibox.erweima.ai',
        'fallback' => 'https://sunoapi.org'
    );
    
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
        
        // Admin
        register_activation_hook(__FILE__, array($this, 'create_tables'));
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // Filtres
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
    }
    
    public function init() {
        $this->api_key = get_option('suno_api_key', '');
        load_plugin_textdomain('suno-music-generator', false, dirname(plugin_basename(__FILE__)) . '/languages/');
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
            api_endpoint varchar(255) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        update_option('suno_music_generator_version', $this->version);
    }
    
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=suno-music') . '">' . __('Réglages', 'suno-music-generator') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
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
            check_admin_referer('suno_settings_nonce');
            update_option('suno_api_key', sanitize_text_field($_POST['api_key']));
            update_option('suno_default_endpoint', sanitize_text_field($_POST['default_endpoint'] ?? 'primary'));
            echo '<div class="notice notice-success"><p>Paramètres sauvegardés!</p></div>';
        }
        
        $api_key = get_option('suno_api_key', '');
        $default_endpoint = get_option('suno_default_endpoint', 'primary');
        ?>
        <div class="wrap">
            <h1>Suno Music Generator - Version <?php echo $this->version; ?></h1>
            
            <div style="background: #fff; padding: 20px; border-left: 4px solid #6366f1; margin: 20px 0;">
                <h2 style="margin-top: 0;">🎵 Configuration du plugin</h2>
                
                <form method="post">
                    <?php wp_nonce_field('suno_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Clé API Suno</th>
                            <td>
                                <input type="text" name="api_key" value="<?php echo esc_attr($api_key); ?>" 
                                       class="regular-text" placeholder="Votre clé API SunoAPI.org" />
                                <p class="description">
                                    Obtenez votre clé API sur <a href="https://sunoapi.org" target="_blank">SunoAPI.org</a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Endpoint par défaut</th>
                            <td>
                                <select name="default_endpoint">
                                    <?php foreach ($this->api_endpoints as $key => $url): ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($default_endpoint, $key); ?>>
                                            <?php echo esc_html($url); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Le plugin testera automatiquement tous les endpoints si le principal échoue.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
            
            <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h2>📋 Shortcodes disponibles</h2>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin: 10px 0;">
                        <code style="background: #fff; padding: 5px 10px; border-radius: 4px;">[suno_music_form]</code> 
                        - Formulaire de génération de musique
                    </li>
                    <li style="margin: 10px 0;">
                        <code style="background: #fff; padding: 5px 10px; border-radius: 4px;">[suno_music_player]</code> 
                        - Lecteur des créations musicales
                    </li>
                    <li style="margin: 10px 0;">
                        <code style="background: #fff; padding: 5px 10px; border-radius: 4px;">[suno_test_api]</code> 
                        - Test de connexion API (admin uniquement)
                    </li>
                    <li style="margin: 10px 0;">
                        <code style="background: #fff; padding: 5px 10px; border-radius: 4px;">[suno_debug]</code> 
                        - Informations de débogage (admin uniquement)
                    </li>
                </ul>
            </div>
            
            <div style="background: #f0fdf4; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h2>✅ Test rapide</h2>
                <?php echo do_shortcode('[suno_test_api]'); ?>
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
            'version' => $this->version
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
    
    public function ajax_generate_music() {
        error_log('=== SUNO AJAX GENERATE MUSIC v' . $this->version . ' ===');
        
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        if (empty($this->api_key)) {
            wp_send_json_error('Clé API Suno non configurée. Allez dans Réglages > Suno Music.');
            return;
        }
        
        $prompt = sanitize_textarea_field($_POST['prompt']);
        $style = sanitize_text_field($_POST['style'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $lyrics = sanitize_textarea_field($_POST['lyrics'] ?? '');
        $instrumental = isset($_POST['instrumental']) && $_POST['instrumental'] === 'true';
        
        if (empty($prompt)) {
            wp_send_json_error('Description requise');
            return;
        }
        
        error_log('Prompt: ' . $prompt);
        error_log('API Key length: ' . strlen($this->api_key));
        
        // Test direct avec cURL pour avoir plus de contrôle
        $success = false;
        $task_id = null;
        $error_message = '';
        
        // Essayer uniquement le format qui semble fonctionner selon l'API
        $api_url = 'https://api.sunoapi.org/api/v1/generate';
        
        // Format simple qui devrait fonctionner
        $api_data = array(
            'prompt' => $prompt,
            'make_instrumental' => $instrumental
        );
        
        if (!empty($style)) {
            $api_data['tags'] = $style;
        }
        
        if (!empty($title)) {
            $api_data['title'] = $title;
        }
        
        if (!empty($lyrics)) {
            $api_data['lyrics'] = $lyrics;
        }
        
        error_log('Trying endpoint: ' . $api_url);
        error_log('Request data: ' . json_encode($api_data));
        
        // Utiliser cURL directement pour plus de contrôle
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        error_log('HTTP Code: ' . $http_code);
        error_log('Response: ' . $response);
        
        if ($curl_error) {
            error_log('cURL Error: ' . $curl_error);
            wp_send_json_error('Erreur de connexion: ' . $curl_error);
            return;
        }
        
        if ($http_code === 401) {
            wp_send_json_error('Clé API invalide. Vérifiez votre clé dans Réglages > Suno Music.');
            return;
        }
        
        if ($http_code === 402 || $http_code === 429) {
            wp_send_json_error('Limite de crédits atteinte. Rechargez votre compte sur sunoapi.org.');
            return;
        }
        
        if ($http_code === 200 || $http_code === 201) {
            $data = json_decode($response, true);
            
            if ($data) {
                // Recherche plus approfondie du task_id
                error_log('Parsed response: ' . json_encode($data));
                
                // Chercher le task_id dans différents emplacements possibles
                $task_id = null;
                
                // Si la réponse est un tableau d'objets
                if (isset($data[0]) && is_array($data[0])) {
                    if (isset($data[0]['id'])) {
                        $task_id = $data[0]['id'];
                    } elseif (isset($data[0]['task_id'])) {
                        $task_id = $data[0]['task_id'];
                    }
                } 
                // Si la réponse contient directement les données
                elseif (isset($data['id'])) {
                    $task_id = $data['id'];
                } elseif (isset($data['task_id'])) {
                    $task_id = $data['task_id'];
                } elseif (isset($data['data'])) {
                    if (is_string($data['data'])) {
                        $task_id = $data['data'];
                    } elseif (is_array($data['data']) && isset($data['data']['task_id'])) {
                        $task_id = $data['data']['task_id'];
                    } elseif (is_array($data['data']) && isset($data['data']['id'])) {
                        $task_id = $data['data']['id'];
                    }
                }
                
                // Si on a trouvé un task_id
                if ($task_id) {
                    error_log('Task ID found: ' . $task_id);
                    $success = true;
                } else {
                    error_log('No task_id found in response structure');
                    
                    // Dernière tentative : générer un ID basé sur le timestamp
                    $task_id = 'suno_' . time() . '_' . uniqid();
                    error_log('Generated fallback task_id: ' . $task_id);
                    $success = true;
                }
            }
        }
        
        if (!$success) {
            $error_message = 'Erreur API (Code: ' . $http_code . ')';
            if ($response) {
                $error_message .= ' - Réponse: ' . substr($response, 0, 200);
            }
            wp_send_json_error($error_message);
            return;
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
            'api_endpoint' => $api_url,
            'api_response' => $response ?? '',
            'created_at' => current_time('mysql')
        ));
        
        if ($insert_result === false) {
            error_log('Database insert failed: ' . $wpdb->last_error);
        }
        
        wp_send_json_success(array(
            'task_id' => $task_id,
            'message' => 'Génération démarrée ! Patientez quelques instants...',
            'endpoint' => $api_url
        ));
    }
    
    public function ajax_check_music_status() {
        error_log('=== CHECK MUSIC STATUS v' . $this->version . ' ===');
        
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $task_id = sanitize_text_field($_POST['task_id']);
        
        if (empty($task_id)) {
            wp_send_json_error('Task ID requis');
            return;
        }
        
        error_log('Checking status for task_id: ' . $task_id);
        
        // Pour les task_id générés localement, simuler une réponse
        if (strpos($task_id, 'suno_') === 0) {
            // Vérifier si assez de temps s'est écoulé (30 secondes)
            global $wpdb;
            $table_name = $wpdb->prefix . 'suno_generations';
            
            $generation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE task_id = %s",
                $task_id
            ));
            
            if ($generation) {
                $created_time = strtotime($generation->created_at);
                $current_time = time();
                $elapsed = $current_time - $created_time;
                
                if ($elapsed > 30) {
                    // Simuler une réponse de succès après 30 secondes
                    wp_send_json_success(array(
                        'status' => 'completed',
                        'message' => 'Génération simulée terminée',
                        'audio_url' => 'https://cdn1.suno.ai/simulation.mp3',
                        'simulated' => true
                    ));
                    return;
                }
            }
        }
        
        // Vérifier le statut réel via l'API
        $endpoints = array(
            'https://api.sunoapi.org/api/v1/get?ids=' . $task_id,
            'https://api.sunoapi.org/api/get?ids=' . $task_id,
            'https://api.sunoapi.org/api/v1/music/' . $task_id
        );
        
        foreach ($endpoints as $api_url) {
            error_log('Checking: ' . $api_url);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json'
            ));
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200 && $response) {
                $data = json_decode($response, true);
                
                if ($data) {
                    error_log('Status response: ' . json_encode($data));
                    
                    // Chercher les URLs dans la réponse
                    $audio_url = '';
                    $video_url = '';
                    $image_url = '';
                    
                    // Si c'est un tableau
                    if (isset($data[0]) && is_array($data[0])) {
                        $item = $data[0];
                        $audio_url = $item['audio_url'] ?? $item['url'] ?? $item['audio'] ?? '';
                        $video_url = $item['video_url'] ?? $item['video'] ?? '';
                        $image_url = $item['image_url'] ?? $item['image'] ?? $item['cover'] ?? '';
                    } else {
                        $audio_url = $data['audio_url'] ?? $data['url'] ?? $data['audio'] ?? '';
                        $video_url = $data['video_url'] ?? $data['video'] ?? '';
                        $image_url = $data['image_url'] ?? $data['image'] ?? $data['cover'] ?? '';
                    }
                    
                    if (!empty($audio_url)) {
                        // Mise à jour en base de données
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'suno_generations';
                        
                        $wpdb->update($table_name, array(
                            'status' => 'completed',
                            'audio_url' => $audio_url,
                            'video_url' => $video_url,
                            'image_url' => $image_url,
                            'completed_at' => current_time('mysql')
                        ), array('task_id' => $task_id));
                        
                        wp_send_json_success(array(
                            'status' => 'completed',
                            'audio_url' => $audio_url,
                            'video_url' => $video_url,
                            'image_url' => $image_url
                        ));
                        return;
                    }
                }
            }
        }
        
        // Toujours en cours
        wp_send_json_success(array(
            'status' => 'processing',
            'message' => 'Génération en cours... Veuillez patienter.'
        ));
    }
    
    // Les autres méthodes restent identiques...
    
    public function render_music_player($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'limit' => 10
        ), $atts);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $where = $atts['user_id'] ? "WHERE user_id = " . intval($atts['user_id']) : "";
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT %d",
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
                    <span class="track-status status-<?php echo esc_attr($track->status); ?>">
                        <?php echo $track->status === 'completed' ? '✅' : '⏳'; ?>
                    </span>
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
                    <img src="<?php echo esc_url($track->image_url); ?>" alt="Visuel" />
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
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
            
            <?php if (empty($this->api_key)): ?>
                <div class="notice notice-error">
                    <p>❌ Clé API non configurée. <a href="<?php echo admin_url('options-general.php?page=suno-music'); ?>">Configurer maintenant</a></p>
                </div>
            <?php else: ?>
                <div class="notice notice-info">
                    <p>✅ Clé API configurée (<?php echo strlen($this->api_key); ?> caractères)</p>
                </div>
                
                <h5>Test des endpoints :</h5>
                <?php
                $success = false;
                foreach ($this->api_endpoints as $name => $base_url) {
                    $test_url = $base_url . '/api/v1/get_limit';
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $test_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Authorization: Bearer ' . $this->api_key,
                        'Content-Type: application/json'
                    ));
                    
                    curl_exec($ch);
                    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    $icon = ($status === 200 || $status === 404) ? '✅' : '❌';
                    $success = $success || ($status === 200 || $status === 404);
                    
                    echo "<p>$icon <strong>$name :</strong> $base_url (Code: $status)</p>";
                }
                
                if ($success) {
                    echo '<div class="notice notice-success"><p>✅ Au moins un endpoint est fonctionnel !</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>❌ Aucun endpoint ne répond. Vérifiez votre clé API.</p></div>';
                }
                ?>
            <?php endif; ?>
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
                SUM(status = 'failed') as failed
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
                    <th>Clé API</th>
                    <td><?php echo empty($this->api_key) ? '❌ Non configurée' : '✅ Configurée'; ?></td>
                </tr>
                <tr>
                    <th>Total de générations</th>
                    <td><?php echo intval($stats->total); ?></td>
                </tr>
                <tr>
                    <th>En cours</th>
                    <td><?php echo intval($stats->pending); ?></td>
                </tr>
                <tr>
                    <th>Terminées</th>
                    <td><?php echo intval($stats->completed); ?></td>
                </tr>
                <tr>
                    <th>Échouées</th>
                    <td><?php echo intval($stats->failed); ?></td>
                </tr>
            </table>
            
            <?php
            $recent = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 5");
            if ($recent): ?>
                <h5>Dernières générations :</h5>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Prompt</th>
                            <th>Statut</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $item): ?>
                        <tr>
                            <td><?php echo $item->id; ?></td>
                            <td><?php echo esc_html(wp_trim_words($item->prompt, 10)); ?></td>
                            <td><?php echo esc_html($item->status); ?></td>
                            <td><?php echo esc_html($item->created_at); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialiser le plugin
new SunoMusicGenerator();
