<?php
/**
 * Plugin Name: Suno Music Generator
 * Description: Générateur de musique IA avec Suno via formulaire WordPress
 * Version: 1.4 (Test multiple endpoints)
 * Author: Assistant IA
 */

if (!defined('ABSPATH')) {
    exit;
}

class SunoMusicGenerator {
    
    private $api_key;
    private $api_base_url = 'https://apibox.erweima.ai'; // ENDPOINT OFFICIEL CORRIGÉ
    
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
            
            <h2>Informations techniques</h2>
            <p><strong>API Server:</strong> <?php echo esc_html($this->api_base_url); ?></p>
            <p><strong>Version:</strong> 1.4 (Test multiple endpoints)</p>
        </div>
        <?php
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('suno-music-js', plugin_dir_url(__FILE__) . 'assets/suno-music.js', array('jquery'), '1.4', true);
        wp_enqueue_style('suno-music-css', plugin_dir_url(__FILE__) . 'assets/suno-music.css', array(), '1.4');
        
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
        error_log('=== SUNO AJAX GENERATE MUSIC (v1.4 - MULTIPLE ENDPOINT TEST) ===');
        
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
        error_log('API Base URL: ' . $this->api_base_url);
        
        // FORMAT OFFICIEL SUNOAPI.ORG selon la documentation
        $api_data = array(
            'prompt' => $prompt,
            'customMode' => false,
            'instrumental' => $instrumental,
            'model' => 'V3_5' // Modèle par défaut
        );
        
        // Mode personnalisé si des paramètres avancés sont fournis
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
        
        error_log('API Data: ' . json_encode($api_data));
        
