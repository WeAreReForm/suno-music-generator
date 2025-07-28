/**
 * Suno Music Generator - JavaScript Interface
 */

jQuery(document).ready(function($) {
    let currentTaskId = null;
    let statusCheckInterval = null;
    
    // Soumission du formulaire
    $('#music-generation-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'generate_music',
            nonce: suno_ajax.nonce,
            prompt: $('#music-prompt').val(),
            style: $('#music-style').val(),
            title: $('#music-title').val(),
            lyrics: $('#music-lyrics').val(),
            instrumental: $('#instrumental').is(':checked')
        };
        
        // Validation
        if (!formData.prompt.trim()) {
            alert('Veuillez saisir une description de la chanson');
            return;
        }
        
        // Afficher le statut de génération
        showGenerationStatus();
        updateStatusText('Envoi de la requête...');
        
        // Désactiver le formulaire
        toggleFormState(false);
        
        // Appel AJAX
        $.post(suno_ajax.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    currentTaskId = response.data.task_id;
                    updateStatusText('Génération démarrée...');
                    updateProgress(25);
                    
                    // Commencer à vérifier le statut
                    startStatusCheck();
                } else {
                    showError('Erreur: ' + response.data);
                    hideGenerationStatus();
                    toggleFormState(true);
                }
            })
            .fail(function() {
                showError('Erreur de connexion');
                hideGenerationStatus();
                toggleFormState(true);
            });
    });
    
    function startStatusCheck() {
        if (!currentTaskId) return;
        
        updateStatusText('Traitement en cours...');
        updateProgress(50);
        
        statusCheckInterval = setInterval(function() {
            checkMusicStatus();
        }, 3000); // Vérifier toutes les 3 secondes
    }
    
    function checkMusicStatus() {
        if (!currentTaskId) {
            stopStatusCheck();
            return;
        }
        
        $.post(suno_ajax.ajax_url, {
            action: 'check_music_status',
            nonce: suno_ajax.nonce,
            task_id: currentTaskId
        })
        .done(function(response) {
            if (response.success) {
                const status = response.data.status;
                
                switch(status) {
                    case 'processing':
                    case 'queued':
                        updateStatusText('Génération en cours...');
                        updateProgress(75);
                        break;
                        
                    case 'completed':
                        updateStatusText('Terminé !');
                        updateProgress(100);
                        setTimeout(() => {
                            showResult(response.data);
                            stopStatusCheck();
                        }, 1000);
                        break;
                        
                    case 'failed':
                        showError('La génération a échoué');
                        stopStatusCheck();
                        break;
                        
                    default:
                        updateStatusText('Statut: ' + status);
                        break;
                }
            } else {
                console.log('Erreur de vérification:', response.data);
            }
        })
        .fail(function() {
            console.log('Erreur de connexion lors de la vérification');
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
    }
    
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
                <p>${message}</p>
                <button onclick="location.reload()" class="suno-btn suno-btn-primary">
                    🔄 Réessayer
                </button>
            </div>
        `;
        
        $('#generation-result').html(errorHtml).fadeIn();
    }
    
    function toggleFormState(enabled) {
        $('#music-generation-form input, #music-generation-form textarea, #music-generation-form select, #music-generation-form button')
            .prop('disabled', !enabled);
    }
    
    // Fonctions de partage social
    window.shareOnTwitter = function(audioUrl) {
        const text = "J'ai créé cette chanson avec l'IA Suno ! 🎵";
        const url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(audioUrl)}`;
        window.open(url, '_blank');
    };
    
    window.shareOnFacebook = function(audioUrl) {
        const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(audioUrl)}`;
        window.open(url, '_blank');
    };
    
    window.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Lien copié dans le presse-papiers !');
        }).catch(function(err) {
            console.error('Erreur de copie:', err);
            // Fallback pour les navigateurs plus anciens
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert('Lien copié !');
        });
    };
    
    // Auto-resize des textareas
    $('textarea').on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    
    // Compteur de caractères pour le prompt
    $('#music-prompt').on('input', function() {
        const length = $(this).val().length;
        const maxLength = 500;
        
        if (!$('#char-counter').length) {
            $(this).after('<div id="char-counter" class="char-counter"></div>');
        }
        
        $('#char-counter').text(`${length}/${maxLength} caractères`);
        
        if (length > maxLength) {
            $('#char-counter').addClass('over-limit');
        } else {
            $('#char-counter').removeClass('over-limit');
        }
    });
    
    // Suggestions de prompts
    const promptSuggestions = [
        "Une ballade pop mélancolique sur l'amour perdu",
        "Un morceau rock énergique pour motiver",
        "Une chanson électronique dansante pour l'été",
        "Une mélodie jazz relaxante pour le soir",
        "Un rap inspirant sur la persévérance",
        "Une chanson folk acoustique sur la nature",
        "Un hymne pop pour célébrer l'amitié",
        "Une berceuse douce et apaisante"
    ];
    
    // Ajouter les suggestions
    if ($('#music-prompt').length) {
        const suggestionsHtml = `
            <div class="prompt-suggestions">
                <p>💡 Suggestions d'idées :</p>
                <div class="suggestions-list">
                    ${promptSuggestions.map(suggestion => 
                        `<button type="button" class="suggestion-btn" data-suggestion="${suggestion}">${suggestion}</button>`
                    ).join('')}
                </div>
            </div>
        `;
        
        $('#music-prompt').after(suggestionsHtml);
        
        // Gérer les clics sur les suggestions
        $('.suggestion-btn').on('click', function() {
            const suggestion = $(this).data('suggestion');
            $('#music-prompt').val(suggestion).trigger('input');
        });
    }
    
    // Sauvegarde automatique en local
    const STORAGE_KEY = 'suno_music_draft';
    
    function saveDraft() {
        const draft = {
            prompt: $('#music-prompt').val(),
            style: $('#music-style').val(),
            title: $('#music-title').val(),
            lyrics: $('#music-lyrics').val(),
            instrumental: $('#instrumental').is(':checked')
        };
        
        localStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
    }
    
    function loadDraft() {
        const draft = localStorage.getItem(STORAGE_KEY);
        if (draft) {
            try {
                const data = JSON.parse(draft);
                $('#music-prompt').val(data.prompt || '');
                $('#music-style').val(data.style || '');
                $('#music-title').val(data.title || '');
                $('#music-lyrics').val(data.lyrics || '');
                $('#instrumental').prop('checked', data.instrumental || false);
            } catch (e) {
                console.log('Erreur de chargement du brouillon');
            }
        }
    }
    
    // Charger le brouillon au démarrage
    loadDraft();
    
    // Sauvegarder automatiquement
    $('#music-generation-form input, #music-generation-form textarea, #music-generation-form select').on('input change', function() {
        saveDraft();
    });
    
    // Nettoyer le brouillon après génération réussie
    $(document).on('generation-complete', function() {
        localStorage.removeItem(STORAGE_KEY);
    });
});