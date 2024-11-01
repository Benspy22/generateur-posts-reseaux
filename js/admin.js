jQuery(document).ready(function($) {
    // Éléments DOM
    const form = $('#gprs-generator-form');
    const loading = $('#gprs-loading');
    const result = $('#gprs-result');
    const content = $('#gprs-content');
    const copyButton = $('#gprs-copy');
    const regenerateButton = $('#gprs-regenerate');
    const resetButton = $('#gprs-reset');
    const submitButton = $('#gprs-submit');
    let isGenerating = false;

    // Debug mode
    const DEBUG = typeof gprsAjax !== 'undefined' && gprsAjax.debug;

    // Fonction pour le logging
    function log(...args) {
        if (DEBUG) {
            console.log('[GPRS]', ...args);
        }
    }

    // Log initial
    log('Script initialized');
    log('Form found:', form.length);

    // Fonction pour montrer une erreur
    function showError(message) {
        log('Error:', message);
        const errorHtml = `
            <div class="notice notice-error">
                <p>${message}</p>
            </div>
        `;
        loading.hide();
        content.html(errorHtml);
        result.show();
        copyButton.hide();
        regenerateButton.hide();
    }

    // Fonction pour montrer le succès
    function showSuccess(postContent) {
        log('Success - Content received');
        loading.hide();
        content.html(postContent);
        result.show();
        copyButton.show();
        regenerateButton.show();

        // Scroll vers le résultat
        $('html, body').animate({
            scrollTop: result.offset().top - 50
        }, 500);
    }

    // Fonction pour basculer l'état du formulaire
    function toggleForm(disabled) {
        form.find('input, select, button').prop('disabled', disabled);
        submitButton.prop('disabled', disabled);
        if (disabled) {
            submitButton.find('.dashicons').addClass('spin');
        } else {
            submitButton.find('.dashicons').removeClass('spin');
        }
        isGenerating = disabled;
        form.toggleClass('generating', disabled);
    }

    // Fonction pour valider le formulaire
    function validateForm(formData) {
        const prompt = formData.get('prompt');
        const tone = formData.get('tone');

        if (!prompt || !tone) {
            showError(gprsAjax.phrases.missingParams);
            return false;
        }

        return true;
    }

    // Gestion de la soumission du formulaire
    form.on('submit', function(e) {
        e.preventDefault();
        log('Form submitted');
        
        if (isGenerating) {
            log('Generation already in progress');
            return false;
        }

        // Récupérer les données du formulaire
        const formData = new FormData(this);
        
        // Log des données
        log('Form Data:', {
            prompt: formData.get('prompt'),
            tone: formData.get('tone'),
            length: formData.get('length'),
            hashtags: formData.get('hashtags')
        });

        // Valider le formulaire
        if (!validateForm(formData)) {
            return false;
        }

        // Préparer l'interface
        loading.show();
        result.hide();
        copyButton.hide();
        regenerateButton.hide();
        toggleForm(true);

        // Ajouter l'action et le nonce
        formData.append('action', 'gprs_generate_post');
        formData.append('nonce', gprsAjax.nonce);

        // Faire la requête AJAX
        $.ajax({
            url: gprsAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 30000,

            success: function(response) {
                log('Server response:', response);

                if (response.success && response.data && response.data.content) {
                    showSuccess(response.data.content);
                } else {
                    showError(response.data || gprsAjax.phrases.error);
                }
            },

            error: function(xhr, status, error) {
                log('AJAX Error:', {xhr, status, error});
                
                let errorMessage = gprsAjax.phrases.networkError;
                
                if (status === 'timeout') {
                    errorMessage = gprsAjax.phrases.timeout;
                } else if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                
                showError(errorMessage);
            },

            complete: function() {
                toggleForm(false);
            }
        });

        return false;
    });

    // Bouton de régénération
    regenerateButton.on('click', function() {
        if (!isGenerating) {
            log('Regenerating content');
            form.submit();
        }
    });

    // Système de copie
    copyButton.on('click', function() {
        const textToCopy = content.text().trim();
        
        // Utiliser l'API Clipboard si disponible
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(textToCopy)
                .then(() => {
                    showCopyFeedback(this);
                })
                .catch(() => {
                    fallbackCopyText(textToCopy, this);
                });
        } else {
            fallbackCopyText(textToCopy, this);
        }
    });

    // Fonction de fallback pour la copie
    function fallbackCopyText(text, button) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);

        try {
            textArea.select();
            document.execCommand('copy');
            showCopyFeedback(button);
        } catch (error) {
            log('Copy fallback error:', error);
            alert(gprsAjax.phrases.copyError || 'Impossible de copier le texte. Veuillez le sélectionner et copier manuellement.');
        } finally {
            document.body.removeChild(textArea);
        }
    }

    // Feedback pour la copie
    function showCopyFeedback(button) {
        const $button = $(button);
        const originalText = $button.text();
        const originalIcon = $button.find('.dashicons').attr('class');

        $button
            .text(gprsAjax.phrases.copied)
            .addClass('copied')
            .find('.dashicons')
            .attr('class', 'dashicons dashicons-yes');

        setTimeout(() => {
            $button
                .text(originalText)
                .removeClass('copied')
                .find('.dashicons')
                .attr('class', originalIcon);
        }, 2000);
    }

    // Réinitialisation du formulaire
    resetButton.on('click', function(e) {
        e.preventDefault();
        log('Form reset requested');
        
        if (!isGenerating) {
            form[0].reset();
            result.hide();
            copyButton.hide();
            regenerateButton.hide();
            
            // Réinitialiser le localStorage
            const formInputs = form.find('input[type="text"], select');
            formInputs.each(function() {
                localStorage.removeItem('gprs_' + this.name);
            });

            // Réinitialiser les conseils
            $('.tone-tip').remove();
        }
    });

    // Raccourcis clavier
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + Enter pour soumettre
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
            if (!isGenerating) {
                e.preventDefault();
                log('Keyboard shortcut: Submit');
                form.submit();
            }
        }
        
        // Échap pour réinitialiser
        if (e.keyCode === 27) {
            if (!isGenerating) {
                e.preventDefault();
                log('Keyboard shortcut: Reset');
                resetButton.click();
            }
        }
    });

    // Conseils contextuels basés sur le ton
    $('#gprs_tone').on('change', function() {
        const selectedTone = $(this).val();
        log('Tone changed:', selectedTone);
        
        const tip = gprsToneTips[selectedTone];
        if (tip) {
            $('.tone-tip').remove();
            $(this).closest('.form-group').append(
                $('<p>').addClass('description tone-tip').text(tip)
            );
        }
    }).trigger('change');

    // Gestion des erreurs globales
    window.onerror = function(msg, url, lineNo, columnNo, error) {
        log('Global error:', {msg, url, lineNo, columnNo, error});
        if (isGenerating) {
            showError(gprsAjax.phrases.error);
            toggleForm(false);
        }
        return false;
    };
});