        // ENDPOINT OFFICIEL
        $api_url = $this->api_base_url . '/api/v1/generate';
        error_log('API URL: ' . $api_url);
        
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($api_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('API Error: ' . $error_message);
            wp_send_json_error('Erreur de connexion API: ' . $error_message);
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('Status Code: ' . $status_code);
        error_log('Response Body: ' . $body);
        
        // Gestion des codes d'erreur selon la documentation officielle
        switch ($status_code) {
            case 401:
                wp_send_json_error('Clé API invalide. Vérifiez votre clé dans Réglages > Suno Music.');
                return;
            case 429:
                wp_send_json_error('Crédits insuffisants. Ajoutez des crédits sur sunoapi.org.');
                return;
            case 413:
                wp_send_json_error('Description trop longue. Réduisez la taille de votre prompt.');
                return;
            case 455:
                wp_send_json_error('API en maintenance. Réessayez dans quelques minutes.');
                return;
        }
        
        if ($status_code !== 200) {
            wp_send_json_error('Erreur API (Code: ' . $status_code . '): ' . $body);
            return;
        }
        
        $data = json_decode($body, true);
        
        if (!$data) {
            error_log('Failed to decode JSON response');
            wp_send_json_error('Réponse API invalide - JSON non valide');
            return;
        }
        
        // Extraire le task_id selon le format de réponse officiel
        $task_id = null;
        if (isset($data['code']) && $data['code'] === 200) {
            if (isset($data['data']['task_id'])) {
                $task_id = $data['data']['task_id'];
            } elseif (isset($data['data'])) {
                $task_id = $data['data'];
            }
        } elseif (isset($data['task_id'])) {
            $task_id = $data['task_id'];
        }
        
        if (!$task_id) {
            error_log('No task_id found in response: ' . json_encode($data));
            wp_send_json_error('Réponse API invalide - Pas de task_id reçu');
            return;
        }
        
        error_log('SUCCESS! Task ID: ' . $task_id);
        
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
            'api_response' => $data
        ));
    }
    
    public function ajax_check_music_status() {
        error_log('=== CHECK MUSIC STATUS (v1.4 - MULTIPLE ENDPOINT TEST) ===');
        
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $task_id = sanitize_text_field($_POST['task_id']);
        
        if (empty($task_id)) {
            wp_send_json_error('Task ID requis');
        }
        
        if (empty($this->api_key)) {
            wp_send_json_error('Clé API non configurée');
        }
        
        error_log('Checking status for task: ' . $task_id);
        
        // ENDPOINT OFFICIEL POUR RÉCUPÉRER LE STATUT
        $api_url = $this->api_base_url . '/api/v1/task/' . $task_id;
        error_log('Status API URL: ' . $api_url);
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            error_log('Status check failed: ' . $response->get_error_message());
            wp_send_json_success(array(
                'status' => 'processing',
                'message' => 'Vérification en cours...'
            ));
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('Status Code: ' . $status_code . ', Body: ' . substr($body, 0, 200));
        
        if ($status_code === 200) {
            $data = json_decode($body, true);
            
            if ($data && isset($data['code']) && $data['code'] === 200) {
                $track_data = $data['data'];
                
                // Si c'est un tableau, prendre le premier élément
                if (is_array($track_data) && isset($track_data[0])) {
                    $track_data = $track_data[0];
                }
                
                error_log('Track data: ' . json_encode($track_data));
                
                // Mettre à jour la base de données
                global $wpdb;
                $table_name = $wpdb->prefix . 'suno_generations';
                
                $update_data = array();
                
                // Status mapping
                if (isset($track_data['status'])) {
                    $update_data['status'] = $track_data['status'];
                }
                
                // URL audio - différents champs possibles
                $audio_fields = ['audio_url', 'audioUrl', 'url', 'file_url'];
                foreach ($audio_fields as $field) {
                    if (isset($track_data[$field]) && !empty($track_data[$field])) {
                        $update_data['audio_url'] = $track_data[$field];
                        $update_data['status'] = 'completed';
                        $update_data['completed_at'] = current_time('mysql');
                        break;
                    }
                }
                
                // Autres champs
                if (isset($track_data['video_url'])) {
                    $update_data['video_url'] = $track_data['video_url'];
                }
                
                if (isset($track_data['image_url'])) {
                    $update_data['image_url'] = $track_data['image_url'];
                }
                
                if (isset($track_data['duration'])) {
                    $update_data['duration'] = intval($track_data['duration']);
                }
                
                if (!empty($update_data)) {
                    $wpdb->update($table_name, $update_data, array('task_id' => $task_id));
                    error_log('Database updated with: ' . json_encode($update_data));
                }
                
                wp_send_json_success($update_data);
                return;
            }
        }
        
        // Statut par défaut si pas de réponse valide
        wp_send_json_success(array(
            'status' => 'processing',
            'message' => 'Génération en cours...'
        ));
    }
    
    public function test_api_connection() {
        if (empty($this->api_key)) {
            return array('error' => 'Clé API manquante');
        }
        
        // TESTER PLUSIEURS ENDPOINTS POSSIBLES
        $test_endpoints = array(
            '/api/v1/credits',
            '/api/v1/credit',
            '/api/v1/account',
            '/api/v1/user',
            '/api/v1/balance',
            '/credits',
            '/credit'
        );
        
        $results = array();
        $success_endpoint = null;
        
        foreach ($test_endpoints as $endpoint) {
            $test_url = $this->api_base_url . $endpoint;
            
            $response = wp_remote_get($test_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 10
            ));
            
            if (is_wp_error($response)) {
                $results[$endpoint] = array(
                    'status' => 'ERROR',
                    'message' => $response->get_error_message()
                );
                continue;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            $results[$endpoint] = array(
                'status_code' => $status_code,
                'body' => substr($body, 0, 200) . (strlen($body) > 200 ? '...' : ''),
                'success' => $status_code === 200
            );
            
            if ($status_code === 200 && !$success_endpoint) {
                $success_endpoint = $endpoint;
                $data = json_decode($body, true);
                if ($data && isset($data['code']) && $data['code'] === 200) {
                    $results[$endpoint]['credits'] = isset($data['data']['credits']) ? $data['data']['credits'] : 'Non disponible';
                    $results[$endpoint]['parsed_data'] = $data;
                }
            }
        }
        
        return array(
            'api_key_length' => strlen($this->api_key),
            'base_url' => $this->api_base_url,
            'success_endpoint' => $success_endpoint,
            'all_results' => $results,
            'success' => !is_null($success_endpoint)
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
            <h3>🔧 Test SunoAPI.org - Version 1.4 (Test multiple endpoints)</h3>
            
            <div style="background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 10px 0;">
                <strong>Configuration :</strong><br>
                ✅ Clé API : <?php echo !empty($this->api_key) ? 'Configurée (' . $test_result['api_key_length'] . ' caractères)' : '❌ NON CONFIGURÉE'; ?><br>
                ✅ Plugin : Version 1.4 avec test multiple endpoints<br>
                ✅ API Server : <?php echo esc_html($test_result['base_url']); ?>
            </div>
            
            <?php if (isset($test_result['success']) && $test_result['success']): ?>
                <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724;">
                    <strong>✅ SUCCÈS !</strong><br>
                    <strong>Endpoint fonctionnel :</strong> <?php echo esc_html($test_result['success_endpoint']); ?><br>
                    <?php 
                    $success_data = $test_result['all_results'][$test_result['success_endpoint']];
                    if (isset($success_data['credits'])): 
                    ?>
                        <strong>Crédits disponibles :</strong> <?php echo esc_html($success_data['credits']); ?><br>
                    <?php endif; ?>
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
                    <strong>❌ ÉCHEC - Aucun endpoint ne fonctionne</strong><br>
                    Tous les endpoints testés ont échoué.<br>
                    <strong>Vérifiez :</strong>
                    <ul>
                        <li>Votre clé API sur <a href="https://sunoapi.org/api-key" target="_blank">sunoapi.org/api-key</a></li>
                        <li>Que vous avez des crédits disponibles</li>
                        <li>Que la clé n'est pas expirée</li>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;">
                <strong>📊 Résultats des tests :</strong><br>
                <?php foreach ($test_result['all_results'] as $endpoint => $result): ?>
                    <div style="margin: 5px 0; padding: 5px; background: <?php echo $result['success'] ? '#d4edda' : '#f8d7da'; ?>; border-radius: 3px;">
                        <strong><?php echo esc_html($endpoint); ?></strong>: 
                        <?php if (isset($result['status'])): ?>
                            <?php echo esc_html($result['status'] . ' - ' . $result['message']); ?>
                        <?php else: ?>
                            Code <?php echo esc_html($result['status_code']); ?> 
                            <?php echo $result['success'] ? '✅' : '❌'; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <details style="margin: 15px 0;">
                <summary style="cursor: pointer; padding: 10px; background: #e9ecef; border-radius: 5px;">
                    🔍 Détails techniques complets (cliquez pour voir)
                </summary>
                <pre style="background: #fff; padding: 10px; border: 1px solid #ccc; margin-top: 10px; overflow: auto; font-size: 11px;"><?php echo esc_html(print_r($test_result, true)); ?></pre>
            </details>
            
            <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;">
                <strong>📋 Prochaines étapes :</strong><br>
                <?php if (isset($test_result['success']) && $test_result['success']): ?>
                    1. ✅ L'API fonctionne !<br>
                    2. 🎵 Testez le formulaire avec [suno_music_form]<br>
                    3. 🎧 Vérifiez vos créations avec [suno_music_player]<br>
                    4. 📊 Surveillez vos crédits dans votre dashboard
                <?php else: ?>
                    1. 🔑 Vérifiez votre clé API sur <a href="https://sunoapi.org/api-key" target="_blank">sunoapi.org/api-key</a><br>
                    2. 💰 Vérifiez vos crédits disponibles<br>
                    3. 🔄 Essayez de régénérer votre clé API<br>
                    4. 📧 Contactez le support : support@sunoapi.org
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new SunoMusicGenerator();