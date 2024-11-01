jQuery(document).ready(function($) {
    console.log('GPRS Frontend initialized');

    // Éléments DOM
    const form = $('#gprs-generator-form');
    const loading = $('#gprs-loading');
    const result = $('#gprs-result');
    const content = $('#gprs-content');
    const copyButton = $('#gprs-copy');
    const resetButton = $('#gprs-reset');
    const submitButton = $('#gprs-submit');
    let isGenerating = false;

    console.log('Elements found:', {
        form: form.length,
        resetButton: resetButton.length,
        submitButton: submitButton.length
    });

    // Fonction pour montrer une erreur
    function showError(message) {
        console.log('Showing error:', message);
        const errorHtml = `
            <div class="notice notice-error">
                <p>${message}</p>
            </div>
        `;
        loading.hide();
        content.html(errorHtml);
        result.show();
        copyButton.hide();
    }

    // Fonction pour montrer le succès
    function showSuccess(postContent) {
        console.log('Showing success');
        loading.hide();
        content.html(postContent);
        result.show();
        copyButton.show();

        // Scroll vers le résultat
        $('html, body').animate({
            scrollTop: result.offset().top - 50
        }, 500);
    }

    // Fonction pour basculer l'état du formulaire
    function toggleForm(disabled) {
        form.find('input, select, button').prop('disabled', disabled);
        isGenerating = disabled;
        form.toggleClass('generating', disabled);
    }

    // Fonction de réinitialisation
    function resetForm() {
        console.log('Resetting form');
        form[0].reset();
        result.hide();
        copyButton.hide();
        content.empty();
        $('#gprs_prompt').focus();
    }

    // Gestion du clic sur réinitialiser
    resetButton.on('click', function(e) {
        console.log('Reset button clicked');
        e.preventDefault();
        if (!isGenerating) {
            resetForm();
        }
    });

    // Gestion de la soumission du formulaire
    form.on('submit', function(e) {
        console.log('Form submitted');
        e.preventDefault();
        
        if (isGenerating) {
            console.log('Generation in progress, submit blocked');
            return false;
        }

        const formData = new FormData(this);
        const prompt = formData.get('prompt');
        const tone = formData.get('tone');

        console.log('Form data:', {
            prompt: prompt,
            tone: tone,
            length: formData.get('length'),
            hashtags: formData.get('hashtags')
        });

        // Validation
        if (!prompt || !tone) {
            showError(gprsAjax.phrases.missingParams);
            return false;
        }

        // Préparer l'interface
        loading.show();
        result.hide();
        copyButton.hide();
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
                console.log('AJAX success:', response);
                if (response.success && response.data && response.data.content) {
                    showSuccess(response.data.content);
                } else {
                    showError(response.data || gprsAjax.phrases.error);
                }
            },

            error: function(xhr, status, error) {
                console.error('AJAX error:', {xhr, status, error});
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

    // Système de copie
    copyButton.on('click', function() {
        console.log('Copy button clicked');
        const textToCopy = content.text().trim();
        
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
            console.error('Copy error:', error);
            alert(gprsAjax.phrases.copyError || 'Impossible de copier le texte');
        } finally {
            document.body.removeChild(textArea);
        }
    }

    function showCopyFeedback(button) {
        const $button = $(button);
        const originalText = $button.text();

        $button.text(gprsAjax.phrases.copied).addClass('copied');

        setTimeout(() => {
            $button.text(originalText).removeClass('copied');
        }, 2000);
    }

    // Raccourcis clavier
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + Enter pour soumettre
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
            if (!isGenerating) {
                console.log('Submit shortcut triggered');
                e.preventDefault();
                form.submit();
            }
        }
        
        // Échap pour réinitialiser
        if (e.keyCode === 27) {
            if (!isGenerating) {
                console.log('Reset shortcut triggered');
                e.preventDefault();
                resetForm();
            }
        }
    });

    // Délégation d'événement alternative pour le reset
    $(document).on('click', '#gprs-reset', function(e) {
        console.log('Reset clicked (delegation)');
        e.preventDefault();
        if (!isGenerating) {
            resetForm();
        }
    });

    // Gestion des erreurs globales
    window.onerror = function(msg, url, lineNo, columnNo, error) {
        console.error('Global error:', {msg, url, lineNo, columnNo, error});
        if (isGenerating) {
            showError(gprsAjax.phrases.error);
            toggleForm(false);
        }
        return false;
    };
});