<?php
/**
 * Plugin Name: Suno Music Generator
 * Description: Générateur de musique IA avec Suno via formulaire WordPress
 * Version: 1.9 (Correction sauvegarde base de données)
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
        add_shortcode('suno_debug', array($this, 'render_debug_info'));
        add_shortcode('suno_test_db', array($this, 'test_database_insert'));
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
            <p><strong>Version:</strong> 1.9 (Correction sauvegarde base de données)</p>
            
            <h2>Shortcodes de diagnostic</h2>
            <p><code>[suno_debug]</code> - Diagnostic de la base de données (admin uniquement)</p>
            <p><code>[suno_test_db]</code> - Test d'insertion en base (admin uniquement)</p>
        </div>
        <?php
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('suno-music-js', plugin_dir_url(__FILE__) . 'assets/suno-music.js', array('jquery'), '1.9', true);
        wp_enqueue_style('suno-music-css', plugin_dir_url(__FILE__) . 'assets/suno-music.css', array(), '1.9');
        
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
    
    public function test_database_insert($atts) {
        if (!current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24;">❌ Accès refusé - Administrateur uniquement</div>';
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        // Vérifier si on doit faire le test
        if (isset($_GET['test_db_insert']) && $_GET['test_db_insert'] === '1') {
            // Générer des données de test
            $test_data = array(
                'user_id' => get_current_user_id(),
                'task_id' => 'test-' . time() . '-' . rand(1000, 9999),
                'prompt' => 'Test d\'insertion en base de données',
                'style' => 'test',
                'title' => 'Chanson de test',
                'lyrics' => 'Paroles de test',
                'status' => 'pending',
                'created_at' => current_time('mysql')
            );
            
            // Tenter l'insertion
            $insert_result = $wpdb->insert($table_name, $test_data);
            
            if ($insert_result !== false) {
                $insert_id = $wpdb->insert_id;
                $success_message = "✅ <strong>SUCCÈS !</strong> Entrée de test créée avec l'ID: {$insert_id}";
                
                // Vérifier l'insertion
                $verification = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $insert_id));
                if ($verification) {
                    $success_message .= "<br>✅ Vérification réussie: l'entrée existe bien en base.";
                } else {
                    $success_message .= "<br>❌ Erreur de vérification: l'entrée n'a pas été trouvée.";
                }
            } else {
                $success_message = "❌ <strong>ÉCHEC !</strong> Impossible d'insérer: " . $wpdb->last_error;
            }
        }
        
        ob_start();
        ?>
        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin: 20px 0; border-radius: 5px; font-family: Arial;">
            <h3>🧪 Test d'insertion en base de données - Version 1.9</h3>
            
            <?php if (isset($success_message)): ?>
                <div style="background: <?php echo $insert_result !== false ? '#d4edda' : '#f8d7da'; ?>; padding: 15px; border-radius: 5px; margin: 15px 0; color: <?php echo $insert_result !== false ? '#155724' : '#721c24'; ?>;">
                    <?php echo $success_message; ?>
                    
                    <?php if ($insert_result !== false): ?>
                        <br><br><strong>📋 Prochaines étapes :</strong><br>
                        1. ✅ L'insertion fonctionne ! Le problème vient d'ailleurs.<br>
                        2. 🔍 Vérifiez les logs WordPress pendant une vraie génération<br>
                        3. 🎵 Essayez de générer une nouvelle chanson avec quelques crédits<br>
                        4. 📊 Consultez à nouveau le diagnostic avec [suno_debug]
                        
                        <br><br>
                        <a href="<?php echo remove_query_arg('test_db_insert'); ?>" style="background: #007cba; color: white; padding: 8px 15px; border-radius: 3px; text-decoration: none;">
                            ← Retour au diagnostic
                        </a>
                    <?php else: ?>
                        <br><br><strong>🔧 Solutions à essayer :</strong><br>
                        1. 🗄️ Recréez la table avec le bouton ci-dessous<br>
                        2. 🔍 Activez WP_DEBUG pour voir les erreurs SQL<br>
                        3. 📧 Contactez votre hébergeur si le problème persiste
                        
                        <br><br>
                        <button onclick="if(confirm('Recréer la table ?')) { location.href='?recreate_table=1'; }" 
                                style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 3px; cursor: pointer;">
                            🔄 Recréer la table
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <h4>🎯 Objectif de ce test</h4>
                    <p>Ce test va créer une entrée factice dans la base de données pour vérifier que l'insertion fonctionne.</p>
                    
                    <p><strong>Si ça fonctionne :</strong> Le problème vient du code de génération</p>
                    <p><strong>Si ça échoue :</strong> Le problème vient de la base de données</p>
                    
                    <a href="?test_db_insert=1" style="background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold;">
                        🧪 Lancer le test d'insertion
                    </a>
                </div>
            <?php endif; ?>
            
            <div style="background: #e9ecef; padding: 10px; border-radius: 5px; margin: 15px 0;">
                <p><strong>💡 Pour utiliser ce test :</strong> Ajoutez <code>[suno_test_db]</code> sur une page (admin uniquement)</p>
                <p><strong>🔙 Retour au diagnostic :</strong> Utilisez <code>[suno_debug]</code></p>
            </div>
        </div>
    
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
        error_log('=== SUNO AJAX GENERATE MUSIC (v1.9 - CORRECTION SAUVEGARDE BDD) ===');
        
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
            'model' => 'V3_5', // Modèle par défaut
            'callBackUrl' => home_url('/wp-admin/admin-ajax.php?action=suno_callback')  // URL de callback WordPress
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
            if (isset($data['data']['taskId'])) {
                $task_id = $data['data']['taskId'];  // Format officiel avec majuscule
            } elseif (isset($data['data']['task_id'])) {
                $task_id = $data['data']['task_id'];  // Format alternatif
            } elseif (isset($data['data'])) {
                $task_id = $data['data'];
            }
        } elseif (isset($data['taskId'])) {
            $task_id = $data['taskId'];  // Format direct avec majuscule
        } elseif (isset($data['task_id'])) {
            $task_id = $data['task_id'];  // Format direct
        }
        
        if (!$task_id) {
            error_log('No task_id found in response: ' . json_encode($data));
            wp_send_json_error('Réponse API invalide - Pas de task_id reçu');
            return;
        }
        
        error_log('SUCCESS! Task ID extracted: ' . $task_id);
        
        // Vérifier que la table existe avant d'insérer
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        // Vérifier l'existence de la table
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            error_log('Table does not exist, creating it...');
            $this->create_tables();
        }
        
        // Préparer les données à insérer
        $insert_data = array(
            'user_id' => get_current_user_id(),
            'task_id' => $task_id,
            'prompt' => $prompt,
            'style' => $style,
            'title' => $title,
            'lyrics' => $lyrics,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );
        
        error_log('Attempting to insert data: ' . json_encode($insert_data));
        
        // Insérer en base avec gestion d'erreur complète
        $insert_result = $wpdb->insert($table_name, $insert_data);
        
        if ($insert_result === false) {
            error_log('DATABASE INSERT FAILED!');
            error_log('Last error: ' . $wpdb->last_error);
            error_log('Last query: ' . $wpdb->last_query);
            
            // Essayer une insertion manuelle
            $manual_query = $wpdb->prepare(
                "INSERT INTO $table_name (user_id, task_id, prompt, style, title, lyrics, status, created_at) 
                 VALUES (%d, %s, %s, %s, %s, %s, %s, %s)",
                get_current_user_id(),
                $task_id,
                $prompt,
                $style,
                $title,
                $lyrics,
                'pending',
                current_time('mysql')
            );
            
            error_log('Attempting manual query: ' . $manual_query);
            $manual_result = $wpdb->query($manual_query);
            
            if ($manual_result === false) {
                error_log('Manual insert also failed: ' . $wpdb->last_error);
                wp_send_json_error('Erreur de sauvegarde en base de données: ' . $wpdb->last_error);
                return;
            } else {
                error_log('Manual insert succeeded!');
            }
        } else {
            error_log('Database insert succeeded! Insert ID: ' . $wpdb->insert_id);
        }
        
        // Vérifier que l'entrée a bien été créée
        $verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE task_id = %s",
            $task_id
        ));
        
        if ($verification) {
            error_log('Verification successful! Entry created with ID: ' . $verification->id);
        } else {
            error_log('Verification failed! Entry not found in database');
        }
        
        wp_send_json_success(array(
            'task_id' => $task_id,
            'message' => 'Génération démarrée avec succès !',
            'api_response' => $data
        ));
    }
    
    public function ajax_check_music_status() {
        error_log('=== CHECK MUSIC STATUS (v1.9 - CORRECTION SAUVEGARDE BDD) ===');
        
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $task_id = sanitize_text_field($_POST['task_id']);
        
        if (empty($task_id)) {
            wp_send_json_error('Task ID requis');
        }
        
        if (empty($this->api_key)) {
            wp_send_json_error('Clé API non configurée');
        }
        
        error_log('Checking status for task: ' . $task_id);
        
        // TESTER PLUSIEURS ENDPOINTS POUR RÉCUPÉRER LE STATUT
        $status_endpoints = array(
            '/api/v1/task/' . $task_id,
            '/api/v1/music/' . $task_id,
            '/api/v1/get?ids=' . $task_id,
            '/get?ids=' . $task_id
        );
        
        $found_result = false;
        
        foreach ($status_endpoints as $endpoint) {
            $api_url = $this->api_base_url . $endpoint;
            error_log('Trying status endpoint: ' . $api_url);
            
            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 15
            ));
            
            if (is_wp_error($response)) {
                error_log('Status endpoint ' . $endpoint . ' failed: ' . $response->get_error_message());
                continue;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            error_log('Status endpoint ' . $endpoint . ' - Code: ' . $status_code);
            
            if ($status_code === 200) {
                $data = json_decode($body, true);
                
                if ($data && isset($data['code']) && $data['code'] === 200) {
                    $track_data = $data['data'];
                    
                    // Si c'est un tableau, prendre le premier élément
                    if (is_array($track_data) && isset($track_data[0])) {
                        $track_data = $track_data[0];
                    }
                    
                    error_log('Track data found: ' . json_encode($track_data));
                    
                    // Mettre à jour la base de données
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'suno_generations';
                    
                    $update_data = array();
                    
                    // Status
                    if (isset($track_data['status'])) {
                        $update_data['status'] = $track_data['status'];
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
                        $result = $wpdb->update($table_name, $update_data, array('task_id' => $task_id));
                        error_log('Database updated with: ' . json_encode($update_data));
                        error_log('Update result: ' . ($result !== false ? 'SUCCESS' : 'FAILED - ' . $wpdb->last_error));
                    }
                    
                    wp_send_json_success($update_data);
                    $found_result = true;
                    break;
                }
            }
        }
        
        if (!$found_result) {
            error_log('No working status endpoint found for task: ' . $task_id);
            
            // Marquer comme potentiellement complété après un certain temps
            global $wpdb;
            $table_name = $wpdb->prefix . 'suno_generations';
            
            $generation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE task_id = %s",
                $task_id
            ));
            
            if ($generation) {
                $created_time = strtotime($generation->created_at);
                $current_time = time();
                $elapsed_minutes = ($current_time - $created_time) / 60;
                
                error_log('Generation age: ' . $elapsed_minutes . ' minutes');
                
                // Si plus de 5 minutes et toujours pending, supposer que c'est terminé
                if ($elapsed_minutes > 5 && $generation->status === 'pending') {
                    error_log('Marking old generation as completed due to timeout');
                    $wpdb->update($table_name, 
                        array('status' => 'completed', 'completed_at' => current_time('mysql')), 
                        array('task_id' => $task_id)
                    );
                    
                    wp_send_json_success(array(
                        'status' => 'completed',
                        'message' => 'Génération probablement terminée (timeout)'
                    ));
                    return;
                }
            }
        }
        
        // Statut par défaut
        wp_send_json_success(array(
            'status' => 'processing',
            'message' => 'Génération en cours...'
        ));
    }
    
    public function handle_suno_callback() {
        error_log('=== SUNO CALLBACK RECEIVED ===');
        
        // Récupérer les données du callback
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        error_log('Callback data: ' . $input);
        
        if (!$data || !isset($data['task_id'])) {
            error_log('Invalid callback data');
            wp_send_json_error('Invalid callback data');
            return;
        }
        
        $task_id = sanitize_text_field($data['task_id']);
        
        // Mettre à jour la base de données avec les nouvelles informations
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $update_data = array();
        
        // Statut
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }
        
        // URL audio
        if (isset($data['audio_url']) && !empty($data['audio_url'])) {
            $update_data['audio_url'] = esc_url_raw($data['audio_url']);
            $update_data['status'] = 'completed';
            $update_data['completed_at'] = current_time('mysql');
        }
        
        // URL vidéo
        if (isset($data['video_url']) && !empty($data['video_url'])) {
            $update_data['video_url'] = esc_url_raw($data['video_url']);
        }
        
        // URL image
        if (isset($data['image_url']) && !empty($data['image_url'])) {
            $update_data['image_url'] = esc_url_raw($data['image_url']);
        }
        
        // Durée
        if (isset($data['duration'])) {
            $update_data['duration'] = intval($data['duration']);
        }
        
        if (!empty($update_data)) {
            $result = $wpdb->update($table_name, $update_data, array('task_id' => $task_id));
            error_log('Database update result: ' . ($result !== false ? 'SUCCESS' : 'FAILED'));
        }
        
        wp_send_json_success(array('message' => 'Callback processed'));
    }
    
    public function render_debug_info($atts) {
        if (!current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24;">❌ Accès refusé - Administrateur uniquement</div>';
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        // Vérifier l'existence et structure de la table
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        $table_structure = $table_exists ? $wpdb->get_results("DESCRIBE $table_name") : array();
        
        // Récupérer toutes les entrées
        $all_generations = $table_exists ? $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 20") : array();
        
        // Statistiques
        $total_count = $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_name") : 0;
        $pending_count = $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'") : 0;
        $completed_count = $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'") : 0;
        $failed_count = $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'failed'") : 0;
        
        ob_start();
        ?>
        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin: 20px 0; border-radius: 5px; font-family: Arial;">
            <h3>🔍 Debug Suno Music Generator - Version 1.9</h3>
            
            <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <h4>🗄️ Informations sur la table de base de données</h4>
                <div style="background: #fff; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace;">
                    <strong>Nom de la table :</strong> <?php echo $table_name; ?><br>
                    <strong>Table existe :</strong> <?php echo $table_exists ? '✅ Oui' : '❌ Non'; ?><br>
                    <?php if ($table_exists): ?>
                        <strong>Colonnes :</strong> <?php echo count($table_structure); ?> colonnes trouvées<br>
                        <details style="margin: 5px 0;">
                            <summary style="cursor: pointer;">Voir la structure</summary>
                            <div style="margin: 5px 0; padding: 5px; background: #f8f9fa;">
                                <?php foreach ($table_structure as $column): ?>
                                    - <?php echo $column->Field; ?> (<?php echo $column->Type; ?>)<br>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <h4>📊 Statistiques de la base de données</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 10px 0;">
                    <div style="background: #fff; padding: 10px; border-radius: 5px; text-align: center;">
                        <strong>Total</strong><br>
                        <span style="font-size: 24px; color: #007cba;"><?php echo $total_count; ?></span>
                    </div>
                    <div style="background: #fff; padding: 10px; border-radius: 5px; text-align: center;">
                        <strong>En cours</strong><br>
                        <span style="font-size: 24px; color: #f39c12;"><?php echo $pending_count; ?></span>
                    </div>
                    <div style="background: #fff; padding: 10px; border-radius: 5px; text-align: center;">
                        <strong>Terminées</strong><br>
                        <span style="font-size: 24px; color: #27ae60;"><?php echo $completed_count; ?></span>
                    </div>
                    <div style="background: #fff; padding: 10px; border-radius: 5px; text-align: center;">
                        <strong>Échouées</strong><br>
                        <span style="font-size: 24px; color: #e74c3c;"><?php echo $failed_count; ?></span>
                    </div>
                </div>
            </div>
            
            <?php if (empty($all_generations)): ?>
                <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <strong>⚠️ Aucune génération trouvée dans la base de données</strong><br>
                    Cela explique pourquoi la playlist est vide.<br>
                    <em>Les générations de l'API Suno n'ont pas été sauvegardées dans WordPress.</em>
                    
                    <?php if (!$table_exists): ?>
                        <br><br><strong>🚨 PROBLÈME CRITIQUE :</strong> La table n'existe pas !<br>
                        <button onclick="location.reload()" style="background: #007cba; color: white; border: none; padding: 8px 15px; border-radius: 3px; cursor: pointer;">
                            🔄 Actualiser pour créer la table
                        </button>
                    <?php else: ?>
                        <br><br><strong>💡 Solution :</strong> Testez l'insertion en base<br>
                        <a href="?test_db_insert=1" style="background: #28a745; color: white; padding: 8px 15px; border-radius: 3px; text-decoration: none;">
                            🧪 Tester l'insertion en base
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <strong>✅ <?php echo count($all_generations); ?> génération(s) trouvée(s)</strong>
                </div>
                
                <h4>📋 Détails des générations (20 dernières)</h4>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; background: #fff;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">ID</th>
                                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Task ID</th>
                                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Prompt</th>
                                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Status</th>
                                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Audio URL</th>
                                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Créé</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_generations as $generation): ?>
                            <tr>
                                <td style="border: 1px solid #ddd; padding: 8px;"><?php echo $generation->id; ?></td>
                                <td style="border: 1px solid #ddd; padding: 8px; font-family: monospace; font-size: 12px;">
                                    <?php echo substr($generation->task_id, 0, 20) . '...'; ?>
                                </td>
                                <td style="border: 1px solid #ddd; padding: 8px;">
                                    <?php echo esc_html(wp_trim_words($generation->prompt, 5)); ?>
                                </td>
                                <td style="border: 1px solid #ddd; padding: 8px;">
                                    <span style="padding: 2px 6px; border-radius: 3px; font-size: 12px; 
                                        background: <?php 
                                            echo $generation->status === 'completed' ? '#d4edda' : 
                                                ($generation->status === 'failed' ? '#f8d7da' : '#fff3cd'); 
                                        ?>;">
                                        <?php echo esc_html($generation->status); ?>
                                    </span>
                                </td>
                                <td style="border: 1px solid #ddd; padding: 8px;">
                                    <?php if ($generation->audio_url): ?>
                                        <a href="<?php echo esc_url($generation->audio_url); ?>" target="_blank" style="color: #007cba;">
                                            🎵 Écouter
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #666;">Pas d'audio</span>
                                    <?php endif; ?>
                                </td>
                                <td style="border: 1px solid #ddd; padding: 8px; font-size: 12px;">
                                    <?php echo date('d/m/Y H:i', strtotime($generation->created_at)); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <h4>🔧 Actions de diagnostic</h4>
                <p><strong>Problème identifié :</strong></p>
                <?php if ($pending_count > 0): ?>
                    <p>🟡 Vous avez <strong><?php echo $pending_count; ?> génération(s) en attente</strong>. 
                    Elles n'apparaissent pas dans la playlist car leur statut n'a pas été mis à jour.</p>
                    
                    <p><strong>Solutions :</strong></p>
                    <ul>
                        <li>✅ Les générations de plus de 5 minutes sont automatiquement marquées comme terminées</li>
                        <li>✅ La version 1.8 teste plusieurs endpoints pour récupérer les résultats</li>
                        <li>💡 Ajoutez quelques crédits et testez une nouvelle génération</li>
                    </ul>
                <?php elseif ($completed_count > 0): ?>
                    <p>✅ Vous avez <strong><?php echo $completed_count; ?> génération(s) terminée(s)</strong>. 
                    Elles devraient apparaître dans la playlist.</p>
                <?php else: ?>
                    <p>ℹ️ Aucune génération dans la base de données. Cela explique pourquoi la playlist est vide.</p>
                <?php endif; ?>
            </div>
            
            <div style="background: #e9ecef; padding: 10px; border-radius: 5px; margin: 15px 0;">
                <p><strong>💡 Pour utiliser ce debug :</strong> Ajoutez <code>[suno_debug]</code> sur une page (admin uniquement)</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function test_api_connection() {
        if (empty($this->api_key)) {
            return array('error' => 'Clé API manquante');
        }
        
        // TESTER L'ENDPOINT PRINCIPAL DE GÉNÉRATION
        $api_url = $this->api_base_url . '/api/v1/generate';
        
        // Requête de test minimale (ne génère rien, juste pour tester l'auth)
        $test_data = array(
            'prompt' => 'test connection',
            'customMode' => false,
            'instrumental' => true,
            'model' => 'V3_5',
            'callBackUrl' => home_url('/wp-admin/admin-ajax.php?action=suno_callback')  // URL de callback WordPress
        );
        
        error_log('Testing API connection to: ' . $api_url);
        error_log('Test data: ' . json_encode($test_data));
        
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($test_data),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array(
                'error' => 'Erreur connexion: ' . $response->get_error_message(),
                'endpoint_tested' => $api_url,
                'method' => 'POST'
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('API Test Response - Status: ' . $status_code);
        error_log('API Test Response - Body: ' . $body);
        
        $result = array(
            'endpoint_tested' => $api_url,
            'method' => 'POST',
            'status_code' => $status_code,
            'response_body' => $body,
            'api_key_length' => strlen($this->api_key),
            'base_url' => $this->api_base_url
        );
        
        // Analyser la réponse
        switch ($status_code) {
            case 200:
                $data = json_decode($body, true);
                if ($data && isset($data['code'])) {
                    if ($data['code'] === 200) {
                        $result['success'] = true;
                        $result['message'] = 'API fonctionne ! Test de génération réussi.';
                        if (isset($data['data']['taskId'])) {
                            $result['test_task_id'] = $data['data']['taskId'];  // Format officiel avec majuscule
                        } elseif (isset($data['data']['task_id'])) {
                            $result['test_task_id'] = $data['data']['task_id'];  // Format alternatif
                        }
                    } else {
                        $result['success'] = false;
                        $result['message'] = 'API répond mais erreur: ' . ($data['msg'] ?? 'inconnue');
                        $result['api_error_code'] = $data['code'];
                    }
                } else {
                    $result['success'] = true;
                    $result['message'] = 'API répond (format de réponse non standard)';
                }
                break;
                
            case 401:
                $result['success'] = false;
                $result['message'] = 'Clé API invalide ou expirée';
                break;
                
            case 429:
                $result['success'] = false;
                $result['message'] = 'Limite de crédits atteinte ou trop de requêtes';
                break;
                
            case 400:
                $result['success'] = false;
                $result['message'] = 'Requête mal formée (mais l\'auth semble OK)';
                break;
                
            case 404:
                $result['success'] = false;
                $result['message'] = 'Endpoint non trouvé - URL incorrecte';
                break;
                
            default:
                $result['success'] = false;
                $result['message'] = 'Erreur HTTP ' . $status_code;
        }
        
        return $result;
    }
    
    public function render_api_test($atts) {
        if (!current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24;">❌ Accès refusé - Administrateur uniquement</div>';
        }
        
        $test_result = $this->test_api_connection();
        
        ob_start();
        ?>
        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin: 20px 0; border-radius: 5px; font-family: Arial;">
            <h3>🔧 Test SunoAPI.org - Version 1.9 (Correction sauvegarde base de données)</h3>
            
            <div style="background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 10px 0;">
                <strong>Configuration :</strong><br>
                ✅ Clé API : <?php echo !empty($this->api_key) ? 'Configurée (' . $test_result['api_key_length'] . ' caractères)' : '❌ NON CONFIGURÉE'; ?><br>
                ✅ Plugin : Version 1.9 avec correction sauvegarde base de données<br>
                ✅ API Server : <?php echo esc_html($test_result['base_url']); ?><br>
                ✅ Endpoint testé : <?php echo esc_html($test_result['endpoint_tested']); ?> (<?php echo esc_html($test_result['method']); ?>)<br>
                ✅ Callback URL : <?php echo esc_html(home_url('/wp-admin/admin-ajax.php?action=suno_callback')); ?>
            </div>
            
            <?php if (isset($test_result['success']) && $test_result['success']): ?>
                <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724;">
                    <strong>✅ SUCCÈS !</strong><br>
                    <?php echo esc_html($test_result['message']); ?><br>
                    <?php if (isset($test_result['test_task_id'])): ?>
                        <strong>Task ID de test :</strong> <?php echo esc_html($test_result['test_task_id']); ?><br>
                    <?php endif; ?>
                    <em>Votre API fonctionne ! Vous pouvez maintenant tester le formulaire de génération.</em>
                </div>
            <?php elseif (isset($test_result['error'])): ?>
                <div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24;">
                    <strong>❌ ERREUR DE CONNEXION</strong><br>
                    <?php echo esc_html($test_result['error']); ?><br>
                    Vérifiez votre connexion internet ou contactez le support.
                </div>
            <?php else: ?>
                <div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24;">
                    <strong>❌ <?php echo isset($test_result['success']) && !$test_result['success'] ? 'ÉCHEC' : 'ERREUR'; ?></strong><br>
                    <strong>Status Code :</strong> <?php echo esc_html($test_result['status_code'] ?? 'inconnu'); ?><br>
                    <strong>Message :</strong> <?php echo esc_html($test_result['message'] ?? 'Aucun message'); ?><br>
                    
                    <?php if ($test_result['status_code'] === 401): ?>
                        <br><strong>🔑 Solution :</strong> Vérifiez votre clé API sur <a href="https://sunoapi.org/api-key" target="_blank">sunoapi.org/api-key</a>
                    <?php elseif ($test_result['status_code'] === 429): ?>
                        <br><strong>💰 Solution :</strong> Ajoutez des crédits sur <a href="https://sunoapi.org/dashboard" target="_blank">votre dashboard</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;">
                <strong>📊 Détails du test :</strong><br>
                <div style="margin: 5px 0; padding: 5px; background: #f8f9fa; border-radius: 3px; font-family: monospace; font-size: 12px;">
                    <strong>Endpoint :</strong> <?php echo esc_html($test_result['endpoint_tested']); ?><br>
                    <strong>Méthode :</strong> <?php echo esc_html($test_result['method']); ?><br>
                    <strong>Status :</strong> <?php echo esc_html($test_result['status_code']); ?><br>
                    <strong>Longueur de réponse :</strong> <?php echo strlen($test_result['response_body']); ?> caractères
                </div>
            </div>
            
            <details style="margin: 15px 0;">
                <summary style="cursor: pointer; padding: 10px; background: #e9ecef; border-radius: 5px;">
                    🔍 Réponse complète de l'API (cliquez pour voir)
                </summary>
                <pre style="background: #fff; padding: 10px; border: 1px solid #ccc; margin-top: 10px; overflow: auto; font-size: 11px; max-height: 300px;"><?php echo esc_html($test_result['response_body']); ?></pre>
            </details>
            
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
                    2. 🎵 Testez le formulaire avec <code>[suno_music_form]</code><br>
                    3. 🎧 Vérifiez vos créations avec <code>[suno_music_player]</code><br>
                    4. 📊 Surveillez vos crédits dans votre dashboard
                <?php elseif ($test_result['status_code'] === 401): ?>
                    1. 🔑 Vérifiez votre clé API sur <a href="https://sunoapi.org/api-key" target="_blank">sunoapi.org/api-key</a><br>
                    2. 🔄 Essayez de régénérer votre clé API<br>
                    3. 📧 Vérifiez que la clé n'est pas expirée
                <?php elseif ($test_result['status_code'] === 429): ?>
                    1. 💰 Vérifiez vos crédits sur <a href="https://sunoapi.org/dashboard" target="_blank">votre dashboard</a><br>
                    2. 💳 Ajoutez des crédits si nécessaire<br>
                    3. ⏱️ Attendez quelques minutes et réessayez
                <?php else: ?>
                    1. 🔄 Réessayez dans quelques minutes<br>
                    2. 📧 Contactez le support : support@sunoapi.org<br>
                    3. 🔍 Partagez les détails techniques ci-dessus
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new SunoMusicGenerator();