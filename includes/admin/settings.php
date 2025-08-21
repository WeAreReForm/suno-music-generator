<?php
/**
 * Page des paramètres admin
 * 
 * @package SunoMusicGenerator
 * @since 5.0.0
 */

// Sécurité
if (!defined('ABSPATH')) {
    exit;
}

// Sauvegarde des paramètres
if (isset($_POST['submit']) && wp_verify_nonce($_POST['suno_settings_nonce'], 'suno_settings')) {
    update_option('suno_api_key', sanitize_text_field($_POST['suno_api_key']));
    update_option('suno_default_style', sanitize_text_field($_POST['suno_default_style']));
    update_option('suno_enable_public_gallery', isset($_POST['suno_enable_public_gallery']) ? 1 : 0);
    update_option('suno_max_generations_per_day', intval($_POST['suno_max_generations_per_day']));
    
    echo '<div class="notice notice-success"><p>Paramètres sauvegardés avec succès !</p></div>';
}

// Récupération des valeurs
$api_key = get_option('suno_api_key', '');
$default_style = get_option('suno_default_style', 'auto');
$enable_gallery = get_option('suno_enable_public_gallery', 0);
$max_per_day = get_option('suno_max_generations_per_day', 10);

?>

<div class="wrap">
    <h1>🎵 Suno Music Generator - Paramètres</h1>
    
    <div style="background: #fff; padding: 20px; border-left: 4px solid #6366f1; margin: 20px 0; border-radius: 4px;">
        <h2 style="margin-top: 0;">Configuration de l'API</h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('suno_settings', 'suno_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="suno_api_key">Clé API Suno <span style="color: red;">*</span></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="suno_api_key" 
                               name="suno_api_key" 
                               value="<?php echo esc_attr($api_key); ?>" 
                               class="regular-text" 
                               placeholder="Votre clé API SunoAPI.org" 
                               required />
                        <p class="description">
                            Obtenez votre clé API sur <a href="https://sunoapi.org" target="_blank">SunoAPI.org</a><br>
                            <strong>Important :</strong> Sans clé API, le plugin ne pourra pas générer de musique.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="suno_default_style">Style par défaut</label>
                    </th>
                    <td>
                        <select id="suno_default_style" name="suno_default_style">
                            <option value="auto" <?php selected($default_style, 'auto'); ?>>Automatique</option>
                            <option value="pop" <?php selected($default_style, 'pop'); ?>>Pop</option>
                            <option value="rock" <?php selected($default_style, 'rock'); ?>>Rock</option>
                            <option value="electronic" <?php selected($default_style, 'electronic'); ?>>Électronique</option>
                            <option value="hip-hop" <?php selected($default_style, 'hip-hop'); ?>>Hip-Hop</option>
                            <option value="jazz" <?php selected($default_style, 'jazz'); ?>>Jazz</option>
                            <option value="classical" <?php selected($default_style, 'classical'); ?>>Classique</option>
                            <option value="country" <?php selected($default_style, 'country'); ?>>Country</option>
                            <option value="reggae" <?php selected($default_style, 'reggae'); ?>>Reggae</option>
                            <option value="blues" <?php selected($default_style, 'blues'); ?>>Blues</option>
                            <option value="folk" <?php selected($default_style, 'folk'); ?>>Folk</option>
                        </select>
                        <p class="description">Style musical proposé par défaut dans le formulaire</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="suno_enable_public_gallery">Galerie publique</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="suno_enable_public_gallery" 
                                   name="suno_enable_public_gallery" 
                                   value="1" 
                                   <?php checked($enable_gallery, 1); ?> />
                            Activer la galerie publique des créations
                        </label>
                        <p class="description">Permet d'afficher les créations publiques avec le shortcode [suno_gallery]</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="suno_max_generations_per_day">Limite quotidienne</label>
                    </th>
                    <td>
                        <input type="number" 
                               id="suno_max_generations_per_day" 
                               name="suno_max_generations_per_day" 
                               value="<?php echo esc_attr($max_per_day); ?>" 
                               min="0" 
                               max="100" 
                               class="small-text" />
                        <span>générations par jour et par utilisateur</span>
                        <p class="description">0 = pas de limite. Recommandé : 10 générations/jour</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Sauvegarder les paramètres'); ?>
        </form>
    </div>
    
    <!-- Test de connexion API -->
    <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h2>🔧 Test de connexion API</h2>
        
        <?php if (empty($api_key)): ?>
            <div style="padding: 15px; background: #fef2f2; border-left: 4px solid #ef4444; margin: 10px 0;">
                <strong>❌ Clé API manquante</strong><br>
                Veuillez configurer votre clé API ci-dessus pour utiliser le plugin.
            </div>
        <?php else: ?>
            <?php
            // Test de l'API
            $test_response = wp_remote_get('https://api.sunoapi.org/api/v1/get_limit', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 10
            ]);
            
            if (!is_wp_error($test_response)) {
                $status_code = wp_remote_retrieve_response_code($test_response);
                
                if ($status_code === 200) {
                    $body = wp_remote_retrieve_body($test_response);
                    $data = json_decode($body, true);
                    ?>
                    <div style="padding: 15px; background: #f0fdf4; border-left: 4px solid #10b981; margin: 10px 0;">
                        <strong>✅ Connexion API réussie !</strong><br>
                        <?php if (isset($data['credits'])): ?>
                            Crédits disponibles : <?php echo intval($data['credits']); ?>
                        <?php endif; ?>
                    </div>
                <?php } elseif ($status_code === 401) { ?>
                    <div style="padding: 15px; background: #fef2f2; border-left: 4px solid #ef4444; margin: 10px 0;">
                        <strong>❌ Clé API invalide</strong><br>
                        Vérifiez votre clé API sur <a href="https://sunoapi.org" target="_blank">SunoAPI.org</a>
                    </div>
                <?php } else { ?>
                    <div style="padding: 15px; background: #fffbeb; border-left: 4px solid #f59e0b; margin: 10px 0;">
                        <strong>⚠️ Erreur de connexion</strong><br>
                        Code d'erreur : <?php echo $status_code; ?>
                    </div>
                <?php }
            } else { ?>
                <div style="padding: 15px; background: #fef2f2; border-left: 4px solid #ef4444; margin: 10px 0;">
                    <strong>❌ Impossible de se connecter à l'API</strong><br>
                    <?php echo $test_response->get_error_message(); ?>
                </div>
            <?php } ?>
        <?php endif; ?>
        
        <p style="margin-top: 15px;">
            <a href="https://sunoapi.org/fr/logs" target="_blank" class="button button-secondary">
                📊 Voir les logs sur SunoAPI
            </a>
        </p>
    </div>
    
    <!-- Shortcodes disponibles -->
    <div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2>📋 Shortcodes disponibles</h2>
        
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Shortcode</th>
                    <th>Description</th>
                    <th>Exemple</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[suno_music_generator]</code></td>
                    <td>Formulaire de génération principal</td>
                    <td><code>[suno_music_generator show_history="true"]</code></td>
                </tr>
                <tr>
                    <td><code>[suno_gallery]</code></td>
                    <td>Galerie des créations publiques</td>
                    <td><code>[suno_gallery limit="12"]</code></td>
                </tr>
                <tr>
                    <td><code>[suno_my_music]</code></td>
                    <td>Musiques de l'utilisateur connecté</td>
                    <td><code>[suno_my_music limit="20"]</code></td>
                </tr>
                <tr>
                    <td><code>[suno_player]</code></td>
                    <td>Lecteur pour une chanson spécifique</td>
                    <td><code>[suno_player id="123"]</code></td>
                </tr>
                <tr>
                    <td><code>[suno_test_api]</code></td>
                    <td>Test de connexion API (admin seulement)</td>
                    <td><code>[suno_test_api]</code></td>
                </tr>
            </tbody>
        </table>
        
        <p style="margin-top: 15px;">
            <strong>💡 Conseil :</strong> Créez vos propres pages et ajoutez-y les shortcodes selon vos besoins.
        </p>
    </div>
    
    <!-- Informations -->
    <div style="background: #fffbeb; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h3>📚 Documentation et support</h3>
        <ul>
            <li>📖 Documentation complète : <a href="https://github.com/WeAreReForm/suno-music-generator" target="_blank">GitHub</a></li>
            <li>🐛 Signaler un bug : <a href="https://github.com/WeAreReForm/suno-music-generator/issues" target="_blank">Issues GitHub</a></li>
            <li>💳 Acheter des crédits : <a href="https://sunoapi.org" target="_blank">SunoAPI.org</a></li>
            <li>📊 Voir vos générations : <a href="https://sunoapi.org/fr/logs" target="_blank">Logs SunoAPI</a></li>
        </ul>
        
        <p><strong>Version du plugin :</strong> <?php echo SUNO_VERSION; ?></p>
    </div>
</div>
