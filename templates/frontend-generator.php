<?php if (!defined('ABSPATH')) exit; ?>

<div class="gprs-frontend-container">
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
    <!-- Bouton Générer -->
    <button type="submit" id="gprs-submit" class="button button-primary">
        <span class="dashicons dashicons-admin-generic"></span>
        <?php _e('Générer le post', 'gprs'); ?>
    </button>
    
    <!-- Bouton Réinitialiser -->
    <button type="reset" id="gprs-reset" class="button">
        <span class="dashicons dashicons-dismiss"></span>
        <?php _e('Réinitialiser', 'gprs'); ?>
    </button>

</div>

            <!-- Conseils -->
            <div class="gprs-tips">
                <ul>
                    <li><?php _e('Soyez précis dans la description de votre sujet', 'gprs'); ?></li>
                    <li><?php _e('Incluez les informations importantes (dates, scores, noms)', 'gprs'); ?></li>
                    <li><?php _e('Choisissez un ton adapté à votre audience', 'gprs'); ?></li>
                    <li><?php _e('N\'hésitez pas à régénérer si le résultat ne vous convient pas', 'gprs'); ?></li>
                </ul>
            </div>
        </form>
    </div>

    <!-- Indicateur de chargement -->
    <div id="gprs-loading" style="display: none;">
        <div class="spinner"></div>
        <span class="loading-text"><?php _e('Génération en cours...', 'gprs'); ?></span>
    </div>
    
    <!-- Zone de résultat -->
    <div class="post-result" id="gprs-result" style="display: none;">
        <div class="post-result-header">
            <h3>
                <span class="dashicons dashicons-megaphone"></span>
                <?php _e('Post généré', 'gprs'); ?>
            </h3>
            <button class="copy-button" id="gprs-copy">
                <span class="dashicons dashicons-clipboard"></span>
                <?php _e('Copier', 'gprs'); ?>
            </button>
        </div>
        <div id="gprs-content"></div>
    </div>
</div>