<?php
/**
 * Plugin Name: Suno Simple
 * Description: Générateur de musique ultra-simple avec Suno
 * Version: 4.0
 * Author: WeAreReForm
 */

// Sécurité WordPress
if (!defined('ABSPATH')) exit;

// ===== CONFIGURATION =====
// METTEZ VOTRE TOKEN ICI (avec Bearer devant)
define('SUNO_TOKEN', 'Bearer eyJhbGciOiJSUzI1NiIsImNhdCI6ImNsX0I3ZDRQRDExMUFBQSIsImtpZCI6Imluc18yT1o2eU1EZzhscWRKRWloMXJvemY4T3ptZG4iLCJ0eXAiOiJKV1QifQ.eyJhdWQiOiJzdW5vLWFwaSIsImF6cCI6Imh0dHBzOi8vc3Vuby5jb20iLCJleHAiOjE3NTU3NzAwODIsImZ2YSI6WzQsLTFdLCJodHRwczovL3N1bm8uYWkvY2xhaW1zL2NsZXJrX2lkIjoidXNlcl8ydHo4ZzVjZDdTOURBVGVTcm9LSkZWVTV1Z1kiLCJodHRwczovL3N1bm8uYWkvY2xhaW1zL2VtYWlsIjoiZ29mb3JyZWZvcm1AZ21haWwuY29tIiwiaHR0cHM6Ly9zdW5vLmFpL2NsYWltcy9waG9uZSI6bnVsbCwiaWF0IjoxNzU1NzY2NDgyLCJpc3MiOiJodHRwczovL2NsZXJrLnN1bm8uY29tIiwianRpIjoiNTNkYjRmZDhlMTYzNzMwMzMwMmYiLCJuYmYiOjE3NTU3NjY0NzIsInNpZCI6InNlc3NfMzFhZGVVNUZ2Q1U0M1JScElLMFVxc3dYaTF0Iiwic3ViIjoidXNlcl8ydHo4ZzVjZDdTOURBVGVTcm9LSkZWVTV1Z1kifQ.SZomY0sFG0HM5vfKPVBfzKaev1MlLyk-QH296M-aMaoODkK3u0sPw8xix-xO0OocQRJFGIlh7cviWTlTSIcv1CpM8e4BXZUnoZSjUYtB6I6uRp59IqL72exyINrqt5MnLBBQgbE7N9qEZO_btIC_sYcbuLuOLiUUIkZJUKkDI8mNanMhpzfbFXkdcmSCMhbwKNFq_iFO8h8Mm1aAk0K5bdqUJt8N7iP-CiSuBWn_uIvBJvDB-aLVT9mJpO50OqvcxZaHSFDjJ9ojAXd0k_MRsPZ63GVhdneTViDcqB92DC2T8l-hJpAyExijqOmpOyS2r9lUvrZfNvF9CiH1p23pVA');

// ===== SHORTCODE PRINCIPAL =====
add_shortcode('suno', 'suno_simple_form');

