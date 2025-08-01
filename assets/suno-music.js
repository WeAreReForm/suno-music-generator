/**
 * Suno Music Generator - JavaScript Interface v1.10.4
 * Protection anti-boucle et améliorations UX
 */

jQuery(document).ready(function($) {
    let currentTaskId = null;
    let statusCheckInterval = null;
    let checkCount = 0; // Compteur de vérifications pour protection anti-boucle
    const maxChecks = 20; // Limite de sécurité v1.10.4
    
    // Console de debug pour v1.10.4
    function debugLog(message, data = null) {
        if (window.console && console.log) {
            console.log(`[Suno v1.10.4] ${message}`, data || '');
        }
    }
    
    debugLog('Plugin Suno Music Generator v1.10.4 initialisé avec protection anti-boucle');
    
    // Soumission du formulaire
    $('#music-generation-form').on('submit', function(e) {
        e.preventDefault();
        
        debugLog('Début de génération avec protection anti-boucle');
        
        const formData = {
            action: 'generate_music',
            nonce: suno_ajax.nonce,
            prompt: $('#music-prompt').val(),
            style: $('#music-style').val(),
            title: $('#music-title').val(),
            lyrics: $('#music-lyrics').val(),
            instrumental: $('#instrumental').is(':checked')
        };
        
        // Validation améliorée
        if (!formData.prompt.trim()) {
            showValidationError('Veuillez saisir une description de la chanson');
            return;
        }
        
        if (formData.prompt.length > 500) {
            showValidationError('La description est trop longue (500 caractères maximum)');
            return;
        }
        
        // Réinitialiser les compteurs pour nouvelle génération
        checkCount = 0;
        
        // Afficher le statut de génération avec protection
        showGenerationStatus();
        updateStatusText('Envoi de la requête...');
        updateProtectionCounter(0);
        
        // Désactiver le formulaire
        toggleFormState(false);
        
        // Appel AJAX
        $.post(suno_ajax.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    currentTaskId = response.data.task_id;
                    debugLog('Task ID reçu:', currentTaskId);
                    updateStatusText('Génération démarrée avec protection anti-boucle...');
                    updateProgress(25);
                    
                    // Afficher message de protection
                    showProtectionInfo();
                    
                    // Commencer à vérifier le statut
                    startStatusCheck();
                } else {
                    debugLog('Erreur de génération:', response.data);
                    showError('Erreur: ' + response.data);
                    hideGenerationStatus();
                    toggleFormState(true);
                }
            })
            .fail(function(xhr, status, error) {
                debugLog('Erreur AJAX:', {xhr, status, error});
                showError('Erreur de connexion - Veuillez réessayer');
                hideGenerationStatus();
                toggleFormState(true);
            });
    });
    
    function startStatusCheck() {
        if (!currentTaskId) return;
        
        debugLog('Démarrage vérification statut avec protection anti-boucle');
        updateStatusText('Traitement en cours...');
        updateProgress(50);
        
        statusCheckInterval = setInterval(function() {
            checkMusicStatus();
        }, 5000); // Vérifier toutes les 5 secondes (optimisé v1.10.4)
    }
    
    function checkMusicStatus() {
        if (!currentTaskId) {
            stopStatusCheck();
            return;
        }
        
        // PROTECTION ANTI-BOUCLE v1.10.4
        checkCount++;
        updateProtectionCounter(checkCount);
        
        debugLog(`Vérification ${checkCount}/${maxChecks}`, currentTaskId);
        
        if (checkCount >= maxChecks) {
            debugLog('Protection anti-boucle activée - Maximum de vérifications atteint');
            showProtectionTriggered();
            stopStatusCheck();
            return;
        }
        
        // Changer l'apparence visuelle selon le nombre de vérifications
        if (checkCount >= 15) {
            $('.protection-counter').addClass('counter-danger');
        } else if (checkCount >= 10) {
            $('.protection-counter').addClass('counter-warning');
        }
        
        $.post(suno_ajax.ajax_url, {
            action: 'check_music_status',
            nonce: suno_ajax.nonce,
            task_id: currentTaskId,
            check_count: checkCount
        })
        .done(function(response) {
            if (response.success) {
                const data = response.data;
                const status = data.status;
                
                debugLog(`Statut reçu: ${status}`, data);
                
                // Vérifier si la protection anti-boucle a été déclenchée côté serveur
                if (data.anti_loop_triggered) {
                    debugLog('Protection anti-boucle déclenchée côté serveur');
                    showProtectionTriggered();
                    stopStatusCheck();
                    return;
                }
                
                switch(status) {
                    case 'processing':
                    case 'queued':
                    case 'pending':
                        updateStatusText(`Génération en cours... (${checkCount}/${maxChecks})`);
                        updateProgress(Math.min(75, 50 + (checkCount * 2)));
                        break;
                        
                    case 'completed':
                        debugLog('Génération terminée avec succès');
                        updateStatusText('Terminé !');
                        updateProgress(100);
                        setTimeout(() => {
                            showResult(data);
                            stopStatusCheck();
                            $(document).trigger('generation-complete');
                        }, 1000);
                        break;
                        
                    case 'failed':
                        debugLog('Génération échouée');
                        showError('La génération a échoué');
                        stopStatusCheck();
                        break;
                        
                    default:
                        updateStatusText(`Statut: ${status} (${checkCount}/${maxChecks})`);
                        break;
                }
            } else {
                debugLog('Erreur de vérification:', response.data);
                // Ne pas arrêter complètement, continuer à vérifier
                updateStatusText(`Vérification... (${checkCount}/${maxChecks})`);
            }
        })
        .fail(function(xhr, status, error) {
            debugLog('Erreur de connexion lors de la vérification:', {xhr, status, error});
            // En cas d'erreur réseau, continuer à essayer mais informer l'utilisateur
            updateStatusText(`Connexion... (${checkCount}/${maxChecks})`);
        });
    }
    
    function stopStatusCheck() {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
            statusCheckInterval = null;
        }
        hideGenerationStatus();
        toggleFormState(true);
        currentTaskId = null;
        checkCount = 0;
    }
    
    function showGenerationStatus() {
        $('#generation-status').fadeIn();
        $('#generation-result').hide();
        updateProgress(0);
    }
    
    function hideGenerationStatus() {
        $('#generation-status').fadeOut();
    }
    
    function updateStatusText(text) {
        $('#status-text').text(text);
    }
    
    function updateProgress(percent) {
        $('.progress-fill').css('width', percent + '%');
        
        // Changer la couleur selon la protection
        if (checkCount >= 15) {
            $('.progress-fill').addClass('progress-danger');
        } else if (checkCount >= 10) {
            $('.progress-fill').addClass('progress-warning');
        } else {
            $('.progress-fill').addClass('progress-protection');
        }
    }
    
    // Nouvelle fonction v1.10.4 : Compteur de protection
    function updateProtectionCounter(count) {
        if (!$('#check-count').length) return;
        
        $('#check-count').text(count);
        
        // Mettre à jour l'apparence visuelle
        const counter = $('.protection-counter');
        counter.removeClass('counter-warning counter-danger');
        
        if (count >= 15) {
            counter.addClass('counter-danger');
        } else if (count >= 10) {
            counter.addClass('counter-warning');
        }
    }
    
    // Nouvelle fonction v1.10.4 : Info de protection
    function showProtectionInfo() {
        if ($('.protection-alert').length === 0) {
            const protectionHtml = `
                <div class="protection-alert">
                    <strong>Protection activée :</strong> Maximum ${maxChecks} vérifications pour protéger vos crédits API.
                </div>
            `;
            $('#generation-status').prepend(protectionHtml);
        }
    }
    
    // Nouvelle fonction v1.10.4 : Protection déclenchée
    function showProtectionTriggered() {
        const protectionHtml = `
            <div class="result-content">
                <h4>🛡️ Protection anti-boucle activée</h4>
                <div class="success-message">
                    <div>
                        <strong>Vos crédits sont protégés !</strong><br>
                        La génération a été automatiquement terminée après ${maxChecks} vérifications.<br>
                        <em>Cette protection évite les consommations excessives de crédits API.</em>
                    </div>
                </div>
                
                <div class="result-actions">
                    <button onclick="checkCompletedGeneration('${currentTaskId}')" class="suno-btn suno-btn-secondary">
                        🔍 Vérifier si terminée
                    </button>
                    <button onclick="location.reload()" class="suno-btn suno-btn-primary">
                        🎵 Nouvelle génération
                    </button>
                </div>
                
                <div class="info-message">
                    <div>
                        <strong>Que faire maintenant ?</strong><br>
                        • Attendez quelques minutes et cliquez sur "Vérifier si terminée"<br>
                        • Ou démarrez une nouvelle génération<br>
                        • La protection a économisé vos crédits API
                    </div>
                </div>
            </div>
        `;
        
        $('#generation-result').html(protectionHtml).fadeIn();
    }
    
    // Nouvelle fonction v1.10.4 : Vérifier génération terminée
    window.checkCompletedGeneration = function(taskId) {
        debugLog('Vérification manuelle de génération terminée');
        
        $.post(suno_ajax.ajax_url, {
            action: 'check_music_status',
            nonce: suno_ajax.nonce,
            task_id: taskId,
            check_count: 0, // Reset pour vérification manuelle
            manual_check: true
        })
        .done(function(response) {
            if (response.success && response.data.status === 'completed') {
                debugLog('Génération trouvée terminée lors de la vérification manuelle');
                showResult(response.data);
            } else {
                showInfo('La génération est encore en cours. Réessayez dans quelques minutes.');
            }
        })
        .fail(function() {
            showError('Erreur lors de la vérification. Réessayez plus tard.');
        });
    };
    
    function showResult(data) {
        const resultHtml = `
            <div class="result-content">
                <h4>🎉 Votre chanson est prête !</h4>
                
                ${data.audio_url ? `
                    <div class="audio-player">
                        <audio controls autoplay>
                            <source src="${data.audio_url}" type="audio/mpeg">
                            Votre navigateur ne supporte pas l'élément audio.
                        </audio>
                    </div>
                ` : ''}
                
                ${data.image_url ? `
                    <div class="track-artwork">
                        <img src="${data.image_url}" alt="Artwork" />
                    </div>
                ` : ''}
                
                <div class="success-message">
                    <div>
                        <strong>✅ Succès avec protection v1.10.4 !</strong><br>
                        Génération terminée en ${checkCount} vérifications (limite: ${maxChecks})<br>
                        <em>Vos crédits API ont été préservés.</em>
                    </div>
                </div>
                
                <div class="result-actions">
                    ${data.audio_url ? `
                        <a href="${data.audio_url}" download class="suno-btn suno-btn-secondary">
                            📥 Télécharger
                        </a>
                    ` : ''}
                    
                    <button onclick="location.reload()" class="suno-btn suno-btn-primary">
                        🎵 Créer une nouvelle chanson
                    </button>
                </div>
                
                <div class="social-share">
                    <p>Partager votre création :</p>
                    <div class="share-buttons">
                        <button onclick="shareOnTwitter('${data.audio_url}')" class="share-btn twitter">
                            🐦 Twitter
                        </button>
                        <button onclick="shareOnFacebook('${data.audio_url}')" class="share-btn facebook">
                            📘 Facebook
                        </button>
                        <button onclick="copyToClipboard('${data.audio_url}')" class="share-btn copy">
                            📋 Copier le lien
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        $('#generation-result').html(resultHtml).fadeIn();
        hideGenerationStatus();
    }
    
    function showError(message) {
        const errorHtml = `
            <div class="error-content">
                <h4>❌ Erreur</h4>
                <div class="error-message">
                    <div>${message}</div>
                </div>
                <div class="result-actions">
                    <button onclick="location.reload()" class="suno-btn suno-btn-primary">
                        🔄 Réessayer
                    </button>
                </div>
            </div>
        `;
        
        $('#generation-result').html(errorHtml).fadeIn();
    }
    
    function showInfo(message) {
        const infoHtml = `
            <div class="info-content">
                <div class="info-message">
                    <div>${message}</div>
                </div>
            </div>
        `;
        
        $('#generation-result').html(infoHtml).fadeIn();
    }
    
    function showValidationError(message) {
        const errorDiv = $('<div class="error-message validation-error" style="margin: 1rem 0;"><div>' + message + '</div></div>');
        $('#music-generation-form').prepend(errorDiv);
        
        // Supprimer après 5 secondes
        setTimeout(() => {
            errorDiv.fadeOut(() => errorDiv.remove());
        }, 5000);
        
        // Faire défiler vers l'erreur
        $('html, body').animate({
            scrollTop: errorDiv.offset().top - 100
        }, 500);
    }
    
    function toggleFormState(enabled) {
        $('#music-generation-form input, #music-generation-form textarea, #music-generation-form select, #music-generation-form button')
            .prop('disabled', !enabled);
    }
    
    // Fonctions de partage social améliorées
    window.shareOnTwitter = function(audioUrl) {
        const text = "J'ai créé cette chanson avec l'IA Suno ! 🎵 (Plugin WordPress v1.10.4 avec protection anti-boucle)";
        const hashtags = "IA,Suno,WordPress,Musique";
        const url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(audioUrl)}&hashtags=${hashtags}`;
        window.open(url, '_blank', 'width=550,height=420');
    };
    
    window.shareOnFacebook = function(audioUrl) {
        const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(audioUrl)}`;
        window.open(url, '_blank', 'width=580,height=296');
    };
    
    window.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(function() {
            showTemporaryMessage('✅ Lien copié dans le presse-papiers !', 'success');
        }).catch(function(err) {
            debugLog('Erreur de copie:', err);
            // Fallback pour les navigateurs plus anciens
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showTemporaryMessage('✅ Lien copié !', 'success');
        });
    };
    
    // Nouvelle fonction : Messages temporaires
    function showTemporaryMessage(message, type = 'info') {
        const messageClass = type === 'success' ? 'success-message' : 
                           type === 'error' ? 'error-message' : 'info-message';
        
        const messageDiv = $(`<div class="${messageClass} temporary-message" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 300px;"><div>${message}</div></div>`);
        
        $('body').append(messageDiv);
        
        setTimeout(() => {
            messageDiv.fadeOut(() => messageDiv.remove());
        }, 3000);
    }
    
    // Auto-resize des textareas amélioré
    $('textarea').on('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.max(this.scrollHeight, 100) + 'px';
    });
    
    // Compteur de caractères amélioré pour le prompt
    $('#music-prompt').on('input', function() {
        const length = $(this).val().length;
        const maxLength = 500;
        
        if (!$('#char-counter').length) {
            $(this).after('<div id="char-counter" class="char-counter"></div>');
        }
        
        const remaining = maxLength - length;
        const counterText = remaining >= 0 ? 
            `${length}/${maxLength} caractères (${remaining} restants)` :
            `${length}/${maxLength} caractères (${Math.abs(remaining)} en trop !)`;
        
        $('#char-counter').text(counterText);
        
        if (length > maxLength) {
            $('#char-counter').addClass('over-limit');
        } else {
            $('#char-counter').removeClass('over-limit');
        }
    });
    
    // Suggestions de prompts améliorées v1.10.4
    const promptSuggestions = [
        "Une ballade pop mélancolique sur l'amour perdu",
        "Un morceau rock énergique pour motiver",
        "Une chanson électronique dansante pour l'été",
        "Une mélodie jazz relaxante pour le soir",
        "Un rap inspirant sur la persévérance",
        "Une chanson folk acoustique sur la nature",
        "Un hymne pop pour célébrer l'amitié",
        "Une berceuse douce et apaisante",
        "Un blues mélancolique au piano",
        "Une chanson country sur la route"
    ];
    
    // Ajouter les suggestions avec animation
    if ($('#music-prompt').length) {
        const suggestionsHtml = `
            <div class="prompt-suggestions">
                <p>💡 Suggestions d'idées (cliquez pour utiliser) :</p>
                <div class="suggestions-list">
                    ${promptSuggestions.map((suggestion, index) => 
                        `<button type="button" class="suggestion-btn" data-suggestion="${suggestion}" style="animation-delay: ${index * 0.1}s">${suggestion}</button>`
                    ).join('')}
                </div>
            </div>
        `;
        
        $('#music-prompt').after(suggestionsHtml);
        
        // Gérer les clics sur les suggestions avec feedback
        $('.suggestion-btn').on('click', function() {
            const suggestion = $(this).data('suggestion');
            $('#music-prompt').val(suggestion).trigger('input');
            
            // Feedback visuel
            $(this).addClass('selected');
            setTimeout(() => $(this).removeClass('selected'), 1000);
            
            showTemporaryMessage('💡 Suggestion appliquée !', 'success');
        });
    }
    
    // Sauvegarde automatique améliorée
    const STORAGE_KEY = 'suno_music_draft_v1104';
    
    function saveDraft() {
        const draft = {
            prompt: $('#music-prompt').val(),
            style: $('#music-style').val(),
            title: $('#music-title').val(),
            lyrics: $('#music-lyrics').val(),
            instrumental: $('#instrumental').is(':checked'),
            timestamp: Date.now(),
            version: '1.10.4'
        };
        
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
            debugLog('Brouillon sauvegardé');
        } catch (e) {
            debugLog('Erreur de sauvegarde du brouillon:', e);
        }
    }
    
    function loadDraft() {
        try {
            const draft = localStorage.getItem(STORAGE_KEY);
            if (draft) {
                const data = JSON.parse(draft);
                
                // Vérifier que le brouillon n'est pas trop ancien (24h)
                if (data.timestamp && (Date.now() - data.timestamp) > 24 * 60 * 60 * 1000) {
                    localStorage.removeItem(STORAGE_KEY);
                    debugLog('Brouillon expiré supprimé');
                    return;
                }
                
                $('#music-prompt').val(data.prompt || '');
                $('#music-style').val(data.style || '');
                $('#music-title').val(data.title || '');
                $('#music-lyrics').val(data.lyrics || '');
                $('#instrumental').prop('checked', data.instrumental || false);
                
                debugLog('Brouillon chargé', data);
                
                if (data.prompt) {
                    showTemporaryMessage('📝 Brouillon restauré', 'info');
                }
            }
        } catch (e) {
            debugLog('Erreur de chargement du brouillon:', e);
            localStorage.removeItem(STORAGE_KEY);
        }
    }
    
    // Charger le brouillon au démarrage
    loadDraft();
    
    // Sauvegarder automatiquement avec débounce
    let saveTimeout;
    $('#music-generation-form input, #music-generation-form textarea, #music-generation-form select').on('input change', function() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(saveDraft, 1000); // Attendre 1 seconde après la dernière modification
    });
    
    // Nettoyer le brouillon après génération réussie
    $(document).on('generation-complete', function() {
        localStorage.removeItem(STORAGE_KEY);
        debugLog('Brouillon nettoyé après génération réussie');
    });
    
    // Gestion des erreurs globales JavaScript
    window.addEventListener('error', function(e) {
        debugLog('Erreur JavaScript globale:', {
            message: e.message,
            filename: e.filename,
            lineno: e.lineno,
            colno: e.colno
        });
    });
    
    // Information sur la version au chargement
    if (window.console && console.info) {
        console.info('🎵 Suno Music Generator v1.10.4 chargé avec succès');
        console.info('🛡️ Protection anti-boucle active (max 20 vérifications)');
        console.info('💾 Sauvegarde automatique des brouillons activée');
        console.info('📱 Interface responsive et accessible');
    }
});