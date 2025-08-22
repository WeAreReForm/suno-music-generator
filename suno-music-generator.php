<?php
/**
 * Plugin Name: Suno Music Generator
 * Description: Générateur de musique IA avec Suno via formulaire WordPress
 * Version: 1.3
 * Author: Assistant IA
 * License: GPL-2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SunoMusicGenerator {
    
    private $api_key;
    private $api_base_url = 'https://api.sunoapi.com';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Shortcodes
        add_shortcode('suno_music_form', array($this, 'render_music_form'));
        add_shortcode('suno_music_player', array($this, 'render_music_player'));
        add_shortcode('suno_test_api', array($this, 'render_api_test'));
        
        // Actions AJAX
        add_action('wp_ajax_generate_music', array($this, 'ajax_generate_music'));
        add_action('wp_ajax_nopriv_generate_music', array($this, 'ajax_generate_music'));
        add_action('wp_ajax_check_music_status', array($this, 'ajax_check_music_status'));
        add_action('wp_ajax_nopriv_check_music_status', array($this, 'ajax_check_music_status'));
        
        // Admin
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // Activation/Désactivation
        register_activation_hook(__FILE__, array($this, 'plugin_activate'));
        register_deactivation_hook(__FILE__, array($this, 'plugin_deactivate'));
    }
    
    public function init() {
        $this->api_key = get_option('suno_api_key', '');
    }
    
    public function plugin_activate() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'suno_generations';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
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
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Ajouter les options par défaut
        add_option('suno_api_key', '');
        add_option('suno_enable_public', '0');
        add_option('suno_max_generations_per_user', '10');
    }
    
    public function plugin_deactivate() {
        // Nettoyer les options temporaires si nécessaire
    }
    
    public function admin_menu() {
        add_menu_page(
            'Suno Music Generator',
            'Suno Music',
            'manage_options',
            'suno-music',
            array($this, 'admin_page'),
            'dashicons-format-audio',
            30
        );
        
        add_submenu_page(
            'suno-music',
            'Réglages Suno',
            'Réglages',
            'manage_options',
            'suno-music-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'suno-music',
            'Historique',
            'Historique',
            'manage_options',
            'suno-music-history',
            array($this, 'history_page')
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>🎵 Suno Music Generator</h1>
            
            <?php if (empty($this->api_key)): ?>
            <div class="notice notice-warning">
                <p><strong>Configuration requise :</strong> Veuillez configurer votre clé API dans les <a href="<?php echo admin_url('admin.php?page=suno-music-settings'); ?>">réglages</a>.</p>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Guide d'utilisation</h2>
                <p>Bienvenue dans Suno Music Generator ! Voici comment utiliser le plugin :</p>
                
                <h3>📌 Shortcodes disponibles</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Shortcode</th>
                            <th>Description</th>
                            <th>Paramètres</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[suno_music_form]</code></td>
                            <td>Affiche le formulaire de génération de musique</td>
                            <td>Aucun</td>
                        </tr>
                        <tr>
                            <td><code>[suno_music_player]</code></td>
                            <td>Affiche les musiques générées</td>
                            <td>
                                <code>user_id</code> (optionnel) - ID de l'utilisateur<br>
                                <code>limit</code> (optionnel) - Nombre de résultats (défaut: 10)
                            </td>
                        </tr>
                        <tr>
                            <td><code>[suno_test_api]</code></td>
                            <td>Test de connexion à l'API (admin uniquement)</td>
                            <td>Aucun</td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>🚀 Démarrage rapide</h3>
                <ol>
                    <li>Configurez votre clé API dans les <a href="<?php echo admin_url('admin.php?page=suno-music-settings'); ?>">réglages</a></li>
                    <li>Créez une nouvelle page</li>
                    <li>Ajoutez le shortcode <code>[suno_music_form]</code></li>
                    <li>Publiez la page et commencez à générer de la musique !</li>
                </ol>
                
                <?php if (!empty($this->api_key)): ?>
                <h3>✅ Test de connexion</h3>
                <div style="background: #f0f0f0; padding: 20px; border-radius: 5px;">
                    <?php echo do_shortcode('[suno_test_api]'); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function settings_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer('suno_settings_nonce');
            
            update_option('suno_api_key', sanitize_text_field($_POST['api_key']));
            update_option('suno_enable_public', isset($_POST['enable_public']) ? '1' : '0');
            update_option('suno_max_generations_per_user', intval($_POST['max_generations']));
            
            echo '<div class="notice notice-success"><p>Paramètres sauvegardés avec succès !</p></div>';
        }
        
        $api_key = get_option('suno_api_key', '');
        $enable_public = get_option('suno_enable_public', '0');
        $max_generations = get_option('suno_max_generations_per_user', '10');
        ?>
        <div class="wrap">
            <h1>Réglages Suno Music Generator</h1>
            
            <form method="post">
                <?php wp_nonce_field('suno_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_key">Clé API Suno</label>
                        </th>
                        <td>
                            <input type="password" 
                                   name="api_key" 
                                   id="api_key"
                                   value="<?php echo esc_attr($api_key); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                Obtenez votre clé API sur <a href="https://sunoapi.com" target="_blank">SunoAPI.com</a>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Accès public</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="enable_public" 
                                       value="1" 
                                       <?php checked($enable_public, '1'); ?> />
                                Permettre aux visiteurs non connectés de générer de la musique
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="max_generations">Limite par utilisateur</label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="max_generations" 
                                   id="max_generations"
                                   value="<?php echo esc_attr($max_generations); ?>" 
                                   min="1" 
                                   max="100"
                                   class="small-text" />
                            <p class="description">
                                Nombre maximum de générations par utilisateur (par jour)
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Sauvegarder les réglages'); ?>
            </form>
            
            <?php if (!empty($api_key)): ?>
            <hr>
            <h2>Test de l'API</h2>
            <p>Utilisez ce test pour vérifier que votre clé API fonctionne correctement :</p>
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
                <?php echo do_shortcode('[suno_test_api]'); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function history_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        
        $results = $wpdb->get_results("
            SELECT g.*, u.display_name 
            FROM $table_name g
            LEFT JOIN {$wpdb->users} u ON g.user_id = u.ID
            ORDER BY g.created_at DESC
            LIMIT 50
        ");
        ?>
        <div class="wrap">
            <h1>Historique des générations</h1>
            
            <?php if (empty($results)): ?>
            <p>Aucune génération pour le moment.</p>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Utilisateur</th>
                        <th>Titre</th>
                        <th>Prompt</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?php echo $row->id; ?></td>
                        <td><?php echo esc_html($row->display_name ?: 'Anonyme'); ?></td>
                        <td><?php echo esc_html($row->title ?: 'Sans titre'); ?></td>
                        <td><?php echo esc_html(wp_trim_words($row->prompt, 10)); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $row->status; ?>">
                                <?php echo ucfirst($row->status); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
                        <td>
                            <?php if ($row->audio_url): ?>
                            <a href="<?php echo esc_url($row->audio_url); ?>" target="_blank" class="button button-small">
                                Écouter
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'suno-music-js', 
            plugin_dir_url(__FILE__) . 'assets/suno-music.js', 
            array('jquery'), 
            '1.3', 
            true
        );
        wp_enqueue_style(
            'suno-music-css', 
            plugin_dir_url(__FILE__) . 'assets/suno-music.css', 
            array(), 
            '1.3'
        );
        
        wp_localize_script('suno-music-js', 'suno_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('suno_music_nonce')
        ));
    }
    
    public function render_music_form($atts) {
        if (empty($this->api_key)) {
            return '<div class="suno-error">Le plugin n\'est pas configuré. Veuillez contacter l\'administrateur.</div>';
        }
        
        $enable_public = get_option('suno_enable_public', '0');
        if (!is_user_logged_in() && $enable_public !== '1') {
            return '<div class="suno-error">Vous devez être connecté pour générer de la musique.</div>';
        }
        
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
                    <div id="char-counter" class="char-counter">0/500</div>
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
                            <option value="metal">Metal</option>
                            <option value="r&b">R&B</option>
                            <option value="disco">Disco</option>
                            <option value="funk">Funk</option>
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
                
                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" id="instrumental" name="instrumental">
                        <span>Version instrumentale (sans voix)</span>
                    </label>
                </div>
                
                <button type="submit" class="suno-btn suno-btn-primary">
                    🎼 Générer la musique
                </button>
            </form>
            
            <div id="generation-status" class="suno-status" style="display: none;">
                <div class="status-content">
                    <div class="spinner"></div>
                    <p class="status-message">
                        <strong>Génération en cours...</strong>
                        <span id="status-text">Initialisation</span>
                    </p>
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <p class="status-info">Cela peut prendre 1 à 2 minutes</p>
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
        
        $query = "SELECT * FROM $table_name WHERE status = 'completed'";
        
        if ($atts['user_id']) {
            $query .= $wpdb->prepare(" AND user_id = %d", intval($atts['user_id']));
        }
        
        $query .= $wpdb->prepare(" ORDER BY created_at DESC LIMIT %d", intval($atts['limit']));
        
        $results = $wpdb->get_results($query);
        
        if (empty($results)) {
            return '<div class="suno-playlist"><p>Aucune chanson générée pour le moment.</p></div>';
        }
        
        ob_start();
        ?>
        <div class="suno-playlist">
            <h3>🎵 Bibliothèque musicale</h3>
            
            <?php foreach ($results as $track): ?>
            <div class="suno-track">
                <div class="track-info">
                    <h4><?php echo esc_html($track->title ?: 'Chanson sans titre'); ?></h4>
                    <p class="track-prompt"><?php echo esc_html(wp_trim_words($track->prompt, 15)); ?></p>
                    <?php if ($track->style): ?>
                    <span class="track-style"><?php echo esc_html($track->style); ?></span>
                    <?php endif; ?>
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
    
    public function render_api_test($atts) {
        if (!current_user_can('manage_options')) {
            return '<div class="suno-error">Accès réservé aux administrateurs.</div>';
        }
        
        if (empty($this->api_key)) {
            return '<div class="suno-error">Clé API non configurée.</div>';
        }
        
        $test_result = $this->test_api_connection();
        
        ob_start();
        ?>
        <div class="suno-api-test">
            <h4>🔧 Test de connexion API</h4>
            
            <?php if ($test_result['success']): ?>
            <div class="test-success">
                <strong>✅ Connexion réussie !</strong><br>
                <span>Crédits disponibles : <?php echo $test_result['credits'] ?? 'N/A'; ?></span><br>
                <span>API Version : <?php echo $test_result['version'] ?? 'N/A'; ?></span>
            </div>
            <?php else: ?>
            <div class="test-error">
                <strong>❌ Erreur de connexion</strong><br>
                <span><?php echo esc_html($test_result['error']); ?></span>
            </div>
            <?php endif; ?>
            
            <details style="margin-top: 10px;">
                <summary>Détails techniques</summary>
                <pre><?php echo esc_html(print_r($test_result, true)); ?></pre>
            </details>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function test_api_connection() {
        // Test avec un endpoint simple qui devrait retourner les infos de compte
        $response = wp_remote_get($this->api_base_url . '/api/get_limit', array(
            'headers' => array(
                'api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 200) {
            $data = json_decode($body, true);
            return array(
                'success' => true,
                'credits' => $data['credits'] ?? $data['quota'] ?? 'Unknown',
                'version' => '1.0',
                'raw' => $data
            );
        } else {
            return array(
                'success' => false,
                'error' => 'Code HTTP ' . $status_code,
                'body' => $body
            );
        }
    }
    
    public function ajax_generate_music() {
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        if (empty($this->api_key)) {
            wp_send_json_error('Clé API non configurée.');
        }
        
        // Vérifier les permissions
        $enable_public = get_option('suno_enable_public', '0');
        if (!is_user_logged_in() && $enable_public !== '1') {
            wp_send_json_error('Vous devez être connecté pour générer de la musique.');
        }
        
        // Récupérer les données
        $prompt = sanitize_textarea_field($_POST['prompt']);
        $style = sanitize_text_field($_POST['style'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $lyrics = sanitize_textarea_field($_POST['lyrics'] ?? '');
        $instrumental = isset($_POST['instrumental']) && $_POST['instrumental'] === 'true';
        
        if (empty($prompt)) {
            wp_send_json_error('Description requise');
        }
        
        // Construire le prompt complet
        $full_prompt = $prompt;
        if (!empty($style)) {
            $full_prompt = $style . ', ' . $full_prompt;
        }
        
        // Préparer les données pour l'API
        $api_data = array(
            'prompt' => $full_prompt,
            'make_instrumental' => $instrumental,
            'wait_audio' => false
        );
        
        if (!empty($title)) {
            $api_data['title'] = $title;
        }
        
        if (!empty($lyrics)) {
            $api_data['prompt'] = '[Verse]\n' . $lyrics . '\n\n' . $full_prompt;
        }
        
        // Appel API pour générer la musique
        $response = wp_remote_post($this->api_base_url . '/api/generate', array(
            'headers' => array(
                'api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($api_data),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Erreur de connexion : ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && !empty($data)) {
            // Extraire les IDs des clips générés
            $clip_ids = array();
            if (isset($data['clips'])) {
                foreach ($data['clips'] as $clip) {
                    $clip_ids[] = $clip['id'];
                }
            } elseif (isset($data[0]['id'])) {
                foreach ($data as $clip) {
                    $clip_ids[] = $clip['id'];
                }
            }
            
            if (!empty($clip_ids)) {
                // Sauvegarder en base
                global $wpdb;
                $wpdb->insert(
                    $wpdb->prefix . 'suno_generations',
                    array(
                        'user_id' => get_current_user_id(),
                        'task_id' => implode(',', $clip_ids),
                        'clip_ids' => json_encode($clip_ids),
                        'prompt' => $prompt,
                        'style' => $style,
                        'title' => $title,
                        'lyrics' => $lyrics,
                        'status' => 'pending',
                        'created_at' => current_time('mysql')
                    )
                );
                
                wp_send_json_success(array(
                    'clip_ids' => $clip_ids,
                    'message' => 'Génération démarrée avec succès !'
                ));
            } else {
                wp_send_json_error('Aucun ID de clip retourné');
            }
        } else {
            $error_msg = $data['detail'] ?? $data['error'] ?? 'Erreur inconnue';
            if ($status_code === 401) {
                $error_msg = 'Clé API invalide';
            } elseif ($status_code === 402) {
                $error_msg = 'Crédits insuffisants';
            }
            
            wp_send_json_error($error_msg . ' (Code: ' . $status_code . ')');
        }
    }
    
    public function ajax_check_music_status() {
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $clip_ids = $_POST['clip_ids'] ?? array();
        
        if (empty($clip_ids)) {
            wp_send_json_error('IDs de clips requis');
        }
        
        // Convertir en tableau si c'est une chaîne
        if (is_string($clip_ids)) {
            $clip_ids = json_decode($clip_ids, true);
        }
        
        // Appel API pour vérifier le statut
        $ids_string = is_array($clip_ids) ? implode(',', $clip_ids) : $clip_ids;
        
        $response = wp_remote_get($this->api_base_url . '/api/get?ids=' . $ids_string, array(
            'headers' => array(
                'api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
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
        
        if (!empty($data)) {
            // Vérifier le statut du premier clip
            $clip = is_array($data) && isset($data[0]) ? $data[0] : $data;
            
            $status = 'processing';
            $audio_url = '';
            $video_url = '';
            $image_url = '';
            
            // Déterminer le statut basé sur les champs disponibles
            if (isset($clip['status'])) {
                $status = $clip['status'];
            }
            
            if (!empty($clip['audio_url'])) {
                $status = 'complete';
                $audio_url = $clip['audio_url'];
                $video_url = $clip['video_url'] ?? '';
                $image_url = $clip['image_url'] ?? $clip['image_large_url'] ?? '';
            }
            
            // Mettre à jour la base si complété
            if ($status === 'complete' && !empty($audio_url)) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'suno_generations',
                    array(
                        'status' => 'completed',
                        'audio_url' => $audio_url,
                        'video_url' => $video_url,
                        'image_url' => $image_url,
                        'completed_at' => current_time('mysql')
                    ),
                    array('task_id' => $ids_string)
                );
            }
            
            wp_send_json_success(array(
                'status' => $status,
                'audio_url' => $audio_url,
                'video_url' => $video_url,
                'image_url' => $image_url,
                'title' => $clip['title'] ?? '',
                'raw' => $clip
            ));
        } else {
            wp_send_json_success(array(
                'status' => 'processing',
                'message' => 'Génération en cours...'
            ));
        }
    }
}

// Initialiser le plugin
new SunoMusicGenerator();