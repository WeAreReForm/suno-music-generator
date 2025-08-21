/**
 * Suno Music Generator v5.0.0
 * Script JavaScript principal
 */

(function($) {
    'use strict';

    // Configuration globale
    const SUNO_CONFIG = window.suno_ajax || {};
    
    // État de l'application
    const SunoState = {
        isGenerating: false,
        currentTaskId: null,
        currentGenerationId: null,
        pollingInterval: null,
        pollingAttempts: 0,
        maxPollingAttempts: 40,
        selectedStyles: []
    };

    /**
     * Classe principale SunoMusicGenerator
     */
    class SunoMusicGenerator {
        constructor() {
            this.init();
        }

        /**
         * Initialisation
         */
        init() {
            this.bindEvents();
            this.initializeForm();
            this.loadHistory();
            this.initializePlayer();
        }

        /**
         * Liaison des événements
         */
        bindEvents() {
            // Soumission du formulaire
            $(document).on('submit', '#suno-music-form', this.handleFormSubmit.bind(this));
            
            // Sélection des styles
            $(document).on('click', '.suno-style-chip', this.handleStyleSelect.bind(this));
            
            // Compteur de caractères
            $(document).on('input', '#suno-prompt', this.updateCharCounter.bind(this));
            $(document).on('input', '#suno-lyrics', this.updateCharCounter.bind(this));
            
            // Actions sur les résultats
            $(document).on('click', '.suno-download-btn', this.handleDownload.bind(this));
            $(document).on('click', '.suno-share-btn', this.handleShare.bind(this));
            $(document).on('click', '.suno-like-btn', this.handleLike.bind(this));
            
            // Historique
            $(document).on('click', '.suno-history-item', this.handleHistoryClick.bind(this));
            $(document).on('click', '.suno-load-more', this.loadMoreHistory.bind(this));
            
            // Modal
            $(document).on('click', '.suno-modal-close', this.closeModal.bind(this));
            $(document).on('click', '.suno-modal', this.handleModalBackdrop.bind(this));
            
            // Suggestions de prompts
            $(document).on('click', '.suno-prompt-suggestion', this.handlePromptSuggestion.bind(this));
        }

        /**
         * Initialisation du formulaire
         */
        initializeForm() {
            // Suggestions de prompts
            this.loadPromptSuggestions();
            
            // Auto-save draft
            this.restoreDraft();
            setInterval(() => this.saveDraft(), 5000);
            
            // Tags autocomplete
            this.initializeTagsInput();
        }

        /**
         * Soumission du formulaire
         */
        handleFormSubmit(e) {
            e.preventDefault();
            
            if (SunoState.isGenerating) {
                this.showNotice('Une génération est déjà en cours', 'warning');
                return;
            }

            const formData = this.getFormData();
            
            if (!this.validateForm(formData)) {
                return;
            }

            this.startGeneration(formData);
        }

        /**
         * Récupération des données du formulaire
         */
        getFormData() {
            return {
                prompt: $('#suno-prompt').val().trim(),
                style: $('#suno-style').val() || SunoState.selectedStyles.join(', '),
                title: $('#suno-title').val().trim(),
                lyrics: $('#suno-lyrics').val().trim(),
                tags: $('#suno-tags').val().trim(),
                instrumental: $('#suno-instrumental').is(':checked'),
                nonce: SUNO_CONFIG.nonce
            };
        }

        /**
         * Validation du formulaire
         */
        validateForm(data) {
            if (!data.prompt) {
                this.showNotice('Veuillez saisir une description de la chanson', 'error');
                $('#suno-prompt').focus();
                return false;
            }

            if (data.prompt.length > 500) {
                this.showNotice('La description ne doit pas dépasser 500 caractères', 'error');
                return false;
            }

            if (data.lyrics && data.lyrics.length > 3000) {
                this.showNotice('Les paroles ne doivent pas dépasser 3000 caractères', 'error');
                return false;
            }

            return true;
        }

        /**
         * Démarrage de la génération
         */
        startGeneration(formData) {
            SunoState.isGenerating = true;
            SunoState.pollingAttempts = 0;
            
            this.setFormState(false);
            this.showGenerationStatus();
            this.updateStatusText('Envoi de votre demande...');
            this.updateProgress(10);

            $.ajax({
                url: SUNO_CONFIG.ajax_url,
                type: 'POST',
                data: {
                    action: 'suno_generate_music',
                    ...formData
                },
                success: (response) => {
                    if (response.success) {
                        SunoState.currentTaskId = response.data.task_id;
                        SunoState.currentGenerationId = response.data.generation_id;
                        
                        this.updateStatusText('Génération lancée avec succès !');
                        this.updateProgress(25);
                        
                        this.clearDraft();
                        this.startPolling();
                    } else {
                        this.handleError(response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    this.handleError('Erreur de connexion au serveur');
                }
            });
        }

        /**
         * Polling pour vérifier le statut
         */
        startPolling() {
            const interval = SUNO_CONFIG.config?.polling_interval || 3000;
            
            SunoState.pollingInterval = setInterval(() => {
                this.checkGenerationStatus();
            }, interval);
            
            // Première vérification immédiate
            setTimeout(() => this.checkGenerationStatus(), 1000);
        }

        /**
         * Vérification du statut de génération
         */
        checkGenerationStatus() {
            SunoState.pollingAttempts++;
            
            if (SunoState.pollingAttempts > SunoState.maxPollingAttempts) {
                this.stopPolling();
                this.handleError('La génération prend plus de temps que prévu. Vérifiez sur SunoAPI.org');
                return;
            }

            $.ajax({
                url: SUNO_CONFIG.ajax_url,
                type: 'POST',
                data: {
                    action: 'suno_check_status',
                    task_id: SunoState.currentTaskId,
                    generation_id: SunoState.currentGenerationId,
                    nonce: SUNO_CONFIG.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const data = response.data;
                        
                        if (data.status === 'completed') {
                            this.stopPolling();
                            this.handleGenerationComplete(data);
                        } else if (data.status === 'failed') {
                            this.stopPolling();
                            this.handleError('La génération a échoué');
                        } else {
                            this.updateProgress(Math.min(90, 25 + (SunoState.pollingAttempts * 2)));
                            this.updateStatusText(data.message || 'Traitement en cours...');
                        }
                    }
                },
                error: () => {
                    // Continue polling même si une requête échoue
                    console.log('Erreur de vérification, nouvelle tentative...');
                }
            });
        }

        /**
         * Arrêt du polling
         */
        stopPolling() {
            if (SunoState.pollingInterval) {
                clearInterval(SunoState.pollingInterval);
                SunoState.pollingInterval = null;
            }
            
            SunoState.isGenerating = false;
            this.setFormState(true);
        }

        /**
         * Génération complétée
         */
        handleGenerationComplete(data) {
            this.updateProgress(100);
            this.updateStatusText('Chanson générée avec succès !');
            
            setTimeout(() => {
                this.hideGenerationStatus();
                this.showResult(data);
                this.addToHistory(data);
                this.triggerEvent('generation-complete', data);
            }, 1000);
        }

        /**
         * Affichage du résultat
         */
        showResult(data) {
            const resultHtml = `
                <div class="suno-result">
                    <div class="suno-result-content">
                        <h3 class="suno-result-title">🎉 Votre chanson est prête !</h3>
                        
                        ${data.title ? `<h4>${data.title}</h4>` : ''}
                        
                        ${data.image_url ? `
                            <div class="suno-track-artwork">
                                <img src="${data.image_url}" alt="Artwork" />
                            </div>
                        ` : ''}
                        
                        ${data.audio_url ? `
                            <div class="suno-audio-player">
                                <audio controls autoplay>
                                    <source src="${data.audio_url}" type="audio/mpeg">
                                    Votre navigateur ne supporte pas l'audio.
                                </audio>
                            </div>
                        ` : ''}
                        
                        <div class="suno-result-actions">
                            ${data.audio_url ? `
                                <button class="suno-btn suno-btn-secondary suno-download-btn" 
                                        data-url="${data.audio_url}">
                                    📥 Télécharger
                                </button>
                            ` : ''}
                            
                            <button class="suno-btn suno-btn-secondary suno-share-btn" 
                                    data-url="${data.audio_url}" 
                                    data-title="${data.title || 'Ma chanson'}">
                                📤 Partager
                            </button>
                            
                            <button class="suno-btn suno-btn-primary" 
                                    onclick="location.reload()">
                                🎵 Nouvelle chanson
                            </button>
                        </div>
                        
                        <div class="suno-notice suno-notice-info suno-mt-3">
                            💡 Retrouvez toutes vos créations sur 
                            <a href="https://sunoapi.org/fr/logs" target="_blank">SunoAPI Logs</a>
                        </div>
                    </div>
                </div>
            `;
            
            $('#suno-generation-result').html(resultHtml).fadeIn();
        }

        /**
         * Gestion des erreurs
         */
        handleError(message) {
            this.stopPolling();
            this.hideGenerationStatus();
            this.setFormState(true);
            this.showNotice(message, 'error');
            
            const errorHtml = `
                <div class="suno-result">
                    <div class="suno-result-content">
                        <h3 class="suno-result-title">❌ Erreur</h3>
                        <p>${message}</p>
                        <div class="suno-result-actions">
                            <button class="suno-btn suno-btn-primary" onclick="location.reload()">
                                🔄 Réessayer
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            $('#suno-generation-result').html(errorHtml).fadeIn();
        }

        /**
         * Affichage du statut de génération
         */
        showGenerationStatus() {
            $('#suno-generation-status').fadeIn();
            $('#suno-generation-result').hide();
        }

        /**
         * Masquer le statut de génération
         */
        hideGenerationStatus() {
            $('#suno-generation-status').fadeOut();
        }

        /**
         * Mise à jour du texte de statut
         */
        updateStatusText(text) {
            $('#suno-status-text').text(text);
        }

        /**
         * Mise à jour de la barre de progression
         */
        updateProgress(percent) {
            $('.suno-progress-fill').css('width', percent + '%');
        }

        /**
         * État du formulaire
         */
        setFormState(enabled) {
            $('#suno-music-form').find('input, textarea, select, button').prop('disabled', !enabled);
        }

        /**
         * Sélection de style
         */
        handleStyleSelect(e) {
            const $chip = $(e.currentTarget);
            const style = $chip.data('style');
            
            $chip.toggleClass('selected');
            
            if ($chip.hasClass('selected')) {
                SunoState.selectedStyles.push(style);
            } else {
                SunoState.selectedStyles = SunoState.selectedStyles.filter(s => s !== style);
            }
        }

        /**
         * Compteur de caractères
         */
        updateCharCounter(e) {
            const $input = $(e.currentTarget);
            const maxLength = $input.attr('maxlength') || 500;
            const currentLength = $input.val().length;
            const $counter = $input.siblings('.suno-char-counter');
            
            if ($counter.length === 0) {
                $input.after(`<div class="suno-char-counter"></div>`);
            }
            
            const percentage = (currentLength / maxLength) * 100;
            let counterClass = '';
            
            if (percentage > 90) {
                counterClass = 'error';
            } else if (percentage > 75) {
                counterClass = 'warning';
            }
            
            $input.siblings('.suno-char-counter')
                .text(`${currentLength}/${maxLength} caractères`)
                .removeClass('warning error')
                .addClass(counterClass);
        }

        /**
         * Téléchargement
         */
        handleDownload(e) {
            e.preventDefault();
            const url = $(e.currentTarget).data('url');
            
            if (url) {
                const link = document.createElement('a');
                link.href = url;
                link.download = 'suno-music-' + Date.now() + '.mp3';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                this.showNotice('Téléchargement démarré', 'success');
            }
        }

        /**
         * Partage
         */
        handleShare(e) {
            e.preventDefault();
            const url = $(e.currentTarget).data('url');
            const title = $(e.currentTarget).data('title');
            
            if (navigator.share) {
                navigator.share({
                    title: title,
                    text: `Écoutez ma chanson créée avec Suno : ${title}`,
                    url: url
                }).catch(() => {
                    this.copyToClipboard(url);
                });
            } else {
                this.copyToClipboard(url);
            }
        }

        /**
         * Copie dans le presse-papiers
         */
        copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                this.showNotice('Lien copié dans le presse-papiers', 'success');
            }).catch(() => {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                this.showNotice('Lien copié', 'success');
            });
        }

        /**
         * Like
         */
        handleLike(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const generationId = $btn.data('id');
            
            $.ajax({
                url: SUNO_CONFIG.ajax_url,
                type: 'POST',
                data: {
                    action: 'suno_toggle_like',
                    generation_id: generationId,
                    nonce: SUNO_CONFIG.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $btn.toggleClass('liked');
                        const $count = $btn.find('.suno-like-count');
                        $count.text(response.data.count);
                    }
                }
            });
        }

        /**
         * Chargement de l'historique
         */
        loadHistory() {
            if (!$('#suno-history').length) {
                return;
            }

            $.ajax({
                url: SUNO_CONFIG.ajax_url,
                type: 'POST',
                data: {
                    action: 'suno_get_history',
                    limit: 10,
                    offset: 0,
                    nonce: SUNO_CONFIG.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderHistory(response.data.items);
                        
                        if (response.data.has_more) {
                            $('#suno-history').append(`
                                <button class="suno-btn suno-btn-secondary suno-load-more">
                                    Charger plus
                                </button>
                            `);
                        }
                    }
                }
            });
        }

        /**
         * Rendu de l'historique
         */
        renderHistory(items) {
            const historyHtml = items.map(item => `
                <div class="suno-history-item" data-id="${item.id}">
                    <div class="suno-history-image">
                        ${item.image_url ? 
                            `<img src="${item.image_url}" alt="">` : 
                            '<div class="suno-history-placeholder">🎵</div>'
                        }
                    </div>
                    <div class="suno-history-info">
                        <div class="suno-history-title">${item.title || 'Sans titre'}</div>
                        <div class="suno-history-meta">
                            <span class="suno-history-date">${this.formatDate(item.created_at)}</span>
                            <span class="suno-history-status suno-status-${item.status}">${item.status}</span>
                        </div>
                    </div>
                </div>
            `).join('');
            
            $('#suno-history').append(historyHtml);
        }

        /**
         * Ajout à l'historique
         */
        addToHistory(data) {
            const historyItem = `
                <div class="suno-history-item suno-history-new" data-id="${data.generation_id}">
                    <div class="suno-history-image">
                        ${data.image_url ? 
                            `<img src="${data.image_url}" alt="">` : 
                            '<div class="suno-history-placeholder">🎵</div>'
                        }
                    </div>
                    <div class="suno-history-info">
                        <div class="suno-history-title">${data.title || 'Sans titre'}</div>
                        <div class="suno-history-meta">
                            <span class="suno-history-date">À l'instant</span>
                            <span class="suno-history-status suno-status-completed">completed</span>
                        </div>
                    </div>
                </div>
            `;
            
            $('#suno-history').prepend(historyItem);
            setTimeout(() => {
                $('.suno-history-new').removeClass('suno-history-new');
            }, 100);
        }

        /**
         * Sauvegarde du brouillon
         */
        saveDraft() {
            const draft = {
                prompt: $('#suno-prompt').val(),
                style: $('#suno-style').val(),
                title: $('#suno-title').val(),
                lyrics: $('#suno-lyrics').val(),
                tags: $('#suno-tags').val(),
                instrumental: $('#suno-instrumental').is(':checked')
            };
            
            localStorage.setItem('suno_draft', JSON.stringify(draft));
        }

        /**
         * Restauration du brouillon
         */
        restoreDraft() {
            const draft = localStorage.getItem('suno_draft');
            if (draft) {
                try {
                    const data = JSON.parse(draft);
                    $('#suno-prompt').val(data.prompt || '');
                    $('#suno-style').val(data.style || '');
                    $('#suno-title').val(data.title || '');
                    $('#suno-lyrics').val(data.lyrics || '');
                    $('#suno-tags').val(data.tags || '');
                    $('#suno-instrumental').prop('checked', data.instrumental || false);
                } catch (e) {
                    console.error('Erreur lors de la restauration du brouillon');
                }
            }
        }

        /**
         * Suppression du brouillon
         */
        clearDraft() {
            localStorage.removeItem('suno_draft');
        }

        /**
         * Chargement des suggestions de prompts
         */
        loadPromptSuggestions() {
            const suggestions = [
                "Une ballade pop mélancolique sur l'amour perdu",
                "Un morceau rock énergique pour se motiver",
                "Une chanson électronique pour faire la fête",
                "Une mélodie jazz relaxante pour le soir",
                "Un rap inspirant sur la persévérance",
                "Une chanson folk acoustique sur la nature",
                "Un hymne pop pour célébrer l'amitié",
                "Une berceuse douce et apaisante"
            ];
            
            const suggestionsHtml = suggestions.map(s => 
                `<button type="button" class="suno-prompt-suggestion" data-prompt="${s}">${s}</button>`
            ).join('');
            
            $('#suno-prompt-suggestions').html(suggestionsHtml);
        }

        /**
         * Utilisation d'une suggestion de prompt
         */
        handlePromptSuggestion(e) {
            e.preventDefault();
            const prompt = $(e.currentTarget).data('prompt');
            $('#suno-prompt').val(prompt).trigger('input');
        }

        /**
         * Initialisation des tags
         */
        initializeTagsInput() {
            // Implémentation simple d'un input de tags
            // Peut être amélioré avec une librairie dédiée
        }

        /**
         * Initialisation du lecteur
         */
        initializePlayer() {
            // Configuration du lecteur audio personnalisé si nécessaire
        }

        /**
         * Affichage de notification
         */
        showNotice(message, type = 'info') {
            const noticeHtml = `
                <div class="suno-notice suno-notice-${type} suno-notice-popup">
                    ${message}
                </div>
            `;
            
            $('body').append(noticeHtml);
            
            setTimeout(() => {
                $('.suno-notice-popup').fadeOut(() => {
                    $('.suno-notice-popup').remove();
                });
            }, 3000);
        }

        /**
         * Fermeture de modal
         */
        closeModal() {
            $('.suno-modal').fadeOut(() => {
                $('.suno-modal').remove();
            });
        }

        /**
         * Gestion du clic sur fond de modal
         */
        handleModalBackdrop(e) {
            if ($(e.target).hasClass('suno-modal')) {
                this.closeModal();
            }
        }

        /**
         * Déclenchement d'événement personnalisé
         */
        triggerEvent(eventName, data) {
            $(document).trigger('suno:' + eventName, [data]);
        }

        /**
         * Formatage de date
         */
        formatDate(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;
            const seconds = Math.floor(diff / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);
            
            if (seconds < 60) {
                return 'À l\'instant';
            } else if (minutes < 60) {
                return `Il y a ${minutes} minute${minutes > 1 ? 's' : ''}`;
            } else if (hours < 24) {
                return `Il y a ${hours} heure${hours > 1 ? 's' : ''}`;
            } else if (days < 7) {
                return `Il y a ${days} jour${days > 1 ? 's' : ''}`;
            } else {
                return date.toLocaleDateString('fr-FR');
            }
        }
    }

    // Style pour les notifications popup
    const style = `
        <style>
        .suno-notice-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
            animation: slideInRight 0.3s ease;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        </style>
    `;
    
    $('head').append(style);

    // Initialisation au chargement du DOM
    $(document).ready(function() {
        new SunoMusicGenerator();
    });

})(jQuery);