function suno_simple_form() {
    ob_start();
    ?>
    <div id="suno-generator" style="max-width: 600px; margin: 20px auto; padding: 20px; background: #f9f9f9; border-radius: 10px;">
        <h2 style="text-align: center; color: #333;">🎵 Générateur de Musique IA</h2>
        
        <form id="suno-form" style="display: flex; flex-direction: column; gap: 15px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">
                    Description de votre chanson :
                </label>
                <textarea 
                    id="suno-prompt" 
                    rows="3" 
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"
                    placeholder="Ex: Une chanson joyeuse sur le soleil et l'été"
                    required
                ></textarea>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">
                    Style (optionnel) :
                </label>
                <select 
                    id="suno-style" 
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"
                >
                    <option value="">Automatique</option>
                    <option value="pop">Pop</option>
                    <option value="rock">Rock</option>
                    <option value="electronic">Électronique</option>
                    <option value="jazz">Jazz</option>
                    <option value="classical">Classique</option>
                    <option value="hip-hop">Hip-Hop</option>
                    <option value="country">Country</option>
                    <option value="folk">Folk</option>
                </select>
            </div>
            
            <button 
                type="submit" 
                style="padding: 15px; background: #6366f1; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;"
                onmouseover="this.style.background='#4f46e5'" 
                onmouseout="this.style.background='#6366f1'"
            >
                🎼 Générer la musique
            </button>
        </form>
        
        <div id="suno-status" style="margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 5px; display: none;">
            <div id="suno-message">En attente...</div>
        </div>
    </div>
    
    <script>
    document.getElementById('suno-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const statusDiv = document.getElementById('suno-status');
        const messageDiv = document.getElementById('suno-message');
        const prompt = document.getElementById('suno-prompt').value;
        const style = document.getElementById('suno-style').value;
        
        // Afficher le statut
        statusDiv.style.display = 'block';
        statusDiv.style.background = '#fff3cd';
        messageDiv.innerHTML = '⏳ Génération en cours... (30-60 secondes)';
        
        try {
            // Appel AJAX vers WordPress
            const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'suno_generate',
                    'prompt': prompt,
                    'style': style
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                statusDiv.style.background = '#d4edda';
                messageDiv.innerHTML = `
                    ✅ <strong>Génération réussie !</strong><br>
                    ${data.message}<br>
                    <small>Vérifiez sur <a href="https://suno.com" target="_blank">Suno.com</a> dans 1 minute</small>
                `;
            } else {
                statusDiv.style.background = '#f8d7da';
                messageDiv.innerHTML = '❌ Erreur : ' + data.message;
            }
        } catch (error) {
            statusDiv.style.background = '#f8d7da';
            messageDiv.innerHTML = '❌ Erreur de connexion : ' + error.message;
        }
    });
    </script>
    <?php
    return ob_get_clean();
}

// ===== AJAX HANDLER =====
add_action('wp_ajax_suno_generate', 'suno_handle_generation');
add_action('wp_ajax_nopriv_suno_generate', 'suno_handle_generation');

function suno_handle_generation() {
    $prompt = sanitize_text_field($_POST['prompt'] ?? '');
    $style = sanitize_text_field($_POST['style'] ?? '');
    
    if (empty($prompt)) {
        wp_send_json_error('Description requise');
        return;
    }
    
    // Préparer la requête pour Suno
    $body = array(
        'prompt' => $prompt,
        'mv' => 'chirp-v3-5', // Modèle v3.5
        'instrumental' => false
    );
    
    // Ajouter le style si spécifié
    if (!empty($style)) {
        $body['tags'] = $style;
    }
    
    // Appel à l'API Suno
    $response = wp_remote_post('https://studio-api.suno.ai/api/generate/v2/', array(
        'headers' => array(
            'Authorization' => SUNO_TOKEN,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($body),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error('Erreur API : ' . $response->get_error_message());
        return;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($status_code === 401) {
        wp_send_json_error('Token expiré - Contactez l\'administrateur');
        return;
    }
    
    if ($status_code !== 200 && $status_code !== 201) {
        wp_send_json_error('Erreur Suno (Code ' . $status_code . ')');
        return;
    }
    
    $data = json_decode($body, true);
    
    if (isset($data['clips']) && is_array($data['clips'])) {
        $clip_ids = array_column($data['clips'], 'id');
        wp_send_json_success(
            'Chansons créées : ' . implode(', ', $clip_ids)
        );
    } else {
        wp_send_json_error('Réponse inattendue de Suno');
    }
}

// ===== SHORTCODE DE TEST =====
add_shortcode('suno_test', 'suno_test_connection');

function suno_test_connection() {
    $response = wp_remote_get('https://studio-api.suno.ai/api/billing/info/', array(
        'headers' => array(
            'Authorization' => SUNO_TOKEN
        ),
        'timeout' => 10
    ));
    
    if (is_wp_error($response)) {
        return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px;">❌ Erreur de connexion</div>';
    }
    
    $status = wp_remote_retrieve_response_code($response);
    
    if ($status === 200) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $credits = $body['total_credits_left'] ?? 'inconnu';
        return '<div style="padding: 15px; background: #d4edda; border-radius: 5px;">
            ✅ Connexion OK<br>
            Crédits restants : ' . $credits . '
        </div>';
    } else if ($status === 401) {
        return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px;">❌ Token expiré</div>';
    } else {
        return '<div style="padding: 15px; background: #f8d7da; border-radius: 5px;">❌ Erreur ' . $status . '</div>';
    }
}

// ===== C'EST TOUT ! =====
