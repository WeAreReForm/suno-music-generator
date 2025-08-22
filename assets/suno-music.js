/**
 * Suno Music Generator - JavaScript Interface
 * Version 1.3
 */

jQuery(document).ready(function($) {
    let currentClipIds = null;
    let statusCheckInterval = null;
    let statusCheckCount = 0;
    const maxStatusChecks = 60; // Maximum 3 minutes (60 * 3 secondes)
    
    // Initialisation
    init();
    
    function init() {
        // Compteur de caractères
        $('#music-prompt').on('input', updateCharCounter);
        
        // Soumission du formulaire
        $('#music-generation-form').on('submit', handleFormSubmit);
        
        // Suggestions de prompts
        addPromptSuggestions();
        
        // Sauvegarde automatique
        initAutoSave();
    }
    
    // Mise à jour du compteur de caractères
    function updateCharCounter() {
        const $textarea = $(this);
        const length = $textarea.val().length;
        const maxLength = 500;
        const $counter = $('#char-counter');
        
        $counter.text(`${length}/${maxLength}`);
        
        if (length > maxLength) {
            $counter.addClass('over-limit');
            $textarea.val($textarea.val().substring(0, maxLength));
        } else {
            $counter.removeClass('over-limit');
        }
    }
    
    // Gestion de la soumission du formulaire
    function handleFormSubmit(e) {
        e.preventDefault();
        
        // Récupération des données
        const formData = {
            action: 'generate_music',
            nonce: $('#suno_nonce').val(),
            prompt: $('#music-prompt').val().trim(),
            style: $('#music-style').val(),
            title: $('#music-title').val().trim(),
            lyrics: $('#music-lyrics').val().trim(),
            instrumental: $('#instrumental').is(':checked')
        };
        
        // Validation
        if (!formData.prompt) {
            showNotification('Veuillez saisir une description pour votre chanson', 'error');
            return;
        }
        
        // Réinitialisation
        statusCheckCount = 0;
        
        // Affichage du statut
        showGenerationStatus();
        updateStatusText('Envoi de votre demande...');
        updateProgress(10);
        
        // Désactiver le formulaire
        setFormEnabled(false);
        
        // Appel AJAX pour générer
        $.ajax({
            url: suno_ajax.ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            timeout: 60000,
            success: function(response) {
                if (response.success) {
                    currentClipIds = response.data.clip_ids;
                    updateStatusText('Génération en cours...');
                    updateProgress(25);
                    
                    // Démarrer la vérification du statut
                    startStatusCheck();
                    
                    // Sauvegarder le brouillon
                    clearAutoSave();
                } else {
                    showError(response.data || 'Erreur lors de la génération');
                    hideGenerationStatus();
                    setFormEnabled(true);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erreur AJAX:', status, error);
                showError('Erreur de connexion. Veuillez réessayer.');
                hideGenerationStatus();
                setFormEnabled(true);
            }
        });
    }
    
    // Démarrer la vérification du statut
    function startStatusCheck() {
        if (!currentClipIds) return;
        
        // Vérifier immédiatement
        checkMusicStatus();
        
        // Puis toutes les 3 secondes
        statusCheckInterval = setInterval(function() {
            statusCheckCount++;
            
            if (statusCheckCount > maxStatusChecks) {
                showError('La génération prend plus de temps que prévu. Veuillez réessayer.');
                stopStatusCheck();
                return;
            }
            
            checkMusicStatus();
        }, 3000);
    }
    
    // Vérifier le statut de la génération
    function checkMusicStatus() {
        if (!currentClipIds) {
            stopStatusCheck();
            return;
        }
        
        $.ajax({
            url: suno_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'check_music_status',
                nonce: suno_ajax.nonce,
                clip_ids: currentClipIds
            },
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    switch(data.status) {
                        case 'processing':
                        case 'submitted':
                        case 'queued':
                            updateStatusText('Création de votre chanson...');
                            updateProgress(50 + (statusCheckCount * 2));
                            break;
                            
                        case 'streaming':
                            updateStatusText('Finalisation...');
                            updateProgress(80);
                            break;
                            
                        case 'complete':
                            updateStatusText('Terminé !');
                            updateProgress(100);
                            
                            setTimeout(() => {
                                showResult(data);
                                stopStatusCheck();
                            }, 1000);
                            break;
                            
                        case 'error':
                        case 'failed':
                            showError('La génération a échoué. Veuillez réessayer.');
                            stopStatusCheck();
                            break;
                            
                        default:
                            console.log('Statut inconnu:', data.status);
                            updateStatusText('Traitement en cours...');
                    }
                } else {
                    console.error('Erreur de vérification:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erreur lors de la vérification:', status, error);
                // Continuer à vérifier en cas d'erreur temporaire
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
        setFormEnabled(true);
        currentClipIds = null;
        statusCheckCount = 0;
    }
    
    // Afficher le résultat
    function showResult(data) {
        const resultHtml = `
            <div class="result-content">
                <h4>🎉 Votre chanson est prête !</h4>
                
                ${data.title ? `<h5>${escapeHtml(data.title)}</h5>` : ''}
                
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
                        <a href="${data.audio_url}" download class="suno-btn suno-btn-secondary">
                            📥 Télécharger MP3
                        </a>
                    ` : ''}
                    
                    ${data.video_url ? `
                        <a href="${data.video_url}" target="_blank" class="suno-btn suno-btn-secondary">
                            🎬 Voir la vidéo
                        </a>
                    ` : ''}
                    
                    <button onclick="location.reload()" class="suno-btn suno-btn-primary">
                        🎵 Créer une nouvelle chanson
                    </button>
                </div>
                
                <div class="social-share">
                    <p>Partager votre création :</p>
                    <div class="share-buttons">
                        <button onclick="shareOnTwitter('${data.audio_url || ''}')" class="share-btn twitter">
                            🐦 Twitter
                        </button>
                        <button onclick="shareOnFacebook('${data.audio_url || ''}')" class="share-btn facebook">
                            📘 Facebook
                        </button>
                        <button onclick="copyToClipboard('${data.audio_url || ''}')" class="share-btn copy">
                            📋 Copier le lien
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        $('#generation-result').html(resultHtml).fadeIn();
        hideGenerationStatus();
    }
    
    // Afficher une erreur
    function showError(message) {
        const errorHtml = `
            <div class="error-content">
                <h4>❌ Erreur</h4>
                <p>${escapeHtml(message)}</p>
                <button onclick="location.reload()" class="suno-btn suno-btn-primary">
                    🔄 Réessayer
                </button>
            </div>
        `;
        
        $('#generation-result').html(errorHtml).fadeIn();
    }
    
    // Afficher/masquer le statut de génération
    function showGenerationStatus() {
        $('#generation-status').fadeIn();
        $('#generation-result').hide();
        updateProgress(0);
    }
    
    function hideGenerationStatus() {
        $('#generation-status').fadeOut();
    }
    
    // Mettre à jour le texte du statut
    function updateStatusText(text) {
        $('#status-text').text(text);
    }
    
    // Mettre à jour la barre de progression
    function updateProgress(percent) {
        percent = Math.min(percent, 100);
        $('.progress-fill').css('width', percent + '%');
    }
    
    // Activer/désactiver le formulaire
    function setFormEnabled(enabled) {
        $('#music-generation-form input, #music-generation-form textarea, #music-generation-form select, #music-generation-form button')
            .prop('disabled', !enabled);
    }
    
    // Suggestions de prompts
    function addPromptSuggestions() {
        const suggestions = [
            "Une ballade pop mélancolique sur l'amour perdu",
            "Un morceau rock énergique pour se motiver",
            "Une chanson électronique dansante pour l'été",
            "Une mélodie jazz relaxante pour le soir",
            "Un rap inspirant sur la persévérance",
            "Une chanson folk acoustique sur la nature",
            "Un hymne pop pour célébrer l'amitié",
            "Une berceuse douce et apaisante",
            "Un morceau metal puissant et intense",
            "Une chanson R&B sensuelle et groovy"
        ];
        
        if ($('#music-prompt').length && !$('.prompt-suggestions').length) {
            const suggestionsHtml = `
                <div class="prompt-suggestions">
                    <p>💡 Suggestions d'idées :</p>
                    <div class="suggestions-list">
                        ${suggestions.map(s => 
                            `<button type="button" class="suggestion-btn" data-suggestion="${escapeHtml(s)}">${escapeHtml(s)}</button>`
                        ).join('')}
                    </div>
                </div>
            `;
            
            $('#music-prompt').after(suggestionsHtml);
            
            // Gérer les clics sur les suggestions
            $('.suggestion-btn').on('click', function() {
                const suggestion = $(this).data('suggestion');
                $('#music-prompt').val(suggestion).trigger('input');
                $(this).addClass('selected').siblings().removeClass('selected');
            });
        }
    }
    
    // Sauvegarde automatique
    const STORAGE_KEY = 'suno_music_draft';
    
    function initAutoSave() {
        // Charger le brouillon
        loadDraft();
        
        // Sauvegarder à chaque modification
        $('#music-generation-form input, #music-generation-form textarea, #music-generation-form select').on('input change', function() {
            saveDraft();
        });
    }
    
    function saveDraft() {
        const draft = {
            prompt: $('#music-prompt').val(),
            style: $('#music-style').val(),
            title: $('#music-title').val(),
            lyrics: $('#music-lyrics').val(),
            instrumental: $('#instrumental').is(':checked'),
            timestamp: Date.now()
        };
        
        localStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
    }
    
    function loadDraft() {
        const draft = localStorage.getItem(STORAGE_KEY);
        if (draft) {
            try {
                const data = JSON.parse(draft);
                
                // Ne charger que si le brouillon a moins de 24h
                if (Date.now() - data.timestamp < 86400000) {
                    $('#music-prompt').val(data.prompt || '').trigger('input');
                    $('#music-style').val(data.style || '');
                    $('#music-title').val(data.title || '');
                    $('#music-lyrics').val(data.lyrics || '');
                    $('#instrumental').prop('checked', data.instrumental || false);
                }
            } catch (e) {
                console.error('Erreur lors du chargement du brouillon:', e);
            }
        }
    }
    
    function clearAutoSave() {
        localStorage.removeItem(STORAGE_KEY);
    }
    
    // Fonctions de partage social (globales)
    window.shareOnTwitter = function(audioUrl) {
        const text = "J'ai créé cette chanson avec l'IA Suno ! 🎵";
        const url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(audioUrl)}`;
        window.open(url, '_blank', 'width=600,height=400');
    };
    
    window.shareOnFacebook = function(audioUrl) {
        const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(audioUrl)}`;
        window.open(url, '_blank', 'width=600,height=400');
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
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            showNotification('Lien copié !', 'success');
        } catch (err) {
            showNotification('Impossible de copier le lien', 'error');
        }
        
        document.body.removeChild(textArea);
    }
    
    // Notification
    function showNotification(message, type = 'info') {
        const notificationHtml = `
            <div class="suno-notification suno-notification-${type}">
                ${message}
            </div>
        `;
        
        $('body').append(notificationHtml);
        
        setTimeout(() => {
            $('.suno-notification').fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
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
        
        return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
    }
    
    // Auto-resize des textareas
    $('textarea').on('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 300) + 'px';
    });
});