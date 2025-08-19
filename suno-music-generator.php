<?php
/**
 * Plugin Name: Suno Music Generator
 * Description: Générateur de musique IA avec Suno via formulaire WordPress
 * Version: 2.2.0
 * Author: WeAreReForm
 * License: GPL-2.0
 * Text Domain: suno-music-generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class SunoMusicGenerator {
    
    private $api_key;
    private $version = '2.2.0';
    private $api_base_url = 'https://api.sunoapi.org';
    
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
            echo '<div class="notice notice-success"><p>Paramètres sauvegardés!</p></div>';
        }
        
        $api_key = get_option('suno_api_key', '');
        ?>
        <div class="wrap">
            <h1>Suno Music Generator - Version <?php echo $this->version; ?></h1>
            
            <div style="background: #fff; padding: 20px; border-left: 4px solid #6366f1; margin: 20px 0;">
                <h2 style="margin-top: 0;">🎵 Configuration du plugin</h2>
                
                <form method="post">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Clé API Suno</th>
                            <td>
                                <input type="text" name="api_key" value="<?php echo esc_attr($api_key); ?>" 
                                       class="regular-text" placeholder="Votre clé API SunoAPI.org" />
                                <p class="description">
                                    Obtenez votre clé API sur <a href="https://sunoapi.org" target="_blank">SunoAPI.org</a><br>
                                    Consultez vos générations sur <a href="https://sunoapi.org/fr/logs" target="_blank">SunoAPI Logs</a>
                                </p>
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
                        - Formulaire de génération
                    </li>
                    <li style="margin: 10px 0;">
                        <code style="background: #fff; padding: 5px 10px; border-radius: 4px;">[suno_music_player]</code> 
                        - Lecteur des créations
                    </li>
                    <li style="margin: 10px 0;">
                        <code style="background: #fff; padding: 5px 10px; border-radius: 4px;">[suno_test_api]</code> 
                        - Test de connexion
                    </li>
                </ul>
            </div>
            
            <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>⚠️ Important</h3>
                <p>Après génération, vérifiez vos créations sur <a href="https://sunoapi.org/fr/logs" target="_blank">SunoAPI Logs</a></p>
                <p>Les générations peuvent prendre 30-60 secondes pour apparaître.</p>
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
    
    public function ajax_generate_music() {
        error_log('=== SUNO GENERATE v' . $this->version . ' ===');
        
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        if (empty($this->api_key)) {
            wp_send_json_error('Clé API non configurée');
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
        
        // URL de callback pour WordPress
        $callback_url = admin_url('admin-ajax.php?action=suno_callback');
        
        // Préparer les données selon le format qui fonctionnait
        $api_data = array(
            'prompt' => $prompt,
            'customMode' => false,
            'instrumental' => $instrumental,
            'callBackUrl' => $callback_url
        );
        
        // Si des options personnalisées sont fournies
        if (!empty($style) || !empty($title) || !empty($lyrics)) {
            $api_data['customMode'] = true;
            
            if (!empty($style)) {
                $api_data['style'] = $style;
            }
            
            if (!empty($title)) {
                $api_data['title'] = $title;
            }
            
            if (!empty($lyrics)) {
                $api_data['lyric'] = $lyrics;
            }
        }
        
        error_log('Sending to API: ' . json_encode($api_data));
        
        // Appel API
        $response = wp_remote_post($this->api_base_url . '/api/v1/generate', array(
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
        error_log('API Response Body: ' . $body);
        
        if ($status_code === 401) {
            wp_send_json_error('Clé API invalide');
            return;
        }
        
        if ($status_code === 402 || $status_code === 429) {
            wp_send_json_error('Limite de crédits atteinte');
            return;
        }
        
        if ($status_code !== 200 && $status_code !== 201) {
            wp_send_json_error('Erreur API: Code ' . $status_code);
            return;
        }
        
        $data = json_decode($body, true);
        
        if (!$data) {
            wp_send_json_error('Réponse API invalide');
            return;
        }
        
        // Extraire le task_id selon différents formats possibles
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
        } elseif (isset($data['id'])) {
            $task_id = $data['id'];
        }
        
        if (!$task_id) {
            error_log('No task_id found in response: ' . json_encode($data));
            wp_send_json_error('Aucun ID de tâche retourné par l\'API');
            return;
        }
        
        error_log('Task ID: ' . $task_id);
        
        // Sauvegarder en base de données
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
            'created_at' => current_time('mysql')
        ));
        
        wp_send_json_success(array(
            'task_id' => $task_id,
            'message' => 'Génération lancée ! Vérifiez sur https://sunoapi.org/fr/logs'
        ));
    }
    
    public function ajax_check_music_status() {
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $task_id = sanitize_text_field($_POST['task_id']);
        
        if (empty($task_id)) {
            wp_send_json_error('Task ID requis');
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
                'image_url' => $generation->image_url
            ));
            return;
        }
        
        // Essayer de récupérer le statut via l'API
        $api_urls = array(
            $this->api_base_url . '/api/v1/music/' . $task_id,
            $this->api_base_url . '/api/v1/get?ids=' . $task_id,
            $this->api_base_url . '/api/v1/status/' . $task_id
        );
        
        foreach ($api_urls as $api_url) {
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
                
                if ($status_code === 200 && !empty($body)) {
                    $data = json_decode($body, true);
                    
                    if ($data) {
                        error_log('Status response: ' . json_encode($data));
                        
                        // Vérifier si la génération est terminée
                        $audio_url = '';
                        $video_url = '';
                        $image_url = '';
                        $status = 'processing';
                        
                        // Adapter selon le format de réponse
                        if (isset($data[0]) && is_array($data[0])) {
                            $item = $data[0];
                            $audio_url = $item['audio_url'] ?? $item['audioUrl'] ?? $item['url'] ?? '';
                            $video_url = $item['video_url'] ?? $item['videoUrl'] ?? '';
                            $image_url = $item['image_url'] ?? $item['imageUrl'] ?? $item['image'] ?? '';
                            $status = $item['status'] ?? 'processing';
                        } elseif (isset($data['data']) && is_array($data['data'])) {
                            if (isset($data['data'][0])) {
                                $item = $data['data'][0];
                                $audio_url = $item['audio_url'] ?? $item['audioUrl'] ?? $item['url'] ?? '';
                                $video_url = $item['video_url'] ?? $item['videoUrl'] ?? '';
                                $image_url = $item['image_url'] ?? $item['imageUrl'] ?? $item['image'] ?? '';
                                $status = $item['status'] ?? 'processing';
                            }
                        } else {
                            $audio_url = $data['audio_url'] ?? $data['audioUrl'] ?? $data['url'] ?? '';
                            $video_url = $data['video_url'] ?? $data['videoUrl'] ?? '';
                            $image_url = $data['image_url'] ?? $data['imageUrl'] ?? $data['image'] ?? '';
                            $status = $data['status'] ?? 'processing';
                        }
                        
                        if (!empty($audio_url)) {
                            // Mise à jour en base de données
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
                        
                        // Si on a un statut mais pas encore d'audio
                        if ($status === 'completed' || $status === 'success') {
                            wp_send_json_success(array(
                                'status' => 'processing',
                                'message' => 'Finalisation en cours...'
                            ));
                            return;
                        }
                    }
                }
            }
        }
        
        // Si on arrive ici, c'est toujours en cours
        wp_send_json_success(array(
            'status' => 'processing',
            'message' => 'Génération en cours... Consultez https://sunoapi.org/fr/logs'
        ));
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
                </div>
                
                <?php if ($track->audio_url): ?>
                <div class="track-player">
                    <audio controls preload="none">
                        <source src="<?php echo esc_url($track->audio_url); ?>" type="audio/mpeg">
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
            
            <div style="margin-top: 20px; padding: 15px; background: #f0f9ff; border-radius: 5px;">
                <p style="margin: 0;">
                    💡 Consultez vos générations sur 
                    <a href="https://sunoapi.org/fr/logs" target="_blank">SunoAPI Logs</a>
                </p>
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
                $test_url = $this->api_base_url . '/api/v1/get_limit';
                $response = wp_remote_get($test_url, array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $this->api_key,
                        'Content-Type' => 'application/json'
                    ),
                    'timeout' => 10
                ));
                
                if (!is_wp_error($response)) {
                    $status = wp_remote_retrieve_response_code($response);
                    $body = wp_remote_retrieve_body($response);
                    
                    if ($status === 200) {
                        $data = json_decode($body, true);
                        echo '<div class="notice notice-success">';
                        echo '<p>✅ Connexion API réussie</p>';
                        if (isset($data['credits'])) {
                            echo '<p>Crédits disponibles : ' . $data['credits'] . '</p>';
                        }
                        echo '</div>';
                    } elseif ($status === 401) {
                        echo '<div class="notice notice-error"><p>❌ Clé API invalide</p></div>';
                    } elseif ($status === 404) {
                        echo '<div class="notice notice-warning"><p>⚠️ Endpoint de test non trouvé (peut être normal)</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>❌ Erreur : Code ' . $status . '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>❌ Erreur de connexion</p></div>';
                }
                ?>
                
                <p>
                    <a href="https://sunoapi.org/fr/logs" target="_blank" class="button">
                        📊 Voir les logs SunoAPI
                    </a>
                </p>
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
                SUM(status = 'completed') as completed
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
            </table>
            
            <?php
            $recent = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 5");
            if ($recent): ?>
                <h5>Dernières générations :</h5>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Prompt</th>
                            <th>Statut</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $item): ?>
                        <tr>
                            <td><?php echo esc_html(wp_trim_words($item->prompt, 10)); ?></td>
                            <td><?php echo esc_html($item->status); ?></td>
                            <td><?php echo esc_html($item->created_at); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <p style="margin-top: 20px;">
                <a href="https://sunoapi.org/fr/logs" target="_blank" class="button button-primary">
                    📊 Voir les logs complets sur SunoAPI
                </a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialiser le plugin
new SunoMusicGenerator();
