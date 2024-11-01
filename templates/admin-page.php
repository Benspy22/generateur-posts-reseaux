<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap gprs-admin-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-share"></span>
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>
    
    <?php 
    // Vérifier la clé API
    if (!defined('OPENAI_API_KEY') || empty(OPENAI_API_KEY)) : ?>
        <div class="notice notice-error">
            <p>
                <?php _e('La clé API OpenAI n\'est pas configurée. Ajoutez cette ligne dans votre fichier wp-config.php :', 'gprs'); ?>
                <br>
                <code>define('OPENAI_API_KEY', 'votre-clé-api');</code>
            </p>
        </div>
    <?php endif; ?>

    <div class="gprs-container">
        <!-- Conteneur du formulaire -->
        <div class="gprs-form-container">
            <form id="gprs-generator-form" class="gprs-form" method="post" action="" onsubmit="return false;">
                <?php wp_nonce_field('gprs_nonce', 'gprs_nonce'); ?>
                
                <!-- Sujet du post -->
                <div class="form-group">
                    <label for="gprs_prompt">
                        <?php _e('Sujet du post :', 'gprs'); ?>
                        <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="gprs_prompt" 
                        name="prompt" 
                        class="regular-text" 
                        required 
                        placeholder="<?php esc_attr_e('Ex: Match de ce weekend contre l\'équipe de Soignies', 'gprs'); ?>"
                        autocomplete="off"
                    >
                    <p class="description">
                        <?php _e('Décrivez le sujet principal de votre post. Soyez aussi précis que possible.', 'gprs'); ?>
                    </p>
                </div>

                <!-- Ton du message -->
                <div class="form-group">
                    <label for="gprs_tone">
                        <?php _e('Ton du message :', 'gprs'); ?>
                        <span class="required">*</span>
                    </label>
                    <select id="gprs_tone" name="tone" required>
                        <option value=""><?php _e('-- Sélectionnez un ton --', 'gprs'); ?></option>
                        <option value="formel"><?php _e('Formel - Pour les annonces officielles', 'gprs'); ?></option>
                        <option value="informel"><?php _e('Informel - Pour les posts du quotidien', 'gprs'); ?></option>
                        <option value="humoristique"><?php _e('Humoristique - Pour les posts légers', 'gprs'); ?></option>
                        <option value="enthousiaste"><?php _e('Enthousiaste - Pour les victoires et célébrations', 'gprs'); ?></option>
                        <option value="motivant"><?php _e('Motivant - Pour encourager l\'équipe', 'gprs'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('Choisissez le ton qui convient le mieux à votre message.', 'gprs'); ?>
                    </p>
                </div>

                <!-- Longueur du message -->
                <div class="form-group">
                    <label for="gprs_length">
                        <?php _e('Longueur souhaitée :', 'gprs'); ?>
                    </label>
                    <select id="gprs_length" name="length">
                        <option value="court"><?php _e('Court - Environ 50 mots', 'gprs'); ?></option>
                        <option value="moyen" selected><?php _e('Moyen - Environ 100 mots', 'gprs'); ?></option>
                        <option value="long"><?php _e('Long - Environ 200 mots', 'gprs'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('La longueur approximative du post généré.', 'gprs'); ?>
                    </p>
                </div>

                <!-- Hashtags -->
                <div class="form-group">
                    <label for="gprs_hashtags">
                        <?php _e('Inclure des hashtags :', 'gprs'); ?>
                    </label>
                    <select id="gprs_hashtags" name="hashtags">
                        <option value="aucun"><?php _e('Aucun hashtag', 'gprs'); ?></option>
                        <option value="peu" selected><?php _e('Quelques hashtags (2-3)', 'gprs'); ?></option>
                        <option value="beaucoup"><?php _e('Plusieurs hashtags (4-5)', 'gprs'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('Le nombre de hashtags à inclure dans le post.', 'gprs'); ?>
                    </p>
                </div>

                <!-- Boutons d'action -->
                <div class="form-group submit-container">
                    <button type="submit" class="button button-primary" id="gprs-submit">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php _e('Générer le post', 'gprs'); ?>
                    </button>
                    
                    <button type="button" class="button" id="gprs-reset">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php _e('Réinitialiser', 'gprs'); ?>
                    </button>
                </div>

                <!-- Info raccourcis -->
                <div class="shortcuts-info">
                    <span class="dashicons dashicons-keyboard"></span>
                    <?php _e('Raccourcis : Ctrl/Cmd + Enter pour générer, Esc pour réinitialiser', 'gprs'); ?>
                </div>
            </form>

            <!-- Conseils -->
            <div class="gprs-tips">
                <h3>
                    <span class="dashicons dashicons-lightbulb"></span>
                    <?php _e('Conseils pour de meilleurs résultats', 'gprs'); ?>
                </h3>
                <ul>
                    <li><?php _e('Soyez précis dans la description de votre sujet', 'gprs'); ?></li>
                    <li><?php _e('Incluez les informations importantes (dates, scores, noms)', 'gprs'); ?></li>
                    <li><?php _e('Choisissez un ton adapté à votre audience', 'gprs'); ?></li>
                    <li><?php _e('N\'hésitez pas à régénérer si le résultat ne vous convient pas', 'gprs'); ?></li>
                </ul>
            </div>
        </div>

        <!-- Conteneur des résultats -->
        <div class="gprs-result-container">
            <!-- Indicateur de chargement -->
            <div id="gprs-loading" style="display: none;">
                <div class="gprs-loading-content">
                    <span class="spinner is-active"></span>
                    <span class="loading-text"><?php _e('Génération du post en cours...', 'gprs'); ?></span>
                </div>
            </div>
            
            <!-- Zone de résultat -->
            <div id="gprs-result" style="display: none;">
                <div class="result-header">
                    <h3>
                        <span class="dashicons dashicons-megaphone"></span>
                        <?php _e('Post généré', 'gprs'); ?>
                    </h3>
                    
                    <div class="result-actions">
                        <button type="button" id="gprs-copy" class="button">
                            <span class="dashicons dashicons-clipboard"></span>
                            <?php _e('Copier', 'gprs'); ?>
                        </button>
                        
                        <button type="button" id="gprs-regenerate" class="button">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Régénérer', 'gprs'); ?>
                        </button>
                    </div>
                </div>

                <!-- Contenu généré -->
                <div id="gprs-content" class="gprs-content-box"></div>

                <!-- Aperçu -->
                <div class="post-preview">
                    <h4><?php _e('Aperçu', 'gprs'); ?></h4>
                    <div class="social-preview">
                        <div id="facebook-preview-content"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pied de page -->
    <div class="gprs-footer">
        <p class="description">
            <?php _e('Ce plugin utilise l\'API OpenAI pour générer des posts engageants pour vos réseaux sociaux.', 'gprs'); ?>
            <?php _e('Développé avec ❤️ pour le club de football de Le Roeulx.', 'gprs'); ?>
        </p>
        <p class="version-info">
            Version <?php echo GPRS_VERSION; ?> |
            <a href="https://github.com/votre-repo" target="_blank" rel="noopener noreferrer">
                <?php _e('Signaler un problème', 'gprs'); ?>
            </a>
        </p>
    </div>
</div>

<?php
// Conseils contextuels basés sur le ton
$tone_tips = [
    'formel' => __('Conseil : Le ton formel est parfait pour les annonces officielles et les communications importantes.', 'gprs'),
    'informel' => __('Conseil : Le ton informel permet de créer une proximité avec vos supporters.', 'gprs'),
    'humoristique' => __('Conseil : L\'humour doit rester bienveillant et adapté à votre audience.', 'gprs'),
    'enthousiaste' => __('Conseil : Partagez votre enthousiasme tout en restant professionnel.', 'gprs'),
    'motivant' => __('Conseil : Utilisez des mots positifs et encourageants.', 'gprs'),
];
?>

<script type="text/javascript">
var gprsToneTips = <?php echo json_encode($tone_tips); ?>;
</script>