/**
 * Suno Music Generator - JavaScript
 * Version 4.0.0
 */

jQuery(document).ready(function($) {
    
    // Gestion du formulaire
    $('#suno-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $status = $('#suno-status');
        const $message = $status.find('.suno-message');
        
        // Récupérer les données
        const prompt = $('#suno-prompt').val();
        const style = $('#suno-style').val();
        
        // Désactiver le formulaire
        $button.prop('disabled', true);
        
        // Afficher le statut
        $status.show().removeClass('success error').addClass('loading');
        $message.html('⏳ Génération en cours... (30-60 secondes)');
        
        // Requête AJAX
        $.ajax({
            url: suno_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'suno_generate',
                nonce: suno_ajax.nonce,
                prompt: prompt,
                style: style
            },
            success: function(response) {
                if (response.success) {
                    $status.removeClass('loading error').addClass('success');
                    $message.html(
                        '✅ <strong>Génération réussie !</strong><br>' +
                        'IDs : ' + response.data.clips.join(', ') + '<br>' +
                        '<small>Vérifiez sur <a href="https://suno.com" target="_blank">Suno.com</a> dans 1 minute</small>'
                    );
                    
                    // Réinitialiser le formulaire
                    $form[0].reset();
                } else {
                    $status.removeClass('loading success').addClass('error');
                    $message.html('❌ Erreur : ' + response.data);
                }
            },
            error: function() {
                $status.removeClass('loading success').addClass('error');
                $message.html('❌ Erreur de connexion');
            },
            complete: function() {
                // Réactiver le bouton
                $button.prop('disabled', false);
            }
        });
    });
    
});