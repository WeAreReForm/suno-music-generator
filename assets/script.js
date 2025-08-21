/**
 * Suno Music Generator v5.0 - JavaScript
 */

(function($) {
    'use strict';
    
    // Variables globales
    let currentTaskId = null;
    let statusInterval = null;
    
    // Initialisation
    $(document).ready(function() {
        initForm();
        initTest();
    });
    
    /**
     * Initialisation du formulaire
     */
    function initForm() {
        $('#suno-generate-form').on('submit', function(e) {
            e.preventDefault();
            
            const prompt = $('#suno-prompt').val().trim();
            const style = $('#suno-style').val().trim();
            
            if (!prompt) {
                showMessage('Veuillez entrer une description', 'error');
                return;
            }
            
            generateMusic(prompt, style);
        });
    }
    
    /**
     * Génération de la musique
     */
    function generateMusic(prompt, style) {
        // Désactiver le formulaire
        $('#suno-generate-form button').prop('disabled', true);
        
        // Afficher le statut
        $('#suno-status').html('<div class="suno-spinner"></div> Génération en cours...').show();
        $('#suno-result').hide();
        
        // Appel AJAX
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
                    currentTaskId = response.data.task_id;
                    $('#suno-status').html('🎵 Création de votre musique...');
                    startStatusCheck();
                } else {
                    showMessage('Erreur : ' + response.data, 'error');
                    $('#suno-generate-form button').prop('disabled', false);
                }
            },
            error: function() {
                showMessage('Erreur de connexion', 'error');
                $('#suno-generate-form button').prop('disabled', false);
            }
        });
    }
    
    /**
     * Vérification du statut
     */
    function startStatusCheck() {
        let attempts = 0;
        const maxAttempts = 60; // 3 minutes max
        
        statusInterval = setInterval(function() {
            attempts++;
            
            if (attempts > maxAttempts) {
                clearInterval(statusInterval);
                showMessage('La génération prend trop de temps. Veuillez réessayer.', 'error');
                $('#suno-generate-form button').prop('disabled', false);
                return;
            }
            
            $.ajax({
                url: suno_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'suno_check_status',
                    nonce: suno_ajax.nonce,
                    task_id: currentTaskId
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.status === 'completed') {
                            clearInterval(statusInterval);
                            showResult(response.data);
                            $('#suno-generate-form button').prop('disabled', false);
                        } else {
                            $('#suno-status').html('🎵 Traitement en cours... (' + attempts + 's)');
                        }
                    }
                }
            });
        }, 3000); // Vérifier toutes les 3 secondes
    }
    
    /**
     * Affichage du résultat
     */
    function showResult(data) {
        $('#suno-status').hide();
        
        let html = '<div class="suno-success">✅ Musique générée avec succès !</div>';
        
        if (data.title) {
            html += '<h4>' + data.title + '</h4>';
        }
        
        if (data.audio_url) {
            html += '<audio controls><source src="' + data.audio_url + '" type="audio/mpeg"></audio>';
            html += '<br><a href="' + data.audio_url + '" download class="suno-button" style="margin-top: 1rem;">📥 Télécharger</a>';
        }
        
        $('#suno-result').html(html).show();
        
        // Réinitialiser le formulaire
        $('#suno-generate-form')[0].reset();
    }
    
    /**
     * Test de l'API
     */
    function initTest() {
        $('#suno-test-btn').on('click', function() {
            const $btn = $(this);
            const $result = $('#suno-test-result');
            
            $btn.prop('disabled', true);
            $result.html('<div class="suno-spinner"></div> Test en cours...');
            
            $.ajax({
                url: suno_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'suno_test_api',
                    nonce: suno_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="suno-success">' + response.data + '</div>');
                    } else {
                        $result.html('<div class="suno-error">' + response.data + '</div>');
                    }
                    $btn.prop('disabled', false);
                },
                error: function() {
                    $result.html('<div class="suno-error">Erreur de test</div>');
                    $btn.prop('disabled', false);
                }
            });
        });
    }
    
    /**
     * Affichage des messages
     */
    function showMessage(message, type) {
        const className = type === 'error' ? 'suno-error' : 'suno-success';
        $('#suno-status').html('<div class="' + className + '">' + message + '</div>').show();
    }
    
})(jQuery);