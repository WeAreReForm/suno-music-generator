<?php
/**
 * Template du shortcode pour le générateur principal
 * 
 * @package SunoMusicGenerator
 * @since 5.0.0
 */

// Sécurité
if (!defined('ABSPATH')) {
    exit;
}

// Récupération des attributs
$atts = wp_parse_args($atts, [
    'styles' => 'all',
    'show_history' => 'true',
    'max_prompt' => 500,
    'max_lyrics' => 3000
]);

// Vérification de la clé API
$api_key = get_option('suno_api_key', '');
$has_api_key = !empty($api_key);

// Styles disponibles
$available_styles = [
    'pop' => 'Pop',
    'rock' => 'Rock',
    'electronic' => 'Électronique',
    'hip-hop' => 'Hip-Hop',
    'jazz' => 'Jazz',
    'classical' => 'Classique',
    'country' => 'Country',
    'reggae' => 'Reggae',
    'blues' => 'Blues',
    'folk' => 'Folk',
    'metal' => 'Metal',
    'r&b' => 'R&B',
    'latin' => 'Latin',
    'indie' => 'Indie',
    'ambient' => 'Ambient'
];

// Filtrer les styles si nécessaire
if ($atts['styles'] !== 'all') {
    $selected_styles = explode(',', $atts['styles']);
    $available_styles = array_filter($available_styles, function($key) use ($selected_styles) {
        return in_array($key, $selected_styles);
    }, ARRAY_FILTER_USE_KEY);
}

?>

<div id="suno-music-generator" class="suno-container">
    
    <div class="suno-header">
        <h2>🎵 Créez votre musique avec l'IA</h2>
        <p>Décrivez la chanson de vos rêves et laissez l'intelligence artificielle la composer pour vous</p>
    </div>

    <?php if (!$has_api_key && current_user_can('manage_options')) : ?>
        <div class="suno-notice suno-notice-warning">
            <strong>Configuration requise :</strong> 
            Veuillez configurer votre clé API dans <a href="<?php echo admin_url('admin.php?page=suno-settings'); ?>">les paramètres</a>.
        </div>
    <?php endif; ?>

    <form id="suno-music-form" class="suno-form">
        <?php wp_nonce_field('suno_music_nonce', 'suno_nonce'); ?>
        
        <!-- Description de la chanson -->
        <div class="suno-form-group">
            <label for="suno-prompt">
                Description de votre chanson <span class="required">*</span>
            </label>
            <textarea 
                id="suno-prompt" 
                name="prompt" 
                rows="4" 
                maxlength="<?php echo esc_attr($atts['max_prompt']); ?>"
                placeholder="Ex: Une ballade pop mélancolique sur l'amour perdu, avec des touches de piano et une voix émotionnelle"
                required
            ></textarea>
            <div class="suno-char-counter"></div>
        </div>

        <!-- Suggestions de prompts -->
        <div class="suno-form-group">
            <p class="suno-form-label">💡 Besoin d'inspiration ?</p>
            <div id="suno-prompt-suggestions" class="suno-prompt-suggestions"></div>
        </div>

        <!-- Styles musicaux -->
        <?php if (!empty($available_styles)) : ?>
        <div class="suno-form-group">
            <label>Style musical (optionnel)</label>
            <div class="suno-music-styles">
                <?php foreach ($available_styles as $value => $label) : ?>
                    <div class="suno-style-chip" data-style="<?php echo esc_attr($value); ?>">
                        <?php echo esc_html($label); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="suno-style" name="style" value="">
        </div>
        <?php endif; ?>

        <div class="suno-form-row">
            <!-- Titre -->
            <div class="suno-form-group">
                <label for="suno-title">
                    Titre de la chanson (optionnel)
                </label>
                <input 
                    type="text" 
                    id="suno-title" 
                    name="title" 
                    maxlength="100"
                    placeholder="Mon titre personnalisé"
                >
            </div>

            <!-- Tags -->
            <div class="suno-form-group">
                <label for="suno-tags">
                    Tags (optionnel)
                </label>
                <input 
                    type="text" 
                    id="suno-tags" 
                    name="tags" 
                    placeholder="joyeux, énergique, été"
                >
            </div>
        </div>

        <!-- Paroles personnalisées -->
        <div class="suno-form-group">
            <label for="suno-lyrics">
                Paroles personnalisées (optionnel)
            </label>
            <textarea 
                id="suno-lyrics" 
                name="lyrics" 
                rows="6" 
                maxlength="<?php echo esc_attr($atts['max_lyrics']); ?>"
                placeholder="Laissez vide pour une génération automatique des paroles"
            ></textarea>
            <div class="suno-char-counter"></div>
        </div>

        <!-- Options -->
        <div class="suno-form-group">
            <div class="suno-checkbox">
                <input type="checkbox" id="suno-instrumental" name="instrumental" value="true">
                <label for="suno-instrumental">
                    Version instrumentale (sans voix)
                </label>
            </div>
        </div>

        <!-- Bouton de soumission -->
        <div class="suno-form-actions">
            <button type="submit" class="suno-btn suno-btn-primary" <?php echo !$has_api_key ? 'disabled' : ''; ?>>
                <span class="suno-btn-icon">🎼</span>
                Générer ma musique
            </button>
        </div>
    </form>

    <!-- Statut de génération -->
    <div id="suno-generation-status" class="suno-generation-status" style="display: none;">
        <div class="suno-status-content">
            <div class="suno-spinner"></div>
            <p class="suno-status-text">
                <span id="suno-status-text">Initialisation...</span>
            </p>
            <div class="suno-progress-bar">
                <div class="suno-progress-fill" style="width: 0%"></div>
            </div>
            <p class="suno-status-subtext">
                La génération peut prendre 30 à 60 secondes
            </p>
        </div>
    </div>

    <!-- Résultat -->
    <div id="suno-generation-result" class="suno-generation-result" style="display: none;"></div>

    <?php if ($atts['show_history'] === 'true' && is_user_logged_in()) : ?>
    <!-- Historique des générations -->
    <div class="suno-history-section suno-mt-3">
        <h3>📜 Vos dernières créations</h3>
        <div id="suno-history" class="suno-history"></div>
    </div>
    <?php endif; ?>

    <!-- Information supplémentaire -->
    <div class="suno-info-section suno-mt-3">
        <div class="suno-notice suno-notice-info">
            <strong>💡 Bon à savoir :</strong>
            <ul>
                <li>Chaque génération utilise des crédits sur votre compte SunoAPI</li>
                <li>Les chansons générées durent environ 2-3 minutes</li>
                <li>Vous pouvez télécharger vos créations en MP3</li>
                <li>Consultez vos générations sur <a href="https://sunoapi.org/fr/logs" target="_blank">SunoAPI Logs</a></li>
            </ul>
        </div>
    </div>

</div>
