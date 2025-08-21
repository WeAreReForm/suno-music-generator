<?php
/**
 * Plugin Name: Suno Music Generator
 * Description: Générateur de musique IA avec Suno via formulaire WordPress
 * Version: 2.1
 * Author: WeAreReForm
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

class SunoMusicGeneratorV2 {
    
    private $api_key;
    private $api_base_url = 'https://api.sunoapi.org';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('suno_music_form', array($this, 'render_music_form'));
        add_shortcode('suno_music_player', array($this, 'render_music_player'));
        add_shortcode('suno_test_api', array($this, 'render_api_test'));
        add_shortcode('suno_maintenance', array($this, 'render_maintenance_tool'));
        
        // AJAX handlers
        add_action('wp_ajax_generate_music', array($this, 'ajax_generate_music'));
        add_action('wp_ajax_nopriv_generate_music', array($this, 'ajax_generate_music'));
        add_action('wp_ajax_check_music_status', array($this, 'ajax_check_music_status'));
        add_action('wp_ajax_nopriv_check_music_status', array($this, 'ajax_check_music_status'));
        add_action('wp_ajax_suno_run_maintenance', array($this, 'ajax_run_maintenance'));
        
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
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
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
            model_name varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Ajouter version dans les options
        update_option('suno_music_generator_version', '2.1');
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
            echo '<div class="notice notice-success"><p>✅ Paramètres sauvegardés!</p></div>';
        }
        
        $api_key = get_option('suno_api_key', '');
        ?>
        <div class="wrap">
            <h1>🎵 Configuration Suno Music Generator v2.1</h1>
            
            <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h3>📋 Version 2.1 - Nouveautés</h3>
                <ul>
                    <li>✅ Outil de maintenance intégré</li>
                    <li>✅ Nettoyage des conflits de versions</li>
                    <li>✅ Diagnostic système amélioré</li>
                    <li>✅ Correction complète du système d'affichage</li>
                </ul>
            </div>
            
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">Clé API SunoAPI.org</th>
                        <td>
                            <input type="text" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                            <p class="description">
                                Obtenez votre clé API sur <a href="https://sunoapi.org" target="_blank">SunoAPI.org</a>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <h2>🔧 Outils de maintenance</h2>
            <p>Pour accéder aux outils de maintenance (administrateurs uniquement) :</p>
            <p><code>[suno_maintenance]</code></p>
            
            <h2>🔧 Test de connectivité</h2>
            <p>Utilisez le shortcode <code>[suno_test_api]</code> pour tester votre connexion API.</p>
            
            <h2>📖 Shortcodes disponibles</h2>
            <table class="widefat">
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
                        <td>Affiche le formulaire de génération</td>
                        <td>Aucun</td>
                    </tr>
                    <tr>
                        <td><code>[suno_music_player]</code></td>
                        <td>Affiche les créations musicales</td>
                        <td>user_id, limit</td>
                    </tr>
                    <tr>
                        <td><code>[suno_test_api]</code></td>
                        <td>Test de l'API (admin seulement)</td>
                        <td>Aucun</td>
                    </tr>
                    <tr style="background: #fef3c7;">
                        <td><code>[suno_maintenance]</code></td>
                        <td><strong>Outils de maintenance</strong> (admin seulement)</td>
                        <td>Aucun</td>
                    </tr>
                </tbody>
            </table>
            
            <h2>📊 Statistiques</h2>
            <?php
            global $wpdb;
            $table_name = $wpdb->prefix . 'suno_generations';
            $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $completed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
            ?>
            <p>
                <strong>Total de générations :</strong> <?php echo intval($total); ?><br>
                <strong>Générations réussies :</strong> <?php echo intval($completed); ?>
            </p>
        </div>
        <?php
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'suno-music-js', 
            plugin_dir_url(__FILE__) . 'assets/suno-music-v2.js', 
            array('jquery'), 
            '2.1', 
            true
        );
        wp_enqueue_style(
            'suno-music-css', 
            plugin_dir_url(__FILE__) . 'assets/suno-music-v2.css', 
            array(), 
            '2.1'
        );
        
        wp_localize_script('suno-music-js', 'suno_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('suno_music_nonce')
        ));
    }
    
    public function render_maintenance_tool($atts) {
        if (!current_user_can('manage_options')) {
            return '<div class="suno-error-box">❌ Accès réservé aux administrateurs</div>';
        }
        
        ob_start();
        ?>
        <div class="suno-maintenance-container" style="max-width: 800px; margin: 0 auto; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h2 style="color: #f59e0b; border-bottom: 2px solid #f59e0b; padding-bottom: 10px;">
                🔧 Outils de maintenance Suno Music Generator
            </h2>
            
            <div style="background: #fef3c7; border: 2px solid #f59e0b; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h3 style="margin-top: 0;">⚠️ Attention</h3>
                <p>Ces outils sont puissants et peuvent modifier votre installation. Utilisez-les avec précaution.</p>
            </div>
            
            <!-- Diagnostic système -->
            <div class="maintenance-section" style="background: #f8fafc; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h3>📊 Diagnostic système</h3>
                <button class="suno-btn suno-btn-primary" onclick="runMaintenance('diagnostic')">
                    🔍 Lancer le diagnostic
                </button>
                <div id="diagnostic-results" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Nettoyage des conflits -->
            <div class="maintenance-section" style="background: #f8fafc; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h3>🧹 Nettoyage des conflits de versions</h3>
                <p>Résout les problèmes de versions multiples du plugin.</p>
                <button class="suno-btn suno-btn-secondary" onclick="runMaintenance('clean_conflicts')">
                    🗑️ Nettoyer les conflits
                </button>
                <div id="clean-conflicts-results" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Réparation de la base de données -->
            <div class="maintenance-section" style="background: #f8fafc; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h3>🗄️ Réparation de la base de données</h3>
                <p>Vérifie et répare la table des générations.</p>
                <button class="suno-btn suno-btn-primary" onclick="runMaintenance('repair_database')">
                    🔧 Réparer la base de données
                </button>
                <div id="repair-database-results" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Vider le cache -->
            <div class="maintenance-section" style="background: #f8fafc; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h3>🔄 Vider le cache</h3>
                <p>Supprime tous les caches et transients du plugin.</p>
                <button class="suno-btn suno-btn-primary" onclick="runMaintenance('clear_cache')">
                    🔄 Vider le cache
                </button>
                <div id="clear-cache-results" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Réinitialisation complète -->
            <div class="maintenance-section" style="background: #fee2e2; padding: 20px; margin: 20px 0; border-radius: 5px; border: 2px solid #ef4444;">
                <h3 style="color: #ef4444;">💀 Réinitialisation complète</h3>
                <p><strong>DANGER :</strong> Supprime TOUTES les données sauf la clé API.</p>
                <label>
                    <input type="checkbox" id="confirm-reset" />
                    Je comprends que cette action est irréversible
                </label><br><br>
                <button class="suno-btn" style="background: #ef4444;" onclick="runMaintenance('full_reset')" disabled id="reset-btn">
                    ⚠️ Réinitialiser complètement
                </button>
                <div id="full-reset-results" style="margin-top: 15px;"></div>
            </div>
        </div>
        
        <script>
        document.getElementById('confirm-reset').addEventListener('change', function() {
            document.getElementById('reset-btn').disabled = !this.checked;
        });
        
        function runMaintenance(action) {
            const resultDiv = document.getElementById(action.replace('_', '-') + '-results');
            resultDiv.innerHTML = '<div class="spinner" style="border: 3px solid #f3f3f3; border-top: 3px solid #6366f1; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite;"></div>';
            
            jQuery.post(suno_ajax.ajax_url, {
                action: 'suno_run_maintenance',
                maintenance_action: action,
                nonce: suno_ajax.nonce
            }, function(response) {
                if (response.success) {
                    resultDiv.innerHTML = '<div style="background: #dcfce7; padding: 15px; border-radius: 5px; margin-top: 10px;">' + response.data + '</div>';
                } else {
                    resultDiv.innerHTML = '<div style="background: #fee2e2; padding: 15px; border-radius: 5px; margin-top: 10px;">Erreur : ' + response.data + '</div>';
                }
            });
        }
        </script>
        
        <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    public function ajax_run_maintenance() {
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès non autorisé');
        }
        
        $action = sanitize_text_field($_POST['maintenance_action']);
        $result = '';
        
        global $wpdb;
        
        switch ($action) {
            case 'diagnostic':
                $result = $this->run_diagnostic();
                break;
                
            case 'clean_conflicts':
                $result = $this->clean_version_conflicts();
                break;
                
            case 'repair_database':
                $result = $this->repair_database();
                break;
                
            case 'clear_cache':
                $result = $this->clear_all_cache();
                break;
                
            case 'full_reset':
                $result = $this->full_reset();
                break;
                
            default:
                wp_send_json_error('Action non reconnue');
        }
        
        wp_send_json_success($result);
    }
    
    private function run_diagnostic() {
        $results = array();
        
        // Version PHP
        $results[] = 'PHP Version: ' . PHP_VERSION . (version_compare(PHP_VERSION, '7.4', '>=') ? ' ✅' : ' ❌');
        
        // Plugin actif
        $active = is_plugin_active('suno-music-generator/suno-music-generator-v2.php');
        $results[] = 'Plugin actif: ' . ($active ? '✅' : '❌');
        
        // Fichiers présents
        $plugin_dir = plugin_dir_path(__FILE__);
        $files = array(
            'suno-music-generator-v2.php' => 'Fichier principal',
            'assets/suno-music-v2.js' => 'JavaScript',
            'assets/suno-music-v2.css' => 'CSS'
        );
        
        foreach ($files as $file => $name) {
            $exists = file_exists($plugin_dir . $file);
            $results[] = $name . ': ' . ($exists ? '✅' : '❌ Manquant');
        }
        
        // Base de données
        global $wpdb;
        $table_name = $wpdb->prefix . 'suno_generations';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        $results[] = 'Table BDD: ' . ($table_exists ? '✅' : '❌');
        
        if ($table_exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $results[] = 'Enregistrements: ' . $count;
        }
        
        // API Key
        $api_key = get_option('suno_api_key', '');
        $results[] = 'Clé API: ' . (!empty($api_key) ? '✅ Configurée' : '❌ Manquante');
        
        // Test API
        if (!empty($api_key)) {
            $response = wp_remote_get('https://api.sunoapi.org/api/get_limit', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 10
            ));
            
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                $results[] = 'Connexion API: ' . ($status_code === 200 ? '✅ OK' : '❌ Erreur ' . $status_code);
            } else {
                $results[] = 'Connexion API: ❌ Erreur réseau';
            }
        }
        
        return '<h4>Résultats du diagnostic :</h4><ul><li>' . implode('</li><li>', $results) . '</li></ul>';
    }
    
    private function clean_version_conflicts() {
        global $wpdb;
        $results = array();
        
        // 1. Désactiver toutes les versions du plugin
        $active_plugins = get_option('active_plugins', array());
        $original_count = count($active_plugins);
        $cleaned_plugins = array();
        
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'suno-music-generator') === false) {
                $cleaned_plugins[] = $plugin;
            }
        }
        
        // Réactiver uniquement la bonne version
        $cleaned_plugins[] = 'suno-music-generator/suno-music-generator-v2.php';
        update_option('active_plugins', $cleaned_plugins);
        
        $removed = $original_count - count($cleaned_plugins) + 1;
        $results[] = "✅ $removed version(s) nettoyée(s)";
        
        // 2. Nettoyer les options obsolètes
        $options_to_clean = array(
            'suno_music_settings',
            'suno_music_cache',
            '_transient_suno_',
            '_site_transient_suno_'
        );
        
        foreach ($options_to_clean as $option) {
            delete_option($option);
        }
        
        // Nettoyer les transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_suno_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_suno_%'");
        
        $results[] = "✅ Options obsolètes nettoyées";
        
        // 3. Vider le cache
        wp_cache_flush();
        $results[] = "✅ Cache vidé";
        
        // 4. Régénérer les règles de réécriture
        flush_rewrite_rules();
        $results[] = "✅ Règles de réécriture réinitialisées";
        
        return '<h4>Nettoyage terminé :</h4><ul><li>' . implode('</li><li>', $results) . '</li></ul>';
    }
    
    private function repair_database() {
        global $wpdb;
        $results = array();
        
        $table_name = $wpdb->prefix . 'suno_generations';
        
        // Vérifier si la table existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            // Créer la table
            $this->create_tables();
            $results[] = "✅ Table créée";
        } else {
            // Optimiser la table
            $wpdb->query("OPTIMIZE TABLE $table_name");
            $results[] = "✅ Table optimisée";
            
            // Nettoyer les enregistrements orphelins
            $cleaned = $wpdb->query("DELETE FROM $table_name WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $results[] = "✅ $cleaned enregistrement(s) orphelin(s) supprimé(s)";
        }
        
        // Vérifier l'intégrité
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $results[] = "📊 Total : $count enregistrements";
        
        return '<h4>Réparation de la base de données :</h4><ul><li>' . implode('</li><li>', $results) . '</li></ul>';
    }
    
    private function clear_all_cache() {
        global $wpdb;
        $results = array();
        
        // 1. Cache WordPress
        wp_cache_flush();
        $results[] = "✅ Cache WordPress vidé";
        
        // 2. Transients du plugin
        $deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_%suno%'");
        $results[] = "✅ $deleted transient(s) supprimé(s)";
        
        // 3. Options de cache personnalisées
        delete_option('suno_music_cache');
        delete_option('suno_api_cache');
        $results[] = "✅ Cache personnalisé supprimé";
        
        // 4. Règles de réécriture
        flush_rewrite_rules();
        $results[] = "✅ Règles de réécriture réinitialisées";
        
        return '<h4>Cache vidé :</h4><ul><li>' . implode('</li><li>', $results) . '</li></ul>';
    }
    
    private function full_reset() {
        global $wpdb;
        $results = array();
        
        // Sauvegarder la clé API
        $api_key = get_option('suno_api_key', '');
        
        // 1. Supprimer la table
        $table_name = $wpdb->prefix . 'suno_generations';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        $results[] = "✅ Table supprimée";
        
        // 2. Supprimer toutes les options
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%suno%' AND option_name != 'suno_api_key'");
        $results[] = "✅ Options supprimées";
        
        // 3. Recréer la table
        $this->create_tables();
        $results[] = "✅ Table recréée";
        
        // 4. Restaurer la clé API
        if (!empty($api_key)) {
            update_option('suno_api_key', $api_key);
            $results[] = "✅ Clé API restaurée";
        }
        
        // 5. Réinitialiser la version
        update_option('suno_music_generator_version', '2.1');
        $results[] = "✅ Plugin réinitialisé en version 2.1";
        
        return '<h4>Réinitialisation complète effectuée :</h4><ul><li>' . implode('</li><li>', $results) . '</li></ul>';
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
                    <div id="char-counter" class="char-counter"></div>
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
                        Version instrumentale (sans voix)
                    </label>
                </div>
                
                <button type="submit" class="suno-btn suno-btn-primary" id="generate-btn">
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
                    <small id="status-details"></small>
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
        
        $where_clause = "";
        if ($atts['user_id']) {
            $where_clause = "WHERE user_id = " . intval($atts['user_id']) . " AND ";
        } else {
            $where_clause = "WHERE ";
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            {$where_clause} status = 'completed' 
            ORDER BY created_at DESC 
            LIMIT %d",
            intval($atts['limit'])
        ));
        
        if (empty($results)) {
            return '<div class="suno-no-results">
                <p>🎵 Aucune chanson générée pour le moment.</p>
                <p>Utilisez le formulaire pour créer votre première chanson !</p>
            </div>';
        }
        
        ob_start();
        ?>
        <div class="suno-playlist">
            <h3>🎵 Vos créations musicales</h3>
            
            <div class="tracks-grid">
                <?php foreach ($results as $track): ?>
                <div class="suno-track" data-track-id="<?php echo esc_attr($track->id); ?>">
                    <div class="track-header">
                        <h4><?php echo esc_html($track->title ?: 'Chanson sans titre'); ?></h4>
                        <?php if ($track->style): ?>
                            <span class="track-style"><?php echo esc_html($track->style); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="track-info">
                        <p class="track-prompt"><?php echo esc_html(wp_trim_words($track->prompt, 20)); ?></p>
                        <span class="track-date">
                            📅 <?php echo date_i18n('d F Y', strtotime($track->created_at)); ?>
                        </span>
                        <?php if ($track->duration): ?>
                            <span class="track-duration">
                                ⏱️ <?php echo gmdate("i:s", $track->duration); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($track->audio_url): ?>
                    <div class="track-player">
                        <audio controls preload="none">
                            <source src="<?php echo esc_url($track->audio_url); ?>" type="audio/mpeg">
                            Votre navigateur ne supporte pas l'élément audio.
                        </audio>
                    </div>
                    
                    <div class="track-actions">
                        <a href="<?php echo esc_url($track->audio_url); ?>" 
                           download 
                           class="track-btn download-btn">
                            📥 Télécharger
                        </a>
                        <button onclick="shareTrack('<?php echo esc_url($track->audio_url); ?>')" 
                                class="track-btn share-btn">
                            🔗 Partager
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($track->image_url): ?>
                    <div class="track-image">
                        <img src="<?php echo esc_url($track->image_url); ?>" 
                             alt="<?php echo esc_attr($track->title); ?>" />
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function ajax_generate_music() {
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        if (empty($this->api_key)) {
            wp_send_json_error('Clé API non configurée. Allez dans Réglages > Suno Music.');
        }
        
        // Récupération des données
        $prompt = sanitize_textarea_field($_POST['prompt']);
        $style = sanitize_text_field($_POST['style'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $lyrics = sanitize_textarea_field($_POST['lyrics'] ?? '');
        $instrumental = isset($_POST['instrumental']) && $_POST['instrumental'] === 'true';
        
        if (empty($prompt)) {
            wp_send_json_error('Description requise');
        }
        
        // Préparation de la requête API
        $api_data = array(
            'prompt' => $prompt,
            'make_instrumental' => $instrumental,
            'wait_audio' => false
        );
        
        if (!empty($style)) {
            $api_data['tags'] = $style;
        }
        
        if (!empty($title)) {
            $api_data['title'] = $title;
        }
        
        if (!empty($lyrics)) {
            $api_data['prompt'] = $lyrics . "\n\n" . $prompt;
        }
        
        // Appel API pour générer la musique
        $response = wp_remote_post($this->api_base_url . '/api/generate', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($api_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Suno API Error: ' . $response->get_error_message());
            wp_send_json_error('Erreur de connexion à l\'API: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        error_log('Suno API Response Code: ' . $status_code);
        error_log('Suno API Response: ' . substr($body, 0, 500));
        
        if ($status_code === 200 && isset($data['id'])) {
            // Sauvegarde en base de données
            global $wpdb;
            $table_name = $wpdb->prefix . 'suno_generations';
            
            $wpdb->insert($table_name, array(
                'user_id' => get_current_user_id(),
                'task_id' => $data['id'],
                'prompt' => $prompt,
                'style' => $style,
                'title' => $title ?: ($data['title'] ?? ''),
                'lyrics' => $lyrics,
                'status' => 'pending',
                'model_name' => $data['model_name'] ?? '',
                'created_at' => current_time('mysql')
            ));
            
            wp_send_json_success(array(
                'task_id' => $data['id'],
                'message' => 'Génération démarrée avec succès !'
            ));
        } else {
            $error_message = $data['detail'] ?? $data['error'] ?? 'Erreur inconnue';
            
            if ($status_code === 401) {
                $error_message = 'Clé API invalide. Vérifiez votre clé dans Réglages > Suno Music.';
            } elseif ($status_code === 402) {
                $error_message = 'Crédits insuffisants. Rechargez votre compte sur sunoapi.org.';
            }
            
            wp_send_json_error($error_message);
        }
    }
    
    public function ajax_check_music_status() {
        check_ajax_referer('suno_music_nonce', 'nonce');
        
        $task_id = sanitize_text_field($_POST['task_id']);
        
        if (empty($task_id)) {
            wp_send_json_error('Task ID requis');
        }
        
        if (empty($this->api_key)) {
            wp_send_json_error('Clé API non configurée');
        }
        
        // Appel API pour vérifier le statut
        $response = wp_remote_get($this->api_base_url . '/api/get?ids=' . $task_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            error_log('Status check error: ' . $response->get_error_message());
            wp_send_json_error('Erreur de vérification');
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('Status check response: ' . $status_code . ' - ' . substr($body, 0, 500));
        
        if ($status_code === 200) {
            $data = json_decode($body, true);
            
            // Gérer différents formats de réponse
            $track_data = null;
            if (is_array($data)) {
                if (isset($data[0])) {
                    $track_data = $data[0];
                } elseif (isset($data['data']) && is_array($data['data'])) {
                    $track_data = $data['data'][0] ?? null;
                }
            }
            
            if ($track_data) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'suno_generations';
                
                $update_data = array();
                $response_data = array(
                    'status' => $track_data['status'] ?? 'processing'
                );
                
                // Vérifier si la génération est terminée
                if (isset($track_data['audio_url']) && !empty($track_data['audio_url'])) {
                    $update_data['status'] = 'completed';
                    $update_data['audio_url'] = $track_data['audio_url'];
                    $update_data['completed_at'] = current_time('mysql');
                    
                    $response_data['status'] = 'completed';
                    $response_data['audio_url'] = $track_data['audio_url'];
                    
                    // URLs additionnelles
                    if (isset($track_data['video_url'])) {
                        $update_data['video_url'] = $track_data['video_url'];
                        $response_data['video_url'] = $track_data['video_url'];
                    }
                    
                    if (isset($track_data['image_url'])) {
                        $update_data['image_url'] = $track_data['image_url'];
                        $response_data['image_url'] = $track_data['image_url'];
                    }
                    
                    // Métadonnées
                    if (isset($track_data['duration'])) {
                        $update_data['duration'] = intval($track_data['duration']);
                        $response_data['duration'] = $track_data['duration'];
                    }
                    
                    if (isset($track_data['title']) && !empty($track_data['title'])) {
                        $update_data['title'] = $track_data['title'];
                        $response_data['title'] = $track_data['title'];
                    }
                } elseif (isset($track_data['status'])) {
                    $update_data['status'] = $track_data['status'];
                    
                    if ($track_data['status'] === 'error' || $track_data['status'] === 'failed') {
                        $response_data['status'] = 'failed';
                        $response_data['error'] = $track_data['error_message'] ?? 'Génération échouée';
                    }
                }
                
                // Mise à jour de la base de données
                if (!empty($update_data)) {
                    $wpdb->update($table_name, $update_data, array('task_id' => $task_id));
                }
                
                wp_send_json_success($response_data);
            } else {
                wp_send_json_success(array(
                    'status' => 'processing',
                    'message' => 'Génération en cours...'
                ));
            }
        } else {
            wp_send_json_success(array(
                'status' => 'processing',
                'message' => 'Vérification en cours...'
            ));
        }
    }
    
    public function render_api_test($atts) {
        if (!current_user_can('manage_options')) {
            return '<div class="suno-error-box">❌ Accès réservé aux administrateurs</div>';
        }
        
        ob_start();
        ?>
        <div class="suno-test-container">
            <h3>🔧 Test de connexion API - Version 2.1</h3>
            
            <?php if (empty($this->api_key)): ?>
                <div class="suno-error-box">
                    ❌ <strong>Clé API manquante</strong><br>
                    Configurez votre clé dans Réglages > Suno Music
                </div>
            <?php else: ?>
                <div class="suno-info-box">
                    ✅ Clé API configurée (<?php echo strlen($this->api_key); ?> caractères)
                </div>
                
                <button id="test-api-btn" class="suno-btn suno-btn-primary">
                    🔍 Tester la connexion
                </button>
                
                <div id="test-results" style="margin-top: 20px;"></div>
                
                <script>
                jQuery('#test-api-btn').on('click', function() {
                    jQuery('#test-results').html('<div class="spinner"></div>');
                    
                    jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'test_suno_api',
                        nonce: '<?php echo wp_create_nonce('suno_test_nonce'); ?>'
                    }).done(function(response) {
                        var html = '';
                        if (response.success) {
                            html = '<div class="suno-success-box">' +
                                   '✅ <strong>Connexion réussie !</strong><br>' +
                                   'Crédits disponibles: ' + response.data.credits + '<br>' +
                                   'API Version: ' + response.data.version +
                                   '</div>';
                        } else {
                            html = '<div class="suno-error-box">' +
                                   '❌ <strong>Erreur:</strong> ' + response.data +
                                   '</div>';
                        }
                        jQuery('#test-results').html(html);
                    });
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialisation du plugin
new SunoMusicGeneratorV2();

// Fonction AJAX pour le test API
add_action('wp_ajax_test_suno_api', 'test_suno_api_connection');
function test_suno_api_connection() {
    check_ajax_referer('suno_test_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission refusée');
    }
    
    $api_key = get_option('suno_api_key', '');
    
    if (empty($api_key)) {
        wp_send_json_error('Clé API non configurée');
    }
    
    // Test de l'API
    $response = wp_remote_get('https://api.sunoapi.org/api/get_limit', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 15
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error('Erreur de connexion: ' . $response->get_error_message());
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($status_code === 200) {
        $data = json_decode($body, true);
        wp_send_json_success(array(
            'credits' => $data['credits'] ?? 'N/A',
            'version' => 'SunoAPI.org v2.1'
        ));
    } else {
        wp_send_json_error('Code erreur: ' . $status_code);
    }
}
