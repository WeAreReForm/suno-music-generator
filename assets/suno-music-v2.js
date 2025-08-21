/**
 * Suno Music Generator v2.0 - JavaScript
 */

jQuery(document).ready(function($) {
    let currentTaskId = null;
    let statusCheckInterval = null;
    let statusCheckCount = 0;
    const MAX_STATUS_CHECKS = 60; // Maximum 3 minutes (60 * 3 seconds)
    
    // Initialisation
    function init() {
        setupFormHandlers();
        setupCharCounter();
        loadDraftIfExists();
    }
    
    // Configuration des gestionnaires de formulaire
    function setupFormHandlers() {
        $('#music-generation-form').on('submit', handleFormSubmit);
        
        // Sauvegarde automatique du brouillon
        $('#music-generation-form input, #music-generation-form textarea, #music-generation-form select').on('change input', function() {
            saveDraft();
        });
    }
    
    // Gestion de la soumission du formulaire
    function handleFormSubmit(e) {
        e.preventDefault();
        
        const formData = {
            action: 'generate_music',
            nonce: suno_ajax.nonce,
            prompt: $('#music-prompt').val().trim(),
            style: $('#music-style').val(),
            title: $('#music-title').val().trim(),
            lyrics: $('#music-lyrics').val().trim(),
            instrumental: $('#instrumental').is(':checked')
        };
        
        // Validation
        if (!formData.prompt) {
            showNotification('Veuillez saisir une description de la chanson', 'error');
            return;
        }
        
        if (formData.prompt.length > 500) {
            showNotification('La description ne doit pas dépasser 500 caractères', 'error');
            return;
        }
        
        // Afficher le statut de génération
        showGenerationStatus();
        updateStatusText('Envoi de la requête...');
        updateProgress(10);
        
        // Désactiver le formulaire
        toggleFormState(false);
        
        // Réinitialiser les compteurs
        statusCheckCount = 0;
        
        // Appel AJAX
        $.ajax({
            url: suno_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    currentTaskId = response.data.task_id;
                    updateStatusText('Génération démarrée ! ID: ' + currentTaskId.substring(0, 8) + '...');
                    updateProgress(25);
                    $('#status-details').text('Cela peut prendre 30 à 60 secondes...');
                    
                    // Commencer à vérifier le statut après 3 secondes
                    setTimeout(function() {
                        startStatusCheck();
                    }, 3000);
                } else {
                    showError(response.data || 'Erreur lors de la génération');
                    hideGenerationStatus();
                    toggleFormState(true);
                }
            },
            error: function(xhr, status, error) {
                showError('Erreur de connexion au serveur. Veuillez réessayer.');
                console.error('AJAX Error:', status, error);
                hideGenerationStatus();
                toggleFormState(true);
            }
        });
    }
    
    // Démarrer la vérification du statut
    function startStatusCheck() {
        if (!currentTaskId) return;
        
        updateStatusText('Création de votre chanson...');
        updateProgress(50);
        
        statusCheckInterval = setInterval(function() {
            checkMusicStatus();
        }, 3000); // Vérifier toutes les 3 secondes
    }
    
    // Vérifier le statut de la génération
    function checkMusicStatus() {
        if (!currentTaskId) {
            stopStatusCheck();
            return;
        }
        
        statusCheckCount++;
        
        // Vérifier si on a atteint la limite
        if (statusCheckCount > MAX_STATUS_CHECKS) {
            showError('La génération prend plus de temps que prévu. Veuillez vérifier plus tard dans votre playlist.');
            stopStatusCheck();
            return;
        }
        
        // Mise à jour de la progression
        const progress = Math.min(50 + (statusCheckCount * 1), 95);
        updateProgress(progress);
        
        $.ajax({
            url: suno_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'check_music_status',
                nonce: suno_ajax.nonce,
                task_id: currentTaskId
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    switch(data.status) {
                        case 'completed':
                            updateStatusText('✅ Terminé !');
                            updateProgress(100);
                            $('#status-details').text('Chargement de votre chanson...');
                            
                            setTimeout(function() {
                                showResult(data);
                                stopStatusCheck();
                                clearDraft(); // Effacer le brouillon après succès
                            }, 1000);
                            break;
                            
                        case 'failed':
                        case 'error':
                            showError(data.error || 'La génération a échoué. Veuillez réessayer.');
                            stopStatusCheck();
                            break;
                            
                        case 'processing':
                        case 'pending':
                        case 'queued':
                            updateStatusText('Génération en cours... (' + statusCheckCount * 3 + 's)');
                            $('#status-details').text(data.message || 'Patience, votre chanson est en création...');
                            break;
                            
                        default:
                            console.log('Statut inconnu:', data.status);
                            break;
                    }
                } else {
                    console.error('Erreur de vérification:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erreur AJAX lors de la vérification:', status, error);
                // Ne pas arrêter la vérification en cas d'erreur temporaire
            }
        });
    }
    
    // Arrêter la vérification du statut
    function stopStatusCheck() {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
            statusCheckInterval = null;
        }
        hideGenerationStatus();
        toggleFormState(true);
        currentTaskId = null;
        statusCheckCount = 0;
    }
    
    // Afficher le résultat
    function showResult(data) {
        const resultHtml = `
            <div class="result-content">
                <h4>🎉 Votre chanson est prête !</h4>
                
                ${data.title ? `<h5 style="text-align: center; color: var(--suno-text-light);">"${data.title}"</h5>` : ''}
                
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
                        <img src="${data.image_url}" alt="Artwork de la chanson" />
                    </div>
                ` : ''}
                
                <div class="result-actions">
                    ${data.audio_url ? `
                        <a href="${data.audio_url}" 
                           download 
                           class="suno-btn suno-btn-secondary">
                            📥 Télécharger
                        </a>
                    ` : ''}
                    
                    <button onclick="location.reload()" class="suno-btn suno-btn-primary">
                        🎵 Créer une nouvelle chanson
                    </button>
                </div>
                
                ${data.audio_url ? `
                <div class="social-share" style="text-align: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--suno-border);">
                    <p style="color: var(--suno-text-light); margin-bottom: 1rem;">Partager votre création :</p>
                    <div class="share-buttons" style="display: flex; gap: 1rem; justify-content: center;">
                        <button onclick="shareOnTwitter('${escapeHtml(data.audio_url)}')" 
                                class="suno-btn" 
                                style="background: #1da1f2;">
                            🐦 Twitter
                        </button>
                        <button onclick="shareOnFacebook('${escapeHtml(data.audio_url)}')" 
                                class="suno-btn" 
                                style="background: #4267b2;">
                            📘 Facebook
                        </button>
                        <button onclick="copyToClipboard('${escapeHtml(data.audio_url)}')" 
                                class="suno-btn" 
                                style="background: var(--suno-text-light);">
                            📋 Copier le lien
                        </button>
                    </div>
                </div>
                ` : ''}
            </div>
        `;
        
        $('#generation-result').html(resultHtml).fadeIn();
        hideGenerationStatus();
    }
    
    // Afficher une erreur
    function showError(message) {
        const errorHtml = `
            <div class="suno-error-box">
                <h4>❌ Erreur</h4>
                <p>${message}</p>
                <button onclick="location.reload()" class="suno-btn suno-btn-primary" style="margin-top: 1rem;">
                    🔄 Réessayer
                </button>
            </div>
        `;
        
        $('#generation-result').html(errorHtml).fadeIn();
    }
    
    // Afficher une notification
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="suno-${type}-box" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                ${message}
            </div>
        `);
        
        $('body').append(notification);
        notification.hide().fadeIn();
        
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Utilitaires UI
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
    }
    
    function toggleFormState(enabled) {
        $('#music-generation-form input, #music-generation-form textarea, #music-generation-form select, #music-generation-form button')
            .prop('disabled', !enabled);
        
        if (!enabled) {
            $('#generate-btn').html('⏳ Génération en cours...');
        } else {
            $('#generate-btn').html('🎼 Générer la musique');
        }
    }
    
    // Compteur de caractères
    function setupCharCounter() {
        $('#music-prompt').on('input', function() {
            const length = $(this).val().length;
            const maxLength = 500;
            
            let counter = $('#char-counter');
            if (!counter.length) {
                counter = $('<div id="char-counter" class="char-counter"></div>');
                $(this).after(counter);
            }
            
            counter.text(`${length}/${maxLength} caractères`);
            
            if (length > maxLength) {
                counter.addClass('over-limit');
            } else {
                counter.removeClass('over-limit');
            }
        });
    }
    
    // Gestion des brouillons
    const STORAGE_KEY = 'suno_music_draft';
    
    function saveDraft() {
        const draft = {
            prompt: $('#music-prompt').val(),
            style: $('#music-style').val(),
            title: $('#music-title').val(),
            lyrics: $('#music-lyrics').val(),
            instrumental: $('#instrumental').is(':checked'),
            timestamp: Date.now()
        };
        
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
        } catch (e) {
            console.error('Erreur lors de la sauvegarde du brouillon:', e);
        }
    }
    
    function loadDraftIfExists() {
        try {
            const draft = localStorage.getItem(STORAGE_KEY);
            if (draft) {
                const data = JSON.parse(draft);
                
                // Vérifier si le brouillon n'est pas trop ancien (7 jours)
                const draftAge = Date.now() - (data.timestamp || 0);
                const sevenDays = 7 * 24 * 60 * 60 * 1000;
                
                if (draftAge < sevenDays) {
                    $('#music-prompt').val(data.prompt || '').trigger('input');
                    $('#music-style').val(data.style || '');
                    $('#music-title').val(data.title || '');
                    $('#music-lyrics').val(data.lyrics || '');
                    $('#instrumental').prop('checked', data.instrumental || false);
                    
                    showNotification('Brouillon restauré automatiquement', 'info');
                } else {
                    clearDraft();
                }
            }
        } catch (e) {
            console.error('Erreur lors du chargement du brouillon:', e);
        }
    }
    
    function clearDraft() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            console.error('Erreur lors de la suppression du brouillon:', e);
        }
    }
    
    // Fonction d'échappement HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    // Fonctions de partage social (globales)
    window.shareOnTwitter = function(audioUrl) {
        const text = "J'ai créé cette chanson avec l'IA Suno ! 🎵";
        const url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(audioUrl)}`;
        window.open(url, '_blank', 'width=550,height=420');
    };
    
    window.shareOnFacebook = function(audioUrl) {
        const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(audioUrl)}`;
        window.open(url, '_blank', 'width=550,height=420');
    };
    
    window.copyToClipboard = function(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showNotification('Lien copié dans le presse-papiers !', 'success');
            }).catch(function(err) {
                fallbackCopyToClipboard(text);
            });
        } else {
            fallbackCopyToClipboard(text);
        }
    };
    
    function fallbackCopyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.top = '0';
        textArea.style.left = '0';
        textArea.style.width = '2em';
        textArea.style.height = '2em';
        textArea.style.padding = '0';
        textArea.style.border = 'none';
        textArea.style.outline = 'none';
        textArea.style.boxShadow = 'none';
        textArea.style.background = 'transparent';
        
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showNotification('Lien copié !', 'success');
            } else {
                showNotification('Impossible de copier le lien', 'error');
            }
        } catch (err) {
            showNotification('Erreur lors de la copie', 'error');
        }
        
        document.body.removeChild(textArea);
    }
    
    window.shareTrack = function(audioUrl) {
        const shareData = {
            title: 'Ma création musicale Suno',
            text: 'Écoutez cette chanson créée avec l\'IA !',
            url: audioUrl
        };
        
        if (navigator.share) {
            navigator.share(shareData)
                .then(() => console.log('Partage réussi'))
                .catch((error) => console.log('Erreur de partage:', error));
        } else {
            copyToClipboard(audioUrl);
        }
    };
    
    // Auto-resize des textareas
    $('textarea').on('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 300) + 'px';
    });
    
    // Initialisation
    init();
    
    // Afficher la notification de version au chargement
    if (!sessionStorage.getItem('v2_notification_shown')) {
        setTimeout(function() {
            showNotification('🎉 Version 2.0 - Génération et affichage améliorés !', 'success');
            sessionStorage.setItem('v2_notification_shown', 'true');
        }, 1000);
    }
});